<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VistaProjectMessage extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'role',
        'content_type',
        'text',
        'version_id',
        'attachment_path',
        'attachment_mime',
        'sequence',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(VistaProject::class, 'project_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(VistaProjectVersion::class, 'version_id');
    }
}
