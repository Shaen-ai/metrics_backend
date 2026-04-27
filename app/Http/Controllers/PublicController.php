<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicOrderRequest;
use App\Http\Resources\AdminPublicResource;
use App\Http\Resources\CatalogItemResource;
use App\Http\Resources\MaterialResource;
use App\Http\Resources\ModuleResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Support\PlanEntitlements;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class PublicController extends Controller
{
    private function findAdmin(string $slug): User
    {
        return User::where('slug', $slug)->firstOrFail();
    }

    public function admin(string $slug): JsonResponse
    {
        $admin = $this->findAdmin($slug);

        return response()->json([
            'data' => new AdminPublicResource($admin),
        ]);
    }

    public function entitlements(string $slug): JsonResponse
    {
        $admin = $this->findAdmin($slug);

        return response()->json([
            'data' => PlanEntitlements::toPublicArray($admin),
        ]);
    }

    public function catalog(string $slug): JsonResponse
    {
        $admin = $this->findAdmin($slug);

        $query = $admin->catalogItems()
            ->where('is_active', true)
            ->with(['images', 'colors']);

        $subModeSlug = request()->query('sub_mode');
        $subMode = null;
        if ($subModeSlug) {
            $subMode = \App\Models\SubMode::where('slug', $subModeSlug)->first();
            if ($subMode) {
                $query->where('sub_mode_id', $subMode->id);
            }
        }

        $items = $query->orderBy('created_at', 'desc')->get();

        $aiRoomCatalogOnly = $subModeSlug === 'ai-room';

        if (! $admin->use_custom_planner_catalog && ! $aiRoomCatalogOnly) {
            $librarySlug = (string) config('app.catalog_library_slug', 'demo');
            $libraryUser = User::where('slug', $librarySlug)->first();
            if ($libraryUser && $libraryUser->id !== $admin->id) {
                $libQuery = $libraryUser->catalogItems()
                    ->where('is_active', true)
                    ->with(['images', 'colors']);
                if ($subMode) {
                    $libQuery->where('sub_mode_id', $subMode->id);
                }
                $libItems = $libQuery->orderBy('created_at', 'desc')->get();
                $seen = $items->pluck('id');
                foreach ($libItems as $row) {
                    if ($seen->contains($row->id)) {
                        continue;
                    }
                    $items->push($row);
                    $seen->push($row->id);
                }
            }
        }

        return response()->json([
            'data' => CatalogItemResource::collection($items),
        ]);
    }

    public function materials(string $slug): JsonResponse
    {
        $admin = $this->findAdmin($slug);

        $materials = $admin->materials()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => MaterialResource::collection($materials),
        ]);
    }

    public function modules(string $slug): JsonResponse
    {
        $admin = $this->findAdmin($slug);

        $modules = $admin->modules()
            ->where('is_active', true)
            ->with(['images', 'connectionPoints', 'compatibleModules'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => ModuleResource::collection($modules),
        ]);
    }

    public function submitOrder(PublicOrderRequest $request, string $slug): JsonResponse
    {
        $admin = $this->findAdmin($slug);

        $order = Order::create([
            'id' => Str::uuid()->toString(),
            'admin_id' => $admin->id,
            ...$request->safe()->except(['items']),
        ]);

        foreach ($request->items as $itemData) {
            $orderItem = $order->items()->create([
                'item_type' => $itemData['item_type'],
                'item_id' => $itemData['item_id'] ?? null,
                'name' => $itemData['name'],
                'quantity' => $itemData['quantity'],
                'price' => $itemData['price'],
                'custom_data' => $itemData['custom_data'] ?? null,
            ]);

            if (!empty($itemData['selected_materials'])) {
                $orderItem->materials()->sync($itemData['selected_materials']);
            }
        }

        $order->load(['items.materials']);

        return response()->json([
            'data' => new OrderResource($order),
        ], 201);
    }
}
