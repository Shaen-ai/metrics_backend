<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreModuleRequest;
use App\Http\Requests\UpdateModuleRequest;
use App\Http\Resources\ModuleResource;
use App\Models\Module;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ModuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $modules = $request->user()
            ->modules()
            ->with(['images', 'connectionPoints', 'compatibleModules'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => ModuleResource::collection($modules),
        ]);
    }

    public function store(StoreModuleRequest $request): JsonResponse
    {
        $module = Module::create([
            'id' => Str::uuid()->toString(),
            'admin_id' => $request->user()->id,
            ...$request->safe()->except(['images', 'connection_points', 'compatible_with']),
        ]);

        if ($request->has('images')) {
            foreach ($request->images as $i => $url) {
                $module->images()->create(['url' => $url, 'sort_order' => $i]);
            }
        }

        if ($request->has('connection_points')) {
            foreach ($request->connection_points as $point) {
                $module->connectionPoints()->create($point);
            }
        }

        if ($request->has('compatible_with')) {
            $module->compatibleModules()->sync($request->compatible_with);
        }

        $module->load(['images', 'connectionPoints', 'compatibleModules']);

        AuditLogger::log($request, $request->user(), 'module.created', Module::class, $module->id);

        return response()->json([
            'data' => new ModuleResource($module),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $module = $request->user()
            ->modules()
            ->with(['images', 'connectionPoints', 'compatibleModules'])
            ->findOrFail($id);

        return response()->json([
            'data' => new ModuleResource($module),
        ]);
    }

    public function update(UpdateModuleRequest $request, string $id): JsonResponse
    {
        $module = $request->user()->modules()->findOrFail($id);
        $module->update($request->safe()->except(['images', 'connection_points', 'compatible_with']));

        if ($request->has('images')) {
            $module->images()->delete();
            foreach ($request->images as $i => $url) {
                $module->images()->create(['url' => $url, 'sort_order' => $i]);
            }
        }

        if ($request->has('connection_points')) {
            $module->connectionPoints()->delete();
            foreach ($request->connection_points as $point) {
                $module->connectionPoints()->create($point);
            }
        }

        if ($request->has('compatible_with')) {
            $module->compatibleModules()->sync($request->compatible_with);
        }

        $module->load(['images', 'connectionPoints', 'compatibleModules']);

        AuditLogger::log($request, $request->user(), 'module.updated', Module::class, $module->id);

        return response()->json([
            'data' => new ModuleResource($module),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $module = $request->user()->modules()->findOrFail($id);
        AuditLogger::log($request, $request->user(), 'module.deleted', Module::class, $module->id);
        $module->delete();

        return response()->json(null, 204);
    }
}
