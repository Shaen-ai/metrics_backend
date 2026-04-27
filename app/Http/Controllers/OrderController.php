<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()
            ->orders()
            ->with(['items.materials'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => OrderResource::collection($orders),
        ]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = Order::create([
            'id' => Str::uuid()->toString(),
            'admin_id' => $request->user()->id,
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

        AuditLogger::log($request, $request->user(), 'order.created', Order::class, $order->id);

        return response()->json([
            'data' => new OrderResource($order),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $order = $request->user()
            ->orders()
            ->with(['items.materials'])
            ->findOrFail($id);

        return response()->json([
            'data' => new OrderResource($order),
        ]);
    }

    public function update(UpdateOrderRequest $request, string $id): JsonResponse
    {
        $order = $request->user()->orders()->findOrFail($id);
        $validated = $request->validated();
        $order->update($validated);

        $meta = array_key_exists('status', $validated)
            ? ['status' => $validated['status']]
            : null;
        AuditLogger::log(
            $request,
            $request->user(),
            array_key_exists('status', $validated) ? 'order.status_changed' : 'order.updated',
            Order::class,
            $order->id,
            $meta,
        );

        return response()->json([
            'data' => new OrderResource($order->fresh()->load(['items.materials'])),
        ]);
    }
}
