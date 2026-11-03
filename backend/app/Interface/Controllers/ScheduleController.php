<?php
namespace App\Interface\Controllers;

use App\Services\ScheduleGenerationService;
use App\Services\ScheduleAutoFixService;
use App\Services\ScheduleAutoFixOrchestrator;
use App\Services\ScheduleFailureExplanationService;
use App\Services\ScheduleStatsService;
use App\Services\ScheduleExportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ScheduleController extends Controller
{
    public function __construct(
        private ScheduleGenerationService $generationService,
        private ScheduleAutoFixService $autoFixService,
        private ScheduleAutoFixOrchestrator $autoFixOrchestrator,
        private ScheduleFailureExplanationService $failureExplanationService,
        private ScheduleStatsService $statsService,
        private ScheduleExportService $exportService,
    ) {}

    public function index(Request $request)
    {
        $month = $request->query('month_year') ?: $request->query('month', date('Y-m'));
        if ($request->query('month') && !$request->query('month_year')) {
            info("ScheduleController::index: using month param instead of month_year (month={$month})");
        }
        try {
            $patterns = \App\Models\SchedulePattern::with('cells')
                ->where('month_year', $month)->orderBy('pattern_number')->get();
            $patternCount = $patterns->count();
            $cellCount = $patterns->sum(fn($p) => $p->cells->count());

            // Log detailed JSON structure
            $firstPattern = $patterns->first();
            $firstPatternJson = $firstPattern ? [
                'id'             => $firstPattern->id,
                'month_year'     => $firstPattern->month_year,
                'pattern_number' => $firstPattern->pattern_number,
                'is_base'        => $firstPattern->is_base,
                'cells_count'    => $firstPattern->cells->count(),
            ] : null;
            $cellSamples = $firstPattern && $firstPattern->cells->isNotEmpty()
                ? $firstPattern->cells->take(5)->map(fn($c) => [
                    'id'            => $c->id,
                    'staff_id'      => $c->staff_id,
                    'target_date'   => $c->target_date instanceof \Carbon\Carbon ? $c->target_date->format('Y-m-d') : $c->target_date,
                    'shift_type_id' => $c->shift_type_id,
                    'is_fixed'      => $c->is_fixed,
                ])->toArray()
                : [];

            info("ScheduleController::index({$month}): {$patternCount} patterns, {$cellCount} cells");
            info("ScheduleController::index first pattern: " . json_encode($firstPatternJson));
            info("ScheduleController::index cell samples: " . json_encode($cellSamples));
            info("ScheduleController::index full JSON size: approx " . strlen(json_encode(['patterns' => $patterns->toArray()])) . " bytes");
        } catch (\Illuminate\Database\QueryException $e) {
            info("ScheduleController::index({$month}) QueryException: " . $e->getMessage());
            info("SQL: " . $e->getSql());
            info("Bindings: " . json_encode($e->getBindings()));
            throw $e;
        } catch (\RuntimeException $e) {
            info("ScheduleController::index({$month}) RuntimeException: " . $e->getMessage());
            throw $e;
        } catch (\Throwable $e) {
            info("ScheduleController::index({$month}) Throwable: " . get_class($e) . ': ' . $e->getMessage());
            throw $e;
        }
        return response()->json(['patterns' => $patterns]);
    }

    public function generate(Request $request)
    {
        $data = $request->validate([
            'month_year' => 'required|string', 'preserve_fixed' => 'boolean',
            'constraints' => 'array',
            'pattern_id' => 'nullable|integer|exists:schedule_patterns,id',
            'night_required' => 'nullable|integer|min:0|max:99',
        ]);
        return response()->json($this->generationService->doGenerate(
            $data['month_year'],
            $data['preserve_fixed'] ?? false,
            [],
            $data['constraints'] ?? [],
            $data['pattern_id'] ?? null,
            false,
            $data['night_required'] ?? null,
        ));
    }

    public function autoFix(Request $request)
    {
        $data = $request->validate([
            'month_year' => 'required|string',
            'preserve_fixed' => 'boolean',
        ]);
        return response()->json($this->autoFixOrchestrator->run(
            $data['month_year'], $data['preserve_fixed'] ?? false, []
        ));
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'cells' => 'required|array', 'month_year' => 'required|string', 'pattern_number' => 'nullable|integer',
        ]);
        $pattern = \App\Models\SchedulePattern::updateOrCreate(
            ['month_year' => $data['month_year'], 'is_base' => true],
            ['pattern_number' => $data['pattern_number'] ?? 1, 'month_year' => $data['month_year'], 'is_base' => true]
        );
        \App\Models\ScheduleCell::where('schedule_pattern_id', $pattern->id)->delete();
        foreach ($data['cells'] as $cell) {
            \App\Models\ScheduleCell::create([
                'schedule_pattern_id' => $pattern->id, 'staff_id' => $cell['staff_id'],
                'target_date' => $cell['target_date'], 'shift_type_id' => $cell['shift_type_id'] ?? null,
                'is_fixed' => $cell['is_fixed'] ?? false,
            ]);
        }
        \App\Models\SchedulePattern::where('month_year', $data['month_year'])
            ->where('id', '!=', $pattern->id)->where('is_base', false)->delete();
        return response()->json(['message' => '保存しました']);
    }

    public function updateCell(Request $request)
    {
        $data = $request->validate([
            'schedule_pattern_id' => 'required|integer|exists:schedule_patterns,id',
            'staff_id'            => 'required|integer|exists:staff,id',
            'target_date'         => 'required|date|date_format:Y-m-d',
            'shift_type_id'       => 'nullable|integer|exists:shift_types,id',
            'month_year'          => 'required|string|regex:/^\d{4}-\d{2}$/',
            'is_fixed'            => 'nullable|boolean',
        ]);

        $pattern = \App\Models\SchedulePattern::findOrFail($data['schedule_pattern_id']);
        if ($pattern->month_year !== $data['month_year']) {
            return response()->json(['message' => 'パターンと月が一致しません'], 422);
        }

        \App\Models\ScheduleCell::updateOrCreate(
            [
                'schedule_pattern_id' => $data['schedule_pattern_id'],
                'staff_id'            => $data['staff_id'],
                'target_date'         => $data['target_date'],
            ],
            [
                'shift_type_id' => $data['shift_type_id'],
                'is_fixed'      => $data['is_fixed'] ?? false,
            ]
        );

        return response()->json(['message' => '更新しました']);
    }

    public function export(Request $request)
    {
        return $this->exportService->exportCsv($request->query('pattern_id'));
    }

    public function exportPdf(Request $request)
    {
        $result = $this->exportService->exportPdf($request->query('pattern_id'));
        return $result['pdf']->download('schedule_' . $result['month_year'] . '.pdf');
    }

    public function swap(Request $request)
    {
        $data = $request->validate([
            'pattern_id'  => 'required|integer|exists:schedule_patterns,id',
            'staff_id_a'  => 'required|integer|exists:staff,id',
            'staff_id_b'  => 'required|integer|exists:staff,id',
            'date'        => 'required|date|date_format:Y-m-d',
        ]);

        $cellA = \App\Models\ScheduleCell::where('schedule_pattern_id', $data['pattern_id'])
            ->where('staff_id', $data['staff_id_a'])
            ->where('target_date', $data['date'])->first();
        $cellB = \App\Models\ScheduleCell::where('schedule_pattern_id', $data['pattern_id'])
            ->where('staff_id', $data['staff_id_b'])
            ->where('target_date', $data['date'])->first();

        if (!$cellA || !$cellB) {
            return response()->json(['message' => '交換対象のセルが見つかりません'], 404);
        }

        $tmp = $cellA->shift_type_id;
        $cellA->update(['shift_type_id' => $cellB->shift_type_id, 'is_fixed' => false]);
        $cellB->update(['shift_type_id' => $tmp, 'is_fixed' => false]);

        return response()->json(['message' => '交換しました']);
    }

    public function yearlyStats(Request $request)
    {
        return response()->json($this->statsService->getYearlyStatsData((int)$request->query('year', date('Y'))));
    }

    public function growthStats(Request $request)
    {
        $data = $this->statsService->getYearlyStatsData((int)$request->query('year', date('Y')));
        return response()->json([
            'year' => $data['year'], 'months' => $data['months'],
            'staff' => $data['staff'], 'growth' => $data['growth'],
        ]);
    }

    public function autoFixApproach(Request $request)
    {
        $data = $request->validate([
            'month_year' => 'required|string',
            'approach' => 'required|string',
            'constraints' => 'nullable|array',
            'preserve_fixed' => 'boolean',
        ]);
        return response()->json($this->autoFixService->applyApproach(
            $data['month_year'], $data['preserve_fixed'] ?? false, $data['constraints'] ?? [], $data['approach']
        ));
    }

    public function autoFixOrchestrator(Request $request)
    {
        $data = $request->validate([
            'month_year' => 'required|string',
            'constraints' => 'nullable|array',
            'preserve_fixed' => 'boolean',
        ]);
        return response()->json($this->autoFixOrchestrator->run(
            $data['month_year'], $data['preserve_fixed'] ?? false, $data['constraints'] ?? []
        ));
    }

    public function explainFailure(Request $request)
    {
        $data = $request->validate(['month_year' => 'required|string', 'constraints' => 'nullable|array']);
        return response()->json($this->failureExplanationService->explain($data['month_year'], $data['constraints'] ?? []));
    }

    public function reset(Request $request)
    {
        $data = $request->validate(['month_year' => 'required|string']);
        \App\Models\SchedulePattern::where('month_year', $data['month_year'])->delete();
        return response()->json(['message' => 'リセットしました', 'conflicts' => []]);
    }
}
