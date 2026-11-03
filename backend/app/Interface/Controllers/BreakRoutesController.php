<?php
namespace App\Interface\Controllers;

use App\Services\BreakRouteService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Break Routes API (NEW FILE — existing controllers untouched).
 * GET  /api/break-routes  : list break route candidates
 * POST /api/apply-route   : apply route steps + regenerate via existing logic
 * GET  /api/route-diff    : before/after prediction for a route
 */
class BreakRoutesController extends Controller
{
    public function __construct(
        private readonly BreakRouteService $breakRouteService,
    ) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'month' => 'required|string|date_format:Y-m',
        ]);

        return response()->json([
            'routes' => $this->breakRouteService->generateBreakRoutes($data['month']),
        ]);
    }

    public function apply(Request $request)
    {
        $data = $request->validate([
            'month' => 'required|string|date_format:Y-m',
            'routeId' => 'required|string',
        ]);

        return response()->json(
            $this->breakRouteService->applyRoute($data['month'], $data['routeId'])
        );
    }

    public function diff(Request $request)
    {
        $data = $request->validate([
            'month' => 'required|string|date_format:Y-m',
            'routeId' => 'required|string',
        ]);

        return response()->json(
            $this->breakRouteService->routeDiff($data['month'], $data['routeId'])
        );
    }

    /**
     * POST /api/auto-resolve
     * NEW: 完全2択フロー — 成立ならそのまま返し、不成立なら最適ルート1つを自動適用する。
     */
    public function autoResolve(Request $request)
    {
        $data = $request->validate([
            'month' => 'required|string|date_format:Y-m',
        ]);

        return response()->json(
            $this->breakRouteService->autoResolve($data['month'])
        );
    }
}
