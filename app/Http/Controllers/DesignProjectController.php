<?php

namespace App\Http\Controllers;

use App\Models\DesignProject;
use App\Models\DesignProjectImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DesignProjectController extends Controller
{
    /**
     * POST /api/design-projects
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'style' => 'nullable|string|max:50',
            'total_area_m2' => 'nullable|numeric|min:10|max:1000',
            'family_members' => 'nullable|integer|min:1|max:20',
            'budget_tier' => 'nullable|string|in:economy,mid,premium,luxury',
            'wishes' => 'nullable|string|max:2000',
            'address' => 'nullable|string|max:500',
            'floor_plan' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf|max:20480',
        ]);

        $floorPlanPath = null;
        if ($request->hasFile('floor_plan')) {
            $floorPlanPath = $request->file('floor_plan')->store(
                'design-projects/floor-plans',
                'public'
            );
        }

        $project = DesignProject::create([
            'user_id' => $user->id,
            'title' => $data['title'] ?? 'Design Project',
            'status' => 'pending',
            'style' => $data['style'] ?? 'modern-neutral',
            'total_area_m2' => $data['total_area_m2'] ?? null,
            'family_members' => $data['family_members'] ?? 2,
            'budget_tier' => $data['budget_tier'] ?? 'mid',
            'wishes' => $data['wishes'] ?? null,
            'address' => $data['address'] ?? null,
            'floor_plan_path' => $floorPlanPath,
        ]);

        return response()->json(['data' => $project], 201);
    }

    /**
     * GET /api/design-projects
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $projects = DesignProject::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($projects);
    }

    /**
     * GET /api/design-projects/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $project = DesignProject::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->with('images')
            ->firstOrFail();

        return response()->json(['data' => $project]);
    }

    /**
     * GET /api/design-projects/{id}/pdf
     */
    public function downloadPdf(Request $request, string $id)
    {
        $project = DesignProject::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if (! $project->pdf_path || ! Storage::disk('public')->exists($project->pdf_path)) {
            return response()->json(['error' => 'PDF not available'], 404);
        }

        return Storage::disk('public')->download(
            $project->pdf_path,
            Str::slug($project->title).'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * POST /api/internal/design-projects/{id}/update
     * Called by Vista server to push progress/results.
     */
    public function internalUpdate(Request $request, string $id): JsonResponse
    {
        $project = DesignProject::findOrFail($id);

        $data = $request->validate([
            'status' => 'nullable|string|max:24',
            'floor_plan_analysis' => 'nullable|array',
            'master_concept' => 'nullable|array',
            'room_results' => 'nullable|array',
            'technical_drawings' => 'nullable|array',
            'pdf_path' => 'nullable|string|max:500',
            'progress_percent' => 'nullable|integer|min:0|max:100',
            'current_phase' => 'nullable|string|max:50',
            'current_room' => 'nullable|string|max:100',
            'error_message' => 'nullable|string|max:5000',
            'title' => 'nullable|string|max:255',
        ]);

        $project->update(array_filter($data, fn ($v) => $v !== null));

        return response()->json(['data' => $project]);
    }

    /**
     * POST /api/internal/design-projects/{id}/images
     * Store a render image from Vista.
     */
    public function internalStoreImage(Request $request, string $id): JsonResponse
    {
        $project = DesignProject::findOrFail($id);

        $data = $request->validate([
            'room_id' => 'required|string|max:50',
            'angle_index' => 'nullable|integer|min:0|max:10',
            'type' => 'nullable|string|in:room_render,technical_drawing,cover',
            'base64' => 'required|string',
            'mime_type' => 'nullable|string|max:50',
        ]);

        $mime = $data['mime_type'] ?? 'image/png';
        $ext = str_contains($mime, 'jpeg') || str_contains($mime, 'jpg') ? 'jpg' : 'png';
        $filename = Str::uuid().'.'.$ext;
        $path = "design-projects/{$project->id}/{$filename}";

        $decoded = base64_decode($data['base64']);
        Storage::disk('public')->put($path, $decoded);

        $image = DesignProjectImage::create([
            'project_id' => $project->id,
            'room_id' => $data['room_id'],
            'angle_index' => $data['angle_index'] ?? 0,
            'file_path' => $path,
            'mime_type' => $mime,
            'file_size_bytes' => strlen($decoded),
            'type' => $data['type'] ?? 'room_render',
        ]);

        return response()->json(['data' => $image], 201);
    }

    /**
     * POST /api/internal/design-projects/{id}/pdf
     * Store final PDF from Vista server.
     */
    public function internalStorePdf(Request $request, string $id): JsonResponse
    {
        $expected = config('services.internal_api_key');
        if (! is_string($expected) || $expected === '') {
            return response()->json(['message' => 'Internal API not configured.'], 503);
        }
        if ($request->header('X-Internal-Key') !== $expected) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $project = DesignProject::findOrFail($id);

        $data = $request->validate([
            'base64' => 'required|string',
        ]);

        $path = "design-projects/{$project->id}/project.pdf";
        Storage::disk('public')->put($path, base64_decode($data['base64']));

        $project->update([
            'pdf_path' => $path,
            'status' => 'complete',
        ]);

        return response()->json(['data' => ['pdf_path' => $path]]);
    }
}
