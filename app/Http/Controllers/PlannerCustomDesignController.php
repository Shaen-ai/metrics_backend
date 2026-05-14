<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCustomDesignRequest;
use App\Http\Requests\SubmitCustomDesignRequest;
use App\Mail\CustomDesignSubmittedMailable;
use App\Models\CustomDesign;
use App\Models\User;
use App\Support\PlanEntitlements;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PlannerCustomDesignController extends Controller
{
    public function save(SaveCustomDesignRequest $request): JsonResponse
    {
        $user = $request->user();

        $design = CustomDesign::updateOrCreate(
            ['owner_user_id' => $user->id, 'status' => 'draft'],
            [
                'id' => CustomDesign::where('owner_user_id', $user->id)->where('status', 'draft')->value('id') ?? Str::uuid()->toString(),
                'admin_id' => $user->id,
                'design' => $request->validated('design'),
            ]
        );

        return response()->json(['data' => $this->resource($design)]);
    }

    public function load(): JsonResponse
    {
        $user = request()->user();
        $design = CustomDesign::where('owner_user_id', $user->id)
            ->where('status', 'draft')
            ->latest('updated_at')
            ->first();

        return response()->json([
            'data' => [
                'design' => $design?->design,
                'updatedAt' => $design?->updated_at?->toISOString(),
            ],
        ]);
    }

    public function submit(SubmitCustomDesignRequest $request): JsonResponse
    {
        $user = $request->user();

        $design = $this->createSubmission($request, $user, $user);
        $this->notifyTeam($design);

        return response()->json(['data' => $this->resource($design)]);
    }

    public function submitPublic(SubmitCustomDesignRequest $request, string $slug): JsonResponse
    {
        $admin = User::where('slug', $slug)->firstOrFail();
        if ($admin->site_published_at === null) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        if (! PlanEntitlements::hasActiveSubscription($admin)) {
            return response()->json(['message' => 'This workspace does not have an active subscription.'], 403);
        }

        $design = $this->createSubmission($request, $admin, null);
        $this->notifyTeam($design);

        return response()->json(['data' => $this->resource($design)]);
    }

    private function createSubmission(SubmitCustomDesignRequest $request, User $admin, ?User $owner): CustomDesign
    {
        $validated = $request->validated();
        $id = Str::uuid()->toString();

        return CustomDesign::create([
            'id' => $id,
            'admin_id' => $admin->id,
            'owner_user_id' => $owner?->id,
            'status' => 'submitted',
            'room_name' => $validated['room_name'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'customer_name' => $validated['customer_name'] ?? null,
            'customer_email' => $validated['customer_email'] ?? null,
            'design' => $validated['design'],
            'snapshot_path' => $this->storeSnapshot($validated['snapshot'] ?? null, $admin->id, $id),
            'submitted_at' => now(),
        ]);
    }

    private function storeSnapshot(?string $snapshot, string $adminId, string $designId): ?string
    {
        if (! is_string($snapshot) || $snapshot === '') {
            return null;
        }

        if (! str_starts_with($snapshot, 'data:image/png;base64,')) {
            return null;
        }

        $raw = base64_decode(substr($snapshot, strlen('data:image/png;base64,')), true);
        if ($raw === false) {
            return null;
        }

        $path = "files/{$adminId}/custom-designs/{$designId}.png";
        Storage::disk('public')->put($path, $raw);

        return $path;
    }

    private function notifyTeam(CustomDesign $design): void
    {
        $email = filter_var($design->admin?->email, FILTER_VALIDATE_EMAIL) ? $design->admin->email : null;
        if ($email === null) {
            Log::warning('Custom design submission skipped: admin has no valid email', [
                'custom_design_id' => $design->id,
                'admin_id' => $design->admin_id,
            ]);

            return;
        }

        try {
            Mail::to($email)->send(new CustomDesignSubmittedMailable($design->loadMissing('admin')));
        } catch (\Throwable $e) {
            Log::error('Custom design submission mail failed', [
                'custom_design_id' => $design->id,
                'admin_id' => $design->admin_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resource(CustomDesign $design): array
    {
        return [
            'id' => $design->id,
            'status' => $design->status,
            'roomName' => $design->room_name,
            'snapshotPath' => $design->snapshot_path,
            'submittedAt' => $design->submitted_at?->toISOString(),
            'updatedAt' => $design->updated_at?->toISOString(),
        ];
    }
}
