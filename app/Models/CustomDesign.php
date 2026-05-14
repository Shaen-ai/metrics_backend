<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomDesign extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'admin_id',
        'owner_user_id',
        'status',
        'room_name',
        'notes',
        'customer_name',
        'customer_email',
        'design',
        'snapshot_path',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'design' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
