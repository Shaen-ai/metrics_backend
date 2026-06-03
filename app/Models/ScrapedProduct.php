<?php

namespace App\Models;

use App\Services\Catalog\Contracts\RerankableProduct;
use App\Support\MySqlFullTextQuery;
use Illuminate\Database\Eloquent\Model;

class ScrapedProduct extends Model implements RerankableProduct
{
    protected $fillable = [
        'source_marketplace',
        'external_url',
        'external_url_hash',
        'external_id',
        'slug',
        'name',
        'name_en',
        'description',
        'description_en',
        'category',
        'category_en',
        'brand',
        'price',
        'currency',
        'old_price',
        'in_stock',
        'width_cm',
        'depth_cm',
        'height_cm',
        'dimensions_raw',
        'has_dimensions',
        'images',
        'main_image_url',
        'attributes',
        'sku',
        'priority',
        'scraped_at',
        'product_family',
        'product_subtype',
        'material_tags',
        'color_tags',
        'ai_tags',
        'ai_enriched_at',
        'embedding_text',
        'embedding_text_version',
        'embedded_at',
        'cutout_image_url',
        'cutout_confidence',
        'unavailable_at',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            if ($model->isDirty('external_url') || empty($model->external_url_hash)) {
                $model->external_url_hash = hash('sha256', $model->external_url);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'attributes' => 'array',
            'material_tags' => 'array',
            'color_tags' => 'array',
            'ai_tags' => 'array',
            'ai_enriched_at' => 'datetime',
            'in_stock' => 'boolean',
            'has_dimensions' => 'boolean',
            'scraped_at' => 'datetime',
            'embedded_at' => 'datetime',
            'cutout_confidence' => 'float',
            'unavailable_at' => 'datetime',
            'priority' => 'integer',
        ];
    }

    /** Higher `priority` values appear first; null = no priority. */
    public function scopeOrderByCatalogPriority($query)
    {
        return $query->orderByRaw('priority IS NULL, priority DESC');
    }

    public function scopeMarketplace($query, string $marketplace)
    {
        return $query->where('source_marketplace', $marketplace);
    }

    public function scopeWithDimensions($query)
    {
        return $query->where('has_dimensions', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('in_stock', true);
    }

    public function scopeWithPrice($query)
    {
        return $query->where('price', '>', 0);
    }

    /**
     * MySQL FULLTEXT search across name, name_en, category_en, brand.
     */
    public function scopeSearch($query, string $term)
    {
        if (config('database.default') === 'mysql') {
            $boolean = MySqlFullTextQuery::toBooleanMode($term);
            if ($boolean !== null) {
                return $query->whereRaw(
                    'MATCH(name, name_en, category_en, brand) AGAINST(? IN BOOLEAN MODE)',
                    [$boolean]
                );
            }
        }

        return $query->where(function ($q) use ($term) {
            $like = '%'.$term.'%';
            $q->where('name', 'LIKE', $like)
                ->orWhere('name_en', 'LIKE', $like)
                ->orWhere('category_en', 'LIKE', $like)
                ->orWhere('brand', 'LIKE', $like);
        });
    }

    public function getPriceFormattedAttribute(): string
    {
        return number_format($this->price).' '.$this->currency;
    }

    public function getDimensionsAttribute(): ?string
    {
        if (! $this->width_cm) {
            return null;
        }

        return "{$this->width_cm}x{$this->depth_cm}x{$this->height_cm} cm";
    }

    // ── RerankableProduct ──

    public function getRerankId(): int|string
    {
        return (int) $this->id;
    }

    public function getWidthCm(): ?float
    {
        return $this->width_cm !== null ? (float) $this->width_cm : null;
    }

    public function getDepthCm(): ?float
    {
        return $this->depth_cm !== null ? (float) $this->depth_cm : null;
    }

    public function getHeightCm(): ?float
    {
        return $this->height_cm !== null ? (float) $this->height_cm : null;
    }

    public function hasDimensions(): bool
    {
        return (bool) $this->has_dimensions && $this->width_cm;
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
        return $this->name_en;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function getPrice(): float
    {
        return (float) ($this->price ?? 0);
    }

    public function getCutoutConfidence(): float
    {
        return (float) ($this->cutout_confidence ?? 0);
    }

    public function getPriority(): int
    {
        return (int) ($this->priority ?? 0);
    }
}
