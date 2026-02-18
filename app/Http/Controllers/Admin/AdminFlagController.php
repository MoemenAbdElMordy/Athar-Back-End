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
        $query = Flag::query();

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        $flags = $query->orderByDesc('id')->paginate(15);

        return response()->json($flags);
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
