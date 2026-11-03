<?php
namespace App\Services;

use App\Models\SchedulePattern;
use App\Models\ShiftType;
use App\Models\Requirement;
use App\Models\DailyOverride;
use App\Models\Preference;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Break Routes service (NEW FILE).
 * Analyzes the current schedule state (read-only) and proposes
 * "break routes" (constraint relaxation candidates) for shortfalls.
 * Regeneration is delegated to the EXISTING ScheduleGenerationService
 * without any modification to it.
 */
class BreakRouteService
{
    private const AKE_NAME = '明け';

    public function __construct(
        private readonly ScheduleGenerationService $generationService,
        private readonly ConstraintChecker $constraintChecker,
    ) {}

    /**
     * GET /api/break-routes
     * Returns break route candidates derived from current schedule state.
     */
    public function generateBreakRoutes(string $month): array
    {
        $state = $this->analyzeMonth($month);
        $routes = [];

        foreach ($state['shortfalls'] as $sf) {
            $date = $sf['date'];

            if (!empty($sf['fixed_prefs'])) {
                $p = $sf['fixed_prefs'][0];
                $routes[] = $this->buildRoute(
                    "fixed_release_{$p->staff_id}_{$date}_{$sf['shift_type_id']}",
                    '固定シフト崩しルート',
                    'high',
                    false,
                    [[
                        'step' => 1,
                        'action' => 'release_fixed_shift',
                        'preference_id' => $p->id,
                        'staff_id' => $p->staff_id,
                        'date' => $date,
                        'description' => "{$date} の固定シフト希望（スタッフID:{$p->staff_id}）を無視して再生成",
                    ]],
                    ['candidates' => 1, 'penalty' => -200, 'will_establish' => $sf['shortfall'] <= 1],
                    $sf
                );
            }

            if (!empty($sf['off_prefs'])) {
                $p = $sf['off_prefs'][0];
                $toDate = $this->findAlternativeDate($month, $state, $date);
                if ($toDate !== null) {
                    $routes[] = $this->buildRoute(
                        "pref_move_{$p->staff_id}_{$date}_{$sf['shift_type_id']}_{$toDate}",
                        '希望休移動ルート',
                        'medium',
                        false,
                        [[
                            'step' => 1,
                            'action' => 'move_preference',
                            'preference_id' => $p->id,
                            'staff_id' => $p->staff_id,
                            'from' => $date,
                            'to' => $toDate,
                            'description' => "スタッフID:{$p->staff_id} の希望休を {$date} → {$toDate} へ移動して再生成",
                        ]],
                        ['candidates' => 1, 'penalty' => -50, 'will_establish' => $sf['shortfall'] <= 1],
                        $sf
                    );
                }
            }
        }

        // --- Composite routes for shortfall -2 days (NEW: addition only) ---
        // One-click resolution: combine preference move (+1) and fixed shift release (+1)
        // into a single route so the user does not have to apply two routes in sequence.
        foreach ($state['shortfalls'] as $sf) {
            if ($sf['shortfall'] < 2) continue;
            $date = $sf['date'];
            $toDate = $this->findAlternativeDate($month, $state, $date);
            if ($toDate === null) continue;

            $steps = [];
            $penalty = 0;

            if (!empty($sf['off_prefs'])) {
                $p = $sf['off_prefs'][0];
                $steps[] = [
                    'step' => count($steps) + 1,
                    'action' => 'move_preference',
                    'preference_id' => $p->id,
                    'staff_id' => $p->staff_id,
                    'from' => $date,
                    'to' => $toDate,
                    'description' => "スタッフID:{$p->staff_id} の希望休を {$date} → {$toDate} へ移動",
                ];
                $penalty += -50;
            }

            if (!empty($sf['fixed_prefs'])) {
                $p = $sf['fixed_prefs'][0];
                $steps[] = [
                    'step' => count($steps) + 1,
                    'action' => 'release_fixed_shift',
                    'preference_id' => $p->id,
                    'staff_id' => $p->staff_id,
                    'date' => $date,
                    'description' => "{$date} の固定シフト希望（スタッフID:{$p->staff_id}）を無視",
                ];
                $penalty += -200;
            }

            // Fallback: when no fixed pref exists, use a second staff's off preference
            if (count($steps) === 1 && count($sf['off_prefs']) >= 2) {
                $p = $sf['off_prefs'][1];
                $steps[] = [
                    'step' => count($steps) + 1,
                    'action' => 'move_preference',
                    'preference_id' => $p->id,
                    'staff_id' => $p->staff_id,
                    'from' => $date,
                    'to' => $toDate,
                    'description' => "スタッフID:{$p->staff_id} の希望休を {$date} → {$toDate} へ移動",
                ];
                $penalty += -50;
            }

            if (count($steps) < 2) continue; // cannot reach +2 with available data

            $hasFixed = !empty($sf['fixed_prefs']);
            $routes[] = $this->buildRoute(
                "combo_{$date}_{$sf['shift_type_id']}",
                $hasFixed ? '複合ルート: 希望休移動 + 固定シフト解除' : '複合ルート: 希望休移動 ×2',
                'high',
                false,
                $steps,
                ['candidates' => 2, 'penalty' => $penalty, 'will_establish' => true],
                $sf
            );
        }

        $nightChain = $this->detectNightChain($state);
        if ($nightChain !== null) {
            $routes[] = $this->buildRoute(
                'night_chain_relief',
                '夜勤連鎖制約の緩和ルート',
                'high',
                false,
                [[
                    'step' => 1,
                    'action' => 'relax_night_chain',
                    'description' => '制約設定で「夜勤→明け→休み（推奨）」を OFF にして再生成（設定変更は設定画面から実施）',
                ]],
                ['candidates' => 2, 'penalty' => -100, 'will_establish' => false],
                $nightChain
            );
        }

        if (!empty($state['shortfalls'])) {
            $routes[] = $this->buildRoute(
                'autofix_existing',
                'オートフィックス（既存）',
                'medium',
                true,
                [[
                    'step' => 1,
                    'action' => 'autofix',
                    'description' => '既存の自動修正 API（POST /api/schedule/auto-fix）を実行',
                ]],
                ['candidates' => 1, 'penalty' => -50, 'will_establish' => false],
                $state['shortfalls'][0]
            );
        }

        return $routes;
    }

    /**
     * POST /api/apply-route
     * Applies the route steps, then regenerates the schedule via the
     * EXISTING ScheduleGenerationService::doGenerate (logic untouched).
     */
    public function applyRoute(string $month, string $routeId): array
    {
        $route = $this->findRoute($month, $routeId);
        if ($route === null) {
            return ['success' => false, 'routeId' => $routeId, 'message' => "Route not found: {$routeId}", 'stepsApplied' => 0];
        }

        $ignoredPreferenceIds = [];
        $applied = [];

        foreach ($route['steps'] as $step) {
            switch ($step['action']) {
                case 'release_fixed_shift':
                    $ignoredPreferenceIds[] = (int) $step['preference_id'];
                    $applied[] = ['action' => 'release_fixed_shift', 'detail' => $step['description']];
                    break;

                case 'move_preference':
                    $pref = Preference::find((int) $step['preference_id']);
                    if (!$pref) {
                        return ['success' => false, 'routeId' => $routeId, 'message' => "Preference not found: {$step['preference_id']}", 'stepsApplied' => 0];
                    }
                    $pref->target_date = $step['to'];
                    $pref->save();
                    $applied[] = ['action' => 'move_preference', 'detail' => $step['description']];
                    break;

                case 'autofix':
                    return [
                        'success' => false,
                        'routeId' => $routeId,
                        'message' => 'このルートは既存の自動修正 API（POST /api/schedule/auto-fix）を使用してください。',
                        'stepsApplied' => 0,
                    ];

                case 'relax_night_chain':
                    return [
                        'success' => false,
                        'routeId' => $routeId,
                        'message' => 'このルートは制約設定の変更が必要です。設定画面で「夜勤→明け→休み（推奨）」を OFF にしてから再生成してください。',
                        'stepsApplied' => 0,
                    ];

                default:
                    return ['success' => false, 'routeId' => $routeId, 'message' => "Unsupported action: {$step['action']}", 'stepsApplied' => 0];
            }
        }

        // Regenerate with existing logic (preserveFixed=false, ignored prefs passed through)
        $result = $this->generationService->doGenerate($month, false, $ignoredPreferenceIds);

        $conflicts = collect($result['conflicts'] ?? []);
        $remaining = $conflicts->filter(fn($c) => ($c['shift_type'] ?? '') !== '生成失敗')->count();

        // 監査ログ: 適用履歴の追跡用（振る舞い変更なし）。binlog 解析なしで移動履歴を特定できるようにする。
        info("applyRoute applied routeId={$routeId} month={$month} steps=" . json_encode($applied, JSON_UNESCAPED_UNICODE));

        return [
            'success' => true,
            'routeId' => $routeId,
            'stepsApplied' => count($applied),
            'appliedSteps' => $applied,
            'scheduleEstablished' => $remaining === 0,
            'remainingConflicts' => $remaining,
            'conflicts' => $result['conflicts'] ?? [],
        ];
    }

    /**
     * GET /api/route-diff
     * Returns before/after prediction for a route (read-only).
     */
    public function routeDiff(string $month, string $routeId): array
    {
        $route = $this->findRoute($month, $routeId);
        if ($route === null) {
            return ['error' => "Route not found: {$routeId}"];
        }

        $ctx = $route['context'];
        $before = $this->assignedCount($month, $ctx['date'], $ctx['shift_type_id']);
        $required = (int) $ctx['required'];
        $after = min($required, $before + max(0, (int) $route['effect']['candidates']));

        return [
            'routeId' => $routeId,
            'before' => [
                'assigned' => $before,
                'required' => $required,
                'status' => $before >= $required ? 'established' : 'error',
            ],
            'after' => [
                'assigned' => $after,
                'required' => $required,
                'status' => $after >= $required ? 'established' : 'error',
            ],
            'differences' => [
                'assigned' => $after - $before,
                'status' => ($before < $required && $after >= $required) ? 'error → established' : 'unchanged',
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Internal helpers (read-only analysis)
    // ------------------------------------------------------------------

    private function findRoute(string $month, string $routeId): ?array
    {
        foreach ($this->generateBreakRoutes($month) as $r) {
            if ($r['routeId'] === $routeId) return $r;
        }
        return null;
    }

    private function buildRoute(string $id, string $name, string $priority, bool $isAutofix, array $steps, array $effect, array $context): array
    {
        return [
            'routeId' => $id,
            'routeName' => $name,
            'priority' => $priority,
            'isAutofix' => $isAutofix,
            'steps' => $steps,
            'effect' => $effect,
            'context' => [
                'date' => $context['date'],
                'shift' => $context['shift_name'],
                'shift_type_id' => $context['shift_type_id'],
                'required' => $context['required'],
                'assigned' => $context['assigned'],
                'shortfall' => $context['shortfall'],
            ],
        ];
    }

    private function analyzeMonth(string $month): array
    {
        $shiftTypes = ShiftType::orderBy('sort_order')->get();
        $requirements = Requirement::get()->keyBy('shift_type_id');
        $overrides = DailyOverride::where('target_date', 'like', $month . '%')->get()
            ->keyBy(fn($o) => substr((string) $o->target_date, 0, 10) . '-' . $o->shift_type_id);

        Staff::where('active', true)->get();

        $prefs = Preference::where('target_date', 'like', $month . '%')->get()
            ->groupBy(fn($p) => $p->staff_id . '-' . substr((string) $p->target_date, 0, 10));

        $pattern = SchedulePattern::where('month_year', $month)->orderBy('pattern_number')->first();
        $cells = $pattern ? $pattern->cells()->get() : collect();

        $assignedMap = [];
        foreach ($cells as $c) {
            if ($c->shift_type_id === null) continue;
            $d = substr((string) $c->target_date, 0, 10);
            $assignedMap[$d][$c->shift_type_id] = ($assignedMap[$d][$c->shift_type_id] ?? 0) + 1;
        }

        $daysInMonth = Carbon::createFromFormat('Y-m', $month)->daysInMonth;
        $shortfalls = [];
        $neededByDate = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%s-%02d', $month, $day);
            $neededTotal = 0;

            foreach ($shiftTypes as $st) {
                if ($st->name === self::AKE_NAME) continue;

                $override = $overrides->get($date . '-' . $st->id);
                $required = (int) ($override->count ?? ($requirements->get($st->id)->count ?? 1));
                $assigned = (int) ($assignedMap[$date][$st->id] ?? 0);
                $neededTotal += $required;

                if ($assigned >= $required) continue;

                $dayPrefs = $prefs->filter(fn($group, $key) => str_ends_with($key, '-' . $date))->flatten(1);

                $shortfalls[] = [
                    'date' => $date,
                    'shift_name' => $st->name,
                    'shift_type_id' => $st->id,
                    'required' => $required,
                    'assigned' => $assigned,
                    'shortfall' => $required - $assigned,
                    'off_prefs' => $dayPrefs->where('type', 'off')->values()->all(),
                    'fixed_prefs' => $dayPrefs->where('type', 'fixed_shift')->values()->all(),
                ];
            }

            $neededByDate[$date] = $neededTotal;
        }

        return ['shortfalls' => $shortfalls, 'neededByDate' => $neededByDate, 'daysInMonth' => $daysInMonth, 'month' => $month];
    }

    private function detectNightChain(array $state): ?array
    {
        $byDate = [];
        foreach ($state['shortfalls'] as $sf) {
            $byDate[$sf['date']][] = $sf;
        }
        $dates = array_keys($byDate);
        sort($dates);

        for ($i = 0; $i < count($dates) - 1; $i++) {
            $d1 = Carbon::createFromFormat('Y-m-d', $dates[$i]);
            $d2 = Carbon::createFromFormat('Y-m-d', $dates[$i + 1]);
            if (!$d1->isSameDay($d2->copy()->subDay())) continue;

            foreach ($byDate[$dates[$i]] as $a) {
                foreach ($byDate[$dates[$i + 1]] as $b) {
                    if ($a['shift_type_id'] === $b['shift_type_id']) {
                        return $b;
                    }
                }
            }
        }
        return null;
    }

    private function findAlternativeDate(string $month, array $state, string $excludeDate): ?string
    {
        $best = null;
        $bestNeeded = PHP_INT_MAX;
        foreach ($state['neededByDate'] as $date => $needed) {
            if ($date === $excludeDate) continue;
            if ($needed < $bestNeeded) {
                $bestNeeded = $needed;
                $best = $date;
            }
        }
        return $best;
    }

    private function assignedCount(string $month, string $date, int $shiftTypeId): int
    {
        $pattern = SchedulePattern::where('month_year', $month)->orderBy('pattern_number')->first();
        if (!$pattern) return 0;
        return $pattern->cells()
            ->whereDate('target_date', $date)
            ->where('shift_type_id', $shiftTypeId)
            ->count();
    }

    /**
     * NEW: 数学的成立判定 — 現在の保存済み勤務表（=直近のdoGenerate結果）を
     * 読み取り専用で分析し、全日が成立しているかを判定する。副作用なし。
     */
    public function isEstablished(string $month): bool
    {
        return empty($this->analyzeMonth($month)['shortfalls']);
    }

    /**
     * NEW: 不成立時の最適崩しルート自動選択。
     * 基準（優先順）: 1) 複合ルート（combo_）最優先 2) will_establish=true 優先
     * 3) candidates 最大 4) penalty 最小（最も負=削減幅最大）。
     * applyRoute で適用できない autofix ルートは選択対象から除外する。
     */
    public function selectOptimalRoute(array $routes): ?array
    {
        $eligible = array_values(array_filter($routes, fn($r) => empty($r['isAutofix'])));
        if (empty($eligible)) return null;

        usort($eligible, function ($a, $b) {
            $aCombo = str_starts_with($a['routeId'], 'combo_') ? 0 : 1;
            $bCombo = str_starts_with($b['routeId'], 'combo_') ? 0 : 1;
            if ($aCombo !== $bCombo) return $aCombo <=> $bCombo;
            $aEst = !empty($a['effect']['will_establish']) ? 0 : 1;
            $bEst = !empty($b['effect']['will_establish']) ? 0 : 1;
            if ($aEst !== $bEst) return $aEst <=> $bEst;
            if ($a['effect']['candidates'] !== $b['effect']['candidates']) {
                return $b['effect']['candidates'] <=> $a['effect']['candidates'];
            }
            return $a['effect']['penalty'] <=> $b['effect']['penalty'];
        });

        return $eligible[0];
    }

    /**
     * NEW: 最適ルート選択理由の構築（透明性強化・返却値の付加情報のみ）。
     * selectOptimalRoute と同一の優先順位で次点と比較し、決定打となった基準を返す。
     * 既存の選択ロジック自体は変更しない。
     */
    private function buildSelectionReason(array $winner, ?array $runnerUp): string
    {
        if ($runnerUp === null) {
            return '適用可能なルートが1件のため選択';
        }
        $wCombo = str_starts_with($winner['routeId'], 'combo_');
        $rCombo = str_starts_with($runnerUp['routeId'], 'combo_');
        if ($wCombo && !$rCombo) return '複合ルートのため優先';
        $wEst = !empty($winner['effect']['will_establish']);
        $rEst = !empty($runnerUp['effect']['will_establish']);
        if ($wEst && !$rEst) return 'will_establish=true のため選択';
        if ($winner['effect']['candidates'] !== $runnerUp['effect']['candidates']) {
            return '候補数 +' . $winner['effect']['candidates'] . ' のため選択';
        }
        if ($winner['effect']['penalty'] !== $runnerUp['effect']['penalty']) {
            return 'penalty が最小のため選択';
        }
        return '優先度が最も高いため選択';
    }

    /**
     * NEW: 完全2択フロー — 成立ならそのまま返し、不成立なら最適ルート1つを自動適用する。
     */
    public function autoResolve(string $month): array
    {
        // 構造的再発防止: 短時間の連続実行を拒否する（正常な反復利用は1クリック/10〜30秒のため閾値以下）。
        // ループ状の多重リクエストはここで遮断され、既存処理には一切到達しない。
        $rateKey = "auto-resolve:{$month}";
        if (RateLimiter::tooManyAttempts($rateKey, 8)) {
            return [
                'established' => false,
                'applied' => false,
                'routeId' => null,
                'message' => '短時間に複数回実行されました。結果を確認してから再度お試しください。',
            ];
        }
        RateLimiter::hit($rateKey, 60);

        $lock = Cache::lock("auto-resolve-{$month}", 300);
        if (!$lock->get()) {
            return [
                'established' => false,
                'applied' => false,
                'routeId' => null,
                'message' => '他の自動解決が実行中です。完了後に再度お試しください。',
            ];
        }
        try {
            if ($this->isEstablished($month)) {
                return [
                    'established' => true,
                    'applied' => false,
                    'routeId' => null,
                    'message' => '勤務表は全日成立しています。崩しは不要です。',
                ];
            }

            // 成功適用のレート制限: 短時間の連続適用を遮断する（参照・ガイダンス応答は数えない）。
            // 正常な反復利用（1クリック/10〜30秒）には当たらない閾値（5分間に5回成功）。
            $successKey = "auto-resolve-success:{$month}";
            if (RateLimiter::tooManyAttempts($successKey, 5)) {
                return [
                    'established' => false,
                    'applied' => false,
                    'routeId' => null,
                    'message' => '短時間に複数回の適用が行われました。結果を確認してから再度お試しください。',
                ];
            }

        $routes = $this->generateBreakRoutes($month);
        $optimal = $this->selectOptimalRoute($routes);
        if ($optimal === null) {
            return [
                'established' => false,
                'applied' => false,
                'routeId' => null,
                'message' => '適用可能な崩しルートが見つかりませんでした。',
            ];
        }

        $runnerUpRoutes = array_values(array_filter($routes, fn($r) => $r['routeId'] !== $optimal['routeId']));
        $runnerUp = $this->selectOptimalRoute($runnerUpRoutes);

        $result = $this->applyRoute($month, $optimal['routeId']);
        if (!empty($result['success'])) {
            RateLimiter::hit($successKey, 300);
        }
        $result['optimalRoute'] = [
            'routeId' => $optimal['routeId'],
            'routeName' => $optimal['routeName'],
            'priority' => $optimal['priority'],
            'effect' => $optimal['effect'],
            'selectionReason' => $this->buildSelectionReason($optimal, $runnerUp),
        ];
        return $result;
        } finally {
            $lock->release();
        }
    }
}
