<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApprovePlaceSubmissionRequest;
use App\Http\Requests\Admin\RejectPlaceSubmissionRequest;
use App\Models\Location;
use App\Models\PlaceSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminPlaceSubmissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PlaceSubmission::query();

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        $submissions = $query->orderByDesc('id')->paginate(15);

        return response()->json($submissions);
    }

    public function approve(ApprovePlaceSubmissionRequest $request, int $id): JsonResponse
    {
        $submission = PlaceSubmission::find($id);

        if (!$submission) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ($submission->status !== 'pending') {
            return response()->json(['message' => 'Only pending submissions can be approved.'], 422);
        }

        $admin = Auth::guard('web')->user();

        $submission->status = 'approved';
        $submission->reviewed_by_admin_id = $admin?->id;
        $submission->reviewed_at = now();
        $submission->rejection_reason = null;
        $submission->save();

        $createdLocation = null;

        $validated = $request->validated();
        $createLocation = (bool) ($validated['create_location'] ?? false);

        if ($createLocation) {
            $createdLocation = Location::create([
                'name' => $submission->name,
                'address' => $submission->address,
                'government_id' => $validated['government_id'],
                'latitude' => $submission->lat,
                'longitude' => $submission->lng,
                'category_id' => $submission->category_id,
            ]);
        }

        return response()->json([
            'success' => true,
            'submission' => $submission,
            'location' => $createdLocation,
        ]);
    }

    public function reject(RejectPlaceSubmissionRequest $request, int $id): JsonResponse
    {
        $submission = PlaceSubmission::find($id);

        if (!$submission) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ($submission->status !== 'pending') {
            return response()->json(['message' => 'Only pending submissions can be rejected.'], 422);
        }

        $admin = Auth::guard('web')->user();

        $submission->status = 'rejected';
        $submission->reviewed_by_admin_id = $admin?->id;
        $submission->reviewed_at = now();
        $submission->rejection_reason = $request->validated()['rejection_reason'];
        $submission->save();

        return response()->json([
            'success' => true,
            'submission' => $submission,
        ]);
    }
}
