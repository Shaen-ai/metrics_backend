<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\Log;

class CatalogSlotResolver
{
    public function __construct(
        private readonly OpenAiEmbeddingClient $embedder,
        private readonly QdrantCatalogClient $qdrant,
        private readonly CatalogRerankService $reranker,
    ) {}

    /**
     * @param  array<int, array{family: string, subtype?: string|null, quantity?: int, placement?: string|null}>  $slots
     * @param  array<int, int>  $pinnedIds
     * @param  array<int, int>|null  $allowlistIds
     * @param  array{width_m?: float, depth_m?: float, height_m?: float}  $roomDimensions
     * @param  array<string, mixed>  $constraints
     * @return array{slots: array<int, array<string, mixed>>, metrics: array<string, mixed>}
     */
    public function resolve(
        string $designIntent,
        array $slots,
        array $pinnedIds = [],
        ?array $allowlistIds = null,
        array $roomDimensions = [],
        array $constraints = [],
        string $roomType = '',
    ): array {
        $intentVector = [];
        try {
            $intentVector = $this->embedder->embed($designIntent);
        } catch (\Throwable $e) {
            Log::warning('catalog.embed_failed', ['message' => $e->getMessage()]);
        }

        $globalChosen = array_values(array_unique(array_map('intval', $pinnedIds)));
        $results = [];
        $fallbackCount = 0;
        $dropRates = [];
        $filledSingletonSubtypes = [];
        $recall = (int) config('catalog.qdrant.recall_limit', 20);
        $prefetchMap = $this->prefetchPrimaryCandidates(
            $intentVector,
            $slots,
            $allowlistIds,
            $recall,
        );

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
                $designIntent,
                $intentVector,
                $family,
                $subtype,
                $quantity,
                $globalChosen,
                $allowlistIds,
                $roomDimensions,
                $constraints,
                $roomType,
                $prefetchMap,
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
     * @param  array<int, int>|null  $allowlistIds
     * @return array<string, mixed>
     */
    private function resolveOneSlot(
        string $designIntent,
        array $intentVector,
        string $family,
        ?string $subtype,
        int $quantity,
        array $globalChosen,
        ?array $allowlistIds,
        array $roomDimensions,
        array $constraints,
        string $roomType = '',
        array $prefetchMap = [],
    ): array {
        $recall = (int) config('catalog.qdrant.recall_limit', 20);
        $subtype = ProductSubtypeNormalizer::normalize($family, $subtype);
        $slotKey = $this->slotPrefetchKey($family, $subtype);

        // ── Pass 1: prioritized products only (priority >= 1) ──
        if ($intentVector !== []) {
            $priorityResult = $this->searchWithFallbacks(
                $intentVector, $family, $subtype, $quantity,
                $globalChosen, $allowlistIds, $roomDimensions,
                $constraints, $roomType, $recall, 1,
                $prefetchMap[$slotKey.'|priority'] ?? null,
            );

            if (count($priorityResult['picked']) >= $quantity) {
                Log::info('catalog.priority_pass_filled', [
                    'family' => $family,
                    'subtype' => $subtype,
                    'picked' => count($priorityResult['picked']),
                ]);

                return $this->buildSlotResult(
                    $family, $subtype, $quantity,
                    $priorityResult['picked'],
                    $priorityResult['candidates'],
                    $priorityResult['rerank'],
                    $priorityResult['fallback_stage'],
                    true,
                );
            }
        }

        // ── Pass 2: all products (no priority filter) ──
        $allResult = $this->searchWithFallbacks(
            $intentVector, $family, $subtype, $quantity,
            $globalChosen, $allowlistIds, $roomDimensions,
            $constraints, $roomType, $recall, null,
            $prefetchMap[$slotKey.'|all'] ?? null,
        );

        return $this->buildSlotResult(
            $family, $subtype, $quantity,
            $allResult['picked'],
            $allResult['candidates'],
            $allResult['rerank'],
            $allResult['fallback_stage'],
            false,
        );
    }

    /**
     * Run Qdrant search + rerank with subtype relaxation and broad recall fallbacks.
     *
     * @param  array<int, float>  $intentVector
     * @param  array<int, int>|null  $allowlistIds
     * @return array{picked: array, candidates: array, rerank: array, fallback_stage: int|null}
     */
    private function searchWithFallbacks(
        array $intentVector,
        string $family,
        ?string $subtype,
        int $quantity,
        array $globalChosen,
        ?array $allowlistIds,
        array $roomDimensions,
        array $constraints,
        string $roomType,
        int $recall,
        ?int $priorityMin,
        ?array $prefetchedCandidates = null,
    ): array {
        $filter = $this->buildFilter($family, $allowlistIds, $subtype, $priorityMin);
        $candidates = [];
        $fallbackStage = null;

        if ($prefetchedCandidates !== null) {
            $candidates = $prefetchedCandidates;
        } elseif ($intentVector !== []) {
            try {
                $candidates = $this->qdrant->search($intentVector, $recall, $filter);
            } catch (\Throwable $e) {
                Log::warning('catalog.qdrant_search_failed', [
                    'family' => $family,
                    'priority_min' => $priorityMin,
                    'message' => $e->getMessage(),
                ]);
                $fallbackStage = 1;
            }
        } else {
            $fallbackStage = 1;
        }

        if ($candidates === [] && $subtype !== null) {
            Log::info('catalog.subtype_filter_relaxed', [
                'family' => $family,
                'subtype' => $subtype,
                'priority_min' => $priorityMin,
                'note' => 'family_only_recall; rerank rejects conflicting subtypes only',
            ]);
            $filter = $this->buildFilter($family, $allowlistIds, null, $priorityMin);
            if ($intentVector !== []) {
                try {
                    $candidates = $this->qdrant->search($intentVector, $recall, $filter);
                } catch (\Throwable $e) {
                    Log::warning('catalog.qdrant_search_relaxed_failed', [
                        'family' => $family,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }

        $rerank = $this->reranker->rerank(
            $candidates, $family, $subtype, $roomDimensions,
            $constraints, $globalChosen, true, $roomType, true,
        );
        $picked = $this->pickTop($rerank['ranked'], $quantity, $globalChosen, $family);

        if (count($picked) < $quantity) {
            $fallbackStage = 1;
            $broadLimit = (int) config('catalog.qdrant.recall_limit_broad', 50);
            if ($intentVector !== []) {
                try {
                    $candidates = $this->qdrant->search($intentVector, $broadLimit, $filter);
                } catch (\Throwable $e) {
                    Log::warning('catalog.qdrant_search_broad_failed', [
                        'family' => $family,
                        'priority_min' => $priorityMin,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
            $rerank = $this->reranker->rerank(
                $candidates, $family, $subtype, $roomDimensions,
                $constraints, $globalChosen, true, $roomType, true,
            );
            $picked = $this->pickTop($rerank['ranked'], $quantity, $globalChosen, $family);
        }

        if (count($picked) < $quantity) {
            $fallbackStage = 2;
            $rerank = $this->reranker->rerank(
                $candidates, $family, $subtype, $roomDimensions,
                $constraints, $globalChosen, false, $roomType, true,
            );
            $picked = $this->pickTop($rerank['ranked'], $quantity, $globalChosen, $family);
        }

        // Last resort for flooring only: relax subtype rejection so a laminate slot can be
        // filled by parquet/tile/vinyl/etc. when the exact subtype is out of stock. Furniture
        // families stay strict to avoid placing the wrong piece.
        if (count($picked) < $quantity && $family === 'flooring' && $subtype !== null && $subtype !== '') {
            $fallbackStage = 2;
            $rerank = $this->reranker->rerank(
                $candidates, $family, $subtype, $roomDimensions,
                $constraints, $globalChosen, false, $roomType, false,
            );
            $picked = $this->pickTop($rerank['ranked'], $quantity, $globalChosen, $family);
        }

        return [
            'picked' => $picked,
            'candidates' => $candidates,
            'rerank' => $rerank,
            'fallback_stage' => $fallbackStage,
        ];
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
        bool $priorityFilled,
    ): array {
        $topScore = $picked !== [] ? max(array_column($picked, 'final_score')) : 0;
        $productIds = array_map(fn ($p) => (int) $p['product_id'], array_slice($picked, 0, $quantity));

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
            'priority_filled' => $priorityFilled,
        ];
    }

    /**
     * @param  array<int, array{product_id: int, final_score: float}>  $ranked
     * @param  array<int, int>  $exclude
     * @return array<int, array{product_id: int, final_score: float}>
     */
    private function pickTop(array $ranked, int $quantity, array $exclude, string $family = ''): array
    {
        $eligible = [];
        foreach ($ranked as $row) {
            $id = (int) $row['product_id'];
            if (in_array($id, $exclude, true)) {
                continue;
            }
            $eligible[] = $row;
        }

        if ($eligible === []) {
            return [];
        }

        if ($family === 'flooring' && $quantity === 1) {
            $epsilon = (float) config('catalog.rerank.flooring_score_epsilon', 0.03);
            $topScore = $eligible[0]['final_score'];
            $tied = array_filter($eligible, fn ($r) => ($topScore - $r['final_score']) <= $epsilon);
            $tied = array_values($tied);

            return [$tied[array_rand($tied)]];
        }

        return array_slice($eligible, 0, $quantity);
    }

    /**
     * @param  array<int, array{family?: string, subtype?: string|null, quantity?: int, placement?: string|null}>  $slots
     * @param  array<int, int>|null  $allowlistIds
     * @return array<string, array<int, array{product_id: int, score: float, payload: array<string, mixed>}>>
     */
    private function prefetchPrimaryCandidates(
        array $intentVector,
        array $slots,
        ?array $allowlistIds,
        int $recall,
    ): array {
        if ($intentVector === []) {
            return [];
        }

        $searches = [];
        $keys = [];

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
            $slotKey = $this->slotPrefetchKey($family, $subtype);

            $keys[] = $slotKey.'|priority';
            $searches[] = [
                'vector' => $intentVector,
                'limit' => $recall,
                'filterMust' => $this->buildFilter($family, $allowlistIds, $subtype, 1),
            ];

            $keys[] = $slotKey.'|all';
            $searches[] = [
                'vector' => $intentVector,
                'limit' => $recall,
                'filterMust' => $this->buildFilter($family, $allowlistIds, $subtype, null),
            ];
        }

        if ($searches === []) {
            return [];
        }

        try {
            $batchResults = $this->qdrant->searchBatch($searches);
        } catch (\Throwable $e) {
            Log::warning('catalog.qdrant_batch_prefetch_failed', [
                'search_count' => count($searches),
                'message' => $e->getMessage(),
            ]);

            return [];
        }

        $map = [];
        foreach ($keys as $index => $key) {
            $map[$key] = $batchResults[$index] ?? [];
        }

        Log::info('catalog.qdrant_batch_prefetch', [
            'search_count' => count($searches),
            'slot_count' => (int) (count($searches) / 2),
        ]);

        return $map;
    }

    private function slotPrefetchKey(string $family, ?string $subtype): string
    {
        return $family.($subtype ? '/'.$subtype : '');
    }

    /**
     * @param  array<int, int>|null  $allowlistIds
     * @return array<int, array<string, mixed>>
     */
    private function buildFilter(string $family, ?array $allowlistIds, ?string $subtype = null, ?int $priorityMin = null): array
    {
        $must = [
            ['key' => 'product_family', 'match' => ['value' => $family]],
        ];

        if ($subtype !== null && $subtype !== '') {
            $must[] = ['key' => 'product_subtype', 'match' => ['value' => $subtype]];
        }

        $must[] = ['key' => 'in_stock', 'match' => ['value' => true]];

        if ($allowlistIds !== null && $allowlistIds !== []) {
            $must[] = [
                'key' => 'product_id',
                'match' => ['any' => array_map('intval', $allowlistIds)],
            ];
        }

        if ($priorityMin !== null && $priorityMin > 0) {
            $must[] = ['key' => 'priority', 'range' => ['gte' => $priorityMin]];
        }

        return $must;
    }

    /**
     * @param  array<string, mixed>  $resolved
     */
    private function logSlot(array $resolved): void
    {
        Log::info('catalog.resolve_slot', [
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
