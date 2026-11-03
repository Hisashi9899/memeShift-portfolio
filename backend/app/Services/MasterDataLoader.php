<?php
namespace App\Services;

use App\Models\SchedulePattern;
use App\Models\ScheduleCell;
use App\Models\Staff;
use App\Models\ShiftType;
use App\Models\Requirement;
use App\Models\DailyOverride;
use App\Models\CompatibilityRule;
use App\Models\Preference;
use Illuminate\Support\Collection;

class MasterDataLoader
{
    public function resolveBasePattern(string $month, ?int $patternId = null): SchedulePattern
    {
        if ($patternId !== null) {
            $pattern = SchedulePattern::find($patternId);
            if ($pattern && $pattern->month_year === $month) {
                return $pattern;
            }
        }
        return SchedulePattern::firstOrCreate(
            ['month_year' => $month, 'pattern_number' => 1],
            ['is_base' => true]
        );
    }

    public function loadFixedCells(SchedulePattern $basePattern, bool $preserveFixed): Collection
    {
        if (!$preserveFixed) {
            return collect();
        }
        $result = ScheduleCell::where('schedule_pattern_id', $basePattern->id)
            ->where('is_fixed', true)
            ->get()
                ->keyBy(fn($c) => $c->staff_id . '-' . $c->target_date);
        return $result;
    }

    public function loadMasterData(string $month, array $ignoredPreferenceIds): array
    {
        $staff = Staff::where('active', true)->get();
        $shiftTypes = ShiftType::orderBy('sort_order')->get();
        $requirements = Requirement::with('shiftType')->get();

        $dailyOverrides = DailyOverride::where('target_date', 'like', $month . '%')
            ->get()
            ->keyBy(fn($o) => ($o->target_date ? $o->target_date->format('Y-m-d') : '') . '-' . $o->shift_type_id);

        $compatRules = CompatibilityRule::all();
        $mustTogether = [];
        $mustSeparate = [];
        foreach ($compatRules as $r) {
            if ($r->rule_type === 'must_together') {
                $mustTogether[$r->staff1_id][] = $r->staff2_id;
                $mustTogether[$r->staff2_id][] = $r->staff1_id;
            } elseif ($r->rule_type === 'must_separate') {
                $mustSeparate[$r->staff1_id][] = $r->staff2_id;
                $mustSeparate[$r->staff2_id][] = $r->staff1_id;
            }
        }

        $parts = explode('-', $month);
        if (count($parts) !== 2 || !checkdate((int)$parts[1], 1, (int)$parts[0])) {
            throw new \InvalidArgumentException('Invalid month format');
        }

        $prefsQuery = Preference::with('staff:id,name')
            ->where('target_date', 'like', $month . '%');

        if (!empty($ignoredPreferenceIds)) {
            $prefsQuery->whereNotIn('id', $ignoredPreferenceIds);
        }

        $preferences = $prefsQuery->get()
            ->groupBy(fn($p) => $p->staff_id . '-' . $p->target_date);

        $ignoredPrefs = [];
        if (!empty($ignoredPreferenceIds)) {
            $ignoredPrefs = Preference::with('staff:id,name')
                ->whereIn('id', $ignoredPreferenceIds)
                ->get()
                ->keyBy('id');
        }

        return compact(
            'staff', 'shiftTypes', 'requirements', 'dailyOverrides',
            'mustTogether', 'mustSeparate', 'preferences', 'ignoredPrefs'
        );
    }

    public function initializeTracking($staff, &$consecutiveCache, &$cacheUntil): void
    {
        $consecutiveCache = [];
        $cacheUntil = [];
        foreach ($staff as $s) {
            $consecutiveCache[$s->id] = 0;
            $cacheUntil[$s->id] = null;
        }
    }
}
