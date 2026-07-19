<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AdminMedia;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    private const SOURCES = ['vista', 'interior', 'project', 'planner'];

    /**
     * GET /api/admin/users/{id}/images?kind=generated|uploaded
     * Unified feed of every image the user owns across all four subsystems.
     */
    public function index(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $kind = (string) $request->query('kind', '');
        $kind = in_array($kind, ['generated', 'uploaded'], true) ? $kind : null;

        return response()->json([
            'data' => AdminMedia::forUser($user, $kind),
        ]);
    }

    /**
     * DELETE /api/admin/images/{source}/{id}
     * Remove one image (file on disk + its DB reference). Irreversible; audit-logged.
     */
    public function destroy(Request $request, string $source, string $id): JsonResponse
    {
        if (! in_array($source, self::SOURCES, true)) {
            abort(404);
        }

        $deleted = AdminMedia::delete($source, $id);
        if (! $deleted) {
            return response()->json(['ok' => false, 'message' => 'Image not found.'], 404);
        }

        AuditLogger::log($request, $request->user(), 'admin.image.delete', $source, $id);

        return response()->json(['ok' => true]);
    }
}
