<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertAccessibilityReportRequest;
use App\Models\AccessibilityReport;
use App\Models\Location;
use Illuminate\Http\JsonResponse;

class AdminAccessibilityReportController extends Controller
{
    public function upsert(UpsertAccessibilityReportRequest $request, int $id): JsonResponse
    {
        $location = Location::find($id);

        if (!$location) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $data = $request->validated();

        $report = AccessibilityReport::firstOrNew(['location_id' => $location->id]);
        $report->fill($data);
        $report->location_id = $location->id;
        $report->save();

        return response()->json($report);
    }
}
