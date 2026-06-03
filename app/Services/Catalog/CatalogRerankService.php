<?php

namespace App\Services\Catalog;

use App\Models\ScrapedProduct;
use App\Services\Catalog\Contracts\RerankableProduct;
use Illuminate\Support\Collection;

class CatalogRerankService
{
    public function __construct(
        private readonly DimensionScorer $dimensionScorer,
    ) {}

    /**
     * @param  array<int, array{product_id: int|string, score: float, payload: array<string, mixed>}>  $candidates
     * @param  Collection<int|string, RerankableProduct>  $products  Pre-loaded product models keyed by ID
     * @param  array{width_m?: float|null, depth_m?: float|null, height_m?: float|null}  $roomDims
     * @param  array{materials?: array<int, string>, colors?: array<int, string>, style_keywords?: array<int, string>, max_price?: int|null}  $constraints
     * @param  array<int, int|string>  $alreadyChosenIds
     * @return array{ranked: array<int, array<string, mixed>>, drop_rate: float, logs: array<int, array<string, mixed>>}
     */
    public function rerank(
        array $candidates,
        string $family,
        ?string $slotSubtype,
        array $roomDims,
        array $constraints,
        array $alreadyChosenIds = [],
        bool $applySubtypeBoost = true,
        string $roomType = '',
        bool $rejectSubtypeMismatch = true,
        ?Collection $products = null,
    ): array {
        $weights = config('catalog.rerank.weights');

        if ($products === null) {
            $products = ScrapedProduct::whereIn(
                'id',
                array_map(fn ($c) => $c['product_id'], $candidates)
            )->get()->keyBy('id');
        }

        $scored = [];
        $logs = [];
        $dropped = 0;

        foreach ($candidates as $cand) {
            $id = $cand['product_id'];
            $product = $products->get($id);
            if (! $product) {
                $dropped++;

                continue;
            }

            $dim = $this->dimensionScorer->score($slotSubtype, $roomDims, $product);
            if ($dim['hard_reject']) {
                $dropped++;
                $logs[] = ['product_id' => $id, 'rejected' => true, 'reason' => $dim['reason']];

                continue;
            }

            if ($roomType !== '' && RoomSubtypePolicy::isSubtypeDeniedForRoom($roomType, $product->getProductSubtype())) {
                $dropped++;
                $logs[] = ['product_id' => $id, 'rejected' => true, 'reason' => 'subtype_denied_for_room'];

                continue;
            }

            if ($roomType !== '' && RoomSubtypePolicy::isFamilyDeniedForRoom($roomType, $product->getProductFamily())) {
                $dropped++;
                $logs[] = ['product_id' => $id, 'rejected' => true, 'reason' => 'family_denied_for_room'];

                continue;
            }

            if ($rejectSubtypeMismatch
                && $slotSubtype !== null
                && $slotSubtype !== ''
                && ProductSubtypeNormalizer::subtypeConflictsWithSlot($family, $slotSubtype, $product->getProductSubtype())) {
                $dropped++;
                $logs[] = [
                    'product_id' => $id,
                    'rejected' => true,
                    'reason' => 'subtype_mismatch',
                    'slot_subtype' => $slotSubtype,
                    'product_subtype' => $product->getProductSubtype(),
                ];

                continue;
            }

            if ($family === 'window_treatments' && $this->isDecorativeCurtainLightingProduct($product)) {
                $dropped++;
                $logs[] = ['product_id' => $id, 'rejected' => true, 'reason' => 'not_fabric_window_treatment'];

                continue;
            }

            if ($family === 'flooring' && $this->isBedTextileProduct($product)) {
                $dropped++;
                $logs[] = ['product_id' => $id, 'rejected' => true, 'reason' => 'not_flooring_material'];

                continue;
            }

            $vectorScore = $this->clamp01($cand['score']);
            $dimScore = $dim['score'];
            $styleScore = $this->styleScore($product, $constraints);
            $subtypeScore = ($applySubtypeBoost && $slotSubtype && ProductSubtypeNormalizer::subtypesMatch($family, $slotSubtype, $product->getProductSubtype())) ? 1.0 : 0.0;
            $priceScore = $this->priceScore($product, $constraints['max_price'] ?? null);
            $cutoutScore = $this->clamp01($product->getCutoutConfidence());
            $priorityScore = min(1.0, $product->getPriority() / 10.0);
            $roomScore = $this->roomScore($product, $roomType);

            $diversityPenalty = in_array($id, $alreadyChosenIds, true)
                ? 1.0
                : $this->brandNamePenalty($product, $products, $alreadyChosenIds);

            if ($roomType !== '' && $roomScore === 0.0 && $this->productHasRoomTags($product)) {
                $diversityPenalty = max($diversityPenalty, 0.35);
            }

            $activeWeights = [
                'vector' => (float) ($weights['vector'] ?? 0.22),
                'style' => (float) ($weights['style'] ?? 0.18),
                'subtype_boost' => (float) ($weights['subtype_boost'] ?? 0.12),
                'price' => (float) ($weights['price'] ?? 0.06),
                'cutout' => (float) ($weights['cutout'] ?? 0.04),
                'priority' => (float) ($weights['priority'] ?? 0.15),
            ];
            $weightedSum = $vectorScore * $activeWeights['vector']
                + $styleScore * $activeWeights['style']
                + $subtypeScore * $activeWeights['subtype_boost']
                + $priceScore * $activeWeights['price']
                + $cutoutScore * $activeWeights['cutout']
                + $priorityScore * $activeWeights['priority'];

            $weightTotal = array_sum($activeWeights);
            if ($dimScore !== null) {
                $dimW = (float) ($weights['dimension'] ?? 0.32);
                $weightedSum += $dimScore * $dimW;
                $weightTotal += $dimW;
            }

            if ($roomType !== '') {
                $roomW = (float) ($weights['room'] ?? 0.10);
                $weightedSum += $roomScore * $roomW;
                $weightTotal += $roomW;
            }

            $final = $weightTotal > 0 ? $weightedSum / $weightTotal : $vectorScore;
            $final -= (float) ($weights['diversity_penalty'] ?? 0.08) * $diversityPenalty;
            $final = $this->clamp01($final);

            $scored[] = [
                'product_id' => $id,
                'final_score' => $final,
                'components' => [
                    'vector' => $vectorScore,
                    'dimension' => $dimScore,
                    'style' => $styleScore,
                    'subtype_boost' => $subtypeScore,
                    'price' => $priceScore,
                    'cutout' => $cutoutScore,
                    'priority' => $priorityScore,
                    'room' => $roomScore,
                    'diversity_penalty' => $diversityPenalty,
                ],
                'dimension_reason' => $dim['reason'],
            ];
            $logs[] = ['product_id' => $id, 'final_score' => $final, 'components' => end($scored)['components']];
        }

        usort($scored, fn ($a, $b) => $b['final_score'] <=> $a['final_score']);

        $candidateCount = max(1, count($candidates));
        $dropRate = round($dropped / $candidateCount, 4);

        return [
            'ranked' => $scored,
            'drop_rate' => $dropRate,
            'logs' => $logs,
        ];
    }

    /**
     * @param  array{materials?: array<int, string>, colors?: array<int, string>, style_keywords?: array<int, string>}  $constraints
     */
    private function styleScore(RerankableProduct $product, array $constraints): float
    {
        $needles = array_merge(
            $constraints['materials'] ?? [],
            $constraints['colors'] ?? [],
            $constraints['style_keywords'] ?? [],
        );
        if ($needles === []) {
            return 0.55;
        }

        $hay = mb_strtolower(implode(' ', array_filter([
            $product->getName(),
            $product->getNameEn(),
            implode(' ', $product->getMaterialTags()),
            implode(' ', $product->getColorTags()),
        ])));

        $hits = 0;
        foreach ($needles as $n) {
            $n = mb_strtolower(trim((string) $n));
            if ($n !== '' && str_contains($hay, $n)) {
                $hits++;
            }
        }

        return $this->clamp01(0.35 + ($hits / max(1, count($needles))) * 0.65);
    }

    private function priceScore(RerankableProduct $product, ?int $maxPrice): float
    {
        if ($maxPrice === null || $maxPrice <= 0) {
            return 0.5;
        }
        if ($product->getPrice() <= $maxPrice) {
            return 1.0;
        }

        return max(0, 1 - (($product->getPrice() - $maxPrice) / max(1, $maxPrice)));
    }

    private function roomScore(RerankableProduct $product, string $roomType): float
    {
        if ($roomType === '') {
            return 0.5;
        }

        $ai = $product->getAiTags();
        $productRooms = $ai['rooms'] ?? null;
        if (! is_array($productRooms) && is_string($productRooms)) {
            $productRooms = array_map('trim', explode(',', $productRooms));
        }
        if (! is_array($productRooms) || $productRooms === []) {
            return 0.3;
        }

        $target = $this->normalizeRoomTag($roomType);
        foreach ($productRooms as $r) {
            $normalized = $this->normalizeRoomTag((string) $r);
            if ($normalized === '' || $target === '') {
                continue;
            }
            if ($normalized === $target || str_contains($normalized, $target) || str_contains($target, $normalized)) {
                return 1.0;
            }
        }

        return 0.0;
    }

    private function normalizeRoomTag(string $value): string
    {
        return mb_strtolower(trim(str_replace(['_', '-'], ' ', $value)));
    }

    private function productHasRoomTags(RerankableProduct $product): bool
    {
        $ai = $product->getAiTags();
        $productRooms = $ai['rooms'] ?? null;
        if (! is_array($productRooms) && is_string($productRooms)) {
            $productRooms = array_map('trim', explode(',', $productRooms));
        }

        return is_array($productRooms) && $productRooms !== [];
    }

    /**
     * @param  Collection<int|string, RerankableProduct>  $allInBatch
     * @param  array<int, int|string>  $chosen
     */
    private function brandNamePenalty(RerankableProduct $product, Collection $allInBatch, array $chosen): float
    {
        if ($chosen === []) {
            return 0;
        }
        $brand = mb_strtolower(trim((string) ($product->getBrand() ?? '')));
        $name = mb_strtolower(trim((string) ($product->getNameEn() ?: $product->getName())));

        foreach ($chosen as $cid) {
            $other = $allInBatch->get($cid);
            if (! $other) {
                continue;
            }
            if ($brand !== '' && $brand === mb_strtolower(trim((string) ($other->getBrand() ?? '')))) {
                return 0.6;
            }
            $on = mb_strtolower(trim((string) ($other->getNameEn() ?: $other->getName())));
            similar_text($name, $on, $pct);
            if ($pct > 72) {
                return 0.85;
            }
        }

        return 0;
    }

    private function clamp01(float $v): float
    {
        return max(0, min(1, $v));
    }

    private function productNameHaystack(RerankableProduct $product): string
    {
        return mb_strtolower(trim(implode(' ', array_filter([
            $product->getName(),
            $product->getNameEn(),
        ]))));
    }

    private function isDecorativeCurtainLightingProduct(RerankableProduct $product): bool
    {
        $hay = $this->productNameHaystack($product);

        if (preg_match('/\b(curtain\s*light|christmas\s*light|string\s*light|fairy\s*light|led\s*string|light\s*string|garland)\b/ui', $hay)) {
            return true;
        }

        return str_contains($hay, 'curtain') && preg_match('/\b(led|\d+\s*led)\b/ui', $hay);
    }

    private function isBedTextileProduct(RerankableProduct $product): bool
    {
        $hay = $this->productNameHaystack($product);

        foreach ([
            'blanket', 'plaid', 'throw', 'duvet', 'comforter', 'bedding', 'bed linen', 'bed sheet',
            'pillow', 'cushion',
        ] as $needle) {
            if (str_contains($hay, $needle)) {
                return true;
            }
        }

        if (preg_match('/\b(xmas|christmas|holiday|festive|noel)\b/i', $hay)
            && preg_match('/\b(pillow|cushion|blanket|plaid|throw)\b/i', $hay)) {
            return true;
        }

        return false;
    }
}
