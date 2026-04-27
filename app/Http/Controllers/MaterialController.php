<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkUpdateMaterialsRequest;
use App\Http\Requests\ImportMaterialTemplatesRequest;
use App\Http\Requests\StoreMaterialRequest;
use App\Http\Requests\UpdateMaterialRequest;
use App\Http\Resources\MaterialResource;
use App\Models\Material;
use App\Models\MaterialTemplate;
use App\Support\AuditLogger;
use App\Support\MaterialTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MaterialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $materials = $request->user()
            ->materials()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => MaterialResource::collection($materials),
        ]);
    }

    public function store(StoreMaterialRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $list = MaterialTypes::normalize($validated['types'] ?? null, $validated['type'] ?? null);
        $validated['types'] = $list;
        $validated['type'] = $list[0];
        $validated['category'] = $validated['categories'][0];

        $material = Material::create([
            'id' => Str::uuid()->toString(),
            'admin_id' => $request->user()->id,
            ...$validated,
        ]);

        AuditLogger::log($request, $request->user(), 'material.created', Material::class, $material->id);

        return response()->json([
            'data' => new MaterialResource($material),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $material = $request->user()->materials()->findOrFail($id);

        return response()->json([
            'data' => new MaterialResource($material),
        ]);
    }

    public function update(UpdateMaterialRequest $request, string $id): JsonResponse
    {
        $material = $request->user()->materials()->findOrFail($id);
        $validated = $request->validated();
        if (array_key_exists('categories', $validated)) {
            $validated['category'] = $validated['categories'][0];
        }
        if (array_key_exists('types', $validated) || array_key_exists('type', $validated)) {
            $list = MaterialTypes::normalize(
                $validated['types'] ?? null,
                $validated['type'] ?? null,
            );
            if (count($list) === 0) {
                $list = MaterialTypes::normalize($material->types, $material->type);
            }
            $validated['types'] = $list;
            $validated['type'] = $list[0];
        }
        $material->update($validated);

        AuditLogger::log($request, $request->user(), 'material.updated', Material::class, $material->id);

        return response()->json([
            'data' => new MaterialResource($material->fresh()),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $material = $request->user()->materials()->findOrFail($id);
        AuditLogger::log($request, $request->user(), 'material.deleted', Material::class, $material->id);
        $material->delete();

        return response()->json(null, 204);
    }

    public function importFromTemplates(ImportMaterialTemplatesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $currency = $validated['currency'] ?? $user->currency ?? 'AMD';
        $pricePerUnit = (string) $validated['price_per_unit'];
        $price = isset($validated['price']) && $validated['price'] !== null
            ? (string) $validated['price']
            : $pricePerUnit;

        $templates = MaterialTemplate::whereIn('id', $validated['template_ids'])->get();
        $order = array_flip($validated['template_ids']);
        $templates = $templates->sortBy(fn (MaterialTemplate $t) => $order[$t->id] ?? 9999)->values();

        $categoriesOverride = $validated['categories'] ?? null;
        $unitOverride = $validated['unit'] ?? null;

        $created = [];

        DB::transaction(function () use (
            $templates,
            $user,
            $validated,
            $currency,
            $pricePerUnit,
            $price,
            $categoriesOverride,
            $unitOverride,
            &$created,
        ) {
            foreach ($templates as $template) {
                $categories = $categoriesOverride ?? $template->categories ?? [$template->category];
                $unit = $unitOverride ?? $template->unit;

                $templateTypes = MaterialTypes::normalize($template->types, $template->type);

                $material = Material::create([
                    'id' => Str::uuid()->toString(),
                    'admin_id' => $user->id,
                    'mode_id' => $validated['mode_id'],
                    'manufacturer' => $template->manufacturer,
                    'sub_mode_id' => $validated['sub_mode_id'] ?? null,
                    'name' => $template->name,
                    'type' => $templateTypes[0],
                    'types' => $templateTypes,
                    'category' => $categories[0],
                    'categories' => $categories,
                    'color' => $template->color,
                    'color_hex' => $template->color_hex,
                    'color_code' => $template->color_code,
                    'price' => $price,
                    'price_per_unit' => $pricePerUnit,
                    'currency' => $currency,
                    'unit' => $unit,
                    'image' => null,
                    'image_url' => $template->image_url,
                    'sheet_width_cm' => $template->sheet_width_cm,
                    'sheet_height_cm' => $template->sheet_height_cm,
                    'grain_direction' => $template->grain_direction,
                    'kerf_mm' => $template->kerf_mm,
                    'is_active' => true,
                ]);
                $created[] = $material;
            }
        });

        foreach ($created as $material) {
            AuditLogger::log($request, $user, 'material.created', Material::class, $material->id, [
                'from_template' => true,
            ]);
        }

        return response()->json([
            'data' => MaterialResource::collection(collect($created)),
        ], 201);
    }

    public function bulkUpdate(BulkUpdateMaterialsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $materials = $user->materials()->whereIn('id', $validated['ids'])->get();

        if ($materials->count() !== count(array_unique($validated['ids']))) {
            return response()->json([
                'message' => 'One or more materials were not found.',
            ], 422);
        }

        $pricePerUnit = (string) $validated['price_per_unit'];
        $price = isset($validated['price']) && $validated['price'] !== null
            ? (string) $validated['price']
            : $pricePerUnit;

        $updates = [
            'price_per_unit' => $pricePerUnit,
            'price' => $price,
        ];
        if (array_key_exists('currency', $validated)) {
            $updates['currency'] = $validated['currency'];
        }

        foreach ($materials as $material) {
            $material->update($updates);
            AuditLogger::log($request, $user, 'material.updated', Material::class, $material->id, [
                'bulk' => true,
            ]);
        }

        $fresh = $user->materials()->whereIn('id', $validated['ids'])->get();

        return response()->json([
            'data' => MaterialResource::collection($fresh),
        ]);
    }
}
