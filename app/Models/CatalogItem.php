<?php

namespace App\Models;

use App\Services\Catalog\Contracts\RerankableProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogItem extends Model implements RerankableProduct
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
        'product_family',
        'product_subtype',
        'material_tags',
        'color_tags',
        'ai_tags',
        'ai_enriched_at',
        'embedding_text',
        'embedding_text_version',
        'embedded_at',
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
            'material_tags' => 'array',
            'color_tags' => 'array',
            'ai_tags' => 'array',
            'ai_enriched_at' => 'datetime',
            'embedded_at' => 'datetime',
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

    // ── RerankableProduct ──

    private function toCm(float $value): float
    {
        if (($this->dimension_unit ?? 'cm') === 'inch') {
            return $value * 2.54;
        }

        return $value;
    }

    public function getRerankId(): int|string
    {
        return (string) $this->id;
    }

    public function getWidthCm(): ?float
    {
        return $this->width !== null ? $this->toCm((float) $this->width) : null;
    }

    public function getDepthCm(): ?float
    {
        return $this->depth !== null ? $this->toCm((float) $this->depth) : null;
    }

    public function getHeightCm(): ?float
    {
        return $this->height !== null ? $this->toCm((float) $this->height) : null;
    }

    public function hasDimensions(): bool
    {
        return $this->width !== null && (float) $this->width > 0;
    }

    public function getProductFamily(): ?string
    {
        return $this->product_family;
    }

    public function getProductSubtype(): ?string
    {
        return $this->product_subtype;
    }

    public function getMaterialTags(): array
    {
        return is_array($this->material_tags) ? $this->material_tags : [];
    }

    public function getColorTags(): array
    {
        return is_array($this->color_tags) ? $this->color_tags : [];
    }

    public function getAiTags(): array
    {
        return is_array($this->ai_tags) ? $this->ai_tags : [];
    }

    public function getName(): string
    {
        return (string) ($this->name ?? '');
    }

    public function getNameEn(): ?string
    {
        return null;
    }

    public function getBrand(): ?string
    {
        return null;
    }

    public function getPrice(): float
    {
        return (float) ($this->price ?? 0);
    }

    public function getCutoutConfidence(): float
    {
        return 0.0;
    }

    public function getPriority(): int
    {
        return 0;
    }
}
