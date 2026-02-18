<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTutorialRequest;
use App\Http\Requests\Admin\UpdateTutorialRequest;
use App\Models\Tutorial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTutorialController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Tutorial::query();

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where('title', 'like', "%{$search}%");
        }

        $tutorials = $query->orderByDesc('id')->paginate(15);

        return response()->json($tutorials);
    }

    public function store(StoreTutorialRequest $request): JsonResponse
    {
        $tutorial = Tutorial::create($request->validated());

        return response()->json($tutorial, 201);
    }

    public function update(UpdateTutorialRequest $request, int $id): JsonResponse
    {
        $tutorial = Tutorial::query()->find($id);

        if (!$tutorial) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $tutorial->update($request->validated());

        return response()->json($tutorial);
    }

    public function destroy(int $id): JsonResponse
    {
        $tutorial = Tutorial::query()->find($id);

        if (!$tutorial) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $tutorial->delete();

        return response()->json(null, 204);
    }
}
