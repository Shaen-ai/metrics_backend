<?php

namespace App\Support;

class VistaFilePath
{
    public static function basePath(string $mode, string $userId, string $projectId): string
    {
        $folder = $mode === 'quick_room' ? 'quick_room' : 'projects';

        return "{$folder}/{$userId}/{$projectId}";
    }

    public static function roomOriginal(string $userId, string $projectId, string $ext = 'jpg'): string
    {
        return "quick_room/{$userId}/{$projectId}/room/original.{$ext}";
    }

    public static function roomExtra(string $userId, string $projectId, int $index, string $ext = 'jpg'): string
    {
        return "quick_room/{$userId}/{$projectId}/room/extra-{$index}.{$ext}";
    }

    public static function version(string $mode, string $userId, string $projectId, int $versionNum, string $ext = 'png'): string
    {
        $base = self::basePath($mode, $userId, $projectId);
        $v = str_pad((string) $versionNum, 3, '0', STR_PAD_LEFT);

        return "{$base}/versions/v{$v}.{$ext}";
    }

    public static function roomVersion(string $userId, string $projectId, string $roomId, int $versionNum, int $angle = 0, string $ext = 'png'): string
    {
        $v = str_pad((string) $versionNum, 3, '0', STR_PAD_LEFT);

        return "projects/{$userId}/{$projectId}/rooms/{$roomId}/v{$v}-angle{$angle}.{$ext}";
    }

    public static function floorPlan(string $userId, string $projectId, string $ext = 'jpg'): string
    {
        return "projects/{$userId}/{$projectId}/floor_plan/plan.{$ext}";
    }

    public static function attachment(string $mode, string $userId, string $projectId, string $messageId, string $ext = 'jpg'): string
    {
        $base = self::basePath($mode, $userId, $projectId);

        return "{$base}/attachments/{$messageId}.{$ext}";
    }

    public static function pdf(string $userId, string $projectId): string
    {
        return "projects/{$userId}/{$projectId}/project.pdf";
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
