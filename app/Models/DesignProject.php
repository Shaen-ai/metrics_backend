<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DesignProject extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'title',
        'status',
        'style',
        'total_area_m2',
        'family_members',
        'budget_tier',
        'wishes',
        'address',
        'floor_plan_path',
        'floor_plan_analysis',
        'master_concept',
        'room_results',
        'technical_drawings',
        'pdf_path',
        'progress_percent',
        'current_phase',
        'current_room',
        'error_message',
        'cost_usd',
    ];

    protected function casts(): array
    {
        return [
            'floor_plan_analysis' => 'array',
            'master_concept' => 'array',
            'room_results' => 'array',
            'technical_drawings' => 'array',
            'total_area_m2' => 'decimal:2',
            'cost_usd' => 'decimal:4',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(DesignProjectImage::class, 'project_id');
    }
}
