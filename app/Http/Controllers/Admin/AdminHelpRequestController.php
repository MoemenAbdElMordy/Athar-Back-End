<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateHelpRequestRequest;
use App\Models\HelpRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminHelpRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = (string) $request->query('status', 'pending');

        $query = HelpRequest::query();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $helpRequests = $query->orderByDesc('id')->paginate(15);

        return response()->json($helpRequests);
    }

    public function show(int $id): JsonResponse
    {
        $helpRequest = HelpRequest::query()->with(['requester', 'volunteer', 'messages'])->find($id);

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
