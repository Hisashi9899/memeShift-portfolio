<?php
namespace App\Services;

use App\Models\SchedulePattern;
use Illuminate\Support\Collection;

class OffDayDistributor
{
    public function __construct(
        private readonly ConstraintChecker $constraintChecker,
    ) {}

    public function distributeRemainingOffDays(
        array &$cells, array $masterData, array &$daysOff,
        string $month, int $daysInMonth, SchedulePattern $basePattern,
        Collection $fixedCells, int $holidayCount, array $constraints = []
    ): void {
        $holidayLimit = [];
        $totalOffDays = [];
        foreach ($masterData['staff'] as $s) {
            $holidayLimit[$s->id] = $holidayCount;
            $totalOffDays[$s->id] = 0;
        }
        foreach ($cells as $c) {
            if ($c['shift_type_id'] === null && isset($totalOffDays[$c['staff_id']])) {
                $totalOffDays[$c['staff_id']]++;
            }
        }
        foreach ($fixedCells as $fc) {
            if ($fc->shift_type_id === null && isset($totalOffDays[$fc->staff_id])) {
                $totalOffDays[$fc->staff_id]++;
            }
        }

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%s-%02d', $month, $day);

            $assignedToday = [];
            foreach ($cells as $c) {
                if ($c['target_date'] === $date) {
                    $assignedToday[$c['staff_id']] = true;
                }
            }
            foreach ($fixedCells as $k => $fc) {
                $parts = explode('-', $k, 2);
                $sid = $parts[0];
                $d = $parts[1] ?? '';
                if ($d === $date) {
                    $assignedToday[$sid] = true;
                }
            }

            foreach ($masterData['staff'] as $s) {
                if (!$this->constraintChecker->isStaffActiveOnDate($s, $date)) continue;
                if (isset($assignedToday[$s->id])) continue;

                $key = $s->id . '-' . $date;
                $staffPrefs = $masterData['preferences'][$key] ?? collect();

                $isPreferenceOff = $staffPrefs->contains(fn($p) => $p->type === 'off');
                $hasOffBudget = ($daysOff[$s->id] ?? 0) > 0;
                $isAtLimit = ($totalOffDays[$s->id] ?? 0) >= ($holidayLimit[$s->id] ?? 999);

                if ($isPreferenceOff && !$isAtLimit) {
                    $totalOffDays[$s->id]++;
                    $cells[] = [
                        'schedule_pattern_id' => $basePattern->id,
                        'staff_id' => $s->id,
                        'target_date' => $date,
                        'shift_type_id' => null,
                        'is_fixed' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    continue;
                }
                if ($hasOffBudget && !$isAtLimit) {
                    $yesterday = \Carbon\Carbon::createFromFormat('Y-m-d', $date)->subDay()->format('Y-m-d');
                    $hasStreak = str_starts_with($yesterday, substr($month, 0, 7)) && $this->hasOffOnDate($cells, $fixedCells, $s->id, $yesterday);
                    if (!$hasStreak) {
                        $daysOff[$s->id]--;
                        $totalOffDays[$s->id]++;
                        $cells[] = [
                            'schedule_pattern_id' => $basePattern->id,
                            'staff_id' => $s->id,
                            'target_date' => $date,
                            'shift_type_id' => null,
                            'is_fixed' => false,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        continue;
                    }
                }
                foreach ($masterData['shiftTypes'] as $st) {
                    if ($this->constraintChecker->isHardBlocked($staffPrefs, $st)) continue;

                    $maxDays = !empty($constraints['max_consecutive_days']) ? (int)$constraints['max_consecutive_days'] : (int)($s->max_consecutive_days ?? 5);
                    $consecutive = $this->countConsecutiveWorkDays($cells, $fixedCells, $s->id, $month, $date);
                    if ($consecutive >= $maxDays) continue 2;

                    $cells[] = [
                        'schedule_pattern_id' => $basePattern->id,
                        'staff_id' => $s->id,
                        'target_date' => $date,
                        'shift_type_id' => $st->id,
                        'is_fixed' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    break;
                }
            }
        }
    }

    public function hasOffOnDate(array &$cells, Collection $fixedCells, int $staffId, string $date): bool
    {
        foreach ($cells as $c) {
            if ($c['staff_id'] === $staffId && $c['target_date'] === $date) {
                return $c['shift_type_id'] === null;
            }
        }
        foreach ($fixedCells as $k => $fc) {
            $parts = explode('-', $k, 2);
            $sid = (int)$parts[0];
            $d = $parts[1] ?? '';
            if ($sid === $staffId && $d === $date) {
                return $fc->shift_type_id === null;
            }
        }
        return false;
    }

    private function countConsecutiveWorkDays(array &$cells, Collection $fixedCells, int $staffId, string $month, string $date): int
    {
        $count = 0;
        $check = \Carbon\Carbon::createFromFormat('Y-m-d', $date);
        while (true) {
            $check = $check->subDay();
            $prevDate = $check->format('Y-m-d');
            if (!str_starts_with($prevDate, substr($month, 0, 7))) break;
            if (!$this->hasWorkOnDate($cells, $fixedCells, $staffId, $prevDate)) break;
            $count++;
        }
        return $count;
    }

    private function hasWorkOnDate(array &$cells, Collection $fixedCells, int $staffId, string $date): bool
    {
        foreach ($cells as $c) {
            if ($c['staff_id'] === $staffId && $c['target_date'] === $date) {
                return $c['shift_type_id'] !== null;
            }
        }
        foreach ($fixedCells as $k => $fc) {
            $parts = explode('-', $k, 2);
            $sid = (int)$parts[0];
            $d = $parts[1] ?? '';
            if ($sid === $staffId && $d === $date) {
                return $fc->shift_type_id !== null;
            }
        }
        return false;
    }
}
