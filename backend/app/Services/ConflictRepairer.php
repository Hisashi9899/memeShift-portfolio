<?php
namespace App\Services;

use App\Models\ShiftType;
use App\Models\SchedulePattern;
use Illuminate\Support\Collection;

class ConflictRepairer
{
    public function __construct(
        private readonly ConstraintChecker $constraintChecker,
    ) {}

    public function applyOperation(
        array &$cells, array &$cellLookup, array &$shiftCounts,
        int $staffId, int $shiftTypeId, string $date
    ): void {
        $key = $staffId . '-' . $date;
        $idx = $cellLookup[$key] ?? null;
        if ($idx === null) return;
        $cells[$idx]['shift_type_id'] = $shiftTypeId;
        $shiftCounts[$date . '-' . $shiftTypeId] = ($shiftCounts[$date . '-' . $shiftTypeId] ?? 0) + 1;
    }

    public function repairConflicts(
        array &$cells, array &$conflicts, array $masterData,
        array $constraints, SchedulePattern $basePattern,
        string $month, int $daysInMonth,
        Collection $fixedCells,
        array $nullCellLookup = []
    ): void {
        if (empty($conflicts)) return;

        $fixedStaffDates = [];
        foreach ($fixedCells as $k => $fc) {
            $parts = explode('-', $k, 2);
            $sid = $parts[0];
            $d = $parts[1] ?? '';
            $fixedStaffDates[$sid . '-' . $d] = true;
        }

        $staffSchedule = [];
        $cellLookup = [];
        foreach ($cells as $i => $c) {
            $staffSchedule[$c['staff_id']][$c['target_date']] = $c['shift_type_id'];
            $cellLookup[$c['staff_id'] . '-' . $c['target_date']] = $i;
        }
        foreach ($fixedCells as $k => $fc) {
            $parts = explode('-', $k, 2);
            $sid = (int)$parts[0];
            $d = $parts[1] ?? '';
            if (!isset($staffSchedule[$sid][$d])) {
                $staffSchedule[$sid][$d] = $fc->shift_type_id;
            }
        }

        $shiftCounts = [];
        foreach ($cells as $c) {
            if ($c['shift_type_id'] !== null) {
                $key = $c['target_date'] . '-' . $c['shift_type_id'];
                $shiftCounts[$key] = ($shiftCounts[$key] ?? 0) + 1;
            }
        }

        for ($iter = 0; $iter < 200; $iter++) {
            $anyImprovement = false;

            foreach ($conflicts as $ci => $conflict) {
                $st = $masterData['shiftTypes']->firstWhere('name', $conflict['shift_type']);
                if (!$st) continue;

                $date = $conflict['date'];
                $required = $conflict['required'];
                $countKey = $date . '-' . $st->id;
                $currentCount = $shiftCounts[$countKey] ?? 0;
                $shortfall = $required - $currentCount;

                if ($shortfall <= 0) continue;

                $candidates = [];
                foreach ($masterData['staff'] as $s) {
                    if (!$this->constraintChecker->isStaffActiveOnDate($s, $date)) continue;

                    $key = $s->id . '-' . $date;
                    if (isset($fixedStaffDates[$key])) continue;

                    $currentShift = $staffSchedule[$s->id][$date] ?? null;
                    if ($currentShift !== null) continue;

                    if (isset($nullCellLookup[$s->id][$date])) continue;

                    if (!$this->constraintChecker->evaluateConstraints($s->id, $st, $date, $staffSchedule, $masterData, $constraints, $month)) continue;

                    $candidates[] = $s->id;
                }

                if (empty($candidates)) continue;

                $selectedId = $candidates[array_rand($candidates)];
                $this->applyOperation($cells, $cellLookup, $shiftCounts, $selectedId, $st->id, $date);
                $staffSchedule[$selectedId][$date] = $st->id;
                $anyImprovement = true;
            }

            if (!$anyImprovement) break;
        }

        $remainingConflicts = [];
        foreach ($conflicts as $c) {
            $st = $masterData['shiftTypes']->firstWhere('name', $c['shift_type']);
            if (!$st) continue;
            $countKey = $c['date'] . '-' . $st->id;
            $currentCount = $shiftCounts[$countKey] ?? 0;
            $shortfall = $c['required'] - $currentCount;
            if ($shortfall > 0) {
                $c['assigned'] = $currentCount;
                $c['shortfall'] = $shortfall;
                $remainingConflicts[] = $c;
            }
        }
        $conflicts = $remainingConflicts;
    }

    public function buildConflict(string $date, ShiftType $st, int $needed, int $assigned, $staff, $preferences, $ignoredPrefs = []): array
    {
        $affectedStaff = [];
        $reasons = [];

        foreach ($staff as $s) {
            $key = $s->id . '-' . $date;
            $prefs = $preferences[$key] ?? collect();

            foreach ($prefs as $p) {
                if (isset($ignoredPrefs[$p->id])) continue;
                $reason = null;
                switch ($p->type) {
                    case 'off':
                        $reason = '休み希望';
                        break;
                    case 'avoid_shift':
                        if ($p->shift_type_id === $st->id) $reason = $st->name . 'を避けたい';
                        break;
                    case 'cannot_night':
                        if ($this->constraintChecker->isNightShift($st)) $reason = '夜勤不可';
                        break;
                    case 'night_only':
                        if (!$this->constraintChecker->isNightShift($st)) $reason = '夜勤のみ可（この勤務は夜勤ではない）';
                        break;
                }
                if ($reason) {
                    $affectedStaff[] = $s->name;
                    $reasons[] = $reason;
                    break;
                }
            }
        }

        $affectedStaff = array_values(array_unique($affectedStaff));
        $reasons = array_values(array_unique($reasons));

        $message = sprintf(
            '%sの%sは%d名必要ですが、%d名しか割り当てられませんでした。',
            $date, $st->name, $needed, $assigned
        );
        if (!empty($affectedStaff)) {
            $message .= ' ' . implode('、', $affectedStaff) . 'が「' . implode('／', $reasons) . '」のため利用できません。';
            $message .= sprintf(
                ' 解決策: 該当者の希望を変更するか、%sの必要人数を調整してください。',
                $st->name
            );
        } else {
            $message .= ' 十分な人数のスタッフがいません。スタッフを追加するか、必要人数を調整してください。';
        }

        return [
            'date' => $date,
            'shift_type' => $st->name,
            'required' => $needed,
            'assigned' => $assigned,
            'shortfall' => $needed - $assigned,
            'affected_staff' => $affectedStaff,
            'message' => $message,
        ];
    }

    public function breakLongOffStreaks(
        array &$cells, array $masterData, array $constraints,
        string $month, int $daysInMonth, Collection $fixedCells,
        array $nullCellLookup = []
    ): void {
        $schedule = [];
        $cellIndex = [];
        foreach ($cells as $i => $c) {
            $schedule[$c['staff_id']][$c['target_date']] = $c['shift_type_id'];
            $cellIndex[$c['staff_id']][$c['target_date']] = $i;
        }
        foreach ($fixedCells as $k => $fc) {
            $parts = explode('-', $k, 2);
            $sid = (int)$parts[0];
            $d = $parts[1] ?? '';
            $schedule[$sid][$d] = $fc->shift_type_id;
            $cellIndex[$sid][$d] = 'fixed';
        }

        $monthPrefix = $month;
        $staffList = $masterData['staff'];

        for ($day = 1; $day <= $daysInMonth - 2; $day++) {
            $dates = [
                sprintf('%s-%02d', $month, $day),
                sprintf('%s-%02d', $month, $day + 1),
                sprintf('%s-%02d', $month, $day + 2),
            ];

            foreach ($staffList as $s) {
                $sid = $s->id;

                $d0 = $schedule[$sid][$dates[0]] ?? null;
                $d1 = $schedule[$sid][$dates[1]] ?? null;
                $d2 = $schedule[$sid][$dates[2]] ?? null;
                if ($d0 !== null || $d1 !== null || $d2 !== null) continue;

                $hasCellOffDay = false;
                foreach ($dates as $d) {
                    if (isset($nullCellLookup[$sid][$d])) {
                        $hasCellOffDay = true;
                        break;
                    }
                }
                if ($hasCellOffDay) continue;

                $targetDate = $dates[1];
                if (($cellIndex[$sid][$targetDate] ?? null) === 'fixed') continue;

                foreach ($staffList as $s2) {
                    if ($s2->id === $sid) continue;
                    $s2id = $s2->id;

                    $s2shift = $schedule[$s2id][$targetDate] ?? null;
                    if ($s2shift === null) continue;
                    if (($cellIndex[$s2id][$targetDate] ?? null) === 'fixed') continue;

                    $st = $masterData['shiftTypes']->firstWhere('id', $s2shift);
                    if (!$st) continue;

                    $hypothetical = $schedule;
                    $hypothetical[$sid][$targetDate] = $s2shift;
                    $hypothetical[$s2id][$targetDate] = null;

                    if (!$this->constraintChecker->evaluateConstraints($sid, $st, $targetDate, $hypothetical, $masterData, $constraints, $month)) continue;

                    $s2consecutive = 1;
                    $checkDate = \Carbon\Carbon::createFromFormat('Y-m-d', $targetDate)->subDay();
                    while (true) {
                        $cd = $checkDate->format('Y-m-d');
                        if (!str_starts_with($cd, $monthPrefix)) break;
                        if (($hypothetical[$s2id][$cd] ?? null) !== null) break;
                        $s2consecutive++;
                        $checkDate->subDay();
                    }
                    $checkDate = \Carbon\Carbon::createFromFormat('Y-m-d', $targetDate)->addDay();
                    while (true) {
                        $cd = $checkDate->format('Y-m-d');
                        if (!str_starts_with($cd, $monthPrefix)) break;
                        if (($hypothetical[$s2id][$cd] ?? null) !== null) break;
                        $s2consecutive++;
                        $checkDate->addDay();
                    }
                    if ($s2consecutive >= 3) continue;

                    $idx1 = $cellIndex[$sid][$targetDate] ?? null;
                    $idx2 = $cellIndex[$s2id][$targetDate] ?? null;
                    if ($idx1 === null || $idx2 === null) continue;

                    $cells[$idx1]['shift_type_id'] = $s2shift;
                    $cells[$idx2]['shift_type_id'] = null;

                    $schedule[$sid][$targetDate] = $s2shift;
                    $schedule[$s2id][$targetDate] = null;

                    break;
                }
            }
        }
    }
}
