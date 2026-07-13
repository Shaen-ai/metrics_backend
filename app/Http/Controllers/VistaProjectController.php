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
    private const MAX_INSPIRATION_IMAGES = 4;

    private const MAX_PLACEMENT_IMAGES = 4;

    private const MAX_ROOM_EXTRA_IMAGES = 5;

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
        foreach ([
            $project->cover_image_path,
            $project->floor_plan_path,
            $project->room_image_path,
        ] as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }

            $resolved = VistaFilePath::resolveExistingPath($this->disk(), $path);
            if ($resolved !== null) {
                return $this->fileUrl($resolved);
            }
        }

        return null;
    }

    private function resolveFileUrl(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        $resolved = VistaFilePath::resolveExistingPath($this->disk(), $path);

        return $resolved !== null ? $this->fileUrl($resolved) : null;
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

    /** @return list<array{path: string, label: string, mime: string, url: string}> */
    private function inspirationImagesFromPreferences(?array $preferences): array
    {
        if (! is_array($preferences)) {
            return [];
        }
        $raw = $preferences['inspirationImages'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $path = $item['path'] ?? null;
            if (! is_string($path) || $path === '') {
                continue;
            }
            $out[] = [
                'path' => $path,
                'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                'mime' => is_string($item['mime'] ?? null) ? $item['mime'] : 'image/jpeg',
                'url' => $this->fileUrl($path),
            ];
        }

        return $out;
    }

    /** @return list<array{path: string, label: string, mime: string, url: string, id?: string}> */
    private function placementImagesFromPreferences(?array $preferences): array
    {
        if (! is_array($preferences)) {
            return [];
        }
        $raw = $preferences['placementImages'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $path = $item['path'] ?? null;
            if (! is_string($path) || $path === '') {
                continue;
            }
            $entry = [
                'path' => $path,
                'label' => is_string($item['label'] ?? null) ? $item['label'] : '',
                'mime' => is_string($item['mime'] ?? null) ? $item['mime'] : 'image/jpeg',
                'url' => $this->fileUrl($path),
            ];
            if (is_string($item['id'] ?? null) && $item['id'] !== '') {
                $entry['id'] = $item['id'];
            }
            $out[] = $entry;
        }

        return $out;
    }

    /** @return list<array{path: string, mime: string, url: string, id?: string}> */
    private function roomExtraImagesFromPaths(?array $roomExtraPaths): array
    {
        if (! is_array($roomExtraPaths)) {
            return [];
        }

        $out = [];
        foreach ($roomExtraPaths as $item) {
            if (is_string($item)) {
                $path = $item;
                $out[] = [
                    'path' => $path,
                    'mime' => 'image/jpeg',
                    'url' => $this->fileUrl($path),
                ];

                continue;
            }
            if (! is_array($item)) {
                continue;
            }
            $path = $item['path'] ?? null;
            if (! is_string($path) || $path === '') {
                continue;
            }
            $entry = [
                'path' => $path,
                'mime' => is_string($item['mime'] ?? null) ? $item['mime'] : 'image/jpeg',
                'url' => $this->fileUrl($path),
            ];
            if (is_string($item['id'] ?? null) && $item['id'] !== '') {
                $entry['id'] = $item['id'];
            }
            $out[] = $entry;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @param  array<string, mixed>|null  $existing
     * @return array<string, mixed>
     */
    private function mergePreferences(?array $existing, array $incoming): array
    {
        $base = is_array($existing) ? $existing : [];

        foreach ($incoming as $key => $value) {
            if ($value === null) {
                continue;
            }
            if ($key === 'quickRoomOptions' && is_array($value) && is_array($base['quickRoomOptions'] ?? null)) {
                $base['quickRoomOptions'] = array_merge($base['quickRoomOptions'], $value);
            } elseif ($key === 'quickRoomPhasedState' && is_array($value) && is_array($base['quickRoomPhasedState'] ?? null)) {
                $base['quickRoomPhasedState'] = array_merge($base['quickRoomPhasedState'], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    private function applyRoomImageUpload(VistaProject $project, string $userId, string $base64, string $mime): void
    {
        $decoded = base64_decode($base64, true);
        if ($decoded === false) {
            return;
        }

        $ext = VistaFilePath::extensionFromMime($mime);
        $path = VistaFilePath::uploadedRoomOriginal($userId, $project->id, $ext);
        $this->disk()->put($path, $decoded);

        $updates = ['room_image_path' => $path];
        if (! $project->cover_image_path) {
            $updates['cover_image_path'] = $path;
        }
        $project->update($updates);
    }

    /**
     * @param  list<array{base64?: string, mime?: string, id?: string}>  $images
     */
    private function applyRoomExtraUpload(VistaProject $project, string $userId, array $images): void
    {
        $images = array_slice($images, 0, self::MAX_ROOM_EXTRA_IMAGES);
        $previous = is_array($project->room_extra_paths) ? $project->room_extra_paths : [];
        foreach ($previous as $old) {
            $oldPath = is_array($old) ? ($old['path'] ?? null) : (is_string($old) ? $old : null);
            if (is_string($oldPath) && $oldPath !== '') {
                $this->disk()->delete($oldPath);
            }
        }

        $stored = [];
        foreach ($images as $index => $image) {
            if (! is_array($image)) {
                continue;
            }
            $base64 = $image['base64'] ?? null;
            if (! is_string($base64) || $base64 === '') {
                continue;
            }
            $decoded = base64_decode($base64, true);
            if ($decoded === false) {
                continue;
            }
            $mime = is_string($image['mime'] ?? null) ? $image['mime'] : 'image/jpeg';
            $ext = VistaFilePath::extensionFromMime($mime);
            $path = VistaFilePath::uploadedRoomExtra($userId, $project->id, $index, $ext);
            $this->disk()->put($path, $decoded);
            $entry = [
                'path' => $path,
                'mime' => $mime,
            ];
            if (is_string($image['id'] ?? null) && $image['id'] !== '') {
                $entry['id'] = $image['id'];
            }
            $stored[] = $entry;
        }

        $project->update(['room_extra_paths' => $stored]);
    }

    /**
     * @param  list<array{base64?: string, mime?: string, label?: string, id?: string}>  $images
     */
    private function applyPlacementUpload(VistaProject $project, string $userId, array $images): void
    {
        $images = array_slice($images, 0, self::MAX_PLACEMENT_IMAGES);
        $preferences = is_array($project->preferences) ? $project->preferences : [];
        $previous = $preferences['placementImages'] ?? [];
        if (is_array($previous)) {
            foreach ($previous as $old) {
                if (is_array($old) && is_string($old['path'] ?? null) && $old['path'] !== '') {
                    $this->disk()->delete($old['path']);
                }
            }
        }

        $stored = [];
        foreach ($images as $index => $image) {
            if (! is_array($image)) {
                continue;
            }
            $base64 = $image['base64'] ?? null;
            if (! is_string($base64) || $base64 === '') {
                continue;
            }
            $decoded = base64_decode($base64, true);
            if ($decoded === false) {
                continue;
            }
            $mime = is_string($image['mime'] ?? null) ? $image['mime'] : 'image/jpeg';
            $ext = VistaFilePath::extensionFromMime($mime);
            $path = VistaFilePath::uploadedPlacement($userId, $project->id, $index, $ext);
            $this->disk()->put($path, $decoded);
            $label = is_string($image['label'] ?? null) ? $image['label'] : '';
            $entry = [
                'path' => $path,
                'label' => $label,
                'mime' => $mime,
            ];
            if (is_string($image['id'] ?? null) && $image['id'] !== '') {
                $entry['id'] = $image['id'];
            }
            $stored[] = $entry;
        }

        $preferences['placementImages'] = $stored;
        $project->update(['preferences' => $preferences]);
    }

    /**
     * @param  list<array{base64?: string, mime?: string, label?: string}>  $images
     */
    private function applyInspirationUpload(VistaProject $project, string $userId, array $images): void
    {
        $images = array_slice($images, 0, self::MAX_INSPIRATION_IMAGES);
        $preferences = is_array($project->preferences) ? $project->preferences : [];
        $previous = $preferences['inspirationImages'] ?? [];
        if (is_array($previous)) {
            foreach ($previous as $old) {
                if (is_array($old) && is_string($old['path'] ?? null) && $old['path'] !== '') {
                    $this->disk()->delete($old['path']);
                }
            }
        }

        $stored = [];
        foreach ($images as $index => $image) {
            if (! is_array($image)) {
                continue;
            }
            $base64 = $image['base64'] ?? null;
            if (! is_string($base64) || $base64 === '') {
                continue;
            }
            $decoded = base64_decode($base64, true);
            if ($decoded === false) {
                continue;
            }
            $mime = is_string($image['mime'] ?? null) ? $image['mime'] : 'image/jpeg';
            $ext = VistaFilePath::extensionFromMime($mime);
            $path = VistaFilePath::inspiration($userId, $project->id, $index, $ext);
            $this->disk()->put($path, $decoded);
            $label = is_string($image['label'] ?? null) ? $image['label'] : '';
            $stored[] = [
                'path' => $path,
                'label' => $label,
                'mime' => $mime,
            ];
        }

        $preferences['inspirationImages'] = $stored;
        $project->update(['preferences' => $preferences]);
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
                $project->update([
                    'room_image_path' => $path,
                    'cover_image_path' => $path,
                ]);

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
            'room_image_base64' => 'nullable|string',
            'room_image_mime' => 'nullable|string|max:48',
            'preferences' => 'nullable|array',
            'pdf_path' => 'nullable|string|max:500',
            'inspiration_images' => 'nullable|array|max:'.self::MAX_INSPIRATION_IMAGES,
            'inspiration_images.*.base64' => 'required_with:inspiration_images|string',
            'inspiration_images.*.mime' => 'nullable|string|max:48',
            'inspiration_images.*.label' => 'nullable|string|max:255',
            'room_extra_images' => 'nullable|array|max:'.self::MAX_ROOM_EXTRA_IMAGES,
            'room_extra_images.*.base64' => 'required_with:room_extra_images|string',
            'room_extra_images.*.mime' => 'nullable|string|max:48',
            'room_extra_images.*.id' => 'nullable|string|max:64',
            'placement_images' => 'nullable|array|max:'.self::MAX_PLACEMENT_IMAGES,
            'placement_images.*.base64' => 'required_with:placement_images|string',
            'placement_images.*.mime' => 'nullable|string|max:48',
            'placement_images.*.label' => 'nullable|string|max:255',
            'placement_images.*.id' => 'nullable|string|max:64',
        ]);

        $floorPlanBase64 = $data['floor_plan_base64'] ?? null;
        $floorPlanMime = $data['floor_plan_mime'] ?? 'image/jpeg';
        $roomImageBase64 = $data['room_image_base64'] ?? null;
        $roomImageMime = $data['room_image_mime'] ?? 'image/jpeg';
        $inspirationImages = $data['inspiration_images'] ?? null;
        $roomExtraImages = $data['room_extra_images'] ?? null;
        $placementImages = $data['placement_images'] ?? null;
        $incomingPreferences = $data['preferences'] ?? null;
        unset(
            $data['floor_plan_base64'],
            $data['floor_plan_mime'],
            $data['room_image_base64'],
            $data['room_image_mime'],
            $data['inspiration_images'],
            $data['room_extra_images'],
            $data['placement_images'],
            $data['preferences'],
        );

        if ($incomingPreferences !== null) {
            $data['preferences'] = $this->mergePreferences($project->preferences, $incomingPreferences);
        }

        $project->update(array_filter($data, fn ($v) => $v !== null));
        $project->refresh();

        if (! empty($roomImageBase64)) {
            $this->applyRoomImageUpload($project, $request->user()->id, $roomImageBase64, $roomImageMime);
            $project->refresh();
        }

        if (! empty($floorPlanBase64)) {
            $this->applyFloorPlanUpload($project, $request->user()->id, $floorPlanBase64, $floorPlanMime);
            $project->refresh();
        }

        if ($inspirationImages !== null) {
            $this->applyInspirationUpload($project, $request->user()->id, $inspirationImages);
            $project->refresh();
        }

        if ($roomExtraImages !== null) {
            $this->applyRoomExtraUpload($project, $request->user()->id, $roomExtraImages);
            $project->refresh();
        }

        if ($placementImages !== null) {
            $this->applyPlacementUpload($project, $request->user()->id, $placementImages);
            $project->refresh();
        }

        $project->update(['last_interaction_at' => now()]);

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
            'type' => 'nullable|string|in:generated,edited,regenerated,phased,viewpoint',
            'room_id' => 'nullable|string|max:50',
            'angle_index' => 'nullable|integer|min:0|max:10',
            'phase' => 'nullable|string|max:32',
            'viewpoint_id' => 'nullable|string|max:64',
            'repair_missing' => 'nullable|boolean',
        ]);

        $decoded = base64_decode($data['base64'], true);
        if ($decoded === false) {
            return response()->json(['message' => 'Invalid base64 data.'], 422);
        }

        $mime = $data['mime_type'] ?? 'image/png';
        $ext = VistaFilePath::extensionFromMime($mime);
        $repairMissing = (bool) ($data['repair_missing'] ?? false);

        if ($repairMissing) {
            $repaired = $this->repairMissingVersion($project, $user->id, $data, $decoded, $mime, $ext);
            if ($repaired !== null) {
                return $repaired;
            }
        }

        $nextVersion = $project->version_count + 1;
        $phase = $data['phase'] ?? null;
        $viewpointId = $data['viewpoint_id'] ?? null;

        if ($project->mode === 'project' && ! empty($data['room_id'])) {
            $path = VistaFilePath::roomVersion(
                $user->id,
                $project->id,
                $data['room_id'],
                $nextVersion,
                $data['angle_index'] ?? 0,
                $ext
            );
        } elseif (is_string($phase) && $phase !== '') {
            $path = VistaFilePath::generatedPhase($user->id, $project->id, $phase, $nextVersion, $ext);
        } elseif (is_string($viewpointId) && $viewpointId !== '') {
            $path = VistaFilePath::generatedViewpoint($user->id, $project->id, $viewpointId, $nextVersion, $ext);
        } else {
            $path = VistaFilePath::version($project->mode, $user->id, $project->id, $nextVersion, $ext);
        }

        $this->disk()->put($path, $decoded);

        $versionRoomId = $data['room_id'] ?? null;
        if ($versionRoomId === null && is_string($phase) && $phase !== '') {
            $versionRoomId = $phase;
        } elseif ($versionRoomId === null && is_string($viewpointId) && $viewpointId !== '') {
            $versionRoomId = $viewpointId;
        }

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
            'room_id' => $versionRoomId,
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
     * @param  array<string, mixed>  $data
     */
    private function repairMissingVersion(
        VistaProject $project,
        string $userId,
        array $data,
        string $decoded,
        string $mime,
        string $ext,
    ): ?JsonResponse {
        $versionNumber = 1;
        $angleIndex = $data['angle_index'] ?? 0;
        $roomId = $data['room_id'] ?? null;

        if ($project->mode === 'project' && ! empty($roomId)) {
            $path = VistaFilePath::roomVersion($userId, $project->id, $roomId, $versionNumber, $angleIndex, $ext);
            $versionQuery = VistaProjectVersion::where('project_id', $project->id)
                ->where('version_number', $versionNumber)
                ->where('room_id', $roomId);
        } else {
            $path = VistaFilePath::version($project->mode, $userId, $project->id, $versionNumber, $ext);
            $versionQuery = VistaProjectVersion::where('project_id', $project->id)
                ->where('version_number', $versionNumber);
        }

        $existingVersion = $versionQuery->first();
        if ($existingVersion === null) {
            return null;
        }

        if (VistaFilePath::resolveExistingPath($this->disk(), $existingVersion->file_path) !== null) {
            return response()->json([
                'data' => [
                    'id' => $existingVersion->id,
                    'file_url' => $this->fileUrl($existingVersion->file_path),
                    'version_number' => $existingVersion->version_number,
                    'type' => $existingVersion->type,
                    'created_at' => $existingVersion->created_at?->toISOString(),
                    'repaired' => false,
                ],
            ]);
        }

        $previousPath = $existingVersion->file_path;
        $this->disk()->put($path, $decoded);
        $existingVersion->update([
            'file_path' => $path,
            'mime_type' => $mime,
            'file_size_bytes' => strlen($decoded),
        ]);

        if ($project->cover_image_path === null || $project->cover_image_path === $previousPath) {
            $project->update(['cover_image_path' => $path]);
        }

        $project->update(['last_interaction_at' => now()]);

        return response()->json([
            'data' => [
                'id' => $existingVersion->id,
                'file_url' => $this->fileUrl($path),
                'version_number' => $existingVersion->version_number,
                'type' => $existingVersion->type,
                'created_at' => $existingVersion->created_at?->toISOString(),
                'repaired' => true,
            ],
        ]);
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
            'room_image_url' => $this->resolveFileUrl($project->room_image_path),
            'room_extra_images' => $this->roomExtraImagesFromPaths($project->room_extra_paths),
            'room_analysis' => $project->room_analysis,
            'room_geometry' => $project->room_geometry,
            'floor_plan_url' => $this->resolveFileUrl($project->floor_plan_path),
            'floor_plan_analysis' => $project->floor_plan_analysis,
            'master_concept' => $project->master_concept,
            'room_results' => $project->room_results,
            'preferences' => $project->preferences,
            'inspiration_images' => $this->inspirationImagesFromPreferences($project->preferences),
            'placement_images' => $this->placementImagesFromPreferences($project->preferences),
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
