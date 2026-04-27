<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'admin_id',
        'mode_id',
        'sub_mode_id',
        'name',
        'description',
        'width',
        'height',
        'depth',
        'dimension_unit',
        'price',
        'currency',
        'image_url',
        'category',
        'is_active',
        'model_url',
        'model_job_id',
        'model_status',
        'model_error',
        'placement_type',
        'default_cabinet_material_id',
        'default_door_material_id',
        'pricing_body_weight',
        'pricing_door_weight',
        'default_handle_id',
        'template_options',
        'allowed_handle_ids',
        'is_configurable_template',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'depth' => 'decimal:2',
            'price' => 'decimal:2',
            'pricing_body_weight' => 'decimal:4',
            'pricing_door_weight' => 'decimal:4',
            'is_active' => 'boolean',
            'is_configurable_template' => 'boolean',
            'template_options' => 'array',
            'allowed_handle_ids' => 'array',
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
        return $this->hasMany(ModuleImage::class)->orderBy('sort_order');
    }

    public function connectionPoints(): HasMany
    {
        return $this->hasMany(ModuleConnectionPoint::class);
    }

    public function compatibleModules(): BelongsToMany
    {
        return $this->belongsToMany(
            Module::class,
            'module_compatibilities',
            'module_id',
            'compatible_module_id'
        );
    }

    public function defaultCabinetMaterial(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'default_cabinet_material_id');
    }

    public function defaultDoorMaterial(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'default_door_material_id');
    }
}
