<?php
namespace App\Services;

use App\Models\SchedulePattern;
use App\Models\ScheduleCell;
use App\Models\ShiftType;
use App\Models\PublicHolidaySetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class ScheduleGenerationService
{
    public function __construct(
        private readonly ConstraintChecker $constraintChecker,
        private readonly MasterDataLoader $masterDataLoader,
        private readonly ConflictRepairer $conflictRepairer,
        private readonly OffDayDistributor $offDayDistributor,
        private readonly PatternRotator $patternRotator,
        private readonly ShiftAssigner $shiftAssigner,
    ) {}

    public function doGenerate(string $month, bool $preserveFixed = false, array $ignoredPreferenceIds = [], array $constraints = [], ?int $patternId = null, bool $skipRequirementCheck = false, ?int $nightRequired = null): array
    {
        $basePattern = $this->masterDataLoader->resolveBasePattern($month, $patternId);

        $fixedCells = $this->masterDataLoader->loadFixedCells($basePattern, $preserveFixed);

        try {
            $masterData = $this->masterDataLoader->loadMasterData($month, $ignoredPreferenceIds);
        } catch (\InvalidArgumentException $e) {
            return ['candidates' => [], 'patterns' => collect(), 'conflicts' => [['message' => $e->getMessage()]]];
        }

        $this->masterDataLoader->initializeTracking($masterData['staff'], $consecutiveCache, $cacheUntil);

        try {
            $result = DB::transaction(function () use ($month, $preserveFixed, $basePattern, $fixedCells, $masterData, $constraints, &$consecutiveCache, &$cacheUntil, $skipRequirementCheck, $nightRequired) {
                $this->clearExistingCells($basePattern, $preserveFixed);

                $holidaySetting = PublicHolidaySetting::where('month_year', $month)->first();
                $holidayCount = $holidaySetting ? (int) $holidaySetting->count : 9;

                $daysOff = [];
                if ($holidayCount > 0) {
                    foreach ($masterData['staff'] as $s) {
                        $daysOff[$s->id] = $holidayCount;
                    }
                }

                [$year, $mon] = explode('-', $month);
                $daysInMonth = \Carbon\Carbon::create((int)$year, (int)$mon)->daysInMonth;

                $cells = [];
                $conflicts = [];
                $nightShiftCounts = [];
                $typeAssignmentCounts = [];
                $nightBlock = [];
                $nextNightBlock = [];
                $nextLateBlock = [];
                $dayBlock = [];
                $nextDayBlock = [];
                $assignedToday = [];

                $fixedShiftCounts = [];
                foreach ($fixedCells as $fc) {
                    $d = $fc->target_date instanceof \Carbon\Carbon
                        ? $fc->target_date->format('Y-m-d')
                        : $fc->target_date;
                    $sid = $fc->shift_type_id;
                    if ($sid === null) continue;
                    $fixedShiftCounts[$d][$sid] = ($fixedShiftCounts[$d][$sid] ?? 0) + 1;
                }

                // Build fixed_shift map from preferences
                $fixedShiftMap = [];
                foreach ($masterData['preferences'] as $key => $prefs) {
                    $fixedShiftTypeId = $this->constraintChecker->hasFixedShift($prefs);
                    if ($fixedShiftTypeId !== null) {
                        $parts = explode('-', $key, 2);
                        $sid = (int)$parts[0];
                        $d = $parts[1];
                        $fixedShiftMap[$sid][$d] = $fixedShiftTypeId;
                    }
                }

                // Phase 1: Pre-allocate holidays before shift assignment
                $holidayDays = $this->allocateHolidays(
                    $month, $daysInMonth, $masterData, $holidayCount,
                    $basePattern, $fixedCells, $fixedShiftMap, $fixedShiftCounts, $cells, $nightRequired
                );

                // Phase 1.5: Reconcile holidays with off-preferences
                $this->reconcileHolidays(
                    $month, $daysInMonth, $masterData, $holidayCount,
                    $basePattern, $fixedShiftMap, $fixedCells, $fixedShiftCounts,
                    $holidayDays, $cells, $nightRequired
                );

                // All holidays are pre-allocated — zero remaining budget per staff
                foreach ($masterData['staff'] as $s) {
                    $daysOff[$s->id] = 0;
                }

                // Build null-cell lookup from cells (authoritative source for off-day detection)
                $nullCellLookup = [];
                foreach ($cells as $c) {
                    if ($c['shift_type_id'] === null) {
                        $nullCellLookup[$c['staff_id']][$c['target_date']] = $c['is_holiday'] ?? false;
                    }
                }

                // Combined check for any off-day (holiday or non-working)
                $isOffDay = function (int $sid, string $date) use ($nullCellLookup): bool {
                    return isset($nullCellLookup[$sid][$date]);
                };

                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = sprintf('%s-%02d', $month, $day);
                    $nightBlock = $nextNightBlock;
                    $nextNightBlock = [];
                    $lateBlock = $nextLateBlock;
                    $nextLateBlock = [];
                    $dayBlock = $nextDayBlock;
                    $nextDayBlock = [];

                    // Compute yesterday's 明け staff to exclude from consecutive count
                    $akeStaffYesterday = [];
                    if ($day > 1) {
                        $yesterdayDate = sprintf('%s-%02d', $month, $day - 1);
                        $akeShiftTypeId = $masterData['shiftTypes']->firstWhere('name', '明け')?->id;
                        if ($akeShiftTypeId) {
                            foreach ($cells as $c) {
                                if ($c['target_date'] === $yesterdayDate && $c['shift_type_id'] === $akeShiftTypeId) {
                                    $akeStaffYesterday[$c['staff_id']] = true;
                                }
                            }
                        }
                    }
                    $this->updateConsecutiveCache($consecutiveCache, $cacheUntil, $assignedToday, $date, $akeStaffYesterday, $nullCellLookup);

                    $assignedToday = $this->collectFixedToday($fixedCells, $date);

                    // Build fixed_shift today map and add to assignedToday to prevent double assignment
                    $fixedShiftToday = [];
                    foreach ($fixedShiftMap as $sid => $dates) {
                        if (isset($dates[$date])) {
                            $fixedShiftToday[$sid] = $dates[$date];
                            $assignedToday[$sid] = true;
                        }
                    }

                    // Mark off-day staff as assigned (from cell-based lookup)
                    foreach ($nullCellLookup as $sid => $dates) {
                        if (isset($dates[$date])) {
                            $assignedToday[$sid] = true;
                        }
                    }

                    $shiftAssignments = [];
                    $dayStaff = $masterData['staff']
                        ->filter(fn($s) => $this->constraintChecker->isStaffActiveOnDate($s, $date))
                        ->filter(fn($s) => !$isOffDay($s->id, $date))
                        ->shuffle();

                    if (!$skipRequirementCheck) {
                        $dailyNeededTotal = 0;
                        foreach ($masterData['shiftTypes'] as $st) {
                            if ($st->name === '明け') continue;
                            $needed = $this->calculateNeeded($st, $date, $masterData['requirements'], $masterData['dailyOverrides'], $nightRequired);
                            $filledByFixed = $fixedShiftCounts[$date][$st->id] ?? 0;
                            $dailyNeededTotal += max(0, $needed - $filledByFixed);
                        }
                        $availableStaffCount = $dayStaff->count();
                        info("day loop {$date}: dayStaff={$availableStaffCount}, neededTotal={$dailyNeededTotal}");
                        if ($dailyNeededTotal > $availableStaffCount) {
                            throw new \RuntimeException(
                                "{$date}の必要人数({$dailyNeededTotal}名)が出勤可能スタッフ数({$availableStaffCount}名)を超えています。\n必要人数を減らすか、スタッフを追加してください。"
                            );
                        }
                    }

                    foreach ($masterData['shiftTypes'] as $st) {
                        if ($st->name === '明け') continue;
                        $needed = $this->calculateNeeded($st, $date, $masterData['requirements'], $masterData['dailyOverrides'], $nightRequired);
                        $filledByFixed = $fixedShiftCounts[$date][$st->id] ?? 0;
                        $needed = max(0, $needed - $filledByFixed);

                        $masterData['shiftAssignments'] = $shiftAssignments;
                        $candidates = $this->shiftAssigner->buildCandidatesForShift(
                            $st, $dayStaff, $date, $assignedToday,
                            $masterData, $constraints, $nightShiftCounts,
                            $nightBlock, $lateBlock, $dayBlock, $consecutiveCache, $cacheUntil,
                            $fixedShiftToday, $typeAssignmentCounts
                        );

                        $this->shiftAssigner->sortCandidates($candidates, $constraints);

                        $assigned = $this->shiftAssigner->assignCandidates(
                            $candidates, $st, $needed, $date, $basePattern,
                            $masterData, $constraints, $nightShiftCounts,
                            $nextNightBlock, $nextLateBlock, $nextDayBlock, $assignedToday, $shiftAssignments,
                            $cells
                        );

                        if ($assigned < $needed) {
                            $conflicts[] = $this->conflictRepairer->buildConflict(
                                $date, $st, $needed, $assigned,
                                $masterData['staff'], $masterData['preferences'], $masterData['ignoredPrefs']
                            );
                        }

                    }

                    $this->shiftAssigner->assignAkeShifts($nightBlock, $constraints, $masterData['shiftTypes'], $date, $assignedToday, $cells, $basePattern);
                    // Track per-type assignment counts
                    foreach ($shiftAssignments[$date] ?? [] as $typSid => $typStid) {
                        if (!isset($typeAssignmentCounts[$typSid][$typStid])) $typeAssignmentCounts[$typSid][$typStid] = 0;
                        $typeAssignmentCounts[$typSid][$typStid]++;
                    }
                }

                $this->offDayDistributor->distributeRemainingOffDays($cells, $masterData, $daysOff, $month, $daysInMonth, $basePattern, $fixedCells, $holidayCount, $constraints);

                if (!empty($conflicts)) {
                    $this->conflictRepairer->repairConflicts($cells, $conflicts, $masterData, $constraints, $basePattern, $month, $daysInMonth, $fixedCells, $nullCellLookup);
                }

                $this->conflictRepairer->breakLongOffStreaks($cells, $masterData, $constraints, $month, $daysInMonth, $fixedCells, $nullCellLookup);

                // Strip in-memory-only is_holiday flag before DB insert
                array_walk($cells, function (&$c) { unset($c['is_holiday']); });
                // Deduplicate by (staff_id, target_date) — last entry wins
                $deduped = [];
                foreach ($cells as $c) {
                    $key = $c['staff_id'] . '-' . $c['target_date'];
                    $deduped[$key] = $c;
                }
                $cells = array_values($deduped);
                // Drop regenerated cells that collide with kept fixed/manual cells in DB.
                // (preserveFixed=true keeps is_fixed rows; the kept rows win.)
                if ($preserveFixed && !empty($cells)) {
                    $keptKeys = ScheduleCell::where('schedule_pattern_id', $basePattern->id)
                        ->select('staff_id', 'target_date')
                        ->get()
                        ->map(fn($r) => $r->staff_id . '-' . substr((string) $r->target_date, 0, 10))
                        ->flip();
                    $dropped = [];
                    $cells = array_values(array_filter($cells, function ($c) use ($keptKeys, &$dropped) {
                        $key = $c['staff_id'] . '-' . substr((string) $c['target_date'], 0, 10);
                        if (isset($keptKeys[$key])) {
                            $dropped[] = $key;
                            return false;
                        }
                        return true;
                    }));
                    if (!empty($dropped)) {
                        info("doGenerate: dropped " . count($dropped) . " regenerated cells colliding with kept fixed cells: " . implode(',', array_slice($dropped, 0, 20)));
                    }
                }
                $cellCount = count($cells);
                info("doGenerate: inserting {$cellCount} cells for {$month}");
                if (!empty($cells)) {
                    try {
                        ScheduleCell::insert($cells);
                        info("doGenerate: successfully inserted {$cellCount} cells");
                    } catch (\Illuminate\Database\QueryException $qe) {
                        info("doGenerate INSERT ERROR: " . $qe->getMessage());
                        info("Cell sample: " . json_encode($cells[0] ?? 'empty'));
                        info("Distinct dates: " . json_encode(array_unique(array_column($cells, 'target_date'))));
                        info("Distinct staff: " . json_encode(array_unique(array_column($cells, 'staff_id'))));
                        throw new \RuntimeException(
                            $this->buildSaveErrorMessage($qe)
                        );
                    }
                } else {
                    info("doGenerate: WARNING — zero cells to insert");
                }

                return compact('cells', 'conflicts');
            });
        } catch (\RuntimeException $e) {
            $patterns = $this->patternRotator->loadFinalPatterns($month);
            return [
                'patterns' => $patterns,
                'conflicts' => [[
                    'date' => '',
                    'shift_type' => '生成失敗',
                    'required' => 0,
                    'assigned' => 0,
                    'shortfall' => 0,
                    'affected_staff' => [],
                    'message' => $e->getMessage(),
                ]],
            ];
        }

        $this->patternRotator->rotateAndDeduplicatePatterns($month, $masterData['shiftTypes']);

        $patterns = $this->patternRotator->loadFinalPatterns($month);

        // Post-commit DB verification
        try {
            $totalDb = \Illuminate\Support\Facades\DB::select(
                'SELECT COUNT(*) AS cnt FROM schedule_cells WHERE target_date LIKE ?', ["{$month}-%"]
            )[0]->cnt ?? 0;
            $perPattern = \Illuminate\Support\Facades\DB::select(
                'SELECT schedule_pattern_id, COUNT(*) AS cnt FROM schedule_cells WHERE target_date LIKE ? GROUP BY schedule_pattern_id',
                ["{$month}-%"]
            );
            $perPatternSummary = array_map(fn($r) => "pattern#{$r->schedule_pattern_id}:{$r->cnt}", $perPattern);
            info("doGenerate DB VERIFY for {$month}: total={$totalDb}, per-pattern=[" . implode(', ', $perPatternSummary) . ']');
        } catch (\Throwable $e) {
            info("doGenerate DB VERIFY ERROR: " . $e->getMessage());
        }

        $totalCells = $patterns->sum(fn($p) => $p->cells->count());
        $patternCount = $patterns->count();
        info("doGenerate response for {$month}: {$patternCount} patterns, {$totalCells} total cells, " . count($result['conflicts'] ?? []) . " conflicts");

        return ['patterns' => $patterns, 'conflicts' => $result['conflicts']];
    }



    private function allocateHolidays(
        string $month, int $daysInMonth, array $masterData, int $holidayCount,
        SchedulePattern $basePattern, Collection $fixedCells, array $fixedShiftMap,
        array $fixedShiftCounts, array &$cells, ?int $nightRequired = null
    ): array {
        $holidayDays = [];
        if ($holidayCount <= 0) return $holidayDays;

        // Step 1: Per-date stats — daily needed total (matching day-loop logic)
        $dailyNeededTotal = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%s-%02d', $month, $day);
            $neededTotal = 0;
            foreach ($masterData['shiftTypes'] as $st) {
                if ($st->name === '明け') continue;
                $needed = $this->calculateNeeded($st, $date, $masterData['requirements'], $masterData['dailyOverrides'], $nightRequired);
                $filledByFixed = $fixedShiftCounts[$date][$st->id] ?? 0;
                $neededTotal += max(0, $needed - $filledByFixed);
            }
            $dailyNeededTotal[$date] = $neededTotal;
        }

        // Step 2: Compute scores per (staff, date) + track active staff per date
        $activeStaffCount = [];
        $dateScores = [];
        $staffHasDate = [];

        foreach ($masterData['staff'] as $s) {
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = sprintf('%s-%02d', $month, $day);
                if (!$this->constraintChecker->isStaffActiveOnDate($s, $date)) continue;

                $activeStaffCount[$date] = ($activeStaffCount[$date] ?? 0) + 1;

                if (isset($fixedShiftMap[$s->id][$date])) continue;
                $hasFixedCell = false;
                foreach ($fixedCells as $k => $fc) {
                    $parts = explode('-', $k, 2);
                    $sid = (int)$parts[0];
                    $d = $parts[1] ?? '';
                    if ($sid === $s->id && $d === $date) { $hasFixedCell = true; break; }
                }
                if ($hasFixedCell) continue;

                $score = 0;
                $key = $s->id . '-' . $date;
                $staffPrefs = $masterData['preferences'][$key] ?? collect();

                // Off preference → less suitable (reconcileHolidays will adjust)
                if ($staffPrefs->contains(fn($p) => $p->type === 'off')) {
                    $score += 10;
                }

                // Low requirement day → more suitable (more capacity for holidays)
                $score -= $dailyNeededTotal[$date];

                // Spread preference — middle of month
                $score += abs($day - $daysInMonth / 2) * 0.1;

                $dateScores[$date][$s->id] = $score;
                $staffHasDate[$s->id][$date] = true;
            }
        }

        // Step 3: Max off capacity per date = active staff - needed staff
        $maxOffForDate = [];
        foreach ($activeStaffCount as $date => $count) {
            $maxOffForDate[$date] = max(0, $count - ($dailyNeededTotal[$date] ?? 0));
        }

        // Step 4: Round-robin holiday assignment
        $staffIds = $masterData['staff']->pluck('id')->toArray();
        $staffHolidayCount = [];
        foreach ($staffIds as $sid) {
            $staffHolidayCount[$sid] = 0;
        }
        $dateHolidayCount = [];

        for ($round = 0; $round < $holidayCount; $round++) {
            $shuffled = $staffIds;
            shuffle($shuffled);
            foreach ($shuffled as $sid) {
                if ($staffHolidayCount[$sid] >= $holidayCount) continue;
                $bestDate = null;
                $bestScore = INF;
                $dates = $staffHasDate[$sid] ?? [];
                foreach ($dates as $date => $_) {
                    if (isset($holidayDays[$sid][$date])) continue;
                    if (($dateHolidayCount[$date] ?? 0) >= ($maxOffForDate[$date] ?? 999)) continue;
                    $score = $dateScores[$date][$sid] ?? INF;
                    if ($score < $bestScore) {
                        $bestScore = $score;
                        $bestDate = $date;
                    }
                }
                if ($bestDate !== null) {
                    $holidayDays[$sid][$bestDate] = true;
                    $staffHolidayCount[$sid]++;
                    $dateHolidayCount[$bestDate] = ($dateHolidayCount[$bestDate] ?? 0) + 1;
                }
            }
        }

        // Step 7: Create null cells
        foreach ($holidayDays as $sid => $dates) {
            foreach ($dates as $date => $_) {
                $cells[] = [
                    'schedule_pattern_id' => $basePattern->id,
                    'staff_id' => $sid,
                    'target_date' => $date,
                    'shift_type_id' => null,
                    'is_fixed' => false,
                    'is_holiday' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Step 8: Log summary
        $lines = ["--- allocateHolidays 日付サマリ (holidayCount={$holidayCount}) ---"];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%s-%02d', $month, $day);
            $lines[] = sprintf(
                '%s: 休=%d/最大=%d, 稼働=%d/必要=%d',
                $date,
                $dateHolidayCount[$date] ?? 0,
                $maxOffForDate[$date] ?? 0,
                $activeStaffCount[$date] ?? 0,
                $dailyNeededTotal[$date] ?? 0
            );
        }
        info(implode("\n", $lines));

        return $holidayDays;
    }

    private function reconcileHolidays(
        string $month, int $daysInMonth, array $masterData, int $holidayCount,
        SchedulePattern $basePattern, array $fixedShiftMap, Collection $fixedCells,
        array $fixedShiftCounts, array &$holidayDays, array &$cells,
        ?int $nightRequired = null
    ): void {
        if ($holidayCount <= 0) return;

        // Compute per-date holiday counts for capacity check
        $dateHolidayCount = [];
        foreach ($holidayDays as $sid => $dates) {
            foreach ($dates as $date => $_) {
                $dateHolidayCount[$date] = ($dateHolidayCount[$date] ?? 0) + 1;
            }
        }
        // Compute per-date active staff count and max off per date (Bug A/B/C fix)
        $activeStaffCount = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%s-%02d', $month, $day);
            $activeStaffCount[$date] = 0;
            $neededTotal = 0;
            foreach ($masterData['staff'] as $s) {
                if ($this->constraintChecker->isStaffActiveOnDate($s, $date)) {
                    $activeStaffCount[$date]++;
                }
            }
            foreach ($masterData['shiftTypes'] as $st) {
                if ($st->name === '明け') continue;
                $needed = $this->calculateNeeded($st, $date, $masterData['requirements'], $masterData['dailyOverrides'], $nightRequired);
                $filledByFixed = $fixedShiftCounts[$date][$st->id] ?? 0;
                $neededTotal += max(0, $needed - $filledByFixed);
            }
            $maxOffForDate[$date] = max(0, $activeStaffCount[$date] - $neededTotal);
        }

        foreach ($masterData['staff'] as $s) {
            $offDates = [];
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = sprintf('%s-%02d', $month, $day);
                if (!$this->constraintChecker->isStaffActiveOnDate($s, $date)) continue;
                if (isset($fixedShiftMap[$s->id][$date])) continue;
                $hasFixedCell = false;
                foreach ($fixedCells as $k => $fc) {
                    $parts = explode('-', $k, 2);
                    $sid = (int)$parts[0];
                    $d = $parts[1] ?? '';
                    if ($sid === $s->id && $d === $date) { $hasFixedCell = true; break; }
                }
                if ($hasFixedCell) continue;

                $key = $s->id . '-' . $date;
                $staffPrefs = $masterData['preferences'][$key] ?? collect();
                if ($staffPrefs->contains(fn($p) => $p->type === 'off')) {
                    $offDates[$date] = true;
                }
            }

            $currentHolidayDates = array_keys($holidayDays[$s->id] ?? []);
            $holidaysOnOff = [];
            $holidaysOnOther = [];
            foreach ($currentHolidayDates as $d) {
                if (isset($offDates[$d])) {
                    $holidaysOnOff[$d] = true;
                } else {
                    $holidaysOnOther[$d] = true;
                }
            }

            $offNotHoliday = array_diff_key($offDates, $holidaysOnOff);

            foreach ($offNotHoliday as $date => $_) {
                // Skip if adding another holiday would exceed per-date capacity
                if (($dateHolidayCount[$date] ?? 0) >= ($maxOffForDate[$date] ?? 999)) continue;
                if (!empty($holidaysOnOther)) {
                    $swapDate = array_key_first($holidaysOnOther);
                    unset($holidaysOnOther[$swapDate]);
                    $this->removeCellByStaffDate($cells, $s->id, $swapDate);
                    unset($holidayDays[$s->id][$swapDate]);
                    $dateHolidayCount[$swapDate]--;
                    $cells[] = [
                        'schedule_pattern_id' => $basePattern->id,
                        'staff_id' => $s->id,
                        'target_date' => $date,
                        'shift_type_id' => null,
                        'is_fixed' => false,
                        'is_holiday' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $holidayDays[$s->id][$date] = true;
                    $dateHolidayCount[$date] = ($dateHolidayCount[$date] ?? 0) + 1;
                }
            }
        }
    }

    private function removeCellByStaffDate(array &$cells, int $staffId, string $date): void
    {
        foreach ($cells as $i => $c) {
            if (($c['staff_id'] ?? 0) === $staffId && ($c['target_date'] ?? '') === $date) {
                array_splice($cells, $i, 1);
                return;
            }
        }
    }

    private function clearExistingCells(SchedulePattern $basePattern, bool $preserveFixed): void
    {
        $allIds = SchedulePattern::where('month_year', $basePattern->month_year)->pluck('id');
        if ($preserveFixed) {
            ScheduleCell::whereIn('schedule_pattern_id', $allIds)
                ->where('is_fixed', false)
                ->delete();
        } else {
            ScheduleCell::whereIn('schedule_pattern_id', $allIds)->delete();
        }
    }

    /**
     * Build a short, human-readable save error message.
     * The full SQL statement stays in the server log only and is
     * never embedded in the user-facing message.
     */
    private function buildSaveErrorMessage(\Illuminate\Database\QueryException $qe): string
    {
        $code = $qe->errorInfo[1] ?? null;
        if ((int) $code === 1062 && preg_match("/Duplicate entry '(\d+)-(\d+)-([\d-]+)'/", $qe->getMessage(), $m)) {
            $staffId = $m[2];
            $date = $m[3];
            return "勤務表の保存中に重複エラーが発生しました（スタッフID:{$staffId}、{$date} の割当が保存済みセルと重複）。"
                . "「手動編集を残す」のチェックを外して再生成するか、該当箇所を確認してください。";
        }
        return "勤務表の保存中にデータベースエラーが発生しました（詳細はサーバーログを確認してください）。";
    }

    private function updateConsecutiveCache(&$consecutiveCache, &$cacheUntil, array $assignedToday, string $date, array $akeStaffYesterday = [], array $nullCellLookup = []): void
    {
        $yesterday = \Carbon\Carbon::createFromFormat('Y-m-d', $date)->subDay()->format('Y-m-d');
        // Check if this is the first day of the month
        $isMonthStart = (int)substr($date, -2) === 1;
        foreach ($assignedToday as $sid => $v) {
            if (isset($nullCellLookup[$sid][$date])) continue;
            // Month start: always reset consecutive count to 0
            if ($isMonthStart) {
                $consecutiveCache[$sid] = 0;
                $cacheUntil[$sid] = $date;
                continue;
            }
            if (isset($nullCellLookup[$sid][$yesterday])) {
                $consecutiveCache[$sid] = 0;
                $cacheUntil[$sid] = $date;
                continue;
            }
            if ($v) {
                $consecutiveCache[$sid] = ($consecutiveCache[$sid] ?? 0) + 1;
                $cacheUntil[$sid] = $date;
            }
        }
    }

    private function collectFixedToday(Collection $fixedCells, string $date): array
    {
        $assignedToday = [];
        foreach ($fixedCells as $k => $fc) {
            $parts = explode('-', $k, 2);
            $sid = $parts[0];
            $d = $parts[1] ?? '';
            if ($d === $date) {
                $assignedToday[$sid] = true;
            }
        }
        return $assignedToday;
    }

    private function calculateNeeded(ShiftType $st, string $date, Collection $requirements, Collection $dailyOverrides, ?int $nightOverride = null): int
    {
        $req = $requirements->firstWhere('shift_type_id', $st->id);
        $override = $dailyOverrides->get($date . '-' . $st->id);
        if ($override) return $override->count;
        if ($nightOverride !== null && $this->isNightShift($st)) return $nightOverride;
        return $req ? $req->count : 1;
    }

    public function isNightShift(ShiftType $st): bool
    {
        return $this->constraintChecker->isNightShift($st);
    }

    public function isAkeShift(?ShiftType $st): bool
    {
        return $this->constraintChecker->isAkeShift($st);
    }

    public function isStaffActiveOnDate($staff, string $date): bool
    {
        return $this->constraintChecker->isStaffActiveOnDate($staff, $date);
    }
}
