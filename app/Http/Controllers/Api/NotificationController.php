<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:all,unread,read'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Notification::query()
            ->where('user_id', $request->user()->id)
            ->latest('id');

        $status = $validated['status'] ?? 'all';

        if ($status === 'unread') {
            $query->where('is_read', false);
        }

        if ($status === 'read') {
            $query->where('is_read', true);
        }

        $notifications = $query->paginate((int) ($validated['per_page'] ?? 20))->withQueryString();

        return $this->successResponse(
            $this->paginatedData($notifications, $notifications->getCollection()->map(function (Notification $notification): array {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'body' => $notification->body,
                    'severity' => $notification->severity,
                    'is_read' => (bool) $notification->is_read,
                    'read_at' => optional($notification->read_at)->toIso8601String(),
                    'metadata' => $notification->metadata ?? (object) [],
                    'created_at' => optional($notification->created_at)->toIso8601String(),
                ];
            }))
        );
    }
}
