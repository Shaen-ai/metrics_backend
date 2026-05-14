<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InteriorDesignSession extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'admin_id',
        'style',
        'room_analysis',
        'design_brief',
        'latest_prompt',
    ];

    protected function casts(): array
    {
        return [
            'room_analysis' => 'array',
            'design_brief' => 'array',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(InteriorDesignImage::class, 'session_id');
    }
}
