<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlannerRequest extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'admin_id',
        'text',
        'image_paths',
        'ai_interpretation',
        'result',
        'estimated_price',
        'status',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'image_paths' => 'array',
            'ai_interpretation' => 'array',
            'result' => 'array',
            'estimated_price' => 'decimal:2',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
