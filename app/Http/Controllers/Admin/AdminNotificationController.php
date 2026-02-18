<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $adminId = Auth::guard('web')->id();
        $status = (string) $request->query('status', 'unread');

        $query = Notification::query()->where('user_id', $adminId);

        if ($status === 'unread') {
            $query->where('is_read', false);
        }

        $notifications = $query->orderByDesc('id')->paginate(15);

        return response()->json($notifications);
    }

    public function markRead(int $id): JsonResponse
    {
        $adminId = Auth::guard('web')->id();

        $notification = Notification::query()->where('user_id', $adminId)->find($id);

        if (!$notification) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if (!$notification->is_read) {
            $notification->is_read = true;
            $notification->read_at = now();
            $notification->save();
        }

        return response()->json(['success' => true, 'notification' => $notification]);
    }

    public function markAllRead(): JsonResponse
    {
        $adminId = Auth::guard('web')->id();

        Notification::query()
            ->where('user_id', $adminId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json(['success' => true]);
    }
}
