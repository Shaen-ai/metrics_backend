<?php

namespace App\Services\LiveSearch;

use Illuminate\Contracts\Support\Arrayable;

class LiveSearchResult implements Arrayable
{
    public function __construct(
        public readonly string $name,
        public readonly float $price,
        public readonly string $currency,
        public readonly string $productUrl,
        public readonly string $sourceMarketplace,
        public readonly string $sourceKey,
        public readonly ?string $imageUrl = null,
        public readonly ?float $oldPrice = null,
        public readonly ?bool $inStock = true,
        public readonly ?string $brand = null,
        public readonly ?string $category = null,
        public readonly ?float $rating = null,
        public readonly ?int $reviewCount = null,
        public readonly ?string $widthCm = null,
        public readonly ?string $depthCm = null,
        public readonly ?string $heightCm = null,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'price' => $this->price,
            'currency' => $this->currency,
            'old_price' => $this->oldPrice,
            'product_url' => $this->productUrl,
            'image_url' => $this->imageUrl,
            'source_marketplace' => $this->sourceMarketplace,
            'source_key' => $this->sourceKey,
            'in_stock' => $this->inStock,
            'brand' => $this->brand,
            'category' => $this->category,
            'rating' => $this->rating,
            'review_count' => $this->reviewCount,
            'width_cm' => $this->widthCm,
            'depth_cm' => $this->depthCm,
            'height_cm' => $this->heightCm,
        ];
    }
}
