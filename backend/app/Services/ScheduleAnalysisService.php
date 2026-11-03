<?php
namespace App\Services;

use App\Models\ScheduleCell;
use App\Models\Staff;
use App\Models\ShiftType;
use App\Models\PublicHolidaySetting;
use App\Models\SchedulePattern;
use Illuminate\Support\Collection;

class ScheduleAnalysisService
{
    public function __construct(
        private ScheduleGenerationService $generationService,
    ) {}

    /**
     * 月間のシフト集計（日勤・早番・遅番・夜勤・明け・休）
     */
    public function getDailySummary(string $month): array
    {
        $pattern = SchedulePattern::where('month_year', $month)->first();
        if (!$pattern) {
            return [];
        }

        $cells = ScheduleCell::where('schedule_pattern_id', $pattern->id)
            ->with('shiftType')
            ->get();

        $staff = Staff::where('active', true)->get();

        [$year, $mon] = explode('-', $month);
        $daysInMonth = \Carbon\Carbon::create((int)$year, (int)$mon)->daysInMonth;

        $result = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%s-%02d', $month, $day);
            $dayCells = $cells->where('target_date', $date);

            $summary = [
                'date' => $date,
                'day_shift' => 0,
                'early_shift' => 0,
                'late_shift' => 0,
                'night_shift' => 0,
                'after_night' => 0,
                'off' => 0,
            ];

            $assignedIds = [];

            foreach ($dayCells as $cell) {
                $assignedIds[] = $cell->staff_id;

                if ($cell->shift_type_id === null || !$cell->shiftType) {
                    $summary['off']++;
                } else {
                    switch ($cell->shiftType->name) {
                        case '日勤': $summary['day_shift']++; break;
                        case '早番': $summary['early_shift']++; break;
                        case '遅番': $summary['late_shift']++; break;
                        case '夜勤': $summary['night_shift']++; break;
                        case '明け': $summary['after_night']++; break;
                        default: $summary['off']++; break;
                    }
                }
            }

            // 未割り当てスタッフは休扱い
            $assignedSet = array_flip($assignedIds);
            foreach ($staff as $s) {
                if (!isset($assignedSet[$s->id])) {
                    $summary['off']++;
                }
            }

            $result[] = $summary;
        }

        return $result;
    }

    /**
     * スタッフごとの月間タイムライン
     */
    public function getStaffTimeline(string $month): array
    {
        $pattern = SchedulePattern::where('month_year', $month)->first();
        if (!$pattern) {
            return [];
        }

        $cells = ScheduleCell::where('schedule_pattern_id', $pattern->id)->get();
        $staff = Staff::where('active', true)->orderBy('sort_order')->get();
        $shiftTypes = ShiftType::all()->keyBy('id');

        [$year, $mon] = explode('-', $month);
        $daysInMonth = \Carbon\Carbon::create((int)$year, (int)$mon)->daysInMonth;

        $result = [];

        foreach ($staff as $s) {
            $timeline = [];
            $staffCells = $cells->where('staff_id', $s->id)->keyBy('target_date');

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = sprintf('%s-%02d', $month, $day);
                $cell = $staffCells->get($date);

                $shiftTypeName = '休';

                if ($cell && $cell->shift_type_id !== null) {
                    $st = $shiftTypes->get($cell->shift_type_id);
                    if ($st) {
                        $shiftTypeName = $st->name;
                    }
                }

                $timeline[] = [
                    'date' => $date,
                    'shift_type' => $shiftTypeName,
                ];
            }

            $result[] = [
                'staff_id' => $s->id,
                'name' => $s->name,
                'timeline' => $timeline,
            ];
        }

        return $result;
    }

    /**
     * 月間の勤務違反検出
     */
    public function getViolations(string $month): array
    {
        $pattern = SchedulePattern::where('month_year', $month)->first();
        if (!$pattern) {
            return [];
        }

        $cells = ScheduleCell::where('schedule_pattern_id', $pattern->id)
            ->orderBy('staff_id')
            ->orderBy('target_date')
            ->get();

        $staff = Staff::where('active', true)->orderBy('sort_order')->get();
        $shiftTypes = ShiftType::all()->keyBy('id');

        $publicHolidaySetting = PublicHolidaySetting::where('month_year', $month)->first();
        $holidayCount = $publicHolidaySetting ? (int) $publicHolidaySetting->count : 9;

        [$year, $mon] = explode('-', $month);
        $daysInMonth = \Carbon\Carbon::create((int)$year, (int)$mon)->daysInMonth;

        $violations = [];

        foreach ($staff as $s) {
            if (!$this->isStaffActiveForMonth($s, $month, $daysInMonth)) continue;

            $staffCells = $cells->where('staff_id', $s->id)->keyBy('target_date');
            $consecutiveDays = 0;
            $offDays = 0;

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = sprintf('%s-%02d', $month, $day);
                $cell = $staffCells->get($date);

                if (!$this->generationService->isStaffActiveOnDate($s, $date)) {
                    $consecutiveDays = 0;
                    continue;
                }

                if (!$cell || $cell->shift_type_id === null) {
                    $offDays++;
                    $consecutiveDays = 0;
                    continue;
                }

                $st = $shiftTypes->get($cell->shift_type_id);
                if (!$st) {
                    $consecutiveDays = 0;
                    continue;
                }

                $prevDate = \Carbon\Carbon::parse($date)->subDay()->format('Y-m-d');
                $prevCell = $staffCells->get($prevDate);

                if ($prevCell && $prevCell->shift_type_id !== null) {
                    $prevSt = $shiftTypes->get($prevCell->shift_type_id);

                    if ($prevSt) {
                        if ($this->generationService->isNightShift($prevSt)
                            && !$this->generationService->isAkeShift($st)
                            && !$this->generationService->isNightShift($st)) {

                            $violations[] = [
                                'staff_id' => $s->id,
                                'date' => $date,
                                'violation_type' => 'night_to_day',
                            ];
                        }

                        if ($this->generationService->isAkeShift($prevSt)) {
                            $violations[] = [
                                'staff_id' => $s->id,
                                'date' => $date,
                                'violation_type' => 'ake_to_work',
                            ];
                        }

                        if ($this->isShortInterval($prevCell, $cell, $shiftTypes)) {
                            $violations[] = [
                                'staff_id' => $s->id,
                                'date' => $date,
                                'violation_type' => 'short_interval',
                            ];
                        }
                    }
                }

                $consecutiveDays++;
                if ($consecutiveDays > 5) {
                    $violations[] = [
                        'staff_id' => $s->id,
                        'date' => $date,
                        'violation_type' => 'max_consecutive_exceeded',
                    ];
                }
            }

            if ($offDays < $holidayCount) {
                $violations[] = [
                    'staff_id' => $s->id,
                    'date' => sprintf('%s-%02d', $month, $daysInMonth),
                    'violation_type' => 'insufficient_holiday',
                ];
            }
        }

        return $violations;
    }

    private function isStaffActiveForMonth($staff, string $month, int $daysInMonth): bool
    {
        $firstDate = sprintf('%s-01', $month);
        $lastDate = sprintf('%s-%02d', $month, $daysInMonth);

        if ($staff->start_date && $staff->start_date > $lastDate) return false;
        if ($staff->end_date && $staff->end_date < $firstDate) return false;

        return true;
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
