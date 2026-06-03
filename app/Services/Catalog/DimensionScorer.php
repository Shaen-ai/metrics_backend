<?php

namespace App\Services\Catalog;

use App\Services\Catalog\Contracts\RerankableProduct;

/**
 * Per-subtype dimension compatibility. Returns null when product dims missing (soft skip).
 */
class DimensionScorer
{
    /**
     * @param  array{width_m?: float|null, depth_m?: float|null, height_m?: float|null}  $roomDims
     * @return array{score: float|null, hard_reject: bool, reason: string}
     */
    public function score(
        ?string $subtype,
        array $roomDims,
        RerankableProduct $product,
    ): array {
        if (! $product->hasDimensions() || ! $product->getWidthCm()) {
            return ['score' => null, 'hard_reject' => false, 'reason' => 'product_dims_missing'];
        }

        $roomW = isset($roomDims['width_m']) ? (float) $roomDims['width_m'] : 0;
        $roomD = isset($roomDims['depth_m']) ? (float) $roomDims['depth_m'] : 0;
        $roomH = isset($roomDims['height_m']) ? (float) $roomDims['height_m'] : 0;

        if ($roomW <= 0 || $roomD <= 0) {
            $neutral = (float) config('catalog.rerank.dimension_neutral_when_room_missing', 0.5);

            return ['score' => $neutral, 'hard_reject' => false, 'reason' => 'room_dims_missing'];
        }

        $subtype = $subtype ?: (string) ($product->getProductSubtype() ?: 'other');
        $pw = (float) $product->getWidthCm() / 100;
        $pd = (float) ($product->getDepthCm() ?: $product->getWidthCm()) / 100;
        $ph = (float) ($product->getHeightCm() ?: 0) / 100;

        $rejectRatio = (float) config('catalog.rerank.dimension_reject_ratio', 0.95);

        if (in_array($subtype, ['rug', 'carpet'], true)) {
            $maxEdge = max($pw, $pd);
            $zone = min($roomW, $roomD) * 0.85;
            $score = $this->fitScore($maxEdge, $zone);
            $hard = $maxEdge > $roomW * $rejectRatio || $maxEdge > $roomD * $rejectRatio;

            return ['score' => $score, 'hard_reject' => $hard, 'reason' => 'rug_area'];
        }

        if (in_array($subtype, ['lamp', 'pendant', 'ceiling', 'wall', 'floor', 'table'], true)) {
            if ($roomH > 0 && $ph > 0) {
                $score = $this->fitScore($ph, $roomH * 0.55);

                return ['score' => $score, 'hard_reject' => false, 'reason' => 'lighting_height'];
            }

            return ['score' => 0.65, 'hard_reject' => false, 'reason' => 'lighting_no_height'];
        }

        if (in_array($subtype, ['curtain', 'blind', 'sheer'], true)) {
            $span = $pw > 0 ? $pw : $pd;
            $score = $this->fitScore($span, $roomW * 0.9);

            return ['score' => $score, 'hard_reject' => false, 'reason' => 'window_span'];
        }

        if (in_array($subtype, ['laminate', 'parquet', 'tile', 'vinyl'], true)) {
            return ['score' => null, 'hard_reject' => false, 'reason' => 'flooring_surface'];
        }

        // sofa, bed, table, storage, default furniture footprint
        $tooWide = $pw > $roomW * $rejectRatio;
        $tooDeep = $pd > $roomD * $rejectRatio;
        if ($tooWide || $tooDeep) {
            return ['score' => 0.05, 'hard_reject' => true, 'reason' => 'footprint_exceeds_room'];
        }

        $wScore = $this->fitScore($pw, $roomW * 0.75);
        $dScore = $this->fitScore($pd, $roomD * 0.75);
        $score = ($wScore + $dScore) / 2;

        return ['score' => $score, 'hard_reject' => false, 'reason' => 'footprint'];
    }

    private function fitScore(float $actual, float $target): float
    {
        if ($target <= 0) {
            return 0.5;
        }
        $ratio = $actual / $target;
        if ($ratio > 1.15) {
            return max(0, 1 - ($ratio - 1.15) * 2);
        }
        if ($ratio < 0.35) {
            return max(0.35, $ratio);
        }

        return 1 - abs(1 - $ratio) * 0.35;
    }
}
