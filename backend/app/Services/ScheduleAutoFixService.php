<?php
namespace App\Services;

use App\Models\Requirement;
use App\Models\Preference;
use App\Models\ShiftType;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

class ScheduleAutoFixService
{
    public function __construct(
        private ScheduleGenerationService $generationService,
    ) {}

    public function applyApproach(string $month, bool $preserveFixed, array $constraints, string $approach): array
    {
        return match ($approach) {
            'reduce_requirements'     => $this->approachReduceRequirements($month, $preserveFixed, $constraints),
            'relax_consecutive_days'  => $this->approachRelaxConsecutiveDays($month, $preserveFixed, $constraints),
            'equalize_preferences'    => $this->approachEqualizePreferences($month, $preserveFixed, $constraints),
            'add_dummy_night_staff'   => $this->approachAddDummyNightStaff($month, $preserveFixed, $constraints),
            'relax_night_chain'       => $this->approachRelaxNightChain($month, $preserveFixed, $constraints),
            default => throw new \InvalidArgumentException("Unknown approach: {$approach}"),
        };
    }

    private function approachReduceRequirements(string $month, bool $preserveFixed, array $constraints): array
    {
        $originals = [];
        $shiftTypes = ShiftType::all()->keyBy('id');
        $requirements = Requirement::all();

        DB::beginTransaction();
        try {
            foreach ($requirements as $req) {
                $st = $shiftTypes->get($req->shift_type_id);
                if (!$st || in_array($st->name, ['明け'], true)) continue;

                $originals[$req->id] = $req->count;
                $newCount = $this->generationService->isNightShift($st)
                    ? max(0, $req->count - 1)
                    : max(1, $req->count - 1);

                if ($newCount !== $req->count) {
                    $req->update(['count' => $newCount]);
                }
            }

            $result = $this->generationService->doGenerate($month, $preserveFixed, [], $constraints, null, true);

            foreach ($originals as $id => $count) {
                Requirement::where('id', $id)->update(['count' => $count]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'patterns'  => $result['patterns'],
            'conflicts' => $result['conflicts'],
            'description' => '夜勤の必要人数を1減らし（最低0）、早番・遅番・日勤の必要人数を1減らしました（最低1）',
        ];
    }

    private function approachRelaxConsecutiveDays(string $month, bool $preserveFixed, array $constraints): array
    {
        $currentMax = (int) ($constraints['max_consecutive_days'] ?? 5);
        $newMax = min(6, $currentMax + 1);
        $constraints['max_consecutive_days'] = $newMax;

        $result = $this->generationService->doGenerate($month, $preserveFixed, [], $constraints, null, true);

        return [
            'patterns'  => $result['patterns'],
            'conflicts' => $result['conflicts'],
            'description' => "最大連続勤務日数を {$currentMax}日→{$newMax}日 に緩和しました",
        ];
    }

    private function approachEqualizePreferences(string $month, bool $preserveFixed, array $constraints): array
    {
        $parts = explode('-', $month);
        $daysInMonth = (int) \Carbon\Carbon::create((int)$parts[0], (int)$parts[1])->daysInMonth;

        $allPrefs = Preference::where('target_date', 'like', $month . '%')
            ->where('type', 'off')
            ->get();

        $grouped = $allPrefs->groupBy('staff_id');
        $originals = [];

        DB::beginTransaction();
        try {
            foreach ($grouped as $staffId => $prefs) {
                $originals[$staffId] = $prefs->pluck('id')->toArray();
                $count = $prefs->count();
                $deleteIds = $prefs->pluck('id')->toArray();

                Preference::whereIn('id', $deleteIds)->delete();

                $spacing = $daysInMonth / ($count + 1);
                for ($i = 1; $i <= $count; $i++) {
                    $day = (int) round($spacing * $i);
                    $day = max(1, min($daysInMonth, $day));
                    $date = sprintf('%s-%02d', $month, $day);

                    Preference::create([
                        'staff_id'    => $staffId,
                        'target_date' => $date,
                        'type'        => 'off',
                        'priority'    => 'medium',
                    ]);
                }
            }

            $result = $this->generationService->doGenerate($month, $preserveFixed, [], $constraints, null, true);

            Preference::where('target_date', 'like', $month . '%')
                ->where('type', 'off')
                ->delete();

            foreach ($originals as $staffId => $ids) {
                $prefs = $allPrefs->where('staff_id', $staffId);
                foreach ($prefs as $p) {
                    $copy = $p->replicate();
                    $copy->created_at = $p->created_at;
                    $copy->updated_at = $p->updated_at;
                    $copy->save();
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'patterns'  => $result['patterns'],
            'conflicts' => $result['conflicts'],
            'description' => '希望休を月全体に均等に再配置しました（件数維持）',
        ];
    }

    private function approachAddDummyNightStaff(string $month, bool $preserveFixed, array $constraints): array
    {
        $nightShiftType = ShiftType::all()->first(fn($st) => $this->generationService->isNightShift($st));
        if (!$nightShiftType) {
            return [
                'patterns'  => collect(),
                'conflicts' => [['message' => '夜勤シフトが見つかりません']],
                'description' => '夜勤シフトタイプが存在しません',
            ];
        }

        $parts = explode('-', $month);
        $daysInMonth = (int) \Carbon\Carbon::create((int)$parts[0], (int)$parts[1])->daysInMonth;

        DB::beginTransaction();
        try {
            $dummy = Staff::create([
                'name'               => '（自動）夜勤専用',
                'employment_type'    => 'fulltime',
                'can_night_shift'    => true,
                'active'             => true,
                'sort_order'         => 999,
            ]);

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = sprintf('%s-%02d', $month, $day);
                Preference::create([
                    'staff_id'    => $dummy->id,
                    'target_date' => $date,
                    'type'        => 'night_only',
                    'priority'    => 'low',
                ]);
            }

            $result = $this->generationService->doGenerate($month, $preserveFixed, [], $constraints, null, true);

            $dummyStaffData = [
                'id'               => $dummy->id,
                'name'             => $dummy->name,
                'can_night_shift'  => true,
                'active'           => true,
                'employment_type'  => 'fulltime',
            ];

            $dummy->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'patterns'       => $result['patterns'],
            'conflicts'      => $result['conflicts'],
            'description'    => '夜勤専用スタッフ「（自動）夜勤専用」を追加して再生成しました（一時的）',
            'temporary_staff' => $dummyStaffData,
        ];
    }

    private function approachRelaxNightChain(string $month, bool $preserveFixed, array $constraints): array
    {
        $constraints['night_ake_rest_recommended'] = false;

        $result = $this->generationService->doGenerate($month, $preserveFixed, [], $constraints, null, true);

        return [
            'patterns'  => $result['patterns'],
            'conflicts' => $result['conflicts'],
            'description' => '夜勤→明け→休み連鎖を推奨から任意に変更しました',
        ];
    }
}
