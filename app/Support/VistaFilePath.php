<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;

class VistaFilePath
{
    public static function projectRoot(string $userId, string $projectId): string
    {
        return "{$userId}/{$projectId}";
    }

    public static function uploadedRoomOriginal(string $userId, string $projectId, string $ext = 'jpg'): string
    {
        return self::projectRoot($userId, $projectId)."/uploaded/room-original.{$ext}";
    }

    public static function uploadedRoomExtra(string $userId, string $projectId, int $index, string $ext = 'jpg'): string
    {
        return self::projectRoot($userId, $projectId)."/uploaded/rooms/extra-{$index}.{$ext}";
    }

    public static function uploadedRoomPhoto(string $userId, string $projectId, string $roomId, string $ext = 'jpg'): string
    {
        return self::projectRoot($userId, $projectId)."/uploaded/rooms/{$roomId}/original.{$ext}";
    }

    public static function floorPlan(string $userId, string $projectId, string $ext = 'jpg'): string
    {
        return self::projectRoot($userId, $projectId)."/uploaded/floor-plan.{$ext}";
    }

    public static function inspiration(string $userId, string $projectId, int $index, string $ext = 'jpg'): string
    {
        return self::projectRoot($userId, $projectId)."/uploaded/inspiration/insp-{$index}.{$ext}";
    }

    public static function uploadedPlacement(string $userId, string $projectId, int $index, string $ext = 'jpg'): string
    {
        return self::projectRoot($userId, $projectId)."/uploaded/placement/place-{$index}.{$ext}";
    }

    public static function generatedPhase(string $userId, string $projectId, string $phase, int $versionNum, string $ext = 'png'): string
    {
        $v = str_pad((string) $versionNum, 3, '0', STR_PAD_LEFT);
        $safePhase = preg_replace('/[^a-z0-9_-]/i', '-', $phase) ?: 'unknown';

        return self::projectRoot($userId, $projectId)."/generated/phases/{$safePhase}/v{$v}.{$ext}";
    }

    public static function generatedViewpoint(string $userId, string $projectId, string $photoId, int $versionNum, string $ext = 'png'): string
    {
        $v = str_pad((string) $versionNum, 3, '0', STR_PAD_LEFT);
        $safeId = preg_replace('/[^a-z0-9_-]/i', '-', $photoId) ?: 'view';

        return self::projectRoot($userId, $projectId)."/generated/viewpoints/{$safeId}/v{$v}.{$ext}";
    }

    public static function version(string $mode, string $userId, string $projectId, int $versionNum, string $ext = 'png'): string
    {
        $v = str_pad((string) $versionNum, 3, '0', STR_PAD_LEFT);

        return self::projectRoot($userId, $projectId)."/generated/versions/v{$v}.{$ext}";
    }

    public static function roomVersion(string $userId, string $projectId, string $roomId, int $versionNum, int $angle = 0, string $ext = 'png'): string
    {
        $v = str_pad((string) $versionNum, 3, '0', STR_PAD_LEFT);

        return self::projectRoot($userId, $projectId)."/generated/rooms/{$roomId}/v{$v}-angle{$angle}.{$ext}";
    }

    public static function attachment(string $mode, string $userId, string $projectId, string $messageId, string $ext = 'jpg'): string
    {
        return self::projectRoot($userId, $projectId)."/uploaded/attachments/{$messageId}.{$ext}";
    }

    public static function pdf(string $userId, string $projectId): string
    {
        return self::projectRoot($userId, $projectId).'/meta/project.pdf';
    }

    public static function generatedRoomFile(string $userId, string $projectId, string $roomId, string $filename): string
    {
        return self::projectRoot($userId, $projectId)."/generated/rooms/{$roomId}/{$filename}";
    }

    public static function approvedRoomFile(string $userId, string $projectId, string $roomId, string $filename): string
    {
        return self::projectRoot($userId, $projectId)."/approved/rooms/{$roomId}/{$filename}";
    }

    public static function metaRoomFile(string $userId, string $projectId, string $roomId, string $filename): string
    {
        return self::projectRoot($userId, $projectId)."/meta/rooms/{$roomId}/{$filename}";
    }

    /** @return list<string> */
    public static function pathCandidates(string $storedPath): array
    {
        $candidates = [$storedPath];

        foreach (self::legacyPathCandidates($storedPath) as $legacy) {
            $candidates[] = $legacy;
        }

        foreach (self::extensionSwapCandidates($storedPath) as $swap) {
            $candidates[] = $swap;
        }

        return array_values(array_unique($candidates));
    }

    public static function resolveExistingPath(Filesystem $disk, string $storedPath): ?string
    {
        foreach (self::pathCandidates($storedPath) as $candidate) {
            if ($disk->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return list<string> */
    private static function extensionSwapCandidates(string $storedPath): array
    {
        if (! preg_match('/\.(jpe?g|png|webp|gif)$/i', $storedPath, $matches)) {
            return [];
        }

        $ext = strtolower($matches[1]);
        $base = substr($storedPath, 0, -strlen($matches[0]));
        $alternates = match ($ext) {
            'jpg', 'jpeg' => ['png', 'webp'],
            'png' => ['jpg', 'webp'],
            'webp' => ['jpg', 'png'],
            default => ['jpg', 'png'],
        };

        return array_map(static fn (string $alternate) => $base.'.'.$alternate, $alternates);
    }

    /** @return list<string> */
    public static function legacyPathCandidates(string $storedPath): array
    {
        $candidates = [$storedPath];

        if (preg_match('#^([^/]+)/([^/]+)/uploaded/#', $storedPath)) {
            return $candidates;
        }

        if (preg_match('#^quick_room/([^/]+)/([^/]+)/room/original\.(\w+)$#', $storedPath, $m)) {
            $candidates[] = self::uploadedRoomOriginal($m[1], $m[2], $m[3]);
        } elseif (preg_match('#^quick_room/([^/]+)/([^/]+)/room/extra-(\d+)\.(\w+)$#', $storedPath, $m)) {
            $candidates[] = self::uploadedRoomExtra($m[1], $m[2], (int) $m[3], $m[4]);
        } elseif (preg_match('#^projects/([^/]+)/([^/]+)/floor_plan/plan\.(\w+)$#', $storedPath, $m)) {
            $candidates[] = self::floorPlan($m[1], $m[2], $m[3]);
        } elseif (preg_match('#^quick_room/([^/]+)/([^/]+)/versions/v(\d+)\.(\w+)$#', $storedPath, $m)) {
            $candidates[] = self::version('quick_room', $m[1], $m[2], (int) $m[3], $m[4]);
        } elseif (preg_match('#^projects/([^/]+)/([^/]+)/versions/v(\d+)\.(\w+)$#', $storedPath, $m)) {
            $candidates[] = self::version('project', $m[1], $m[2], (int) $m[3], $m[4]);
        } elseif (preg_match('#^projects/([^/]+)/([^/]+)/rooms/([^/]+)/v(\d+)-angle(\d+)\.(\w+)$#', $storedPath, $m)) {
            $candidates[] = self::roomVersion($m[1], $m[2], $m[3], (int) $m[4], (int) $m[5], $m[6]);
        } elseif (preg_match('#^(quick_room|projects)/([^/]+)/([^/]+)/attachments/([^/]+)\.(\w+)$#', $storedPath, $m)) {
            $candidates[] = self::attachment($m[1], $m[2], $m[3], $m[4], $m[5]);
        } elseif (preg_match('#^projects/([^/]+)/([^/]+)/project\.pdf$#', $storedPath, $m)) {
            $candidates[] = self::pdf($m[1], $m[2]);
        }

        return array_values(array_unique($candidates));
    }

    /** @deprecated Use projectRoot() for deletes */
    public static function basePath(string $mode, string $userId, string $projectId): string
    {
        return self::projectRoot($userId, $projectId);
    }

    /** @deprecated Use uploadedRoomOriginal() */
    public static function roomOriginal(string $userId, string $projectId, string $ext = 'jpg'): string
    {
        return self::uploadedRoomOriginal($userId, $projectId, $ext);
    }

    /** @deprecated Use uploadedRoomExtra() */
    public static function roomExtra(string $userId, string $projectId, int $index, string $ext = 'jpg'): string
    {
        return self::uploadedRoomExtra($userId, $projectId, $index, $ext);
    }

    public static function extensionFromMime(string $mimeType): string
    {
        return match (true) {
            str_contains($mimeType, 'jpeg'), str_contains($mimeType, 'jpg') => 'jpg',
            str_contains($mimeType, 'webp') => 'webp',
            str_contains($mimeType, 'gif') => 'gif',
            default => 'png',
        };
    }
}
