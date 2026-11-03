<?php
namespace App\Interface\Controllers;

use App\Services\ScheduleAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ScheduleAnalysisController extends Controller
{
    public function __construct(
        private ScheduleAnalysisService $analysisService,
    ) {}

    public function summary(Request $request)
    {
        return response()->json($this->analysisService->getDailySummary($request->query('month_year'), $request->query('pattern_id')));
    }

    public function timeline(Request $request)
    {
        return response()->json($this->analysisService->getStaffTimeline($request->query('month_year'), $request->query('pattern_id')));
    }

    public function violations(Request $request)
    {
        return response()->json($this->analysisService->getViolations($request->query('month_year'), $request->query('pattern_id')));
    }
}
