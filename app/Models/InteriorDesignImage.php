<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InteriorDesignImage extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'session_id',
        'file_path',
        'prompt_used',
        'type',
        'mime_type',
        'file_size_bytes',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(InteriorDesignSession::class, 'session_id');
    }
}
