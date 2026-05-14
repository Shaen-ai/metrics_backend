<?php

namespace App\Http\Controllers;

use App\Models\InteriorDesignImage;
use App\Models\InteriorDesignSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InteriorDesignController extends Controller
{
    /**
     * POST /api/interior-design/sessions
     * Create a new session and optionally upload images (base64).
     */
    public function createSession(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'style' => 'nullable|string|max:48',
            'room_analysis' => 'nullable|array',
            'design_brief' => 'nullable|array',
            'latest_prompt' => 'nullable|string|max:10000',
            'images' => 'nullable|array|max:10',
            'images.*.base64' => 'required_with:images|string',
            'images.*.mime_type' => 'nullable|string|max:48',
            'images.*.prompt_used' => 'nullable|string|max:10000',
            'images.*.type' => 'nullable|string|in:original,generated,edited',
        ]);

        $session = InteriorDesignSession::create([
            'admin_id' => $user->id,
            'style' => $data['style'] ?? 'modern',
            'room_analysis' => $data['room_analysis'] ?? null,
            'design_brief' => $data['design_brief'] ?? null,
            'latest_prompt' => $data['latest_prompt'] ?? null,
        ]);

        $savedImages = [];
        foreach ($data['images'] ?? [] as $imgData) {
            $image = $this->storeBase64Image($session, $imgData);
            if ($image) {
                $savedImages[] = $image;
            }
        }

        return response()->json([
            'data' => [
                'session' => $session,
                'images' => $savedImages,
            ],
        ], 201);
    }

    /**
     * GET /api/interior-design/sessions
     * List current user's sessions.
     */
    public function listSessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $sessions = InteriorDesignSession::where('admin_id', $user->id)
            ->with('images')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($sessions);
    }

    /**
     * GET /api/interior-design/sessions/{id}
     */
    public function showSession(Request $request, string $id): JsonResponse
    {
        $session = InteriorDesignSession::where('admin_id', $request->user()->id)
            ->with('images')
            ->findOrFail($id);

        return response()->json(['data' => $session]);
    }

    /**
     * POST /api/interior-design/sessions/{id}/images
     * Upload one or more images to an existing session.
     */
    public function addImages(Request $request, string $id): JsonResponse
    {
        $session = InteriorDesignSession::where('admin_id', $request->user()->id)
            ->findOrFail($id);

        $data = $request->validate([
            'images' => 'required|array|min:1|max:10',
            'images.*.base64' => 'required|string',
            'images.*.mime_type' => 'nullable|string|max:48',
            'images.*.prompt_used' => 'nullable|string|max:10000',
            'images.*.type' => 'nullable|string|in:original,generated,edited',
        ]);

        if ($request->has('latest_prompt')) {
            $session->update(['latest_prompt' => $request->input('latest_prompt')]);
        }

        $saved = [];
        foreach ($data['images'] as $imgData) {
            $image = $this->storeBase64Image($session, $imgData);
            if ($image) {
                $saved[] = $image;
            }
        }

        return response()->json(['data' => $saved], 201);
    }

    /**
     * GET /api/interior-design/images/{id}
     * Serve a stored image file.
     */
    public function serveImage(string $id): mixed
    {
        $image = InteriorDesignImage::findOrFail($id);

        if (! Storage::disk('public')->exists($image->file_path)) {
            return response()->json(['message' => 'Image file not found.'], 404);
        }

        return response()->file(Storage::disk('public')->path($image->file_path), [
            'Content-Type' => $image->mime_type,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * DELETE /api/interior-design/sessions/{id}
     */
    public function deleteSession(Request $request, string $id): JsonResponse
    {
        $session = InteriorDesignSession::where('admin_id', $request->user()->id)
            ->findOrFail($id);

        foreach ($session->images as $img) {
            Storage::disk('public')->delete($img->file_path);
        }
        $session->delete();

        return response()->json(['message' => 'Session deleted.']);
    }

    /**
     * POST /api/public/{slug}/interior-design/upload
     * Public endpoint: Next.js API routes push generated images here for persistence.
     */
    public function publicUpload(Request $request, string $slug): JsonResponse
    {
        $expected = config('services.internal_api_key');
        if (! is_string($expected) || $expected === '' || $request->header('X-Internal-Key') !== $expected) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $user = User::where('slug', $slug)->first();
        if (! $user) {
            return response()->json(['message' => 'Unknown storefront.'], 404);
        }

        $data = $request->validate([
            'session_id' => 'nullable|uuid',
            'style' => 'nullable|string|max:48',
            'base64' => 'required|string',
            'mime_type' => 'nullable|string|max:48',
            'prompt_used' => 'nullable|string|max:10000',
            'type' => 'nullable|string|in:original,generated,edited',
        ]);

        $session = null;
        if (! empty($data['session_id'])) {
            $session = InteriorDesignSession::find($data['session_id']);
        }
        if (! $session) {
            $session = InteriorDesignSession::create([
                'admin_id' => $user->id,
                'style' => $data['style'] ?? 'modern',
            ]);
        }

        $image = $this->storeBase64Image($session, $data);

        return response()->json([
            'data' => [
                'session_id' => $session->id,
                'image' => $image,
            ],
        ], 201);
    }

    private function storeBase64Image(InteriorDesignSession $session, array $imgData): ?InteriorDesignImage
    {
        $base64 = $imgData['base64'] ?? '';
        if ($base64 === '') {
            return null;
        }

        $decoded = base64_decode($base64, true);
        if ($decoded === false) {
            return null;
        }

        $mimeType = $imgData['mime_type'] ?? 'image/png';
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };

        $fileName = Str::uuid().'.'.$extension;
        $filePath = 'interior-design/'.$session->id.'/'.$fileName;

        Storage::disk('public')->put($filePath, $decoded);

        return InteriorDesignImage::create([
            'session_id' => $session->id,
            'file_path' => $filePath,
            'prompt_used' => $imgData['prompt_used'] ?? null,
            'type' => $imgData['type'] ?? 'generated',
            'mime_type' => $mimeType,
            'file_size_bytes' => strlen($decoded),
        ]);
    }
}
