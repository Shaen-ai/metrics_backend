<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogItem extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'admin_id',
        'mode_id',
        'sub_mode_id',
        'name',
        'model',
        'description',
        'width',
        'height',
        'depth',
        'dimension_unit',
        'price',
        'unit',
        'currency',
        'delivery_days',
        'category',
        'additional_categories',
        'planner_subcategory',
        'is_active',
        'model_url',
        'model_job_id',
        'model_status',
        'model_error',
        'supports_outdoor_cushions',
        'outdoor_cushion_defaults',
        'is_fabric_customizable',
        'fabric_parts',
        'for_design',
        'surface_texture_width_cm',
        'surface_texture_height_cm',
        'surface_item_width_cm',
        'surface_item_height_cm',
        'surface_layout_pattern',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'depth' => 'decimal:2',
            'price' => 'decimal:2',
            'delivery_days' => 'integer',
            'is_active' => 'boolean',
            'for_design' => 'boolean',
            'additional_categories' => 'array',
            'supports_outdoor_cushions' => 'boolean',
            'outdoor_cushion_defaults' => 'array',
            'is_fabric_customizable' => 'boolean',
            'fabric_parts' => 'array',
            'surface_texture_width_cm' => 'decimal:2',
            'surface_texture_height_cm' => 'decimal:2',
            'surface_item_width_cm' => 'decimal:2',
            'surface_item_height_cm' => 'decimal:2',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function mode(): BelongsTo
    {
        return $this->belongsTo(Mode::class);
    }

    public function subMode(): BelongsTo
    {
        return $this->belongsTo(SubMode::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(CatalogItemImage::class)->orderBy('sort_order');
    }

    public function colors(): HasMany
    {
        return $this->hasMany(CatalogItemColor::class);
    }

    /**
     * Primary `category` plus optional extra placements (e.g. show same chair under Kitchen).
     *
     * @return list<string>
     */
    public function mergedCategoryLabels(): array
    {
        $primary = trim((string) ($this->category ?? ''));
        $extra = is_array($this->additional_categories) ? $this->additional_categories : [];
        $out = [];
        $seenLower = [];

        foreach (array_merge($primary !== '' ? [$primary] : [], $extra) as $s) {
            $t = is_string($s) ? trim($s) : '';
            if ($t === '') {
                continue;
            }
            $lk = strtolower($t);
            if (isset($seenLower[$lk])) {
                continue;
            }
            $seenLower[$lk] = true;
            $out[] = $t;
        }

        return $out;
    }
}
