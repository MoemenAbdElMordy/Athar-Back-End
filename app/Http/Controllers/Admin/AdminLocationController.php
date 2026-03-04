<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLocationRequest;
use App\Http\Requests\Admin\UpdateLocationRequest;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminLocationController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $location = Location::query()
            ->with(['category', 'government', 'accessibilityReport'])
            ->find($id);

        if (!$location) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json($location);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Location::query();

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('government_id')) {
            $query->where('government_id', $request->query('government_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        $perPage = (int) ($validated['per_page'] ?? 15);
        $locations = $query->orderByDesc('id')->paginate($perPage);

        return response()->json($locations);
    }

    public function store(StoreLocationRequest $request): JsonResponse
    {
        $location = Location::create($request->validated());

        return response()->json($location, 201);
    }

    public function update(UpdateLocationRequest $request, int $id): JsonResponse
    {
        $location = Location::find($id);

        if (!$location) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $location->update($request->validated());

        return response()->json($location);
    }

    public function destroy(int $id): JsonResponse
    {
        $location = Location::find($id);

        if (!$location) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $location->delete();

        return response()->json(['success' => true]);
    }
}
