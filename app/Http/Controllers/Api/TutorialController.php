<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Tutorial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TutorialController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Tutorial::query()
            ->where('is_published', true)
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $search = (string) $validated['search'];
            $query->where(function ($inner) use ($search): void {
                $inner
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', (string) $validated['category']);
        }

        $perPage = (int) ($validated['per_page'] ?? 50);
        $tutorials = $query->paginate($perPage);

        $items = collect($tutorials->items())
            ->map(fn (Tutorial $tutorial) => $this->serializeTutorial($tutorial))
            ->values();

        return $this->successResponse($items);
    }

    public function trackView(int $id): JsonResponse
    {
        $tutorial = Tutorial::query()
            ->where('id', $id)
            ->where('is_published', true)
            ->first();

        if (!$tutorial) {
            return $this->errorResponse('Not Found.', status: 404);
        }

        if ($tutorial->views_count === null) {
            $tutorial->views_count = 0;
            $tutorial->save();
        }

        $tutorial->increment('views_count');
        $tutorial->refresh();

        return $this->successResponse([
            'id' => $tutorial->id,
            'views_count' => (int) ($tutorial->views_count ?? 0),
        ]);
    }

    private function serializeTutorial(Tutorial $tutorial): array
    {
        return [
            'id' => $tutorial->id,
            'title' => $tutorial->title,
            'description' => $tutorial->description,
            'video_url' => $tutorial->video_url,
            'thumbnail_url' => $tutorial->thumbnail_url,
            'category' => $tutorial->category,
            'is_published' => (bool) $tutorial->is_published,
            'views_count' => (int) ($tutorial->views_count ?? 0),
            'created_at' => $tutorial->created_at?->toIso8601String(),
            'updated_at' => $tutorial->updated_at?->toIso8601String(),
        ];
    }
}
