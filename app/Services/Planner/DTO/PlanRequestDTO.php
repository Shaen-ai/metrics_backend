<?php

namespace App\Services\Planner\DTO;

use App\Models\User;

readonly class PlanRequestDTO
{
    public function __construct(
        public User $admin,
        public string $text,
        public ?string $roomImagePath = null,
        public ?string $roomImageUrl = null,
        public ?string $roomImageMime = null,
        public ?string $roomImageBase64 = null,
        public ?string $inspirationImagePath = null,
        public ?string $inspirationImageUrl = null,
        public ?string $inspirationImageMime = null,
        public ?string $inspirationImageBase64 = null,
    ) {
    }

    public function imagePaths(): array
    {
        return [
            'room' => $this->roomImagePath,
            'inspiration' => $this->inspirationImagePath,
        ];
    }

    public function imageContext(): array
    {
        return array_values(array_filter([
            $this->roomImageBase64 ? [
                'kind' => 'room',
                'mime' => $this->roomImageMime ?? 'image/jpeg',
                'url' => $this->roomImageUrl,
                'base64' => $this->roomImageBase64,
            ] : null,
            $this->inspirationImageBase64 ? [
                'kind' => 'inspiration',
                'mime' => $this->inspirationImageMime ?? 'image/jpeg',
                'url' => $this->inspirationImageUrl,
                'base64' => $this->inspirationImageBase64,
            ] : null,
        ]));
    }
}
