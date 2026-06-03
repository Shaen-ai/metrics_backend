<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProduct extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'source_type',
        'source_url',
        'name',
        'name_en',
        'description',
        'category',
        'price',
        'currency',
        'width_cm',
        'depth_cm',
        'height_cm',
        'images',
        'main_image_url',
        'uploaded_image_path',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
