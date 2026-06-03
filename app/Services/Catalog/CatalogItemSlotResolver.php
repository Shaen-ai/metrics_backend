<?php

namespace App\Services\Catalog;

use App\Models\CatalogItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CatalogItemSlotResolver
{
    public function __construct(
        private readonly OpenAiEmbeddingClient $embedder,
        private readonly CatalogItemQdrantClient $qdrant,
        private readonly CatalogRerankService $reranker,
    ) {}

    /**
     * @param  array<int, array{family: string, subtype?: string|null, quantity?: int, placement?: string|null}>  $slots
     * @param  array<int, string>  $pinnedIds
     * @param  array{width_m?: float, depth_m?: float, height_m?: float}  $roomDimensions
     * @param  array<string, mixed>  $constraints
     * @return array{slots: array<int, array<string, mixed>>, metrics: array<string, mixed>}
     */
    public function resolve(
        string $designIntent,
        array $slots,
        string $adminId,
        array $pinnedIds = [],
        array $roomDimensions = [],
        array $constraints = [],
        string $roomType = '',
    ): array {
        $intentVector = [];
        try {
            $intentVector = $this->embedder->embed($designIntent);
        } catch (\Throwable $e) {
            Log::warning('catalog_item.embed_failed', ['message' => $e->getMessage()]);
        }

        $globalChosen = array_values(array_unique($pinnedIds));
        $results = [];
        $fallbackCount = 0;
        $dropRates = [];
        $filledSingletonSubtypes = [];

        foreach ($slots as $slot) {
            $family = trim((string) ($slot['family'] ?? ''));
            if ($family === '') {
                continue;
            }
            $subtype = isset($slot['subtype']) ? trim((string) $slot['subtype']) : null;
            if ($subtype === '' || $subtype === 'other') {
                $subtype = null;
            }
            $subtype = ProductSubtypeNormalizer::normalize($family, $subtype);
            $quantity = max(1, (int) ($slot['quantity'] ?? 1));

            if ($subtype !== null && $this->isSingletonSubtype($subtype) && isset($filledSingletonSubtypes[$subtype])) {
                $results[] = [
                    'slot' => $family.($subtype ? '/'.$subtype : ''),
                    'family' => $family,
                    'subtype' => $subtype,
                    'quantity' => $quantity,
                    'product_ids' => [],
                    'scores' => [],
                    'qdrant_candidates' => 0,
                    'rerank_drop_rate' => 0,
                    'fallback_stage' => null,
                    'top_score' => 0,
                    'resolved_count' => 0,
                    'skipped_duplicate_subtype' => true,
                ];

                continue;
            }

            $resolved = $this->resolveOneSlot(
                $intentVector,
                $family,
                $subtype,
                $quantity,
                $globalChosen,
                $adminId,
                $roomDimensions,
                $constraints,
                $roomType,
            );

            if (($resolved['fallback_stage'] ?? null) !== null) {
                $fallbackCount++;
            }
            $dropRates[] = $resolved['rerank_drop_rate'];
            $globalChosen = array_values(array_unique(array_merge($globalChosen, $resolved['product_ids'])));

            if ($subtype !== null && $this->isSingletonSubtype($subtype) && count($resolved['product_ids']) > 0) {
                $filledSingletonSubtypes[$subtype] = true;
            }

            $results[] = $resolved;
            $this->logSlot($resolved);
        }

        $slotCount = max(1, count($results));
        $successSlots = count(array_filter($results, fn ($r) => count($r['product_ids']) >= ($r['quantity'] ?? 1)));

        return [
            'slots' => $results,
            'metrics' => [
                'slot_success_rate' => round($successSlots / $slotCount, 4),
                'fallback_usage' => round($fallbackCount / $slotCount, 4),
                'rerank_drop_rate' => count($dropRates) ? round(array_sum($dropRates) / count($dropRates), 4) : 0,
            ],
        ];
    }

    /**
     * @param  array<int, float>  $intentVector
     * @return array<string, mixed>
     */
    private function resolveOneSlot(
        array $intentVector,
        string $family,
        ?string $subtype,
        int $quantity,
        array $globalChosen,
        string $adminId,
        array $roomDimensions,
        array $constraints,
        string $roomType = '',
    ): array {
        $recall = (int) config('catalog.qdrant.recall_limit', 20);
        $subtype = ProductSubtypeNormalizer::normalize($family, $subtype);

        $result = $this->searchWithFallbacks(
            $intentVector, $family, $subtype, $quantity,
            $globalChosen, $adminId, $roomDimensions,
            $constraints, $roomType, $recall,
        );

        return $this->buildSlotResult(
            $family, $subtype, $quantity,
            $result['picked'],
            $result['candidates'],
            $result['rerank'],
            $result['fallback_stage'],
        );
    }

    /**
     * Run Qdrant search + rerank with subtype relaxation and broad recall fallbacks.
     *
     * @param  array<int, float>  $intentVector
     * @return array{picked: array, candidates: array, rerank: array, fallback_stage: int|null}
     */
    private function searchWithFallbacks(
        array $intentVector,
        string $family,
        ?string $subtype,
        int $quantity,
        array $globalChosen,
        string $adminId,
        array $roomDimensions,
        array $constraints,
        string $roomType,
        int $recall,
    ): array {
        $filter = $this->buildFilter($family, $subtype);
        $candidates = [];
        $fallbackStage = null;

        if ($intentVector !== []) {
            try {
                $candidates = $this->qdrant->search($intentVector, $adminId, $recall, $filter);
            } catch (\Throwable $e) {
                Log::warning('catalog_item.qdrant_search_failed', [
                    'family' => $family,
                    'admin_id' => $adminId,
                    'message' => $e->getMessage(),
                ]);
                $fallbackStage = 1;
            }
        } else {
            $fallbackStage = 1;
        }

        if ($candidates === [] && $subtype !== null) {
            Log::info('catalog_item.subtype_filter_relaxed', [
                'family' => $family,
                'subtype' => $subtype,
                'admin_id' => $adminId,
            ]);
            $filter = $this->buildFilter($family, null);
            if ($intentVector !== []) {
                try {
                    $candidates = $this->qdrant->search($intentVector, $adminId, $recall, $filter);
                } catch (\Throwable $e) {
                    Log::warning('catalog_item.qdrant_search_relaxed_failed', [
                        'family' => $family,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        $products = $this->loadProducts($candidates);

        $rerank = $this->reranker->rerank(
            $candidates, $family, $subtype, $roomDimensions,
            $constraints, $globalChosen, true, $roomType, true, $products,
        );
        $picked = $this->pickTop($rerank['ranked'], $quantity, $globalChosen);

        if (count($picked) < $quantity) {
            $fallbackStage = 1;
            $broadLimit = (int) config('catalog.qdrant.recall_limit_broad', 50);
            if ($intentVector !== []) {
                try {
                    $candidates = $this->qdrant->search($intentVector, $adminId, $broadLimit, $filter);
                } catch (\Throwable $e) {
                    Log::warning('catalog_item.qdrant_search_broad_failed', [
                        'family' => $family,
                        'admin_id' => $adminId,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
            $products = $this->loadProducts($candidates);
            $rerank = $this->reranker->rerank(
                $candidates, $family, $subtype, $roomDimensions,
                $constraints, $globalChosen, true, $roomType, true, $products,
            );
            $picked = $this->pickTop($rerank['ranked'], $quantity, $globalChosen);
        }

        if (count($picked) < $quantity) {
            $fallbackStage = 2;
            $rerank = $this->reranker->rerank(
                $candidates, $family, $subtype, $roomDimensions,
                $constraints, $globalChosen, false, $roomType, true, $products,
            );
            $picked = $this->pickTop($rerank['ranked'], $quantity, $globalChosen);
        }

        return [
            'picked' => $picked,
            'candidates' => $candidates,
            'rerank' => $rerank,
            'fallback_stage' => $fallbackStage,
        ];
    }

    /**
     * @param  array<int, array{product_id: string, score: float, payload: array<string, mixed>}>  $candidates
     * @return Collection<string, CatalogItem>
     */
    private function loadProducts(array $candidates): Collection
    {
        if ($candidates === []) {
            return collect();
        }

        return CatalogItem::whereIn('id', array_map(fn ($c) => $c['product_id'], $candidates))
            ->get()->keyBy('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSlotResult(
        string $family,
        ?string $subtype,
        int $quantity,
        array $picked,
        array $candidates,
        array $rerank,
        ?int $fallbackStage,
    ): array {
        $topScore = $picked !== [] ? max(array_column($picked, 'final_score')) : 0;
        $productIds = array_map(fn ($p) => (string) $p['product_id'], array_slice($picked, 0, $quantity));

        return [
            'slot' => $family.($subtype ? '/'.$subtype : ''),
            'family' => $family,
            'subtype' => $subtype,
            'quantity' => $quantity,
            'product_ids' => $productIds,
            'scores' => array_map(fn ($p) => round((float) ($p['final_score'] ?? 0), 4), array_slice($picked, 0, $quantity)),
            'qdrant_candidates' => count($candidates),
            'rerank_drop_rate' => $rerank['drop_rate'],
            'fallback_stage' => $fallbackStage,
            'top_score' => round($topScore, 4),
            'resolved_count' => count($productIds),
        ];
    }

    /**
     * @param  array<int, array{product_id: string, final_score: float}>  $ranked
     * @param  array<int, string>  $exclude
     * @return array<int, array{product_id: string, final_score: float}>
     */
    private function pickTop(array $ranked, int $quantity, array $exclude): array
    {
        $out = [];
        foreach ($ranked as $row) {
            $id = (string) $row['product_id'];
            if (in_array($id, $exclude, true)) {
                continue;
            }
            $out[] = $row;
            if (count($out) >= $quantity) {
                break;
            }
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildFilter(string $family, ?string $subtype = null): array
    {
        $must = [
            ['key' => 'product_family', 'match' => ['value' => $family]],
        ];

        if ($subtype !== null && $subtype !== '') {
            $must[] = ['key' => 'product_subtype', 'match' => ['value' => $subtype]];
        }

        $must[] = ['key' => 'in_stock', 'match' => ['value' => true]];

        return $must;
    }

    /**
     * @param  array<string, mixed>  $resolved
     */
    private function logSlot(array $resolved): void
    {
        Log::info('catalog_item.resolve_slot', [
            'slot' => $resolved['slot'] ?? null,
            'qdrant_candidates' => $resolved['qdrant_candidates'] ?? 0,
            'rerank_drop_rate' => $resolved['rerank_drop_rate'] ?? 0,
            'fallback_stage' => $resolved['fallback_stage'] ?? null,
            'top_score' => $resolved['top_score'] ?? 0,
            'resolved_count' => $resolved['resolved_count'] ?? 0,
        ]);
    }

    private function isSingletonSubtype(string $subtype): bool
    {
        static $singletons = [
            'sofa', 'coffee_table', 'dining_table', 'bed', 'desk', 'tv_stand', 'wardrobe',
            'curtain', 'blind', 'sheer', 'rug', 'carpet', 'laminate', 'tile', 'wallpaper',
            'ceiling', 'pendant', 'table',
        ];

        return in_array(strtolower($subtype), $singletons, true);
    }
}
