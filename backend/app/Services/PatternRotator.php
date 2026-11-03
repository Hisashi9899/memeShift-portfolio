<?php
namespace App\Services;

use App\Models\SchedulePattern;
use App\Models\ScheduleCell;
use Illuminate\Support\Collection;

class PatternRotator
{
    public function rotateAndDeduplicatePatterns(string $month, Collection $shiftTypes): void
    {
        $basePattern = SchedulePattern::where('month_year', $month)
            ->where('pattern_number', 1)
            ->first();

        if (!$basePattern) return;

        $baseCells = ScheduleCell::where('schedule_pattern_id', $basePattern->id)->get();
        $sig = $baseCells->map(fn($c) => $c->staff_id . '-' . $c->target_date . '-' . ($c->shift_type_id ?? 'null'))->sort()->implode('|');
        $patternSignatures = [1 => $sig];

        for ($p = 2; $p <= 20; $p++) {
            $pattern = SchedulePattern::firstOrCreate(
                ['month_year' => $month, 'pattern_number' => $p],
                ['is_base' => false]
            );

            $existingFixed = ScheduleCell::where('schedule_pattern_id', $pattern->id)
                ->where('is_fixed', true)
                ->get()
                ->keyBy(fn($c) => $c->staff_id . '-' . $c->target_date);

            ScheduleCell::where('schedule_pattern_id', $pattern->id)
                ->where('is_fixed', false)
                ->delete();

            $newCells = [];
            foreach ($baseCells as $bc) {
                $key = $bc->staff_id . '-' . $bc->target_date;
                if (isset($existingFixed[$key])) continue;

                $newShiftTypeId = $bc->shift_type_id;
                if ($bc->shift_type_id && $shiftTypes->count() > 1) {
                    $stKeys = $shiftTypes->where('name', '!=', '明け')->pluck('id')->toArray();
                    $keyCount = count($stKeys);
                    if ($keyCount > 0) {
                        $pos = array_search($bc->shift_type_id, $stKeys);
                        if ($pos !== false) {
                            $newPos = ($pos + $p - 1) % $keyCount;
                            $newShiftTypeId = $stKeys[$newPos];
                        }
                    }
                }
                $newCells[] = [
                    'schedule_pattern_id' => $pattern->id,
                    'staff_id' => $bc->staff_id,
                    'target_date' => $bc->target_date,
                    'shift_type_id' => $newShiftTypeId,
                    'is_fixed' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (empty($newCells)) {
                if ($existingFixed->isNotEmpty()) {
                    $patternSignatures[$p] = $existingFixed->map(fn($c) => $c->staff_id . '-' . $c->target_date . '-' . ($c->shift_type_id ?? 'null'))->sort()->implode('|');
                    continue;
                }
            } else {
                ScheduleCell::insert($newCells);
            }

            $allCells = ScheduleCell::where('schedule_pattern_id', $pattern->id)->get();
            $newSig = $allCells->map(fn($c) => $c->staff_id . '-' . $c->target_date . '-' . ($c->shift_type_id ?? 'null'))->sort()->implode('|');

            if ($existingFixed->isNotEmpty()) {
                $patternSignatures[$p] = $newSig;
            } else {
                $isDuplicate = false;
                foreach ($patternSignatures as $existingSig) {
                    if ($newSig === $existingSig) {
                        $isDuplicate = true;
                        break;
                    }
                }

                if ($isDuplicate) {
                    ScheduleCell::where('schedule_pattern_id', $pattern->id)->delete();
                    $pattern->delete();
                    break;
                } else {
                    $patternSignatures[$p] = $newSig;
                }
            }
        }

        $allPatterns = SchedulePattern::where('month_year', $month)->orderBy('pattern_number')->get();
        $num = 1;
        foreach ($allPatterns as $ap) {
            if ($ap->pattern_number !== $num) {
                $ap->pattern_number = $num;
                $ap->save();
            }
            $num++;
        }

        // Generate labels for all patterns
        $rotatableShiftTypes = $shiftTypes->where('name', '!=', '明け')->values();
        foreach ($allPatterns as $ap) {
            $label = $this->computePatternLabel($ap, $basePattern, $rotatableShiftTypes);
            if ($label !== null && $ap->label !== $label) {
                $ap->label = $label;
                $ap->save();
            }
        }
    }

    private function computePatternLabel(SchedulePattern $pattern, SchedulePattern $basePattern, Collection $rotatableShiftTypes): ?string
    {
        if ($pattern->is_base) return '標準（基準パターン）';
        if ($rotatableShiftTypes->isEmpty()) return null;

        $count = $rotatableShiftTypes->count();
        $shiftIds = $rotatableShiftTypes->pluck('id')->toArray();
        $shiftNames = $rotatableShiftTypes->pluck('name')->toArray();

        // Determine rotation offset by comparing one non-fixed cell
        $baseCell = ScheduleCell::where('schedule_pattern_id', $basePattern->id)
            ->where('is_fixed', false)
            ->whereNotNull('shift_type_id')
            ->first();

        if (!$baseCell) return null;

        $currentCell = ScheduleCell::where('schedule_pattern_id', $pattern->id)
            ->where('staff_id', $baseCell->staff_id)
            ->where('target_date', $baseCell->target_date)
            ->first();

        if (!$currentCell || !$currentCell->shift_type_id) return null;

        $basePos = array_search($baseCell->shift_type_id, $shiftIds);
        $currentPos = array_search($currentCell->shift_type_id, $shiftIds);

        if ($basePos === false || $currentPos === false) return null;

        $offset = ($currentPos - $basePos + $count) % $count;
        if ($offset === 0) return '標準';

        // Build a description from the rotation mapping
        $mapping = [];
        for ($i = 0; $i < $count; $i++) {
            $toIdx = ($i + $offset) % $count;
            $mapping[] = "{$shiftNames[$i]}→{$shiftNames[$toIdx]}";
        }

        // Use the first 2 transitions for brevity
        $desc = implode(', ', array_slice($mapping, 0, 2));
        if ($count > 2) $desc .= " 他" . ($count - 2) . "種";

        return "{$desc}（+{$offset}）";
    }

    public function loadFinalPatterns(string $month): Collection
    {
        return SchedulePattern::with('cells')
            ->where('month_year', $month)
            ->orderBy('pattern_number')
            ->get();
    }
}
