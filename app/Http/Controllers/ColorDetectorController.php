<?php

namespace App\Http\Controllers;

use App\Services\ColorDetectorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ColorDetectorController extends Controller
{
    public function detect(Request $request, ColorDetectorService $service): JsonResponse
    {
        $request->validate([
            'image_url' => ['required', 'url'],
        ]);

        try {
            $result = $service->detect($request->input('image_url'));

            return response()->json([
                'data' => [
                    'color' => $result['name'],
                    'colorHex' => $result['hex'],
                    'rgb' => $result['rgb'],
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
