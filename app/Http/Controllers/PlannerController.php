<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlanGenerateRequest;
use App\Http\Resources\PlannerRequestResource;
use App\Models\PlannerRequest;
use App\Services\Planner\AI\ImageContextService;
use App\Services\Planner\AI\IntentParserService;
use App\Services\Planner\Engine\FurnitureEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class PlannerController extends Controller
{
    public function generate(
        PlanGenerateRequest $request,
        ImageContextService $imageContext,
        IntentParserService $intentParser,
        FurnitureEngine $engine,
    ): JsonResponse {
        $admin = $request->plannerAdmin();
        $validated = $request->validated();
        $planRequest = $imageContext->build(
            ['admin' => $admin, 'text' => $validated['text']],
            $request->file('room_image'),
            $request->file('inspiration_image'),
        );

        try {
            $intent = $intentParser->parse($planRequest);
            $result = $engine->generate($planRequest, $intent);
            $resultArray = $result->toArray();

            $record = PlannerRequest::create([
                'id' => Str::uuid()->toString(),
                'admin_id' => $admin->id,
                'text' => $planRequest->text,
                'image_paths' => $planRequest->imagePaths(),
                'ai_interpretation' => $intent,
                'result' => [
                    'furniture_plan' => $resultArray['furniture_plan'],
                    'modules' => $resultArray['modules'],
                    'warnings' => $resultArray['warnings'],
                ],
                'estimated_price' => $resultArray['estimated_price'],
                'status' => 'completed',
            ]);

            return response()->json([
                'data' => [
                    'request_id' => $record->id,
                    ...$resultArray,
                ],
            ]);
        } catch (RuntimeException $e) {
            PlannerRequest::create([
                'id' => Str::uuid()->toString(),
                'admin_id' => $admin->id,
                'text' => $planRequest->text,
                'image_paths' => $planRequest->imagePaths(),
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    public function history(Request $request): JsonResponse
    {
        $records = PlannerRequest::where('admin_id', $request->user()->id)
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'data' => PlannerRequestResource::collection($records),
        ]);
    }
}
