<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Material extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'admin_id',
        'mode_id',
        'manufacturer',
        'sub_mode_id',
        'name',
        'type',
        'types',
        'category',
        'categories',
        'color',
        'color_hex',
        'color_code',
        'price',
        'price_per_unit',
        'currency',
        'unit',
        'image',
        'image_url',
        'sheet_width_cm',
        'sheet_height_cm',
        'grain_direction',
        'kerf_mm',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_per_unit' => 'decimal:2',
            'sheet_width_cm' => 'decimal:2',
            'sheet_height_cm' => 'decimal:2',
            'kerf_mm' => 'decimal:2',
            'is_active' => 'boolean',
            'categories' => 'array',
            'types' => 'array',
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

    public function orderItems(): BelongsToMany
    {
        return $this->belongsToMany(OrderItem::class, 'order_item_materials');
    }
}
