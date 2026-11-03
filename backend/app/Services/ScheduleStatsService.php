<?php
namespace App\Services;

use App\Models\Staff;
use App\Models\ShiftType;
use App\Models\SchedulePattern;
use App\Models\Preference;
use App\Models\ShiftChangeRequest;
use Illuminate\Support\Collection;

class ScheduleStatsService
{
    public function __construct(
        private ScheduleGenerationService $generationService
    ) {}

    public function getYearlyStatsData(int $year): array
    {
        $staff = Staff::where('active', true)->orderBy('sort_order')->get();
        $shiftTypes = ShiftType::orderBy('sort_order')->get();
        $isNightCache = $this->buildNightCache($shiftTypes);
        $nightIds = array_keys(array_filter($isNightCache));
        $namedTypes = $this->extractNamedTypes($shiftTypes);

        $labels = $this->buildMonthLabels();
        $metrics = $this->initializeMetrics($staff);
        $perStaffMonthly = $this->initializePerStaffMonthly($staff);
        $newcomerIds = $this->identifyNewcomers($staff, $year);

        for ($month = 1; $month <= 12; $month++) {
            $monthKey = $month - 1;
            $monthYear = sprintf('%s-%02d', $year, $month);

            $patterns = $this->loadMonthPatterns($monthYear);
            if ($patterns->isEmpty()) continue;

            $cells = $this->resolveBaseCells($patterns);
            if ($cells->isEmpty()) continue;

            $cellsByStaff = $this->indexCellsByStaff($cells);

            $analysis = $this->analyzeMonth(
                $month, $year, $staff, $shiftTypes, $isNightCache, $namedTypes,
                $cellsByStaff, $newcomerIds, $metrics, $perStaffMonthly
            );

            $this->computeMonthlyAggregates($monthKey, $staff, $analysis, $metrics, $namedTypes, $monthYear, $cellsByStaff);
        }

        return $this->buildResponse($year, $labels, $staff, $shiftTypes, $metrics, $perStaffMonthly);
    }

    private function buildNightCache(Collection $shiftTypes): array
    {
        $cache = [];
        foreach ($shiftTypes as $st) {
            $cache[$st->id] = $this->generationService->isNightShift($st);
        }
        return $cache;
    }

    private function extractNamedTypes(Collection $shiftTypes): array
    {
        return [
            'akeType' => $shiftTypes->firstWhere('name', '明け'),
            'earlyType' => $shiftTypes->firstWhere('name', '早番'),
            'lateType' => $shiftTypes->firstWhere('name', '遅番'),
            'nightType' => $shiftTypes->firstWhere('name', '夜勤'),
        ];
    }

    private function buildMonthLabels(): array
    {
        $labels = [];
        for ($m = 1; $m <= 12; $m++) {
            $labels[] = $m . '月';
        }
        return $labels;
    }

    private function initializeMetrics(Collection $staff): array
    {
        $burden = [
            'night_shift_by_month' => ['label' => '夜勤回数', 'unit' => '回', 'data' => array_fill(0, 12, 0), 'per_staff' => []],
            'night_to_ake_next_day' => ['label' => '夜勤明け翌日勤務', 'unit' => '回', 'data' => array_fill(0, 12, 0), 'per_staff' => []],
            'avg_consecutive_days' => ['label' => '平均連続勤務日数', 'unit' => '日', 'data' => array_fill(0, 12, 0), 'per_staff' => []],
            'early_late_imbalance' => ['label' => '早番-遅番の差', 'unit' => '回', 'data' => array_fill(0, 12, 0), 'per_staff' => []],
            'avg_holiday_days' => ['label' => '平均休日日数', 'unit' => '日', 'data' => array_fill(0, 12, 0), 'per_staff' => []],
        ];
        $fairness = [
            'shift_type_stddev' => ['label' => '勤務種別の偏り', 'unit' => '', 'data' => array_fill(0, 12, 0)],
            'night_stddev' => ['label' => '夜勤の公平度', 'unit' => '', 'data' => array_fill(0, 12, 0)],
            'preference_achievement_rate' => ['label' => '希望休取得率', 'unit' => '%', 'data' => array_fill(0, 12, 0)],
            'change_request_count' => ['label' => 'シフト変更依頼数', 'unit' => '件', 'data' => array_fill(0, 12, 0)],
        ];
        $safety = [
            'danger_pattern_count' => ['label' => '夜勤→明け→早番', 'unit' => '回', 'data' => array_fill(0, 12, 0), 'per_staff' => []],
            'night_consecutive_max_avg' => ['label' => '夜勤連続回数(平均)', 'unit' => '日', 'data' => array_fill(0, 12, 0), 'per_staff' => []],
            'short_interval_count' => ['label' => '勤務間隔8h未満', 'unit' => '回', 'data' => array_fill(0, 12, 0), 'per_staff' => []],
            'newcomer_night_ratio' => ['label' => '新人の夜勤比率', 'unit' => '%', 'data' => array_fill(0, 12, 0)],
        ];
        $growth = [
            'experience_score' => ['label' => '経験スコア', 'unit' => '点', 'data' => array_fill(0, 12, 0), 'per_staff' => []],
            'aptitude_score' => ['label' => '適性スコア', 'unit' => '点', 'data' => array_fill(0, 12, 0), 'per_staff' => []],
            'tolerance_score' => ['label' => '負担耐性スコア', 'unit' => '点', 'data' => array_fill(0, 12, 0), 'per_staff' => []],
            'night_aptitude' => ['label' => '夜勤適性', 'unit' => '点', 'data' => array_fill(0, 12, 0), 'per_staff' => []],
            'early_aptitude' => ['label' => '早番適性', 'unit' => '点', 'data' => array_fill(0, 12, 0), 'per_staff' => []],
            'late_aptitude' => ['label' => '遅番適性', 'unit' => '点', 'data' => array_fill(0, 12, 0), 'per_staff' => []],
            'newcomer_night_debut' => ['label' => '新人夜勤デビュー月', 'unit' => '', 'data' => []],
        ];

        foreach ($staff as $s) {
            $burden['night_shift_by_month']['per_staff'][$s->id] = array_fill(0, 12, 0);
            $burden['night_to_ake_next_day']['per_staff'][$s->id] = array_fill(0, 12, 0);
            $burden['avg_consecutive_days']['per_staff'][$s->id] = array_fill(0, 12, 0);
            $burden['early_late_imbalance']['per_staff'][$s->id] = array_fill(0, 12, 0);
            $burden['avg_holiday_days']['per_staff'][$s->id] = array_fill(0, 12, 0);
            $safety['danger_pattern_count']['per_staff'][$s->id] = array_fill(0, 12, 0);
            $safety['night_consecutive_max_avg']['per_staff'][$s->id] = array_fill(0, 12, 0);
            $safety['short_interval_count']['per_staff'][$s->id] = array_fill(0, 12, 0);
            $growth['experience_score']['per_staff'][$s->id] = array_fill(0, 12, 0);
            $growth['aptitude_score']['per_staff'][$s->id] = array_fill(0, 12, 0);
            $growth['tolerance_score']['per_staff'][$s->id] = array_fill(0, 12, 0);
            $growth['night_aptitude']['per_staff'][$s->id] = array_fill(0, 12, 0);
            $growth['early_aptitude']['per_staff'][$s->id] = array_fill(0, 12, 0);
            $growth['late_aptitude']['per_staff'][$s->id] = array_fill(0, 12, 0);
        }

        return compact('burden', 'fairness', 'safety', 'growth');
    }

    private function initializePerStaffMonthly(Collection $staff): array
    {
        $perStaffMonthly = [];
        foreach ($staff as $s) {
            $perStaffMonthly[$s->id] = [
                'name' => $s->name,
                'night' => array_fill(0, 12, 0),
                'early' => array_fill(0, 12, 0),
                'late' => array_fill(0, 12, 0),
                'day' => array_fill(0, 12, 0),
                'total' => array_fill(0, 12, 0),
                'consecutive_max' => array_fill(0, 12, 0),
            ];
        }
        return $perStaffMonthly;
    }

    private function identifyNewcomers(Collection $staff, int $year): array
    {
        $ids = [];
        foreach ($staff as $s) {
            if ($s->start_date) {
                $startYear = (int)date('Y', strtotime($s->start_date));
                if ($startYear >= $year - 1) {
                    $ids[] = $s->id;
                }
            }
        }
        return $ids;
    }

    private function loadMonthPatterns(string $monthYear): Collection
    {
        return SchedulePattern::with(['cells.shiftType', 'cells.staff'])
            ->where('month_year', $monthYear)
            ->orderBy('pattern_number')
            ->get();
    }

    private function resolveBaseCells(Collection $patterns): Collection
    {
        $basePattern = $patterns->firstWhere('is_base', true) ?? $patterns->first();
        return $basePattern ? $basePattern->cells : collect();
    }

    private function indexCellsByStaff(Collection $cells): array
    {
        $byStaff = [];
        foreach ($cells as $c) {
            $sid = $c->staff_id;
            if (!isset($byStaff[$sid])) $byStaff[$sid] = [];
            $dateStr = $c->target_date instanceof \Carbon\Carbon
                ? $c->target_date->format('Y-m-d')
                : $c->target_date;
            $byStaff[$sid][$dateStr] = $c;
        }
        return $byStaff;
    }

    private function analyzeMonth(
        int $month, int $year, Collection $staff, Collection $shiftTypes,
        array $isNightCache, array $namedTypes, array $cellsByStaff,
        array $newcomerIds, array &$metrics, array &$perStaffMonthly
    ): array {
        $monthKey = $month - 1;
        $monthYear = sprintf('%s-%02d', $year, $month);
        $daysInMonth = \Carbon\Carbon::create($year, $month)->daysInMonth;

        $totalShiftCount = 0;
        $staffShiftCount = [];
        $nightCounts = [];
        $earlyCounts = [];
        $lateCounts = [];
        $dayCounts = [];
        $staffNightCount = [];
        $consecutiveAll = [];
        $dangerPatternCount = 0;
        $shortIntervalCount = 0;
        $nightConsecutiveAll = [];

        foreach ($staff as $s) {
            $result = $this->analyzeStaffMonth(
                $s, $month, $year, $monthYear, $daysInMonth, $shiftTypes,
                $isNightCache, $namedTypes, $cellsByStaff, $newcomerIds,
                $metrics, $perStaffMonthly, $monthKey
            );

            $totalShiftCount += $result['totalCount'];
            $staffShiftCount[$s->id] = $result['totalCount'];
            $nightCounts[$s->id] = $result['nightCount'];
            $earlyCounts[$s->id] = $result['earlyCount'];
            $lateCounts[$s->id] = $result['lateCount'];
            $dayCounts[$s->id] = $result['dayCount'];
            $staffNightCount[$s->id] = $result['nightCount'];
            $consecutiveAll[] = $result['maxConsecutive'];
            $nightConsecutiveAll[] = $result['maxNightConsecutive'];
            $dangerPatternCount += $result['staffDangerCount'];
            $shortIntervalCount += $result['staffShortIntervalCount'];
        }

        return compact(
            'monthKey', 'daysInMonth', 'totalShiftCount', 'staffShiftCount',
            'nightCounts', 'earlyCounts', 'lateCounts', 'dayCounts',
            'staffNightCount', 'consecutiveAll', 'nightConsecutiveAll',
            'dangerPatternCount', 'shortIntervalCount', 'newcomerIds'
        );
    }

    private function analyzeStaffMonth(
        $staff, int $month, int $year, string $monthYear, int $daysInMonth,
        Collection $shiftTypes, array $isNightCache, array $namedTypes,
        array $cellsByStaff, array $newcomerIds, array &$metrics,
        array &$perStaffMonthly, int $monthKey
    ): array {
        $nightCount = 0;
        $earlyCount = 0;
        $lateCount = 0;
        $dayCount = 0;
        $totalCount = 0;
        $currentConsecutive = 0;
        $maxConsecutive = 0;
        $currentNightConsecutive = 0;
        $maxNightConsecutive = 0;
        $prevCell = null;
        $staffDangerCount = 0;
        $staffShortIntervalCount = 0;

        $staffCells = $cellsByStaff[$staff->id] ?? [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateStr = sprintf('%s-%02d', $monthYear, $day);
            $cell = $staffCells[$dateStr] ?? null;

            if (!$cell || !$cell->shift_type_id) {
                $currentConsecutive = 0;
                $currentNightConsecutive = 0;
                $prevCell = null;
                continue;
            }

            $stId = $cell->shift_type_id;
            $isNight = $isNightCache[$stId] ?? false;
            $totalCount++;

            if ($isNight) $nightCount++;
            if ($stId === ($namedTypes['earlyType']?->id)) $earlyCount++;
            if ($stId === ($namedTypes['lateType']?->id)) $lateCount++;
            if (!$isNight && $stId !== ($namedTypes['earlyType']?->id) && $stId !== ($namedTypes['lateType']?->id)) $dayCount++;

            $currentConsecutive++;
            if ($currentConsecutive > $maxConsecutive) $maxConsecutive = $currentConsecutive;

            if ($isNight) {
                $currentNightConsecutive++;
                if ($currentNightConsecutive > $maxNightConsecutive) $maxNightConsecutive = $currentNightConsecutive;
            } else {
                $currentNightConsecutive = 0;
            }

            if ($namedTypes['nightType'] && $isNight) {
                $nextDateStr = sprintf('%s-%02d', $monthYear, $day + 1);
                $nextCell = $staffCells[$nextDateStr] ?? null;
                if ($namedTypes['akeType'] && $nextCell && $nextCell->shift_type_id === $namedTypes['akeType']->id) {
                    $nextNextDateStr = sprintf('%s-%02d', $monthYear, $day + 2);
                    $nextNextCell = $staffCells[$nextNextDateStr] ?? null;
                    if ($nextNextCell && $nextNextCell->shift_type_id === ($namedTypes['earlyType']->id ?? null)) {
                        $staffDangerCount++;
                        $metrics['safety']['danger_pattern_count']['per_staff'][$staff->id][$monthKey]++;
                    }
                }
            }

            if ($prevCell && $this->isShortInterval($prevCell, $cell, $shiftTypes)) {
                $staffShortIntervalCount++;
                $metrics['safety']['short_interval_count']['per_staff'][$staff->id][$monthKey]++;
            }

            $prevCell = $cell;
        }

        $nightAkeNext = $this->computeNightAkeNext($staff->id, $monthYear, $daysInMonth, $staffCells, $namedTypes);

        $metrics['burden']['avg_consecutive_days']['per_staff'][$staff->id][$monthKey] = $maxConsecutive;
        $metrics['burden']['early_late_imbalance']['per_staff'][$staff->id][$monthKey] = abs($earlyCount - $lateCount);
        $metrics['burden']['avg_holiday_days']['per_staff'][$staff->id][$monthKey] = $daysInMonth - $totalCount;
        $metrics['safety']['night_consecutive_max_avg']['per_staff'][$staff->id][$monthKey] = $maxNightConsecutive;
        $metrics['burden']['night_shift_by_month']['per_staff'][$staff->id][$monthKey] = $nightCount;
        $metrics['burden']['night_to_ake_next_day']['per_staff'][$staff->id][$monthKey] = $nightAkeNext;

        $perStaffMonthly[$staff->id]['night'][$monthKey] = $nightCount;
        $perStaffMonthly[$staff->id]['early'][$monthKey] = $earlyCount;
        $perStaffMonthly[$staff->id]['late'][$monthKey] = $lateCount;
        $perStaffMonthly[$staff->id]['day'][$monthKey] = $dayCount;
        $perStaffMonthly[$staff->id]['total'][$monthKey] = $totalCount;
        $perStaffMonthly[$staff->id]['consecutive_max'][$monthKey] = $maxConsecutive;

        $this->computeNewcomerDebut($staff, $month, $year, $month, $nightCount, $metrics);

        $metrics['burden']['off_days_per_staff'] = $metrics['burden']['off_days_per_staff'] ?? [];
        $metrics['burden']['off_days_per_staff'][$staff->id] = $metrics['burden']['off_days_per_staff'][$staff->id] ?? array_fill(0, 12, 0);
        $metrics['burden']['off_days_per_staff'][$staff->id][$monthKey] = $daysInMonth - $totalCount;

        $this->computeGrowthScores($staff, $monthKey, $totalCount, $nightCount, $earlyCount, $lateCount, $dayCount, $daysInMonth, $maxConsecutive, $metrics);

        return compact(
            'nightCount', 'earlyCount', 'lateCount', 'dayCount', 'totalCount',
            'maxConsecutive', 'maxNightConsecutive', 'staffDangerCount', 'staffShortIntervalCount'
        );
    }

    private function computeNightAkeNext(int $staffId, string $monthYear, int $daysInMonth, array $staffCells, array $namedTypes): int
    {
        $nightAkeNext = 0;
        if (!$namedTypes['nightType'] || !$namedTypes['akeType']) return 0;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dateStr = sprintf('%s-%02d', $monthYear, $day);
            $cell = $staffCells[$dateStr] ?? null;
            if ($cell && $cell->shift_type_id === $namedTypes['nightType']->id) {
                $nextDateStr = sprintf('%s-%02d', $monthYear, $day + 1);
                $nextCell = $staffCells[$nextDateStr] ?? null;
                if ($nextCell && $nextCell->shift_type_id === $namedTypes['akeType']->id) {
                    $nextNextDateStr = sprintf('%s-%02d', $monthYear, $day + 2);
                    $nextNextCell = $staffCells[$nextNextDateStr] ?? null;
                    if ($nextNextCell && $nextNextCell->shift_type_id) {
                        $nightAkeNext++;
                    }
                }
            }
        }
        return $nightAkeNext;
    }

    private function computeNewcomerDebut($staff, int $month, int $year, int $monthNum, int $nightCount, array &$metrics): void
    {
        if (!$staff->start_date) return;

        $startDate = $staff->start_date instanceof \Carbon\Carbon
            ? $staff->start_date->format('Y-m-d')
            : $staff->start_date;
        $startMonth = (int)date('m', strtotime($startDate));
        $startY = (int)date('Y', strtotime($startDate));

        if ($startY === $year && $startMonth === $monthNum && $nightCount > 0) {
            $metrics['growth']['newcomer_night_debut']['data'][] = [
                'name' => $staff->name,
                'month' => $monthNum . '月',
                'night_count' => $nightCount,
            ];
        }
    }

    private function computeGrowthScores(
        $staff, int $monthKey, int $totalCount, int $nightCount,
        int $earlyCount, int $lateCount, int $dayCount, int $daysInMonth,
        int $maxConsecutive, array &$metrics
    ): void {
        $tc = max(1, $totalCount);
        $nr = $nightCount / $tc;
        $er = $earlyCount / $tc;
        $lr = $lateCount / $tc;
        $dr = $dayCount / $tc;

        $metrics['growth']['experience_score']['per_staff'][$staff->id][$monthKey] = round(min(100, ($nightCount * 3 + $earlyCount * 1.5 + $lateCount * 1.5 + $dayCount * 1) / $daysInMonth * 100), 1);

        $mean = 0.25;
        $var = (pow($nr - $mean, 2) + pow($er - $mean, 2) + pow($lr - $mean, 2) + pow($dr - $mean, 2)) / 4;
        $metrics['growth']['aptitude_score']['per_staff'][$staff->id][$monthKey] = round(max(0, 100 - sqrt($var) * 150), 1);

        $dangerCnt = $metrics['safety']['danger_pattern_count']['per_staff'][$staff->id][$monthKey] ?? 0;
        $shortCnt = $metrics['safety']['short_interval_count']['per_staff'][$staff->id][$monthKey] ?? 0;
        $consP = max(0, ($maxConsecutive - 5) / 10);
        $metrics['growth']['tolerance_score']['per_staff'][$staff->id][$monthKey] = round(max(0, 100 - ($dangerCnt / $tc * 40 + $shortCnt / $tc * 30 + $consP * 30)), 1);

        $metrics['growth']['night_aptitude']['per_staff'][$staff->id][$monthKey] = round(min(100, $nr * 150), 1);
        $metrics['growth']['early_aptitude']['per_staff'][$staff->id][$monthKey] = round(min(100, $er * 150), 1);
        $metrics['growth']['late_aptitude']['per_staff'][$staff->id][$monthKey] = round(min(100, $lr * 150), 1);
    }

    private function computeMonthlyAggregates(
        int $monthKey, Collection $staff, array $analysis, array &$metrics,
        array $namedTypes, string $monthYear, array $cellsByStaff
    ): void {
        $staffCount = count($staff);

        $metrics['burden']['night_shift_by_month']['data'][$monthKey] = $staffCount > 0
            ? round(array_sum($analysis['nightCounts']) / $staffCount, 1) : 0;
        $metrics['burden']['avg_consecutive_days']['data'][$monthKey] = count($analysis['consecutiveAll']) > 0
            ? round(array_sum($analysis['consecutiveAll']) / count($analysis['consecutiveAll']), 1) : 0;
        $metrics['burden']['early_late_imbalance']['data'][$monthKey] = $staffCount > 0
            ? round(abs(array_sum($analysis['earlyCounts']) - array_sum($analysis['lateCounts'])) / $staffCount, 1) : 0;
        $metrics['burden']['avg_holiday_days']['data'][$monthKey] = $staffCount > 0
            ? round(($staffCount * $analysis['daysInMonth'] - $analysis['totalShiftCount']) / $staffCount, 1) : 0;

        $totalNightAke = 0;
        foreach ($staff as $s) {
            $totalNightAke += $metrics['burden']['night_to_ake_next_day']['per_staff'][$s->id][$monthKey] ?? 0;
        }
        $metrics['burden']['night_to_ake_next_day']['data'][$monthKey] = $totalNightAke;

        $this->computeFairnessMetrics($monthKey, $staff, $analysis, $metrics, $namedTypes, $monthYear, $cellsByStaff);
        $this->computeSafetyAggregates($monthKey, $staff, $analysis, $metrics);
        $this->computeGrowthAggregates($monthKey, $staff, $metrics);
    }

    private function computeFairnessMetrics(
        int $monthKey, Collection $staff, array $analysis, array &$metrics,
        array $namedTypes, string $monthYear, array $cellsByStaff
    ): void {
        $staffCount = count($staff);

        if ($staffCount > 1) {
            $nightMean = array_sum($analysis['nightCounts']) / $staffCount;
            $nightVariance = 0;
            foreach ($analysis['nightCounts'] as $nc) {
                $nightVariance += pow($nc - $nightMean, 2);
            }
            $metrics['fairness']['night_stddev']['data'][$monthKey] = round(sqrt($nightVariance / $staffCount), 1);

            $shiftTypeStddevs = [];
            $shiftTypes = \App\Models\ShiftType::orderBy('sort_order')->get();
            foreach ($shiftTypes as $st) {
                $counts = [];
                foreach ($staff as $s2) {
                    $c = 0;
                    $sc = $cellsByStaff[$s2->id] ?? [];
                    foreach ($sc as $cell) {
                        if ($cell->shift_type_id === $st->id) $c++;
                    }
                    $counts[] = $c;
                }
                $mean = array_sum($counts) / $staffCount;
                $var = 0;
                foreach ($counts as $c) {
                    $var += pow($c - $mean, 2);
                }
                $shiftTypeStddevs[] = sqrt($var / $staffCount);
            }
            $metrics['fairness']['shift_type_stddev']['data'][$monthKey] = round(array_sum($shiftTypeStddevs), 1);
        } else {
            $metrics['fairness']['night_stddev']['data'][$monthKey] = 0;
            $metrics['fairness']['shift_type_stddev']['data'][$monthKey] = 0;
        }

        $prefOffTotal = 0;
        $prefOffGrantedTotal = 0;
        $preferences = Preference::whereIn('staff_id', $staff->pluck('id'))
            ->where('target_date', 'like', $monthYear . '-%')
            ->where('type', 'off')
            ->get()
            ->groupBy('staff_id');

        foreach ($preferences as $sid => $prefs) {
            foreach ($prefs as $p) {
                $prefOffTotal++;
                $dateStr = $p->target_date instanceof \Carbon\Carbon
                    ? $p->target_date->format('Y-m-d')
                    : $p->target_date;
                $sc = $cellsByStaff[$sid] ?? [];
                $assigned = false;
                foreach ($sc as $cell) {
                    $cds = $cell->target_date instanceof \Carbon\Carbon
                        ? $cell->target_date->format('Y-m-d')
                        : $cell->target_date;
                    if ($cds === $dateStr && $cell->shift_type_id) {
                        $assigned = true;
                        break;
                    }
                }
                if (!$assigned) $prefOffGrantedTotal++;
            }
        }
        $metrics['fairness']['preference_achievement_rate']['data'][$monthKey] = $prefOffTotal > 0
            ? round($prefOffGrantedTotal / $prefOffTotal * 100, 1) : 0;

        $metrics['fairness']['change_request_count']['data'][$monthKey] = ShiftChangeRequest::whereYear('request_date', substr($monthYear, 0, 4))
            ->whereMonth('request_date', $monthKey + 1)
            ->whereIn('requester_staff_id', $staff->pluck('id'))
            ->count();
    }

    private function computeSafetyAggregates(int $monthKey, Collection $staff, array $analysis, array &$metrics): void
    {
        $metrics['safety']['danger_pattern_count']['data'][$monthKey] = $analysis['dangerPatternCount'];
        $metrics['safety']['night_consecutive_max_avg']['data'][$monthKey] = count($analysis['nightConsecutiveAll']) > 0
            ? round(array_sum($analysis['nightConsecutiveAll']) / count($analysis['nightConsecutiveAll']), 1) : 0;
        $metrics['safety']['short_interval_count']['data'][$monthKey] = $analysis['shortIntervalCount'];

        $newcomerNightCount = 0;
        $newcomerTotalCount = 0;
        foreach ($staff as $s) {
            if (in_array($s->id, $analysis['newcomerIds'] ?? [])) {
                $newcomerTotalCount++;
                if (($analysis['nightCounts'][$s->id] ?? 0) > 0) $newcomerNightCount++;
            }
        }
        $metrics['safety']['newcomer_night_ratio']['data'][$monthKey] = $newcomerTotalCount > 0
            ? round($newcomerNightCount / $newcomerTotalCount * 100, 1) : 0;
    }

    private function computeGrowthAggregates(int $monthKey, Collection $staff, array &$metrics): void
    {
        $expSum = 0; $aptSum = 0; $tolSum = 0;
        $nightAptSum = 0; $earlyAptSum = 0; $lateAptSum = 0;

        foreach ($staff as $s) {
            $expSum += $metrics['growth']['experience_score']['per_staff'][$s->id][$monthKey] ?? 0;
            $aptSum += $metrics['growth']['aptitude_score']['per_staff'][$s->id][$monthKey] ?? 0;
            $tolSum += $metrics['growth']['tolerance_score']['per_staff'][$s->id][$monthKey] ?? 0;
            $nightAptSum += $metrics['growth']['night_aptitude']['per_staff'][$s->id][$monthKey] ?? 0;
            $earlyAptSum += $metrics['growth']['early_aptitude']['per_staff'][$s->id][$monthKey] ?? 0;
            $lateAptSum += $metrics['growth']['late_aptitude']['per_staff'][$s->id][$monthKey] ?? 0;
        }

        $sc = count($staff);
        $metrics['growth']['experience_score']['data'][$monthKey] = $sc > 0 ? round($expSum / $sc, 1) : 0;
        $metrics['growth']['aptitude_score']['data'][$monthKey] = $sc > 0 ? round($aptSum / $sc, 1) : 0;
        $metrics['growth']['tolerance_score']['data'][$monthKey] = $sc > 0 ? round($tolSum / $sc, 1) : 0;
        $metrics['growth']['night_aptitude']['data'][$monthKey] = $sc > 0 ? round($nightAptSum / $sc, 1) : 0;
        $metrics['growth']['early_aptitude']['data'][$monthKey] = $sc > 0 ? round($earlyAptSum / $sc, 1) : 0;
        $metrics['growth']['late_aptitude']['data'][$monthKey] = $sc > 0 ? round($lateAptSum / $sc, 1) : 0;
    }

    private function buildResponse(int $year, array $labels, Collection $staff, Collection $shiftTypes, array $metrics, array $perStaffMonthly): array
    {
        $perStaffMonthlyJson = [];
        foreach ($perStaffMonthly as $sid => $data) {
            $perStaffMonthlyJson[] = [
                'staff_id' => $sid,
                'name' => $data['name'],
                'monthly' => $data,
            ];
        }

        unset($metrics['burden']['off_days_per_staff']);

        return [
            'year' => $year,
            'months' => $labels,
            'staff' => $staff->map(fn($s) => ['id' => $s->id, 'name' => $s->name]),
            'shift_types' => $shiftTypes,
            'burden' => $metrics['burden'],
            'fairness' => $metrics['fairness'],
            'safety' => $metrics['safety'],
            'growth' => $metrics['growth'],
            'per_staff_monthly' => $perStaffMonthlyJson,
        ];
    }

    private function isShortInterval($prevCell, $currCell, Collection $shiftTypes): bool
    {
        if (!$prevCell || !$prevCell->shift_type_id || !$currCell || !$currCell->shift_type_id) return false;
        $prevSt = $shiftTypes->firstWhere('id', $prevCell->shift_type_id);
        $currSt = $shiftTypes->firstWhere('id', $currCell->shift_type_id);
        if (!$prevSt || !$currSt) return false;
        if (!$prevSt->end_time || !$currSt->start_time) return false;

        $prevDate = $prevCell->target_date instanceof \Carbon\Carbon
            ? $prevCell->target_date->format('Y-m-d')
            : $prevCell->target_date;

        $prevEnd = strtotime($prevDate . ' ' . $prevSt->end_time);
        $prevStart = strtotime($prevDate . ' ' . $prevSt->start_time);

        if ($prevEnd < $prevStart) {
            $prevEnd = strtotime($prevDate . ' ' . $prevSt->end_time . ' +1 day');
        }

        $currDate = $currCell->target_date instanceof \Carbon\Carbon
            ? $currCell->target_date->format('Y-m-d')
            : $currCell->target_date;

        $currStart = strtotime($currDate . ' ' . $currSt->start_time);

        $interval = abs($currStart - $prevEnd) / 3600;
        return $interval < 8;
    }
}
