<?php
namespace App\Services;

use App\Models\ShiftType;
use Illuminate\Support\Collection;

class ConstraintChecker
{
    public function isNightShift(ShiftType $st): bool
    {
        if (!$st->start_time || !$st->end_time) return false;
        $hour = (int) substr($st->start_time, 0, 2);
        $endHour = (int) substr($st->end_time, 0, 2);
        if ($endHour < $hour) return true;
        return $hour >= 17 || $hour < 5;
    }

    public function isAkeShift(?ShiftType $st): bool
    {
        return $st && $st->name === '明け';
    }

    public function isStaffActiveOnDate($staff, string $date): bool
    {
        if ($staff->start_date && $date < $staff->start_date) return false;
        if ($staff->end_date && $date > $staff->end_date) return false;
        return true;
    }

    public function isHardBlocked($prefs, ShiftType $st): bool
    {
        $fixedShiftTypeId = $this->hasFixedShift($prefs);
        if ($fixedShiftTypeId !== null) {
            return $fixedShiftTypeId !== $st->id;
        }

        foreach ($prefs as $p) {
            switch ($p->type) {
                case 'off':
                    return true;
                case 'avoid_shift':
                    if ($p->shift_type_id === $st->id) return true;
                    break;
                case 'cannot_night':
                    if ($this->isNightShift($st)) return true;
                    break;
                case 'night_only':
                    if (!$this->isNightShift($st)) return true;
                    break;
            }
        }
        return false;
    }

    public function hasFixedShift($prefs): ?int
    {
        $fixed = $prefs->firstWhere('type', 'fixed_shift');
        return $fixed ? (int)$fixed->shift_type_id : null;
    }

    public function evaluateWithScore(
        int $staffId, ShiftType $st, string $date,
        array $staffSchedule, array $masterData, array $constraints,
        string $month
    ): array {
        $penalty = 0;
        $key = $staffId . '-' . $date;
        $staffPrefs = $masterData['preferences'][$key] ?? collect();

        if ($this->isHardBlocked($staffPrefs, $st)) {
            return ['valid' => false, 'penalty' => 9999];
        }
        if (!$staffPrefs->contains(fn($p) => $p->type === 'fixed_shift' && $p->shift_type_id === $st->id)) {
            if ($staffPrefs->contains(fn($p) => $p->type === 'off')) {
                return ['valid' => false, 'penalty' => 9999];
            }
        }

        // max_consecutive_days (must = 250)
        $maxDays = !empty($constraints['max_consecutive_days']) ? (int)$constraints['max_consecutive_days'] : 5;
        $consecutive = 0;
        $checkDate = \Carbon\Carbon::createFromFormat('Y-m-d', $date)->subDay();
        while ($consecutive < $maxDays) {
            $prevDate = $checkDate->format('Y-m-d');
            if (!str_starts_with($prevDate, substr($month, 0, 7))) break;
            $shift = $staffSchedule[$staffId][$prevDate] ?? null;
            if ($shift === null) break;
            $consecutive++;
            $checkDate->subDay();
        }
        if ($consecutive >= $maxDays) $penalty += 250;

        // no_consecutive_night (forbidden = 100)
        if ($this->isNightShift($st) && !empty($constraints['no_consecutive_night'])) {
            $yesterday = \Carbon\Carbon::createFromFormat('Y-m-d', $date)->subDay()->format('Y-m-d');
            $prevShiftId = $staffSchedule[$staffId][$yesterday] ?? null;
            if ($prevShiftId !== null) {
                $prevSt = $masterData['shiftTypes']->firstWhere('id', $prevShiftId);
                if ($prevSt && $this->isNightShift($prevSt)) $penalty += 100;
            }
        }

        // late_to_early_forbidden (forbidden = 100)
        if ($st->name === '早番' && !empty($constraints['late_to_early_forbidden'])) {
            $yesterday = \Carbon\Carbon::createFromFormat('Y-m-d', $date)->subDay()->format('Y-m-d');
            $prevShiftId = $staffSchedule[$staffId][$yesterday] ?? null;
            if ($prevShiftId !== null) {
                $prevSt = $masterData['shiftTypes']->firstWhere('id', $prevShiftId);
                if ($prevSt && $prevSt->name === '遅番') $penalty += 100;
            }
        }

        // day_to_early_forbidden (forbidden = 100)
        if ($st->name === '早番' && !empty($constraints['day_to_early_forbidden'])) {
            $yesterday = \Carbon\Carbon::createFromFormat('Y-m-d', $date)->subDay()->format('Y-m-d');
            $prevShiftId = $staffSchedule[$staffId][$yesterday] ?? null;
            if ($prevShiftId !== null) {
                $prevSt = $masterData['shiftTypes']->firstWhere('id', $prevShiftId);
                if ($prevSt && $prevSt->name === '日勤') $penalty += 100;
            }
        }

        // night_to_ake (must = 90)
        if ($this->isNightShift($st) && !empty($constraints['night_to_ake'])) {
            $yesterday = \Carbon\Carbon::createFromFormat('Y-m-d', $date)->subDay()->format('Y-m-d');
            $prevShiftId = $staffSchedule[$staffId][$yesterday] ?? null;
            if ($prevShiftId !== null) {
                $prevSt = $masterData['shiftTypes']->firstWhere('id', $prevShiftId);
                if ($prevSt && $this->isNightShift($prevSt)) $penalty += 90;
            }
        }

        // Threshold: 300 (2段階のフォールバック)
        // penalty >= 300: immediate invalid
        // 200 <= penalty < 300: valid but high_penalty (soft constraint)
        $highPenalty = ($penalty >= 200) ? true : false;
        return ['valid' => $penalty < 300, 'penalty' => $penalty, 'high_penalty' => $highPenalty];
    }

    public function evaluateConstraints(
        int $staffId, ShiftType $st, string $date,
        array $staffSchedule, array $masterData, array $constraints,
        string $month
    ): bool {
        return $this->evaluateWithScore($staffId, $st, $date, $staffSchedule, $masterData, $constraints, $month)['valid'];
    }
}
