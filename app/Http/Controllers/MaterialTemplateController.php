<?php

namespace App\Http\Controllers;

use App\Http\Resources\MaterialTemplateResource;
use App\Models\MaterialTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = MaterialTemplate::query()->orderBy('sort_order')->orderBy('manufacturer')->orderBy('name');

        if ($type = $request->query('type')) {
            $q->where(function ($sub) use ($type) {
                $sub->where('type', $type)
                    ->orWhereJsonContains('types', $type);
            });
        }

        if ($manufacturer = $request->query('manufacturer')) {
            $q->where('manufacturer', $manufacturer);
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $q->where(function ($sub) use ($like) {
                $sub->where('name', 'like', $like)
                    ->orWhere('external_code', 'like', $like)
                    ->orWhere('color', 'like', $like);
            });
        }

        $templates = $q->get();

        return response()->json([
            'data' => MaterialTemplateResource::collection($templates),
        ]);
    }
}
