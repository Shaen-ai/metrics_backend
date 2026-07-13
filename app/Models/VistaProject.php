<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VistaProject extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'mode',
        'title',
        'cover_image_path',
        'status',
        'share_enabled',
        'share_token',
        'share_enabled_at',
        'style',
        'room_image_path',
        'room_extra_paths',
        'room_analysis',
        'room_geometry',
        'floor_plan_path',
        'floor_plan_analysis',
        'master_concept',
        'room_results',
        'preferences',
        'pdf_path',
        'last_interaction_at',
        'message_count',
        'version_count',
    ];

    protected function casts(): array
    {
        return [
            'room_extra_paths' => 'array',
            'room_analysis' => 'array',
            'room_geometry' => 'array',
            'floor_plan_analysis' => 'array',
            'master_concept' => 'array',
            'room_results' => 'array',
            'preferences' => 'array',
            'last_interaction_at' => 'datetime',
            'share_enabled' => 'boolean',
            'share_enabled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(VistaProjectVersion::class, 'project_id')->orderBy('version_number');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(VistaProjectMessage::class, 'project_id')->orderBy('sequence');
    }
}
