<?php

namespace App\Http\Controllers;

use App\Models\VistaProject;
use App\Models\VistaProjectMessage;
use App\Models\VistaProjectVersion;
use App\Support\VistaFilePath;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VistaProjectController extends Controller
{
    private function disk(): Filesystem
    {
        return Storage::disk('vista_files');
    }

    private function fileUrl(string $path): string
    {
        $baseUrl = rtrim(config('filesystems.disks.vista_files.url', '/vista-files'), '/');

        return $baseUrl.'/'.$path;
    }

    private function resolveCoverImageUrl(VistaProject $project): ?string
    {
        if ($project->cover_image_path) {
            return $this->fileUrl($project->cover_image_path);
        }
        if ($project->floor_plan_path) {
            return $this->fileUrl($project->floor_plan_path);
        }
        if ($project->room_image_path) {
            return $this->fileUrl($project->room_image_path);
        }

        return null;
    }

    private function saveFloorPlanFromBase64(string $userId, string $projectId, string $base64, string $mime): ?string
    {
        $decoded = base64_decode($base64, true);
        if ($decoded === false) {
            return null;
        }

        $ext = VistaFilePath::extensionFromMime($mime);
        $path = VistaFilePath::floorPlan($userId, $projectId, $ext);
        $this->disk()->put($path, $decoded);

        return $path;
    }

    private function applyFloorPlanUpload(VistaProject $project, string $userId, string $base64, string $mime): void
    {
        $path = $this->saveFloorPlanFromBase64($userId, $project->id, $base64, $mime);
        if ($path === null) {
            return;
        }

        $updates = ['floor_plan_path' => $path];
        if (! $project->cover_image_path) {
            $updates['cover_image_path'] = $path;
        }
        $project->update($updates);
    }

    private function orchestratorProjectIdFromPreferences(?array $preferences): ?string
    {
        if (! is_array($preferences)) {
            return null;
        }
        $id = $preferences['orchestratorProjectId'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * GET /api/vista/projects
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = VistaProject::where('user_id', $user->id)
            ->where('status', 'active')
            ->orderByDesc('last_interaction_at');

        if ($request->has('mode') && in_array($request->input('mode'), ['quick_room', 'project'])) {
            $query->where('mode', $request->input('mode'));
        }

        $projects = $query->paginate(20);

        $projects->getCollection()->transform(function (VistaProject $project) {
            return [
                'id' => $project->id,
                'mode' => $project->mode,
                'title' => $project->title,
                'cover_image_url' => $this->resolveCoverImageUrl($project),
                'orchestrator_project_id' => $this->orchestratorProjectIdFromPreferences($project->preferences),
                'status' => $project->status,
                'style' => $project->style,
                'message_count' => $project->message_count,
                'version_count' => $project->version_count,
                'last_interaction_at' => $project->last_interaction_at?->toISOString(),
                'created_at' => $project->created_at?->toISOString(),
            ];
        });

        return response()->json($projects);
    }

    /**
     * POST /api/vista/projects
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'mode' => 'required|string|in:quick_room,project',
            'title' => 'nullable|string|max:255',
            'style' => 'nullable|string|max:48',
            'room_image_base64' => 'nullable|string',
            'room_image_mime' => 'nullable|string|max:48',
            'floor_plan_base64' => 'nullable|string',
            'floor_plan_mime' => 'nullable|string|max:48',
            'room_analysis' => 'nullable|array',
            'room_geometry' => 'nullable|array',
            'preferences' => 'nullable|array',
        ]);

        $project = VistaProject::create([
            'user_id' => $user->id,
            'mode' => $data['mode'],
            'title' => $data['title'] ?? 'Untitled Design',
            'style' => $data['style'] ?? null,
            'room_analysis' => $data['room_analysis'] ?? null,
            'room_geometry' => $data['room_geometry'] ?? null,
            'preferences' => $data['preferences'] ?? null,
            'status' => 'active',
            'last_interaction_at' => now(),
        ]);

        // Store room image if provided
        if (! empty($data['room_image_base64'])) {
            $mime = $data['room_image_mime'] ?? 'image/jpeg';
            $ext = VistaFilePath::extensionFromMime($mime);
            $path = VistaFilePath::roomOriginal($user->id, $project->id, $ext);
            $decoded = base64_decode($data['room_image_base64'], true);

            if ($decoded !== false) {
                $this->disk()->put($path, $decoded);
                $project->update(['room_image_path' => $path]);

                // Add system message for room upload
                VistaProjectMessage::create([
                    'project_id' => $project->id,
                    'role' => 'system',
                    'content_type' => 'image_upload',
                    'text' => 'Room photo uploaded',
                    'attachment_path' => $path,
                    'attachment_mime' => $mime,
                    'sequence' => 1,
                    'created_at' => now(),
                ]);
                $project->increment('message_count');
            }
        }

        if ($data['mode'] === 'project' && ! empty($data['floor_plan_base64'])) {
            $this->applyFloorPlanUpload(
                $project,
                $user->id,
                $data['floor_plan_base64'],
                $data['floor_plan_mime'] ?? 'image/jpeg',
            );
            $project->refresh();
        }

        return response()->json(['data' => $this->formatProjectDetail($project)], 201);
    }

    /**
     * GET /api/vista/projects/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $project = VistaProject::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $project->load(['messages.version', 'versions']);

        return response()->json(['data' => $this->formatProjectDetail($project)]);
    }

    /**
     * PATCH /api/vista/projects/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $project = VistaProject::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,archived',
            'style' => 'nullable|string|max:48',
            'room_analysis' => 'nullable|array',
            'room_geometry' => 'nullable|array',
            'room_results' => 'nullable|array',
            'master_concept' => 'nullable|array',
            'floor_plan_analysis' => 'nullable|array',
            'floor_plan_base64' => 'nullable|string',
            'floor_plan_mime' => 'nullable|string|max:48',
            'preferences' => 'nullable|array',
            'pdf_path' => 'nullable|string|max:500',
        ]);

        $floorPlanBase64 = $data['floor_plan_base64'] ?? null;
        $floorPlanMime = $data['floor_plan_mime'] ?? 'image/jpeg';
        unset($data['floor_plan_base64'], $data['floor_plan_mime']);

        $project->update(array_filter($data, fn ($v) => $v !== null));

        if (! empty($floorPlanBase64)) {
            $this->applyFloorPlanUpload($project, $request->user()->id, $floorPlanBase64, $floorPlanMime);
            $project->refresh();
        }

        return response()->json(['data' => $this->formatProjectDetail($project)]);
    }

    /**
     * DELETE /api/vista/projects/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $project = VistaProject::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $basePath = VistaFilePath::basePath($project->mode, $request->user()->id, $project->id);
        $this->disk()->deleteDirectory($basePath);

        $project->delete();

        return response()->json(['message' => 'Project deleted.']);
    }

    /**
     * POST /api/vista/projects/{id}/versions
     */
    public function addVersion(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $project = VistaProject::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $data = $request->validate([
            'base64' => 'required|string',
            'mime_type' => 'nullable|string|max:48',
            'prompt_used' => 'nullable|string|max:10000',
            'feedback' => 'nullable|string|max:5000',
            'design_brief' => 'nullable|array',
            'products_used' => 'nullable|array',
            'room_geometry' => 'nullable|array',
            'type' => 'nullable|string|in:generated,edited,regenerated',
            'room_id' => 'nullable|string|max:50',
            'angle_index' => 'nullable|integer|min:0|max:10',
        ]);

        $decoded = base64_decode($data['base64'], true);
        if ($decoded === false) {
            return response()->json(['message' => 'Invalid base64 data.'], 422);
        }

        $mime = $data['mime_type'] ?? 'image/png';
        $ext = VistaFilePath::extensionFromMime($mime);
        $nextVersion = $project->version_count + 1;

        if ($project->mode === 'project' && ! empty($data['room_id'])) {
            $path = VistaFilePath::roomVersion(
                $user->id,
                $project->id,
                $data['room_id'],
                $nextVersion,
                $data['angle_index'] ?? 0,
                $ext
            );
        } else {
            $path = VistaFilePath::version($project->mode, $user->id, $project->id, $nextVersion, $ext);
        }

        $this->disk()->put($path, $decoded);

        $version = VistaProjectVersion::create([
            'project_id' => $project->id,
            'file_path' => $path,
            'mime_type' => $mime,
            'file_size_bytes' => strlen($decoded),
            'prompt_used' => $data['prompt_used'] ?? null,
            'feedback' => $data['feedback'] ?? null,
            'design_brief' => $data['design_brief'] ?? null,
            'products_used' => $data['products_used'] ?? null,
            'room_geometry' => $data['room_geometry'] ?? null,
            'version_number' => $nextVersion,
            'type' => $data['type'] ?? 'generated',
            'room_id' => $data['room_id'] ?? null,
            'angle_index' => $data['angle_index'] ?? 0,
            'created_at' => now(),
        ]);

        $project->increment('version_count');
        $project->update(['last_interaction_at' => now()]);

        // Set cover image on first version
        if ($nextVersion === 1) {
            $project->update(['cover_image_path' => $path]);
        }

        return response()->json([
            'data' => [
                'id' => $version->id,
                'file_url' => $this->fileUrl($version->file_path),
                'version_number' => $version->version_number,
                'type' => $version->type,
                'created_at' => $version->created_at?->toISOString(),
            ],
        ], 201);
    }

    /**
     * POST /api/vista/projects/{id}/messages
     */
    public function addMessage(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $project = VistaProject::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $data = $request->validate([
            'role' => 'required|string|in:user,assistant,system',
            'content_type' => 'nullable|string|in:text,image_upload,generation,action',
            'text' => 'nullable|string|max:10000',
            'version_id' => 'nullable|uuid',
            'attachment_base64' => 'nullable|string',
            'attachment_mime' => 'nullable|string|max:48',
        ]);

        $nextSequence = $project->message_count + 1;
        $attachmentPath = null;

        // Store attachment if provided
        if (! empty($data['attachment_base64'])) {
            $decoded = base64_decode($data['attachment_base64'], true);
            if ($decoded !== false) {
                $mime = $data['attachment_mime'] ?? 'image/jpeg';
                $ext = VistaFilePath::extensionFromMime($mime);
                $msgId = Str::uuid()->toString();
                $attachmentPath = VistaFilePath::attachment($project->mode, $user->id, $project->id, $msgId, $ext);
                $this->disk()->put($attachmentPath, $decoded);
            }
        }

        $message = VistaProjectMessage::create([
            'project_id' => $project->id,
            'role' => $data['role'],
            'content_type' => $data['content_type'] ?? 'text',
            'text' => $data['text'] ?? null,
            'version_id' => $data['version_id'] ?? null,
            'attachment_path' => $attachmentPath,
            'attachment_mime' => $data['attachment_mime'] ?? null,
            'sequence' => $nextSequence,
            'created_at' => now(),
        ]);

        $project->increment('message_count');
        $project->update(['last_interaction_at' => now()]);

        return response()->json([
            'data' => [
                'id' => $message->id,
                'role' => $message->role,
                'content_type' => $message->content_type,
                'text' => $message->text,
                'version_id' => $message->version_id,
                'attachment_url' => $attachmentPath ? $this->fileUrl($attachmentPath) : null,
                'sequence' => $message->sequence,
                'created_at' => $message->created_at?->toISOString(),
            ],
        ], 201);
    }

    private function formatProjectDetail(VistaProject $project): array
    {
        $result = [
            'id' => $project->id,
            'mode' => $project->mode,
            'title' => $project->title,
            'cover_image_url' => $this->resolveCoverImageUrl($project),
            'status' => $project->status,
            'style' => $project->style,
            'room_image_url' => $project->room_image_path ? $this->fileUrl($project->room_image_path) : null,
            'room_analysis' => $project->room_analysis,
            'room_geometry' => $project->room_geometry,
            'floor_plan_url' => $project->floor_plan_path ? $this->fileUrl($project->floor_plan_path) : null,
            'floor_plan_analysis' => $project->floor_plan_analysis,
            'master_concept' => $project->master_concept,
            'room_results' => $project->room_results,
            'preferences' => $project->preferences,
            'pdf_url' => $project->pdf_path ? $this->fileUrl($project->pdf_path) : null,
            'message_count' => $project->message_count,
            'version_count' => $project->version_count,
            'last_interaction_at' => $project->last_interaction_at?->toISOString(),
            'created_at' => $project->created_at?->toISOString(),
            'updated_at' => $project->updated_at?->toISOString(),
        ];

        if ($project->relationLoaded('messages')) {
            $result['messages'] = $project->messages->map(fn (VistaProjectMessage $msg) => [
                'id' => $msg->id,
                'role' => $msg->role,
                'content_type' => $msg->content_type,
                'text' => $msg->text,
                'version_id' => $msg->version_id,
                'attachment_url' => $msg->attachment_path ? $this->fileUrl($msg->attachment_path) : null,
                'attachment_mime' => $msg->attachment_mime,
                'sequence' => $msg->sequence,
                'created_at' => $msg->created_at?->toISOString(),
                'version' => $msg->relationLoaded('version') && $msg->version ? [
                    'id' => $msg->version->id,
                    'file_url' => $this->fileUrl($msg->version->file_path),
                    'mime_type' => $msg->version->mime_type,
                    'version_number' => $msg->version->version_number,
                    'type' => $msg->version->type,
                    'design_brief' => $msg->version->design_brief,
                    'products_used' => $msg->version->products_used,
                    'prompt_used' => $msg->version->prompt_used,
                    'feedback' => $msg->version->feedback,
                    'created_at' => $msg->version->created_at?->toISOString(),
                ] : null,
            ]);
        }

        if ($project->relationLoaded('versions')) {
            $result['versions'] = $project->versions->map(fn (VistaProjectVersion $v) => [
                'id' => $v->id,
                'file_url' => $this->fileUrl($v->file_path),
                'mime_type' => $v->mime_type,
                'version_number' => $v->version_number,
                'type' => $v->type,
                'room_id' => $v->room_id,
                'angle_index' => $v->angle_index,
                'design_brief' => $v->design_brief,
                'products_used' => $v->products_used,
                'prompt_used' => $v->prompt_used,
                'feedback' => $v->feedback,
                'created_at' => $v->created_at?->toISOString(),
            ]);
        }

        return $result;
    }
}
