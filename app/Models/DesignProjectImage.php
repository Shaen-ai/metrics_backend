<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignProjectImage extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_id',
        'room_id',
        'angle_index',
        'file_path',
        'mime_type',
        'file_size_bytes',
        'type',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(DesignProject::class, 'project_id');
    }
}
