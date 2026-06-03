<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnonymousTokenSession extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'device_id';

    protected $keyType = 'string';

    protected $fillable = [
        'device_id',
        'token_balance',
        'granted_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
