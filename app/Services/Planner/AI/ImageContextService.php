<?php

namespace App\Services\Planner\AI;

use App\Services\Planner\DTO\PlanRequestDTO;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImageContextService
{
    public function build(array $validated, ?UploadedFile $roomImage, ?UploadedFile $inspirationImage): PlanRequestDTO
    {
        $room = $this->storeImage($roomImage, $validated['admin']->id, 'room');
        $inspiration = $this->storeImage($inspirationImage, $validated['admin']->id, 'inspiration');

        return new PlanRequestDTO(
            admin: $validated['admin'],
            text: trim($validated['text']),
            roomImagePath: $room['path'] ?? null,
            roomImageUrl: $room['url'] ?? null,
            roomImageMime: $room['mime'] ?? null,
            roomImageBase64: $room['base64'] ?? null,
            inspirationImagePath: $inspiration['path'] ?? null,
            inspirationImageUrl: $inspiration['url'] ?? null,
            inspirationImageMime: $inspiration['mime'] ?? null,
            inspirationImageBase64: $inspiration['base64'] ?? null,
        );
    }

    private function storeImage(?UploadedFile $file, string $adminId, string $kind): ?array
    {
        if (! $file) {
            return null;
        }

        $path = $file->store("files/{$adminId}/planner", 'public');
        $mime = $file->getMimeType() ?: 'image/jpeg';

        return [
            'path' => $path,
            'url' => url('/storage/'.$path),
            'mime' => in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true) ? $mime : 'image/jpeg',
            'base64' => base64_encode(Storage::disk('public')->get($path)),
            'kind' => $kind,
        ];
    }
}
