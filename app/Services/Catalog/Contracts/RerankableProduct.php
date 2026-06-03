<?php

namespace App\Services\Catalog\Contracts;

/**
 * Common interface so both ScrapedProduct and CatalogItem can be scored
 * by CatalogRerankService and DimensionScorer.
 */
interface RerankableProduct
{
    public function getRerankId(): int|string;

    public function getWidthCm(): ?float;

    public function getDepthCm(): ?float;

    public function getHeightCm(): ?float;

    public function hasDimensions(): bool;

    public function getProductFamily(): ?string;

    public function getProductSubtype(): ?string;

    /** @return array<int, string> */
    public function getMaterialTags(): array;

    /** @return array<int, string> */
    public function getColorTags(): array;

    /** @return array<string, mixed> */
    public function getAiTags(): array;

    public function getName(): string;

    public function getNameEn(): ?string;

    public function getBrand(): ?string;

    public function getPrice(): float;

    public function getCutoutConfidence(): float;

    public function getPriority(): int;
}
