<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCatalogItemRequest;
use App\Http\Requests\UpdateCatalogItemRequest;
use App\Http\Resources\CatalogItemResource;
use App\Models\CatalogItem;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CatalogItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = $request->user()
            ->catalogItems()
            ->with(['images', 'colors'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => CatalogItemResource::collection($items),
        ]);
    }

    public function store(StoreCatalogItemRequest $request): JsonResponse
    {
        $item = CatalogItem::create([
            'id' => Str::uuid()->toString(),
            'admin_id' => $request->user()->id,
            ...$request->safe()->except(['images', 'colors']),
        ]);

        if ($request->has('images')) {
            foreach ($request->images as $i => $url) {
                $item->images()->create(['url' => $url, 'sort_order' => $i]);
            }
        }

        if ($request->has('colors')) {
            foreach ($request->colors as $color) {
                $item->colors()->create($color);
            }
        }

        $item->load(['images', 'colors']);

        AuditLogger::log($request, $request->user(), 'catalog_item.created', CatalogItem::class, $item->id);

        return response()->json([
            'data' => new CatalogItemResource($item),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $item = $request->user()
            ->catalogItems()
            ->with(['images', 'colors'])
            ->findOrFail($id);

        return response()->json([
            'data' => new CatalogItemResource($item),
        ]);
    }

    public function update(UpdateCatalogItemRequest $request, string $id): JsonResponse
    {
        $item = $request->user()->catalogItems()->findOrFail($id);
        $item->update($request->safe()->except(['images', 'colors']));

        if ($request->has('images')) {
            $item->images()->delete();
            foreach ($request->images as $i => $url) {
                $item->images()->create(['url' => $url, 'sort_order' => $i]);
            }
        }

        if ($request->has('colors')) {
            $item->colors()->delete();
            foreach ($request->colors as $color) {
                $item->colors()->create($color);
            }
        }

        $item->load(['images', 'colors']);

        AuditLogger::log($request, $request->user(), 'catalog_item.updated', CatalogItem::class, $item->id);

        return response()->json([
            'data' => new CatalogItemResource($item),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $item = $request->user()->catalogItems()->findOrFail($id);
        AuditLogger::log($request, $request->user(), 'catalog_item.deleted', CatalogItem::class, $item->id);
        $item->delete();

        return response()->json(null, 204);
    }
}
