<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicOrderRequest;
use App\Http\Resources\AdminPublicResource;
use App\Http\Resources\CatalogItemResource;
use App\Http\Resources\MaterialResource;
use App\Http\Resources\ModuleResource;
use App\Http\Resources\OrderResource;
use App\Mail\OrderPlacedCustomerMailable;
use App\Mail\OrderPlacedMailable;
use App\Models\Order;
use App\Models\User;
use App\Support\PlanEntitlements;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PublicController extends Controller
{
    private function findAdmin(string $slug): User
    {
        return User::where('slug', $slug)->firstOrFail();
    }

    private function ensureSitePublished(User $admin): ?JsonResponse
    {
        if ($admin->site_published_at === null) {
            return response()->json([
                'message' => 'Not found.',
            ], 404);
        }

        return null;
    }

    private function ensureStorefrontSubscribed(User $admin): ?JsonResponse
    {
        if (! PlanEntitlements::hasActiveSubscription($admin)) {
            return response()->json([
                'message' => 'This workspace does not have an active subscription.',
            ], 403);
        }

        return null;
    }

    public function admin(string $slug): JsonResponse
    {
        $admin = $this->findAdmin($slug);
        if ($blocked = $this->ensureSitePublished($admin)) {
            return $blocked;
        }
        if ($blocked = $this->ensureStorefrontSubscribed($admin)) {
            return $blocked;
        }

        return response()->json([
            'data' => new AdminPublicResource($admin),
        ]);
    }

    public function entitlements(string $slug): JsonResponse
    {
        $admin = $this->findAdmin($slug);
        if ($blocked = $this->ensureSitePublished($admin)) {
            return $blocked;
        }
        if ($blocked = $this->ensureStorefrontSubscribed($admin)) {
            return $blocked;
        }

        return response()->json([
            'data' => PlanEntitlements::toPublicArray($admin),
        ]);
    }

    public function catalog(string $slug): JsonResponse
    {
        $admin = $this->findAdmin($slug);
        if ($blocked = $this->ensureSitePublished($admin)) {
            return $blocked;
        }
        if ($blocked = $this->ensureStorefrontSubscribed($admin)) {
            return $blocked;
        }

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

        if (request()->boolean('include_library') && ! $admin->use_custom_planner_catalog && ! $aiRoomCatalogOnly) {
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
        if ($blocked = $this->ensureSitePublished($admin)) {
            return $blocked;
        }
        if ($blocked = $this->ensureStorefrontSubscribed($admin)) {
            return $blocked;
        }

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
        if ($blocked = $this->ensureSitePublished($admin)) {
            return $blocked;
        }
        if ($blocked = $this->ensureStorefrontSubscribed($admin)) {
            return $blocked;
        }

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
        if ($blocked = $this->ensureSitePublished($admin)) {
            return $blocked;
        }
        if ($blocked = $this->ensureStorefrontSubscribed($admin)) {
            return $blocked;
        }

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

        $this->sendOrderPlacedMails($order, $admin);

        return response()->json([
            'data' => new OrderResource($order),
        ], 201);
    }

    /**
     * Send new-order email to the merchant and a confirmation to the customer.
     * Uses {@see Mail} directly so recipients are always set (notification + {@see Mailable} has no implicit To).
     */
    private function sendOrderPlacedMails(Order $order, User $admin): void
    {
        $order->loadMissing('items');

        $adminEmail = $this->normalizedEmail($admin->email);
        if ($adminEmail !== null) {
            try {
                Mail::to($adminEmail)->send(new OrderPlacedMailable($order));
            } catch (\Throwable $e) {
                Log::error('Failed to send new-order email to admin', [
                    'order_id' => $order->id,
                    'admin_id' => $admin->id,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            Log::warning('Order placed but admin account has no valid email; merchant notification skipped', [
                'order_id' => $order->id,
                'admin_id' => $admin->id,
            ]);
        }

        $customerEmail = $this->normalizedEmail($order->customer_email);
        if ($customerEmail !== null) {
            try {
                Mail::to($customerEmail)->send(new OrderPlacedCustomerMailable($order, $admin));
            } catch (\Throwable $e) {
                Log::error('Failed to send order confirmation to customer', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function normalizedEmail(?string $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }
        $trimmed = trim($email);
        if ($trimmed === '' || ! filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $trimmed;
    }
}
