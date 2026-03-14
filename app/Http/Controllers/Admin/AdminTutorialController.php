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

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'published' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($request->filled('search')) {
            $search = (string) $validated['search'];
            $query->where(function ($inner) use ($search) {
                $inner
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', (string) $validated['category']);
        }

        if ($request->filled('published')) {
            $query->where('is_published', filter_var($request->query('published'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = (int) ($validated['per_page'] ?? 15);
        $tutorials = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            ...$tutorials->toArray(),
            'summary' => [
                'total' => Tutorial::query()->count(),
                'published' => Tutorial::query()->where('is_published', true)->count(),
                'draft' => Tutorial::query()->where('is_published', false)->count(),
            ],
            'categories' => Tutorial::query()
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->values(),
        ]);
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
