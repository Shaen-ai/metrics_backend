<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = $request->user()->userProducts()
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source_type' => 'required|string|in:url,upload',
            'source_url' => 'nullable|url|max:1024',
            'name' => 'required|string|max:512',
            'name_en' => 'nullable|string|max:512',
            'description' => 'nullable|string|max:2000',
            'category' => 'nullable|string|max:256',
            'price' => 'nullable|integer|min:0',
            'currency' => 'nullable|string|max:8',
            'width_cm' => 'nullable|integer|min:0|max:9999',
            'depth_cm' => 'nullable|integer|min:0|max:9999',
            'height_cm' => 'nullable|integer|min:0|max:9999',
            'main_image_url' => 'nullable|url|max:1024',
        ]);

        $product = $request->user()->userProducts()->create($data);

        return response()->json(['data' => $product], 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $product = $request->user()->userProducts()->findOrFail($id);
        $product->delete();

        return response()->json(['message' => 'Product removed.']);
    }
}
