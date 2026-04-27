<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialTemplate extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'material_templates';

    protected $fillable = [
        'id',
        'manufacturer',
        'external_code',
        'name',
        'type',
        'types',
        'categories',
        'category',
        'color',
        'color_hex',
        'color_code',
        'unit',
        'image_url',
        'source_url',
        'sheet_width_cm',
        'sheet_height_cm',
        'grain_direction',
        'kerf_mm',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'types' => 'array',
            'categories' => 'array',
            'sheet_width_cm' => 'decimal:2',
            'sheet_height_cm' => 'decimal:2',
            'kerf_mm' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }
}
