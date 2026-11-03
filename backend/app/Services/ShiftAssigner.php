<?php
namespace App\Services;

use App\Models\SchedulePattern;
use App\Models\ShiftType;
use Illuminate\Support\Collection;

class ShiftAssigner
{
    public function __construct(
        private readonly ConstraintChecker $constraintChecker,
    ) {}

    public function buildCandidatesForShift(
        ShiftType $st, Collection $dayStaff, string $date, array $assignedToday,
        array $masterData, array $constraints, array $nightShiftCounts,
        array $nightBlock, array $lateBlock, array $dayBlock, array &$consecutiveCache, array &$cacheUntil,
        array $fixedShiftToday = [], array $typeAssignmentCounts = []
    ): array {
        $candidates = [];

        foreach ($dayStaff as $s) {
            $fixedShiftTarget = $fixedShiftToday[$s->id] ?? null;
            if ($fixedShiftTarget !== null) {
                if ($fixedShiftTarget === $st->id) {
                    $candidates[] = [
                        'staff' => $s,
                        'wants' => true,
                        'nightCount' => 0,
                        'fixedShift' => true,
                        'penalty' => 0,
                    ];
                }
                continue;
            }

            if (isset($assignedToday[$s->id])) continue;

            $penalty = 0;

            // forbidden constraints: hard block (continue)
            if (!empty($constraints['late_to_early_forbidden']) && $st->name === '早番' && !empty($lateBlock[$s->id])) continue;
            if (!empty($constraints['day_to_early_forbidden']) && $st->name === '早番' && !empty($dayBlock[$s->id])) continue;
            if (!empty($constraints['no_consecutive_night']) && $this->constraintChecker->isNightShift($st) && !empty($nightBlock[$s->id])) continue;

            // must constraints: soft block (penalty instead of continue)
            if (!empty($nightBlock[$s->id]) && !empty($constraints['night_to_ake'])) {
                $penalty += 1000;
            }

            $key = $s->id . '-' . $date;
            $staffPrefs = $masterData['preferences'][$key] ?? collect();

            if ($this->constraintChecker->isHardBlocked($staffPrefs, $st)) continue;

            if (!empty($masterData['mustSeparate'][$s->id])) {
                $skipDueToSep = false;
                foreach ($masterData['mustSeparate'][$s->id] as $sid2) {
                    if (isset($masterData['shiftAssignments'][$date][$sid2]) &&
                        $masterData['shiftAssignments'][$date][$sid2] === $st->id) {
                        $skipDueToSep = true;
                        break;
                    }
                }
                if ($skipDueToSep) continue;
            }

            $maxDays = !empty($constraints['max_consecutive_days']) ? (int)$constraints['max_consecutive_days'] : $s->max_consecutive_days;
            if ($cacheUntil[$s->id] !== $date) {
                $consecutiveCache[$s->id] = 0;
                $cacheUntil[$s->id] = $date;
            }
            $consecutive = $consecutiveCache[$s->id];
            if ($consecutive >= $maxDays) {
                continue;
            }

            // recommended constraints: small penalty (no continue)
            if (!empty($constraints['night_ake_rest_recommended']) && !empty($nightBlock[$s->id])) {
                $penalty += 30;
            }

            $nightCount = $nightShiftCounts[$s->id] ?? 0;
            $typeCount = $typeAssignmentCounts[$s->id][$st->id] ?? 0;
            $candidates[] = [
                'staff' => $s,
                'wants' => $this->hasWantShift($staffPrefs, $st->id),
                'nightCount' => $nightCount,
                'typeCount' => $typeCount,
                'penalty' => $penalty,
            ];
        }

        return $candidates;
    }

    public function sortCandidates(array &$candidates, array $constraints): void
    {
        if (empty($candidates)) return;

        $priority = fn($c) => ($c['fixedShift'] ?? false) ? 2 : (($c['wants'] ?? false) ? 1 : 0);
        $penalty = fn($c) => $c['penalty'] ?? 0;

        if (!empty($constraints['equalize_night_shifts'])) {
            usort($candidates, fn($a, $b) => ($penalty($a) - $penalty($b)) ?: ($priority($b) - $priority($a)) ?: ($a['nightCount'] - $b['nightCount']) ?: (($a['typeCount'] ?? 0) - ($b['typeCount'] ?? 0)));
        } else {
            usort($candidates, fn($a, $b) => ($penalty($a) - $penalty($b)) ?: ($priority($b) - $priority($a)) ?: (($a['typeCount'] ?? 0) - ($b['typeCount'] ?? 0)));
        }
    }

    public function assignCandidates(
        array &$candidates, ShiftType $st, int $needed, string $date, SchedulePattern $basePattern,
        array $masterData, array $constraints, array &$nightShiftCounts,
        array &$nextNightBlock, array &$nextLateBlock, array &$nextDayBlock, array &$assignedToday, array &$shiftAssignments,
        array &$cells
    ): int {
        $assigned = 0;

        foreach ($candidates as $c) {
            $isFixedShift = !empty($c['fixedShift']);
            if (!$isFixedShift && $assigned >= $needed) break;
            $s = $c['staff'];

            $cells[] = [
                'schedule_pattern_id' => $basePattern->id,
                'staff_id' => $s->id,
                'target_date' => $date,
                'shift_type_id' => $st->id,
                'is_fixed' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $assignedToday[$s->id] = true;
            $assigned++;
            $shiftAssignments[$date][$s->id] = $st->id;

            if ($this->constraintChecker->isNightShift($st)) {
                if (!isset($nightShiftCounts[$s->id])) $nightShiftCounts[$s->id] = 0;
                $nightShiftCounts[$s->id]++;
                if (!empty($constraints['night_to_ake']) || !empty($constraints['night_ake_rest_recommended'])) {
                    $nextNightBlock[$s->id] = true;
                }
            }
            if ($st->name === '遅番') {
                $nextLateBlock[$s->id] = true;
            }
            if ($st->name === '日勤') {
                $nextDayBlock[$s->id] = true;
            }

            $assigned = $this->assignPartnerIfNeeded(
                $s, $st, $needed, $assigned, $date, $basePattern,
                $masterData, $constraints, $nightShiftCounts,
                $nextNightBlock, $nextLateBlock, $nextDayBlock, $assignedToday, $shiftAssignments, $cells
            );
        }

        return $assigned;
    }

    private function assignPartnerIfNeeded(
        $staffMember, ShiftType $st, int $needed, int &$assigned, string $date, SchedulePattern $basePattern,
        array $masterData, array $constraints, array &$nightShiftCounts,
        array &$nextNightBlock, array &$nextLateBlock, array &$nextDayBlock, array &$assignedToday, array &$shiftAssignments,
        array &$cells
    ): int {
        if (!$staffMember || empty($masterData['mustTogether'][$staffMember->id])) {
            return $assigned;
        }

        foreach ($masterData['mustTogether'][$staffMember->id] as $sid2) {
            if ($assigned >= $needed) break;
            if (isset($assignedToday[$sid2])) continue;

            $partner = $masterData['staff']->firstWhere('id', $sid2);
            if (!$partner) continue;
            if (!$this->constraintChecker->isStaffActiveOnDate($partner, $date)) continue;

            $partnerKey = $sid2 . '-' . $date;
            $partnerPrefs = $masterData['preferences'][$partnerKey] ?? collect();
            if ($this->constraintChecker->isHardBlocked($partnerPrefs, $st)) continue;

            $assignedToday[$sid2] = true;
            $cells[] = [
                'schedule_pattern_id' => $basePattern->id,
                'staff_id' => $sid2,
                'target_date' => $date,
                'shift_type_id' => $st->id,
                'is_fixed' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $assigned++;
            $shiftAssignments[$date][$sid2] = $st->id;

            if ($this->constraintChecker->isNightShift($st)) {
                if (!isset($nightShiftCounts[$sid2])) $nightShiftCounts[$sid2] = 0;
                $nightShiftCounts[$sid2]++;
                if (!empty($constraints['night_to_ake']) || !empty($constraints['night_ake_rest_recommended'])) {
                    $nextNightBlock[$sid2] = true;
                }
            }
            if ($st->name === '遅番') {
                $nextLateBlock[$sid2] = true;
            }
            if ($st->name === '日勤') {
                $nextDayBlock[$sid2] = true;
            }
        }

        return $assigned;
    }

    public function assignAkeShifts(
        array $nightBlock, array $constraints, Collection $shiftTypes,
        string $date, array &$assignedToday, array &$cells, SchedulePattern $basePattern
    ): void {
        if (empty($constraints['night_to_ake']) || empty($nightBlock)) return;

        $akeShiftType = $shiftTypes->firstWhere('name', '明け');
        if (!$akeShiftType) return;

        foreach ($nightBlock as $sid => $v) {
            if (!isset($assignedToday[$sid])) {
                $cells[] = [
                    'schedule_pattern_id' => $basePattern->id,
                    'staff_id' => $sid,
                    'target_date' => $date,
                    'shift_type_id' => $akeShiftType->id,
                    'is_fixed' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $assignedToday[$sid] = true;
            }
        }
    }

    private function hasWantShift($prefs, int $shiftTypeId): bool
    {
        return $prefs->contains(fn($p) => $p->type === 'want_shift' && $p->shift_type_id === $shiftTypeId);
    }
}
