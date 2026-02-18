<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Flag;
use App\Models\HelpRequest;
use App\Models\Location;
use App\Models\Notification;
use App\Models\PlaceSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AdminDashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $adminId = Auth::guard('web')->id();

        return response()->json([
            'counts' => [
                'locations' => Location::query()->count(),
                'categories' => Category::query()->count(),
                'pending_place_submissions' => PlaceSubmission::query()->where('status', 'pending')->count(),
                'open_flags' => Flag::query()->where('status', 'open')->count(),
                'pending_help_requests' => HelpRequest::query()->where('status', 'pending')->count(),
                'unread_notifications' => Notification::query()->where('user_id', $adminId)->where('is_read', false)->count(),
            ],
        ]);
    }
}
