<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\User;
use App\Services\Catalog\CatalogItemSlotResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PublicCatalogSlotController extends Controller
{
    /**
     * POST /api/public/{slug}/catalog/resolve-slots
     *
     * Vector recall + rerank per slot, scoped to a single merchant's catalog_items.
     */
    public function resolveSlots(Request $request, string $slug, CatalogItemSlotResolver $resolver): JsonResponse
    {
        $admin = User::where('slug', $slug)->first();
        if (! $admin) {
            return response()->json(['message' => 'Merchant not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'design_intent' => 'required|string|min:8|max:4000',
            'slots' => 'required|array|min:1|max:24',
            'slots.*.family' => 'required|string|max:48',
            'slots.*.subtype' => 'nullable|string|max:48',
            'slots.*.quantity' => 'nullable|integer|min:1|max:8',
            'slots.*.placement' => 'nullable|string|max:200',
            'pinnedIds' => 'nullable|array|max:48',
            'pinnedIds.*' => 'string|max:36',
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
            ->map(fn ($id) => trim((string) $id))
            ->filter(fn ($id) => $id !== '')
            ->unique()
            ->values()
            ->all();

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
                $admin->id,
                $pinnedIds,
                $roomDimensions,
                $request->input('constraints', []),
                $roomType,
            );
        } catch (\Throwable $e) {
            Log::error('catalog_items.resolve_slots_failed', [
                'slug' => $slug,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Catalog resolution failed.'], 503);
        }

        $allIds = $pinnedIds;
        foreach ($result['slots'] as $slot) {
            foreach ($slot['product_ids'] as $id) {
                $allIds[] = (string) $id;
            }
        }
        $allIds = array_values(array_unique(array_filter($allIds, fn ($id) => $id !== '')));

        $namesById = $allIds !== []
            ? CatalogItem::query()->whereIn('id', $allIds)->pluck('name', 'id')->all()
            : [];

        Log::info('catalog_items.resolve_slots', array_merge($result['metrics'], [
            'slug' => $slug,
            'slot_count' => count($result['slots']),
            'resolved_ids' => count($allIds),
        ]));

        return response()->json([
            'data' => [
                'slots' => $result['slots'],
                'ids' => $allIds,
                'metrics' => $result['metrics'],
            ],
        ]);
    }
}
