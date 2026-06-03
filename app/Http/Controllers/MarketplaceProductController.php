<?php

namespace App\Http\Controllers;

use App\Models\ScrapedProduct;
use App\Services\Catalog\CatalogSlotResolver;
use App\Services\Catalog\QdrantCatalogClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MarketplaceProductController extends Controller
{
    /**
     * GET /api/marketplace/products/search?q=...&marketplace=...&has_dimensions=1&page=1
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:200',
            'marketplace' => 'nullable|string|in:vega,domus,jysk',
            'has_dimensions' => 'nullable|boolean',
            'in_stock' => 'nullable|boolean',
            'min_price' => 'nullable|integer|min:0',
            'max_price' => 'nullable|integer|min:0',
            'category' => 'nullable|string|max:256',
            'product_family' => 'nullable|string|max:48',
            'product_subtype' => 'nullable|string|max:48',
            'product_subtypes' => 'nullable|string|max:256',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = ScrapedProduct::query()->withPrice();

        $query->search($request->input('q'));

        if ($request->filled('marketplace')) {
            $query->marketplace($request->input('marketplace'));
        }

        if ($request->boolean('has_dimensions', false)) {
            $query->withDimensions();
        }

        if ($request->boolean('in_stock', true)) {
            $query->inStock();
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->input('max_price'));
        }

        if ($request->filled('category')) {
            $query->where('category_en', 'LIKE', '%'.$request->input('category').'%');
        }

        if ($request->filled('product_family')) {
            $query->where('product_family', $request->input('product_family'));
        }

        if ($request->filled('product_subtypes')) {
            $subtypes = array_filter(array_map('trim', explode(',', $request->input('product_subtypes'))));
            if (count($subtypes) > 0) {
                $query->whereIn('product_subtype', $subtypes);
            }
        } elseif ($request->filled('product_subtype')) {
            $query->where('product_subtype', $request->input('product_subtype'));
        }

        $perPage = $request->input('per_page', 20);
        $results = $query
            ->orderByCatalogPriority()
            ->select([
                'id', 'source_marketplace', 'external_url', 'slug',
                'name', 'name_en', 'category', 'category_en', 'brand',
                'price', 'currency', 'old_price', 'in_stock', 'priority',
                'width_cm', 'depth_cm', 'height_cm', 'has_dimensions',
                'main_image_url', 'images', 'sku',
                'product_family', 'product_subtype',
            ])->paginate($perPage);

        return response()->json($results);
    }

    /**
     * GET /api/marketplace/products/browse?marketplace=...&q=...&page=1
     * Paginated catalog browse — q is optional (unlike /search).
     */
    public function browse(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'nullable|string|min:2|max:200',
            'marketplace' => 'nullable|string|in:vega,domus,jysk',
            'has_dimensions' => 'nullable|boolean',
            'in_stock' => 'nullable|boolean',
            'min_price' => 'nullable|integer|min:0',
            'max_price' => 'nullable|integer|min:0',
            'category' => 'nullable|string|max:256',
            'product_family' => 'nullable|string|max:48',
            'product_subtype' => 'nullable|string|max:48',
            'product_subtypes' => 'nullable|string|max:256',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = ScrapedProduct::query()->withPrice();

        if ($request->filled('q')) {
            $query->search($request->input('q'));
        }

        if ($request->filled('marketplace')) {
            $query->marketplace($request->input('marketplace'));
        }

        if ($request->boolean('has_dimensions', false)) {
            $query->withDimensions();
        }

        if ($request->has('in_stock')) {
            if ($request->boolean('in_stock')) {
                $query->inStock();
            }
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->input('max_price'));
        }

        if ($request->filled('category')) {
            $query->where('category_en', 'LIKE', '%'.$request->input('category').'%');
        }

        if ($request->filled('product_family')) {
            $query->where('product_family', $request->input('product_family'));
        }

        if ($request->filled('product_subtypes')) {
            $subtypes = array_filter(array_map('trim', explode(',', $request->input('product_subtypes'))));
            if (count($subtypes) > 0) {
                $query->whereIn('product_subtype', $subtypes);
            }
        } elseif ($request->filled('product_subtype')) {
            $query->where('product_subtype', $request->input('product_subtype'));
        }

        $perPage = $request->input('per_page', 50);
        $results = $query
            ->orderByCatalogPriority()
            ->select([
                'id', 'source_marketplace', 'external_url', 'slug',
                'name', 'name_en', 'category', 'category_en', 'brand',
                'price', 'currency', 'old_price', 'in_stock', 'priority',
                'width_cm', 'depth_cm', 'height_cm', 'has_dimensions',
                'main_image_url', 'images', 'sku',
                'product_family', 'product_subtype',
            ])->paginate($perPage);

        return response()->json($results);
    }

    /**
     * GET /api/marketplace/products/{id}
     */
    public function show(int $id): JsonResponse
    {
        $product = ScrapedProduct::findOrFail($id);

        return response()->json(['data' => $product]);
    }

    /**
     * GET /api/marketplace/products/by-ids?ids=1,2,3
     * Fetch multiple products by IDs (for AI design pipeline).
     */
    public function byIds(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|string',
        ]);

        $ids = array_map('intval', explode(',', $request->input('ids')));
        $ids = array_filter($ids, fn ($id) => $id > 0);

        if (count($ids) > 50) {
            return response()->json(['message' => 'Maximum 50 products per request.'], 422);
        }

        $products = ScrapedProduct::whereIn('id', $ids)
            ->withPrice()
            ->orderByCatalogPriority()
            ->get();

        return response()->json(['data' => $products]);
    }

    /**
     * POST /api/marketplace/products/deactivate
     * Mark products unavailable after live URL check; remove from Qdrant.
     */
    public function deactivate(Request $request, QdrantCatalogClient $qdrant): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1|max:20',
            'ids.*' => 'integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $ids = collect($request->input('ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return response()->json(['data' => ['deactivated' => 0, 'deleted_from_qdrant' => 0]]);
        }

        ScrapedProduct::whereIn('id', $ids)
            ->where('in_stock', true)
            ->update([
                'in_stock' => false,
                'unavailable_at' => now(),
            ]);

        $embeddedIds = ScrapedProduct::whereIn('id', $ids)
            ->whereNotNull('embedded_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $deletedFromQdrant = 0;
        if ($embeddedIds !== []) {
            try {
                $qdrant->deleteProducts($embeddedIds);
                ScrapedProduct::whereIn('id', $embeddedIds)->update(['embedded_at' => null]);
                $deletedFromQdrant = count($embeddedIds);
            } catch (\Throwable $e) {
                Log::warning('catalog.deactivate_qdrant_failed', [
                    'message' => $e->getMessage(),
                    'ids' => $embeddedIds,
                ]);
            }
        }

        Log::info('catalog.deactivate', [
            'ids' => $ids,
            'deleted_from_qdrant' => $deletedFromQdrant,
        ]);

        return response()->json([
            'data' => [
                'deactivated' => count($ids),
                'deleted_from_qdrant' => $deletedFromQdrant,
            ],
        ]);
    }

    /**
     * POST /api/marketplace/products/resolve-slots
     * Vector recall + deterministic rerank + fallback chain per slot.
     */
    public function resolveSlots(Request $request, CatalogSlotResolver $resolver): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'design_intent' => 'required|string|min:8|max:4000',
            'slots' => 'required|array|min:1|max:24',
            'slots.*.family' => 'required|string|max:48',
            'slots.*.subtype' => 'nullable|string|max:48',
            'slots.*.quantity' => 'nullable|integer|min:1|max:8',
            'slots.*.placement' => 'nullable|string|max:200',
            'pinnedIds' => 'nullable|array|max:48',
            'pinnedIds.*' => 'integer|min:1',
            'allowlistIds' => 'nullable|array|max:500',
            'allowlistIds.*' => 'integer|min:1',
            'room_dimensions' => 'nullable|array',
            'room_dimensions.width_m' => 'nullable|numeric|min:0|max:50',
            'room_dimensions.depth_m' => 'nullable|numeric|min:0|max:50',
            'room_dimensions.height_m' => 'nullable|numeric|min:0|max:10',
            'constraints' => 'nullable|array',
            'constraints.materials' => 'nullable|array',
            'constraints.colors' => 'nullable|array',
            'constraints.style_keywords' => 'nullable|array',
            'constraints.max_price' => 'nullable|integer|min:0',
            'room_type' => 'nullable|string|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $pinnedIds = collect($request->input('pinnedIds', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $allowlist = $request->input('allowlistIds');
        $allowlistIds = is_array($allowlist)
            ? collect($allowlist)->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values()->all()
            : null;

        $room = $request->input('room_dimensions', []);
        $roomDimensions = [
            'width_m' => isset($room['width_m']) ? (float) $room['width_m'] : null,
            'depth_m' => isset($room['depth_m']) ? (float) $room['depth_m'] : null,
            'height_m' => isset($room['height_m']) ? (float) $room['height_m'] : null,
        ];

        $roomType = is_string($request->input('room_type')) ? trim($request->input('room_type')) : '';

        try {
            $result = $resolver->resolve(
                (string) $request->input('design_intent'),
                $request->input('slots', []),
                $pinnedIds,
                $allowlistIds,
                $roomDimensions,
                $request->input('constraints', []),
                $roomType,
            );
        } catch (\Throwable $e) {
            Log::error('catalog.resolve_slots_failed', ['message' => $e->getMessage()]);

            return response()->json(['message' => 'Catalog resolution failed.'], 503);
        }

        $allIds = $pinnedIds;
        foreach ($result['slots'] as $slot) {
            foreach ($slot['product_ids'] as $id) {
                $allIds[] = (int) $id;
            }
        }
        $allIds = array_values(array_unique(array_filter($allIds, fn ($id) => $id > 0)));

        $namesById = $allIds !== []
            ? ScrapedProduct::query()->whereIn('id', $allIds)->pluck('name', 'id')->all()
            : [];

        Log::info('catalog.resolve_slots.detail', [
            'resolved_ids' => $allIds,
            'products' => array_map(
                fn (int $id) => [
                    'id' => $id,
                    'name' => $namesById[$id] ?? null,
                ],
                $allIds,
            ),
            'per_slot' => collect($result['slots'])->map(fn ($s) => [
                'slot' => $s['slot'] ?? null,
                'product_ids' => $s['product_ids'] ?? [],
                'top_score' => $s['top_score'] ?? 0,
                'fallback_stage' => $s['fallback_stage'] ?? null,
                'resolved_count' => $s['resolved_count'] ?? 0,
            ])->values()->all(),
        ]);

        Log::info('catalog.resolve_slots', array_merge($result['metrics'], [
            'slot_count' => count($result['slots']),
            'resolved_ids' => count($allIds),
        ]));

        return response()->json([
            'data' => [
                'slots' => $result['slots'],
                'ids' => $allIds,
                'mpKeys' => array_map(fn ($id) => 'mp-'.$id, $allIds),
                'metrics' => $result['metrics'],
            ],
        ]);
    }

    /**
     * POST /api/marketplace/products/resolve-intents
     * Resolve semantic product_intents to scraped_products ids.
     */
    public function resolveIntents(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'intents' => 'required|array|max:24',
            'intents.*.family' => 'required|string|max:48',
            'intents.*.subtype' => 'nullable|string|max:48',
            'intents.*.query' => 'required|string|min:2|max:200',
            'intents.*.quantity' => 'nullable|integer|min:1|max:8',
            'pinnedIds' => 'nullable|array|max:48',
            'pinnedIds.*' => 'integer|min:1',
            'perIntentLimit' => 'nullable|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $intents = $request->input('intents', []);
        $pinnedIds = collect($request->input('pinnedIds', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $perIntentLimit = (int) $request->input('perIntentLimit', 3);

        $seen = [];
        $ids = [];

        foreach ($pinnedIds as $id) {
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $ids[] = $id;
        }

        foreach ($intents as $intent) {
            $query = trim((string) ($intent['query'] ?? ''));
            if (strlen($query) < 2) {
                continue;
            }

            $q = ScrapedProduct::query()->search($query);

            $family = trim((string) ($intent['family'] ?? ''));
            if ($family !== '') {
                $q->where('product_family', $family);
            }

            $subtype = trim((string) ($intent['subtype'] ?? ''));
            if ($subtype !== '' && $subtype !== 'other') {
                $q->where('product_subtype', $subtype);
            }

            $rows = [];
            try {
                $rows = $q->inStock()
                    ->orderByDesc('has_dimensions')
                    ->limit($perIntentLimit)
                    ->pluck('id')
                    ->all();
            } catch (\Throwable $e) {
                Log::warning('catalog.resolve_intent_failed', [
                    'family' => $family,
                    'subtype' => $subtype,
                    'query' => $query,
                    'message' => $e->getMessage(),
                ]);
            }

            foreach ($rows as $id) {
                $id = (int) $id;
                if ($id <= 0 || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $ids[] = $id;
            }
        }

        $mpKeys = array_map(fn ($id) => 'mp-'.$id, $ids);

        return response()->json([
            'data' => [
                'ids' => $ids,
                'mpKeys' => $mpKeys,
            ],
        ]);
    }

    /**
     * GET /api/marketplace/stats
     */
    public function stats(): JsonResponse
    {
        $stats = [];

        foreach (['vega', 'domus', 'jysk'] as $marketplace) {
            $query = ScrapedProduct::marketplace($marketplace);
            $stats[$marketplace] = [
                'total' => (clone $query)->count(),
                'with_dimensions' => (clone $query)->withDimensions()->count(),
                'in_stock' => (clone $query)->inStock()->count(),
            ];
        }

        return response()->json([
            'data' => $stats,
            'total_all' => ScrapedProduct::query()->count(),
        ]);
    }
}
