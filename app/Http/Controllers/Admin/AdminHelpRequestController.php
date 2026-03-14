<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateHelpRequestRequest;
use App\Models\HelpRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class AdminHelpRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = (string) $request->query('status', 'pending');
        $hasReviewsTable = Schema::hasTable('volunteer_reviews');
        $hasPaymentCol = Schema::hasColumn('help_requests', 'payment_method');

        $eagerLoads = [
            'requester:id,name,full_name,email,phone',
            'volunteer:id,name,full_name,email,phone',
        ];
        if ($hasReviewsTable) {
            $eagerLoads[] = 'volunteerReview:id,help_request_id,rating,comment';
        }

        $query = HelpRequest::query()->with($eagerLoads);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($request->filled('assistance_type')) {
            $query->where('assistance_type', $request->query('assistance_type'));
        }

        if ($hasPaymentCol && $request->filled('payment_method')) {
            $query->where('payment_method', $request->query('payment_method'));
        }

        if ($request->filled('urgency_level')) {
            $query->where('urgency_level', $request->query('urgency_level'));
        }

        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('created_at', [$request->query('from'), $request->query('to') . ' 23:59:59']);
        }

        $perPage = min((int) ($request->query('per_page', 15) ?: 15), 100);
        $helpRequests = $query->orderByDesc('id')->paginate($perPage);

        return response()->json($helpRequests);
    }

    public function show(int $id): JsonResponse
    {
        $hasReviewsTable = Schema::hasTable('volunteer_reviews');

        $eagerLoads = [
            'requester:id,name,full_name,email,phone',
            'volunteer:id,name,full_name,email,phone',
            'messages',
        ];
        if ($hasReviewsTable) {
            $eagerLoads[] = 'volunteerReview';
        }

        $helpRequest = HelpRequest::query()->with($eagerLoads)->find($id);

        if (!$helpRequest) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json($helpRequest);
    }

    public function update(UpdateHelpRequestRequest $request, int $id): JsonResponse
    {
        $helpRequest = HelpRequest::query()->find($id);

        if (!$helpRequest) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $data = $request->validated();

        if (array_key_exists('status', $data)) {
            $helpRequest->status = $data['status'];
        }

        if (array_key_exists('assigned_admin_id', $data)) {
            $helpRequest->assigned_admin_id = $data['assigned_admin_id'];
        }

        $helpRequest->save();

        return response()->json($helpRequest);
    }

    public function resolve(int $id): JsonResponse
    {
        $helpRequest = HelpRequest::query()->find($id);

        if (!$helpRequest) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ($helpRequest->status !== 'resolved') {
            $helpRequest->status = 'resolved';
            $helpRequest->resolved_at = now();

            if (!$helpRequest->assigned_admin_id) {
                $admin = Auth::guard('web')->user();
                $helpRequest->assigned_admin_id = $admin?->id;
            }

            $helpRequest->save();
        }

        return response()->json(['success' => true, 'help_request' => $helpRequest]);
    }
}
