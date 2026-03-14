<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Flag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminFlagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Flag::query()->with([
            'flagger:id,name,full_name,email',
            'handler:id,name,full_name,email',
            'flaggable',
        ]);

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = (string) $validated['search'];
            $query->where(function ($inner) use ($search) {
                $inner
                    ->where('reason', 'like', "%{$search}%")
                    ->orWhere('details', 'like', "%{$search}%")
                    ->orWhere('admin_note', 'like', "%{$search}%");
            });
        }

        $perPage = (int) ($validated['per_page'] ?? 15);
        $flags = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            ...$flags->toArray(),
            'summary' => [
                'open' => Flag::query()->where('status', 'open')->count(),
                'need_info' => Flag::query()->where('status', 'need_info')->count(),
                'resolved' => Flag::query()->where('status', 'resolved')->count(),
                'dismissed' => Flag::query()->where('status', 'dismissed')->count(),
            ],
        ]);
    }

    public function resolve(int $id): JsonResponse
    {
        $flag = Flag::find($id);

        if (!$flag) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ($flag->status === 'resolved') {
            return response()->json(['success' => true, 'flag' => $flag]);
        }

        $admin = Auth::guard('web')->user();

        $flag->status = 'resolved';
        $flag->handled_by_admin_id = $admin?->id;
        $flag->handled_at = now();
        $flag->save();

        return response()->json(['success' => true, 'flag' => $flag]);
    }

    public function requestInfo(Request $request, int $id): JsonResponse
    {
        $flag = Flag::find($id);

        if (!$flag) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $validated = $request->validate([
            'note' => ['nullable', 'string'],
        ]);

        $flag->status = 'need_info';
        $flag->admin_note = $validated['note'] ?? null;
        $flag->save();

        return response()->json(['success' => true, 'flag' => $flag]);
    }

    public function dismiss(Request $request, int $id): JsonResponse
    {
        $flag = Flag::find($id);

        if (!$flag) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $validated = $request->validate([
            'note' => ['nullable', 'string'],
        ]);

        $admin = Auth::guard('web')->user();

        $flag->status = 'dismissed';
        $flag->admin_note = $validated['note'] ?? null;
        $flag->handled_by_admin_id = $admin?->id;
        $flag->handled_at = now();
        $flag->save();

        return response()->json(['success' => true, 'flag' => $flag]);
    }
}
