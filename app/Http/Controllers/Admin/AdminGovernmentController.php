<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Government;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminGovernmentController extends Controller
{
    public function index(): JsonResponse
    {
        $governments = Government::query()->orderBy('id')->get();

        return response()->json($governments);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'accessible_locations' => ['nullable', 'string'],
        ]);

        $government = new Government();
        $government->accessible_locations = $validated['accessible_locations'] ?? null;
        $government->save();

        return response()->json($government, 201);
    }
}
