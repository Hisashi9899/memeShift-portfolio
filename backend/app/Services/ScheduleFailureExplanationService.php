<?php
namespace App\Services;

use App\Models\Staff;
use App\Models\ShiftType;
use App\Models\Preference;
use App\Models\Requirement;

class ScheduleFailureExplanationService
{
    public function __construct(
        private ScheduleGenerationService $generationService,
    ) {}

    private function dateWithMostOffPrefs($offPrefs, string $month, int $startDay, int $endDay): string
    {
        $counts = [];
        for ($d = $startDay; $d <= $endDay; $d++) {
            $date = sprintf('%s-%02d', $month, $d);
            $counts[$date] = 0;
        }
        foreach ($offPrefs as $p) {
            if (isset($counts[$p->target_date])) {
                $counts[$p->target_date]++;
            }
        }
        arsort($counts);
        return array_key_first($counts) ?? sprintf('%s-%02d', $month, $startDay);
    }

    private function firstOffDateForStaff($offPrefs, int $staffId): ?string
    {
        foreach ($offPrefs as $p) {
            if ($p->staff_id === $staffId && $p->type === 'off') {
                return $p->target_date;
            }
        }
        return null;
    }

    public function explain(string $month, array $constraints): array
    {
        $parts = explode('-', $month);
        $daysInMonth = (int) \Carbon\Carbon::create((int)$parts[0], (int)$parts[1])->daysInMonth;

        $shiftTypes = ShiftType::all();
        $staff = Staff::where('active', true)->get();
        $requirements = Requirement::with('shiftType')->get();
        $preferences = Preference::where('target_date', 'like', $month . '%')->get();

        $explanations = [];

        $this->checkNightShortage($explanations, $month, $shiftTypes, $staff, $requirements, $preferences, $constraints, $daysInMonth);
        $this->checkPreferenceClustering($explanations, $preferences, $month, $daysInMonth);
        $this->checkConsecutiveLimit($explanations, $staff, $constraints, $daysInMonth);
        $this->checkRequirementOverload($explanations, $requirements, $staff, $daysInMonth);
        $this->checkStaffShortage($explanations, $requirements, $staff, $daysInMonth);

        usort($explanations, fn($a, $b) => ($b['impact'] ?? 0) <=> ($a['impact'] ?? 0));

        $summary = $this->buildSummary($explanations);

        return [
            'explanations' => $explanations,
            'summary'      => $summary,
        ];
    }

    private function checkNightShortage(
        array &$explanations, string $month, $shiftTypes, $staff, $requirements, $preferences, array $constraints, int $daysInMonth
    ): void {
        $nightShiftId = null;
        $akeShiftId = null;
        foreach ($shiftTypes as $st) {
            if ($this->generationService->isNightShift($st)) $nightShiftId = $st->id;
            if ($st->name === '明け') $akeShiftId = $st->id;
        }

        if (!$nightShiftId) return;

        $nightStaffCount = $staff->filter(fn($s) => $s->can_night_shift)->count();
        $nightRequirement = $requirements->firstWhere('shift_type_id', $nightShiftId);
        $needed = $nightRequirement ? (int) $nightRequirement->count : 0;

        $nightOnlyCount = 0;
        $cannotNightCount = 0;
        foreach ($staff as $s) {
            $hasNightOnly = false;
            $hasCannotNight = false;
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = sprintf('%s-%02d', $month, $day);
                $key = $s->id . '-' . $date;
                $dayPrefs = $preferences->filter(fn($p) => $p->staff_id . '-' . $p->target_date === $key);
                foreach ($dayPrefs as $p) {
                    if ($p->type === 'night_only') $hasNightOnly = true;
                    if ($p->type === 'cannot_night') $hasCannotNight = true;
                }
            }
            if ($hasNightOnly) $nightOnlyCount++;
            if ($hasCannotNight) $cannotNightCount++;
        }

        if ($needed > 0 && $nightStaffCount < $needed) {
            $impact = (int) min(100, round(($needed - $nightStaffCount) / $needed * 100));
            $explanations[] = [
                'type'           => 'night_shortage_staff',
                'message'        => "夜勤に入れるスタッフが不足しています。\n夜勤可能スタッフは{$nightStaffCount}名ですが、必要人数は{$needed}名です。",
                'impact'         => max($impact, 70),
                'suggested_date' => sprintf('%s-%02d', $month, 1),
                'shift_type_id'  => $nightShiftId,
                'shift_type_name'=> '夜勤',
            ];
        }

        if (!empty($constraints['night_to_ake']) && !empty($constraints['night_ake_rest_recommended'])) {
            $impact = 40;
            if ($needed > 0 && $nightStaffCount > 0 && $nightStaffCount >= $needed) {
                $impact = 30;
            }
            $explanations[] = [
                'type'           => 'night_chain_depletion',
                'message'        => "夜勤→明け→休みの連鎖により、月後半で夜勤可能者が枯渇しています。\n夜勤の翌日は明け、明けの翌日は休みとなるため、連続した夜勤シフトの確保が難しくなっています。",
                'impact'         => $impact,
                'suggested_date' => sprintf('%s-%02d', $month, 15),
                'constraint_key' => 'night_ake_rest_recommended',
            ];
        }
    }

    private function checkPreferenceClustering(array &$explanations, $preferences, string $month, int $daysInMonth): void
    {
        $offPrefs = $preferences->where('type', 'off');
        $total = $offPrefs->count();
        if ($total === 0) return;

        $firstHalf = 0;
        $midpoint = (int) ceil($daysInMonth / 2);
        foreach ($offPrefs as $p) {
            $day = (int) substr($p->target_date, 8, 2);
            if ($day <= $midpoint) $firstHalf++;
        }

        $ratio = $firstHalf / $total;
        if ($ratio > 0.65) {
            $peakDate = $this->dateWithMostOffPrefs($offPrefs, $month, 1, $midpoint);
            $impact = (int) min(100, round(abs($ratio - 0.5) * 200));
            $explanations[] = [
                'type'           => 'preference_clustering_early',
                'message'        => "希望休が月前半に集中しています（{$total}件中{$firstHalf}件）。\n前半に希望休が集中すると、後半の勤務可能者が減少し、シフト割り当てが困難になります。",
                'impact'         => $impact,
                'suggested_date' => $peakDate,
            ];
        } elseif ($ratio < 0.35) {
            $peakDate = $this->dateWithMostOffPrefs($offPrefs, $month, $midpoint + 1, $daysInMonth);
            $impact = (int) min(100, round(abs($ratio - 0.5) * 200));
            $explanations[] = [
                'type'           => 'preference_clustering_late',
                'message'        => "希望休が月後半に集中しています（{$total}件中" . ($total - $firstHalf) . "件）。\n後半の休み希望が多く、シフト割り当てが困難です。",
                'impact'         => $impact,
                'suggested_date' => $peakDate,
            ];
        }

        $staffWithManyPrefs = $offPrefs->groupBy('staff_id')->filter(fn($g) => $g->count() > $daysInMonth * 0.4);
        foreach ($staffWithManyPrefs as $sid => $prefs) {
            $s = $prefs->first()->staff;
            $name = $s ? $s->name : "ID:{$sid}";
            $offCount = $prefs->count();
            $impact = (int) min(100, round($offCount / $daysInMonth * 100));
            $explanations[] = [
                'type'           => 'individual_many_off',
                'message'        => "{$name}の希望休が{$offCount}日と多いため、割り当て可能なシフトが制限されています。",
                'impact'         => $impact,
                'staff_id'       => $sid,
                'staff_name'     => $name,
                'suggested_date' => $this->firstOffDateForStaff($offPrefs, $sid) ?? sprintf('%s-%02d', $month, 1),
            ];
        }
    }

    private function checkConsecutiveLimit(array &$explanations, $staff, array $constraints, int $daysInMonth): void
    {
        $maxDays = (int) ($constraints['max_consecutive_days'] ?? 5);
        $totalDays = $daysInMonth;
        $minRequired = (int) ceil($totalDays / $maxDays);

        $staffCount = $staff->count();
        if ($staffCount === 0) return;

        if ($minRequired > $staffCount) {
            $impact = (int) min(100, round((1 - $staffCount / $minRequired) * 100));
            $explanations[] = [
                'type'           => 'consecutive_limit_too_strict',
                'message'        => "最大連勤制限（{$maxDays}日）が厳しすぎます。\n月{$totalDays}日を{$maxDays}日ずつに分割すると最低{$minRequired}名のスタッフが必要ですが、現在{$staffCount}名しかいません。",
                'impact'         => $impact,
                'suggested_date' => sprintf('%s-%02d', $month, 1),
                'constraint_key' => 'max_consecutive_days',
            ];
        }
    }

    private function checkRequirementOverload(array &$explanations, $requirements, $staff, int $daysInMonth): void
    {
        $totalRequiredPerDay = 0;
        foreach ($requirements as $req) {
            $totalRequiredPerDay += (int) $req->count;
        }

        $staffCount = $staff->count();
        if ($totalRequiredPerDay > $staffCount) {
            $impact = (int) min(100, round(($totalRequiredPerDay - $staffCount) / $staffCount * 100));
            $explanations[] = [
                'type'           => 'requirement_overload',
                'message'        => "1日あたりの必要人数（{$totalRequiredPerDay}名）がスタッフ数（{$staffCount}名）を超えています。\n全スタッフが出勤しても必要人数を満たせない日があります。",
                'impact'         => $impact,
                'suggested_date' => sprintf('%s-%02d', $month, 1),
            ];
        }
    }

    private function checkStaffShortage(array &$explanations, $requirements, $staff, int $daysInMonth): void
    {
        $staffCount = $staff->count();
        $avgRequiredPerDay = 0;
        $count = 0;
        foreach ($requirements as $req) {
            $avgRequiredPerDay += (int) $req->count;
            $count++;
        }
        if ($count > 0) $avgRequiredPerDay = $avgRequiredPerDay / $count;

        if ($staffCount < 2 && $avgRequiredPerDay >= 1) {
            $explanations[] = [
                'type'           => 'staff_shortage',
                'message'        => "スタッフ数（{$staffCount}名）が不足しています。\n最低でも1日あたりの必要人数以上（{$avgRequiredPerDay}名以上）のスタッフを登録してください。",
                'impact'         => 90,
                'suggested_date' => sprintf('%s-%02d', $month, 1),
            ];
        }
    }

    private function buildSummary(array $explanations): string
    {
        if (empty($explanations)) {
            return '現在の制約・条件では勤務表を生成できません。必要条件を確認してください。';
        }

        $lines = [];
        foreach ($explanations as $e) {
            $lines[] = $e['message'];
        }

        return implode("\n\n", $lines);
    }
}
