<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CatalogItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    /**
     * GET /api/admin/users/{id}/catalog
     * A merchant's product catalog with image URLs and 3D model status.
     */
    public function index(string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $items = CatalogItem::where('admin_id', $user->id)
            ->with('images:id,catalog_item_id,url,sort_order')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (CatalogItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category,
                'price' => $item->price,
                'currency' => $item->currency,
                'unit' => $item->unit,
                'is_active' => (bool) $item->is_active,
                'model_url' => $item->model_url,
                'model_status' => $item->model_status,
                'images' => $item->images->map(fn ($img) => [
                    'id' => $img->id,
                    'url' => $img->url,
                ])->values(),
                'created_at' => optional($item->created_at)->toIso8601String(),
            ]);

        return response()->json(['data' => $items]);
    }
}
