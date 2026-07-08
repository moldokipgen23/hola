<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'type' => 'required|in:wrong_contact,wrong_location,duplicate,other',
            'message' => 'nullable|string|max:1000',
        ]);

        $report = Report::create([
            'user_id' => $request->user()->id,
            'business_id' => $request->business_id,
            'type' => $request->type,
            'message' => $request->message,
        ]);

        ActivityLogService::log('report_submitted', $report, ['business_id' => $request->business_id, 'type' => $request->type]);

        return response()->json([
            'report' => $report,
            'message' => 'Report submitted successfully.',
        ]);
    }
}
