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
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicVistaShareController extends Controller
{
    private function disk(): Filesystem
    {
        return Storage::disk('vista_files');
    }

    private function findSharedProject(string $token): ?VistaProject
    {
        return VistaProject::query()
            ->where('share_token', $token)
            ->where('share_enabled', true)
            ->where('mode', 'quick_room')
            ->where('status', 'active')
            ->first();
    }

    private function assetPath(string $token, string $suffix): string
    {
        return '/api/public/vista/share/'.rawurlencode($token).'/assets/'.$suffix;
    }

    /**
     * GET /api/public/vista/share/{token}
     */
    public function show(string $token): JsonResponse
    {
        $project = $this->findSharedProject($token);
        if ($project === null) {
            return response()->json(['message' => 'Share link not found or disabled.'], 404);
        }

        $project->load(['versions', 'messages']);

        $preferences = is_array($project->preferences) ? $project->preferences : [];
        $draftPrompt = $preferences['draftPrompt'] ?? null;
        $draftPrompt = is_string($draftPrompt) && $draftPrompt !== '' ? $draftPrompt : null;

        $versions = $project->versions->map(fn (VistaProjectVersion $v) => [
            'id' => $v->id,
            'version_number' => $v->version_number,
            'type' => $v->type,
            'image_url' => $this->assetPath($token, 'versions/'.rawurlencode($v->id)),
            'prompt_used' => $v->prompt_used,
            'feedback' => $v->feedback,
            'products_used' => $v->products_used,
            'created_at' => $v->created_at?->toISOString(),
        ])->values();

        $latestVersion = $project->versions->sortByDesc('version_number')->first();

        $promptHistory = $project->messages
            ->filter(function (VistaProjectMessage $msg) {
                if ($msg->role !== 'user') {
                    return false;
                }
                if ($msg->content_type !== 'text') {
                    return false;
                }

                return is_string($msg->text) && trim($msg->text) !== '';
            })
            ->map(fn (VistaProjectMessage $msg) => [
                'role' => $msg->role,
                'text' => $msg->text,
                'created_at' => $msg->created_at?->toISOString(),
            ])
            ->values();

        $roomImageUrl = null;
        if (is_string($project->room_image_path) && $project->room_image_path !== '') {
            $roomImageUrl = $this->assetPath($token, 'room');
        }

        return response()->json([
            'data' => [
                'title' => $project->title,
                'style' => $project->style,
                'created_at' => $project->created_at?->toISOString(),
                'draft_prompt' => $draftPrompt,
                'room_image_url' => $roomImageUrl,
                'versions' => $versions,
                'products_used' => $latestVersion?->products_used,
                'prompt_history' => $promptHistory,
            ],
        ]);
    }

    /**
     * GET /api/public/vista/share/{token}/assets/{asset}
     * asset = "room" | "versions/{versionId}"
     */
    public function asset(string $token, string $asset): StreamedResponse|JsonResponse
    {
        $project = $this->findSharedProject($token);
        if ($project === null) {
            return response()->json(['message' => 'Share link not found or disabled.'], 404);
        }

        $storedPath = null;
        $mime = 'application/octet-stream';

        if ($asset === 'room') {
            $storedPath = $project->room_image_path;
        } elseif (preg_match('#^versions/([0-9a-f-]{36})$#i', $asset, $m)) {
            $version = VistaProjectVersion::query()
                ->where('id', $m[1])
                ->where('project_id', $project->id)
                ->first();
            if ($version !== null) {
                $storedPath = $version->file_path;
                $mime = $version->mime_type ?: 'image/png';
            }
        } else {
            return response()->json(['message' => 'Invalid asset.'], 404);
        }

        if (! is_string($storedPath) || $storedPath === '') {
            return response()->json(['message' => 'Asset not found.'], 404);
        }

        $resolvedPath = VistaFilePath::resolveExistingPath($this->disk(), $storedPath);
        if ($resolvedPath === null) {
            return response()->json(['message' => 'Asset not found.'], 404);
        }

        if ($asset === 'room') {
            $mime = $this->disk()->mimeType($resolvedPath) ?: 'image/jpeg';
        }

        return response()->stream(function () use ($resolvedPath) {
            $stream = $this->disk()->readStream($resolvedPath);
            if ($stream === false) {
                return;
            }
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
