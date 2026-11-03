<?php
namespace App\Services;

use App\Models\Requirement;
use App\Models\SchedulePattern;
use App\Models\ShiftType;

class ScheduleAutoFixOrchestrator
{
    private const APPROACH_ORDER = [
        'reduce_requirements',
        'equalize_preferences',
        'relax_consecutive_days',
        'relax_night_chain',
        'add_dummy_night_staff',
    ];

    public function __construct(
        private ScheduleAutoFixService $autoFixService,
        private ScheduleFailureExplanationService $failureExplanationService,
    ) {}

    public function run(string $month, bool $preserveFixed, array $constraints): array
    {
        $bestResult = null;
        $bestApproach = null;
        $bestConflictCount = PHP_INT_MAX;
        $approachResults = [];
        $baselineConflictCount = null;

        foreach (self::APPROACH_ORDER as $approach) {
            $result = $this->autoFixService->applyApproach($month, $preserveFixed, $constraints, $approach);
            $conflictCount = count($result['conflicts'] ?? []);

            if ($baselineConflictCount === null) {
                $baselineConflictCount = $conflictCount;
            }

            $approachResults[$approach] = [
                'description'    => $result['description'] ?? '',
                'conflict_count' => $conflictCount,
            ];

            if ($conflictCount < $bestConflictCount) {
                $bestConflictCount = $conflictCount;
                $bestResult = $result;
                $bestApproach = $approach;
            }
        }

        // Re-generate with the best approach so DB state matches the returned result
        if ($bestApproach !== null) {
            $finalResult = $this->autoFixService->applyApproach($month, $preserveFixed, $constraints, $bestApproach);
            $bestResult = $finalResult;
            $bestConflictCount = count($finalResult['conflicts'] ?? []);
        }

        $success = $bestConflictCount === 0;
        $showRecommendation = $bestConflictCount <= 3;

        // Build approach_results array with applied flags
        $approachResultsList = [];
        foreach (self::APPROACH_ORDER as $approach) {
            $isBest = $approach === $bestApproach;
            $approachResultsList[] = [
                'name'           => $approach,
                'description'    => $approachResults[$approach]['description'] ?? '',
                'conflict_count' => $approachResults[$approach]['conflict_count'] ?? 0,
                'applied'        => $success && $isBest,
            ];
        }

        $response = [
            'patterns'             => $bestResult['patterns'] ?? collect(),
            'conflicts'            => $bestResult['conflicts'] ?? [],
            'success'              => $success,
            'show_recommendation'  => $showRecommendation,
            'applied_approaches'   => $success ? [$bestApproach] : [],
            'approach_results'     => $approachResultsList,
            'applied_changes'      => $success ? $this->generateAppliedChanges($bestApproach, $month, $constraints) : [],
            'recommended_approach' => $bestApproach,
            'recommended_description' => $approachResults[$bestApproach]['description'] ?? '',
            'recommended_effect'   => $this->generateEffectDescription($bestApproach, $month, $constraints),
            'recommended_confidence' => max(0, min(100, 100 - ($bestConflictCount * 20))),
        ];

        if (!$success && $this->isAutoFixLimitReached($baselineConflictCount, $bestConflictCount, $approachResults)) {
            $explanations = $this->failureExplanationService->explain($month, $constraints);
            $response['auto_fix_limit_reached'] = true;
            $response['manual_fix_summary'] = $explanations['summary'] ?? '';
            $response['manual_fix_suggestions'] = $this->generateManualFixSuggestions(
                $explanations['explanations'] ?? [],
                $month,
                $bestResult['conflicts'] ?? []
            );
        }

        return $response;
    }

    private function generateAppliedChanges(string $approach, string $month, array $constraints): array
    {
        return match ($approach) {
            'reduce_requirements' => $this->changesReduceRequirements($month),
            'relax_consecutive_days' => $this->changesRelaxConsecutiveDays($constraints),
            'equalize_preferences' => [
                ['action' => '希望再配置', 'detail' => '希望休を月全体に均等に配置（件数維持）', 'target' => 'preferences'],
            ],
            'add_dummy_night_staff' => [
                ['action' => '仮スタッフ追加', 'detail' => '夜勤専用スタッフ「（自動）夜勤専用」を1名追加', 'target' => 'staff'],
            ],
            'relax_night_chain' => [
                ['action' => '制約緩和', 'detail' => '夜勤→明け→休み（推奨）をOFF', 'target' => 'night_ake_rest_recommended'],
            ],
            default => [],
        };
    }

    private function changesReduceRequirements(string $month): array
    {
        $requirements = Requirement::all();
        $shiftTypes = ShiftType::all()->keyBy('id');
        $changes = [];
        foreach ($requirements as $req) {
            $st = $shiftTypes->get($req->shift_type_id);
            if (!$st || in_array($st->name, ['明け'], true)) continue;
            $min = $st->name === '夜勤' ? 0 : 1;
            $newCount = max($min, $req->count - 1);
            if ($newCount !== $req->count) {
                $changes[] = [
                    'action' => '必要人数削減',
                    'detail' => "{$st->name}: {$req->count}名→{$newCount}名",
                    'target' => 'requirements',
                ];
            }
        }
        return $changes;
    }

    private function changesRelaxConsecutiveDays(array $constraints): array
    {
        $current = $constraints['max_consecutive_days'] ?? 5;
        $newMax = min(6, $current + 1);
        return [
            ['action' => '制約緩和', 'detail' => "最大連続勤務日数: {$current}日→{$newMax}日", 'target' => 'max_consecutive_days'],
        ];
    }

    private function isAutoFixLimitReached(int $baseline, int $best, array $approachResults): bool
    {
        // Only show manual fix guide when many conflicts remain (≥4).
        // For fewer conflicts, always show the recommendation card so the user
        // can apply a single approach even if improvement is small.
        return $best >= 4;
    }

    private function generateManualFixSuggestions(array $explanations, string $month, array $conflicts): array
    {
        $suggestions = [];
        $fallbackDate = $conflicts[0]['date'] ?? $month . '-15';

        foreach ($explanations as $e) {
            $type = $e['type'] ?? '';
            $reason = $e['message'] ?? '';
            $date = $e['suggested_date'] ?? '';

            $suggestion = match ($type) {
                'night_shortage_staff' => [
                    'shift' => '夜勤',
                    'date' => $date ?: $fallbackDate,
                    'reason' => $reason,
                    'suggestion' => '夜勤可能なスタッフを追加登録するか、夜勤の必要人数を減らしてください',
                    'jump_type' => 'settings',
                    'jump_payload' => [
                        'shift_type_id'   => $e['shift_type_id'] ?? null,
                        'shift_type_name' => $e['shift_type_name'] ?? '夜勤',
                    ],
                ],
                'night_chain_depletion' => [
                    'shift' => '夜勤',
                    'date' => $date ?: $fallbackDate,
                    'reason' => $reason,
                    'suggestion' => '制約設定で「夜勤→明け→休み（推奨）」をOFFにして再作成してください',
                    'jump_type' => 'constraints',
                    'jump_payload' => [
                        'constraint_key' => $e['constraint_key'] ?? 'night_ake_rest_recommended',
                    ],
                ],
                'preference_clustering_early' => [
                    'shift' => '勤務表全体',
                    'date' => $date ?: $fallbackDate,
                    'reason' => $reason,
                    'suggestion' => 'スタッフに希望休を月全体に分散するよう依頼してください',
                    'jump_type' => 'preferences',
                    'jump_payload' => [
                        'date' => $date ?: $fallbackDate,
                    ],
                ],
                'preference_clustering_late' => [
                    'shift' => '勤務表全体',
                    'date' => $date ?: $fallbackDate,
                    'reason' => $reason,
                    'suggestion' => 'スタッフに希望休を月全体に分散するよう依頼してください',
                    'jump_type' => 'preferences',
                    'jump_payload' => [
                        'date' => $date ?: $fallbackDate,
                    ],
                ],
                'individual_many_off' => [
                    'shift' => '勤務表全体',
                    'date' => $date ?: $fallbackDate,
                    'reason' => $reason,
                    'suggestion' => '該当スタッフの希望休日数を減らすか、他のスタッフに分散してください',
                    'jump_type' => 'preferences',
                    'jump_payload' => [
                        'staff_id'   => $e['staff_id'] ?? null,
                        'staff_name' => $e['staff_name'] ?? '',
                        'date'       => $date ?: $fallbackDate,
                    ],
                ],
                'consecutive_limit_too_strict' => [
                    'shift' => '勤務表全体',
                    'date' => $date ?: $fallbackDate,
                    'reason' => $reason,
                    'suggestion' => '制約設定の「最大連続勤務日数」を増やしてください',
                    'jump_type' => 'constraints',
                    'jump_payload' => [
                        'constraint_key' => $e['constraint_key'] ?? 'max_consecutive_days',
                    ],
                ],
                'requirement_overload' => [
                    'shift' => '勤務表全体',
                    'date' => $date ?: $fallbackDate,
                    'reason' => $reason,
                    'suggestion' => '必要人数を減らすか、スタッフを追加登録してください',
                    'jump_type' => 'settings',
                ],
                'staff_shortage' => [
                    'shift' => '勤務表全体',
                    'date' => $date ?: $fallbackDate,
                    'reason' => $reason,
                    'suggestion' => 'スタッフを追加登録してください',
                    'jump_type' => 'settings',
                ],
                default => [
                    'shift' => '勤務表全体',
                    'date' => $date ?: $fallbackDate,
                    'reason' => $reason,
                    'suggestion' => '必要条件や制約を見直してください',
                    'jump_type' => 'cell',
                ],
            };

            $impact = $e['impact'] ?? 50;
            $suggestions[] = array_merge($suggestion, ['impact' => $impact]);
        }

        if (empty($suggestions) && !empty($conflicts)) {
            foreach ($conflicts as $c) {
                $date = $c['date'] ?? '';
                $shift = $c['shift_type'] ?? '';
                $message = $c['message'] ?? '';

                $suggestions[] = [
                    'shift' => $shift,
                    'date' => $date,
                    'reason' => $message,
                    'suggestion' => 'スタッフのシフトを手動で調整するか、必要人数を見直してください',
                    'jump_type' => 'cell',
                ];
            }
        }

        return $suggestions;
    }

    private function generateEffectDescription(string $approach, string $month, array $constraints): string
    {
        return match ($approach) {
            'reduce_requirements'    => $this->describeReduceRequirements($month),
            'relax_consecutive_days' => $this->describeRelaxConsecutiveDays($constraints),
            'equalize_preferences'   => '希望休を月全体に均等に配置します（件数維持）',
            'add_dummy_night_staff'  => '夜勤専用の仮スタッフ「（自動）夜勤専用」を1名追加します',
            'relax_night_chain'      => '夜勤→明け→休みの制約を「推奨」に変更します',
            default                  => '',
        };
    }

    private function describeReduceRequirements(string $month): string
    {
        $requirements = Requirement::all();
        $shiftTypes = ShiftType::all()->keyBy('id');
        $parts = [];
        foreach ($requirements as $req) {
            $st = $shiftTypes->get($req->shift_type_id);
            if (!$st || in_array($st->name, ['明け'], true)) continue;
            $min = $st->name === '夜勤' ? 0 : 1;
            $newCount = max($min, $req->count - 1);
            if ($newCount !== $req->count) {
                $parts[] = "{$st->name}: {$req->count}名→{$newCount}名";
            }
        }
        return implode(', ', $parts);
    }

    private function describeRelaxConsecutiveDays(array $constraints): string
    {
        $current = $constraints['max_consecutive_days'] ?? 5;
        $newMax = min(6, $current + 1);
        return "最大連続勤務日数: {$current}日→{$newMax}日";
    }
}
