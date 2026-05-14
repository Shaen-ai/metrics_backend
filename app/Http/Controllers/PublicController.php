<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicOrderRequest;
use App\Http\Requests\PublicPlannerInquiryRequest;
use App\Http\Resources\AdminPublicResource;
use App\Http\Resources\CatalogItemResource;
use App\Http\Resources\MaterialResource;
use App\Http\Resources\ModuleResource;
use App\Http\Resources\OrderResource;
use App\Mail\OrderPlacedCustomerMailable;
use App\Mail\OrderPlacedMailable;
use App\Mail\PlannerInquiryMailable;
use App\Models\Order;
use App\Models\SubMode;
use App\Models\User;
use App\Support\PlanEntitlements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $request = request();
        $slugList = self::normalizedPublicCatalogSubModeSlugs($request);
        $subModes = collect();
        if ($slugList !== []) {
            $subModes = SubMode::whereIn('slug', $slugList)->get();
        }

        /** When true (server-side AI), include SKU rows flagged for_design (hidden from normal storefront browsing). */
        $interiorDesignAi = $request->boolean('interior_design_ai');

        $query = $admin->catalogItems()
            ->where('is_active', true);

        if (! $interiorDesignAi) {
            $query->where('for_design', false);
        }

        $query->with(['images', 'colors']);

        if ($subModes->isNotEmpty()) {
            $query->whereIn('sub_mode_id', $subModes->pluck('id')->all());
        }

        $items = $query->orderBy('created_at', 'desc')->get();

        $aiRoomCatalogOnly = $slugList !== [] && in_array('ai-room', $slugList, true);

        if ($request->boolean('include_library') && ! $admin->use_custom_planner_catalog && ! $aiRoomCatalogOnly) {
            $librarySlug = (string) config('app.catalog_library_slug', 'demo');
            $libraryUser = User::where('slug', $librarySlug)->first();
            if ($libraryUser && $libraryUser->id !== $admin->id) {
                $libQuery = $libraryUser->catalogItems()
                    ->where('is_active', true);

                if (! $interiorDesignAi) {
                    $libQuery->where('for_design', false);
                }

                $libQuery->with(['images', 'colors']);
                if ($subModes->isNotEmpty()) {
                    $libQuery->whereIn('sub_mode_id', $subModes->pluck('id')->all());
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

    public function plannerInquiry(PublicPlannerInquiryRequest $request, string $slug): JsonResponse
    {
        $admin = $this->findAdmin($slug);
        if ($blocked = $this->ensureSitePublished($admin)) {
            return $blocked;
        }
        if ($blocked = $this->ensureStorefrontSubscribed($admin)) {
            return $blocked;
        }

        $validated = $request->validated();
        $design = $validated['design'];

        if (isset($design['previewImageBase64']) && is_string($design['previewImageBase64'])) {
            if (strlen($design['previewImageBase64']) > 750_000) {
                return response()->json([
                    'message' => 'Preview image is too large to send.',
                ], 422);
            }
        }

        $designForMeasure = $design;
        unset($designForMeasure['previewImageBase64']);
        $encoded = json_encode($designForMeasure);
        if ($encoded === false || strlen($encoded) > 450000) {
            return response()->json([
                'message' => 'Design data is too large to send.',
            ], 422);
        }

        $adminEmail = $this->normalizedEmail($admin->email);
        if ($adminEmail === null) {
            Log::warning('Planner inquiry skipped: admin has no valid email', [
                'admin_id' => $admin->id,
            ]);

            return response()->json([
                'message' => 'This store cannot receive inquiry emails yet.',
            ], 503);
        }

        $plannerLabel = is_string($validated['planner_label'] ?? null) && trim($validated['planner_label']) !== ''
            ? trim($validated['planner_label'])
            : $validated['planner_type'];

        $notesRaw = $validated['notes'] ?? null;
        $notes = is_string($notesRaw) ? trim($notesRaw) : null;
        if ($notes === '') {
            $notes = null;
        }

        try {
            Mail::to($adminEmail)->send(new PlannerInquiryMailable(
                admin: $admin,
                customerName: $validated['customer_name'],
                customerEmail: $validated['customer_email'],
                plannerType: $validated['planner_type'],
                plannerLabel: $plannerLabel,
                notes: $notes,
                design: $design,
            ));
        } catch (\Throwable $e) {
            Log::error('Planner inquiry mail failed', [
                'admin_id' => $admin->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'We could not send your inquiry. Please try again later.',
            ], 503);
        }

        return response()->json(['ok' => true]);
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

            if (! empty($itemData['selected_materials'])) {
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

    /**
     * Public catalog filtering: merges ?sub_mode=, comma ?sub_modes=, and repeated sub_mode[]=
     *
     * @return list<string>
     */
    private static function normalizedPublicCatalogSubModeSlugs(Request $request): array
    {
        $out = [];

        $one = $request->query('sub_mode');
        if (is_array($one)) {
            foreach ($one as $s) {
                if (is_string($s) && ($t = trim($s)) !== '') {
                    $out[] = $t;
                }
            }
        } elseif (is_string($one) && ($t = trim($one)) !== '') {
            $out[] = $t;
        }

        $bulk = $request->query('sub_modes');
        if (is_string($bulk) && ($t = trim($bulk)) !== '') {
            foreach (explode(',', $t) as $part) {
                $u = trim($part);
                if ($u !== '') {
                    $out[] = $u;
                }
            }
        }

        /** @var list<string> $deduped */
        $deduped = [];
        $seen = [];
        foreach ($out as $s) {
            $k = strtolower($s);
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $deduped[] = $s;
        }

        return $deduped;
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
