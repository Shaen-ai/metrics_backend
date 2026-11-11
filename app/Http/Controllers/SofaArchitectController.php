<?php

namespace App\Http\Controllers;

use App\Services\SofaArchitect\SofaStructureAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SofaArchitectController extends Controller
{
    public function analyze(Request $request, SofaStructureAnalyzer $analyzer): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'file', 'image', 'max:10240'],
        ]);

        if (! $request->hasFile('image') && trim((string) ($validated['text'] ?? '')) === '') {
            return response()->json(['message' => 'Provide an image and/or text notes.'], 422);
        }

        try {
            $draft = $analyzer->analyze(
                $request->file('image'),
                (string) ($validated['text'] ?? ''),
            );

            return response()->json(['data' => $draft]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }
}
