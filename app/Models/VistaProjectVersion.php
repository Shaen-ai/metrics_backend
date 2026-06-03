<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VistaProjectVersion extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'file_path',
        'mime_type',
        'file_size_bytes',
        'prompt_used',
        'feedback',
        'design_brief',
        'products_used',
        'room_geometry',
        'version_number',
        'type',
        'room_id',
        'angle_index',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'design_brief' => 'array',
            'products_used' => 'array',
            'room_geometry' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(VistaProject::class, 'project_id');
    }
}
