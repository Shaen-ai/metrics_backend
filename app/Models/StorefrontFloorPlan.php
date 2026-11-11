<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontFloorPlan extends Model
{
    use HasUuids;

    protected $fillable = [
        'admin_id',
        'floor_plan_path',
        'floor_plan_analysis',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'floor_plan_analysis' => 'array',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
