<?php

namespace App\Support;

use App\Models\DesignProjectImage;
use App\Models\InteriorDesignImage;
use App\Models\PlannerRequest;
use App\Models\User;
use App\Models\VistaProjectVersion;
use Illuminate\Support\Facades\Storage;

/**
 * Aggregates every image a platform user owns across the four disjoint subsystems
 * into one normalized shape for the Overseer admin panel. There is no central media
 * table, and the owner column differs per source (admin_id vs user_id), so all of
 * that scatter is centralized here.
 *
 * Normalized item:
 *   [ 'source', 'id', 'kind' (generated|uploaded), 'url', 'type', 'prompt', 'context', 'created_at' ]
 *
 * `id` is the delete handle passed back to AdminMedia::delete($source, $id).
 * For planner uploads the id encodes the array index: "{requestId}::{index}".
 */
class AdminMedia
{
    /** @return list<array<string,mixed>> */
    public static function forUser(User $user, ?string $kind = null): array
    {
        $items = array_merge(
            self::vista($user),
            self::interior($user),
            self::designProjects($user),
            self::planner($user),
        );

        if ($kind === 'generated' || $kind === 'uploaded') {
            $items = array_values(array_filter($items, static fn ($i) => $i['kind'] === $kind));
        }

        usort($items, static fn ($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        return $items;
    }

    /** Cheap per-user counts for the list view. @return array{catalog:int,generated:int,uploaded:int} */
    public static function counts(User $user): array
    {
        $generated = VistaProjectVersion::whereHas('project', fn ($q) => $q->where('user_id', $user->id))->count()
            + InteriorDesignImage::whereHas('session', fn ($q) => $q->where('admin_id', $user->id))->where('type', 'generated')->count()
            + DesignProjectImage::whereHas('project', fn ($q) => $q->where('user_id', $user->id))->count();

        $uploaded = InteriorDesignImage::whereHas('session', fn ($q) => $q->where('admin_id', $user->id))->where('type', '!=', 'generated')->count();
        foreach (PlannerRequest::where('admin_id', $user->id)->pluck('image_paths') as $paths) {
            $uploaded += is_array($paths) ? count($paths) : 0;
        }

        return [
            'catalog' => $user->catalogItems()->count(),
            'generated' => $generated,
            'uploaded' => $uploaded,
        ];
    }

    /**
     * Delete one image (file + DB reference). Returns true on success.
     * Guards each source explicitly — an unknown source is a no-op false.
     */
    public static function delete(string $source, string $id): bool
    {
        return match ($source) {
            'vista' => self::deleteVista($id),
            'interior' => self::deleteInterior($id),
            'project' => self::deleteDesignProject($id),
            'planner' => self::deletePlanner($id),
            default => false,
        };
    }

    // ── source readers ──

    /** @return list<array<string,mixed>> */
    private static function vista(User $user): array
    {
        $out = [];
        $versions = VistaProjectVersion::whereHas('project', fn ($q) => $q->where('user_id', $user->id))
            ->with('project:id,title')
            ->get();
        foreach ($versions as $v) {
            $out[] = [
                'source' => 'vista',
                'id' => (string) $v->id,
                'kind' => 'generated',
                'url' => self::vistaUrl($v->file_path),
                'type' => (string) ($v->type ?? 'generated'),
                'prompt' => $v->prompt_used,
                'context' => $v->project?->title,
                'created_at' => optional($v->created_at)->toIso8601String(),
            ];
        }

        return $out;
    }

    /** @return list<array<string,mixed>> */
    private static function interior(User $user): array
    {
        $out = [];
        $images = InteriorDesignImage::whereHas('session', fn ($q) => $q->where('admin_id', $user->id))
            ->with('session:id,style')
            ->get();
        foreach ($images as $img) {
            $isGen = ($img->type ?? 'generated') === 'generated';
            $out[] = [
                'source' => 'interior',
                'id' => (string) $img->id,
                'kind' => $isGen ? 'generated' : 'uploaded',
                'url' => self::publicUrl($img->file_path),
                'type' => (string) ($img->type ?? 'generated'),
                'prompt' => $img->prompt_used,
                'context' => $img->session?->style,
                'created_at' => optional($img->created_at)->toIso8601String(),
            ];
        }

        return $out;
    }

    /** @return list<array<string,mixed>> */
    private static function designProjects(User $user): array
    {
        $out = [];
        $images = DesignProjectImage::whereHas('project', fn ($q) => $q->where('user_id', $user->id))
            ->with('project:id,title')
            ->get();
        foreach ($images as $img) {
            $out[] = [
                'source' => 'project',
                'id' => (string) $img->id,
                'kind' => 'generated',
                'url' => self::publicUrl($img->file_path),
                'type' => (string) ($img->type ?? 'room_render'),
                'prompt' => null,
                'context' => $img->project?->title,
                'created_at' => optional($img->created_at)->toIso8601String(),
            ];
        }

        return $out;
    }

    /** @return list<array<string,mixed>> */
    private static function planner(User $user): array
    {
        $out = [];
        $requests = PlannerRequest::where('admin_id', $user->id)->get(['id', 'image_paths', 'created_at', 'text']);
        foreach ($requests as $req) {
            $paths = is_array($req->image_paths) ? $req->image_paths : [];
            foreach ($paths as $index => $path) {
                if (! is_string($path) || $path === '') {
                    continue;
                }
                $out[] = [
                    'source' => 'planner',
                    'id' => $req->id.'::'.$index,
                    'kind' => 'uploaded',
                    'url' => self::asIsOrPublicUrl($path),
                    'type' => 'planner_upload',
                    'prompt' => is_string($req->text) ? mb_strimwidth($req->text, 0, 120, '…') : null,
                    'context' => null,
                    'created_at' => optional($req->created_at)->toIso8601String(),
                ];
            }
        }

        return $out;
    }

    // ── deleters ──

    private static function deleteVista(string $id): bool
    {
        $v = VistaProjectVersion::find($id);
        if ($v === null) {
            return false;
        }
        $disk = Storage::disk('vista_files');
        if (is_string($v->file_path) && $v->file_path !== '') {
            $resolved = VistaFilePath::resolveExistingPath($disk, $v->file_path);
            if ($resolved !== null) {
                $disk->delete($resolved);
            }
        }
        $v->delete();

        return true;
    }

    private static function deleteInterior(string $id): bool
    {
        $img = InteriorDesignImage::find($id);
        if ($img === null) {
            return false;
        }
        self::deletePublic($img->file_path);
        $img->delete();

        return true;
    }

    private static function deleteDesignProject(string $id): bool
    {
        $img = DesignProjectImage::find($id);
        if ($img === null) {
            return false;
        }
        self::deletePublic($img->file_path);
        $img->delete();

        return true;
    }

    private static function deletePlanner(string $compositeId): bool
    {
        [$requestId, $indexRaw] = array_pad(explode('::', $compositeId, 2), 2, null);
        if ($requestId === null || $indexRaw === null || ! is_numeric($indexRaw)) {
            return false;
        }
        $index = (int) $indexRaw;
        $req = PlannerRequest::find($requestId);
        if ($req === null) {
            return false;
        }
        $paths = is_array($req->image_paths) ? $req->image_paths : [];
        if (! array_key_exists($index, $paths)) {
            return false;
        }
        // Best-effort file removal, then drop the array entry.
        self::deletePublicFromUrl((string) $paths[$index]);
        unset($paths[$index]);
        $req->update(['image_paths' => array_values($paths)]);

        return true;
    }

    // ── url + path helpers ──

    private static function vistaUrl(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        return url('/vista-files/'.ltrim($path, '/'));
    }

    private static function publicUrl(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        return url('/storage/'.ltrim($path, '/'));
    }

    /** Planner entries are stored as absolute URLs already; pass through, else treat as public path. */
    private static function asIsOrPublicUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return (string) self::publicUrl($path);
    }

    private static function deletePublic(?string $path): void
    {
        if (is_string($path) && $path !== '') {
            Storage::disk('public')->delete($path);
        }
    }

    /** Map an absolute /storage/... URL back to a public-disk path and delete it. */
    private static function deletePublicFromUrl(string $url): void
    {
        $pos = strpos($url, '/storage/');
        if ($pos === false) {
            return;
        }
        $relative = substr($url, $pos + strlen('/storage/'));
        self::deletePublic($relative !== '' ? $relative : null);
    }
}
