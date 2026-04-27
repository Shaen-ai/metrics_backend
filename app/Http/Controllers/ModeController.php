<?php

namespace App\Http\Controllers;

use App\Http\Resources\ModeResource;
use App\Models\Mode;
use Illuminate\Http\JsonResponse;

class ModeController extends Controller
{
    public function index(): JsonResponse
    {
        $modes = Mode::with('subModes')->where('is_active', true)->get();

        return response()->json([
            'data' => ModeResource::collection($modes),
        ]);
    }
}
