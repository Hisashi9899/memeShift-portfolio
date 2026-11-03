'use client';
import { useEffect, useState } from 'react';
import { tokenStorage } from '@/lib/token';
import { RouteStepper } from './RouteStepper';
import type { Conflict } from '@/types';

interface BreakRoute {
  routeId: string;
  routeName: string;
  priority: 'high' | 'medium' | 'low';
  isAutofix: boolean;
  steps: { step: number; description: string }[];
  effect: { candidates: number; penalty: number; will_establish: boolean };
}

interface AutoResolveResult {
  established?: boolean;
  applied?: boolean;
  success?: boolean;
  routeId?: string | null;
  message?: string;
  stepsApplied?: number;
  scheduleEstablished?: boolean;
  remainingConflicts?: number;
  appliedSteps?: { action: string; detail: string }[];
  optimalRoute?: {
    routeId: string;
    routeName: string;
    priority: string;
    selectionReason?: string;
    effect: { candidates: number; penalty: number; will_establish: boolean };
  };
  conflicts?: { date?: string; shift_type?: string; message?: string }[];
}

const PRIORITY_STYLE: Record<string, string> = {
  high: 'bg-red-100 text-red-700',
  medium: 'bg-yellow-100 text-yellow-700',
  low: 'bg-blue-100 text-blue-700',
};
const PRIORITY_LABEL: Record<string, string> = { high: '優先度 高', medium: '優先度 中', low: '優先度 低' };

function buildLocalRoutes(conflicts: Conflict[]): BreakRoute[] {
  const routes: BreakRoute[] = [];
  const hasNightChain = conflicts.some(c => c.message?.includes('夜勤'));
  if (hasNightChain) {
    routes.push({
      routeId: 'local_night_chain',
      routeName: '夜勤連鎖制約の緩和ルート',
      priority: 'high', isAutofix: false,
      steps: [{ step: 1, description: '制約設定で「夜勤→明け→休み（推奨）」を OFF にして再生成' }],
      effect: { candidates: 2, penalty: -100, will_establish: false },
    });
  }
  routes.push({
    routeId: 'local_fixed_shift',
    routeName: '固定シフト崩しルート',
    priority: 'medium', isAutofix: false,
    steps: [{ step: 1, description: '不足日の固定シフトを一時解除して再生成' }],
    effect: { candidates: 3, penalty: -200, will_establish: false },
  });
  routes.push({
    routeId: 'local_preference',
    routeName: '希望休移動ルート',
    priority: 'low', isAutofix: false,
    steps: [{ step: 1, description: '不足日の希望休を別日へ移動して再生成' }],
    effect: { candidates: 1, penalty: -50, will_establish: false },
  });
  return routes;
}

// autoResolve モード用: 適用ルートの steps からハイライト対象セルキーを抽出する。
// バックエンド返却の構造化データを使用し、UI 表示文のパースは行わない。
// キー形式: `${staff_id}-${YYYY-MM-DD}`（ScheduleTable のセルキーと一致）
async function fetchHighlightKeys(month: string, routeId: string | undefined, token: string | null): Promise<string[]> {
  if (!month || !routeId) return [];
  try {
    const res = await fetch(`/api/break-routes?month=${month}`, {
      credentials: 'include',
      headers: token ? { Authorization: `Bearer ${token}` } : undefined,
    });
    if (!res.ok) return [];
    const data = await res.json();
    const route = (data.routes ?? []).find((r: any) => r.routeId === routeId);
    if (!route) return [];
    const keys: string[] = [];
    for (const s of route.steps ?? []) {
      if (s.action === 'move_preference' && s.staff_id != null) {
        if (s.from) keys.push(`${s.staff_id}-${s.from}`);
        if (s.to) keys.push(`${s.staff_id}-${s.to}`);
      } else if (s.action === 'release_fixed_shift' && s.staff_id != null && s.date) {
        keys.push(`${s.staff_id}-${s.date}`);
      }
    }
    return keys;
  } catch {
    return [];
  }
}

// autoResolve モード用: ルート一覧から最適ルート1つを自動選択する。
// 基準（優先順）: 1) 複合ルート（combo_）最優先 2) will_establish=true 優先
// 3) candidates 最大 4) penalty 最小。autofix ルートは除外する。
function selectOptimalRoute(routes: BreakRoute[]): BreakRoute | null {
  const eligible = routes.filter(r => !r.isAutofix);
  if (eligible.length === 0) return null;
  const sorted = [...eligible].sort((a, b) => {
    const aCombo = a.routeId.startsWith('combo_') ? 0 : 1;
    const bCombo = b.routeId.startsWith('combo_') ? 0 : 1;
    if (aCombo !== bCombo) return aCombo - bCombo;
    const aEst = a.effect.will_establish ? 0 : 1;
    const bEst = b.effect.will_establish ? 0 : 1;
    if (aEst !== bEst) return aEst - bEst;
    if (a.effect.candidates !== b.effect.candidates) return b.effect.candidates - a.effect.candidates;
    return a.effect.penalty - b.effect.penalty;
  });
  return sorted[0];
}

export function BreakRoutesPanel({ conflicts, onClose, autoResolve = false }: { conflicts: Conflict[]; onClose: () => void; autoResolve?: boolean }) {
  const [routes, setRoutes] = useState<BreakRoute[]>([]);
  const [loading, setLoading] = useState(true);
  const [applyingRoute, setApplyingRoute] = useState<BreakRoute | null>(null);
  const [resolving, setResolving] = useState(false);
  const [resolveResult, setResolveResult] = useState<AutoResolveResult | null>(null);
  const month = conflicts[0]?.date?.slice(0, 7) ?? '';

  useEffect(() => {
    if (autoResolve) return;
    let cancelled = false;
    (async () => {
      try {
        const month = conflicts[0]?.date?.slice(0, 7) ?? '';
        const token = tokenStorage.get();
        const res = await fetch(`/api/break-routes${month ? `?month=${month}` : ''}`, {
          credentials: 'include',
          headers: token ? { Authorization: `Bearer ${token}` } : undefined,
        });
        if (res.ok) {
          const data = await res.json();
          if (!cancelled) setRoutes(data.routes ?? []);
          return;
        }
      } catch { /* fall through to local fallback */ }
      if (!cancelled) setRoutes(buildLocalRoutes(conflicts));
    })();
    return () => { cancelled = true; };
  }, [conflicts]);

  // autoResolve モード: 明示的な確認クリック時のみ auto-resolve API を呼び出す。
  // マウント時の自動実行は行わない（意図しない多重適用を構造的に防止）。
  // ボタンは適用前（!resolving && !resolveResult）のみ描画されるため、二重送信は発生しない。
  const applyOptimalRoute = async () => {
    if (resolving || resolveResult) return;
    setResolving(true);
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 180000);
    try {
      const m = conflicts[0]?.date?.slice(0, 7) ?? '';
      const token = tokenStorage.get();
      const res = await fetch('/api/auto-resolve', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
        body: JSON.stringify({ month: m }),
        signal: controller.signal,
      });
      const data = await res.json().catch(() => ({}));
      if (res.ok) {
        setResolveResult(data);
        if (data.success) {
          const highlight = await fetchHighlightKeys(m, data.routeId, token);
          window.dispatchEvent(new CustomEvent('memeshift:schedule-regenerated', { detail: { conflicts: data.conflicts ?? [], highlight } }));
        }
      } else {
        setResolveResult({ success: false, message: data.message ?? `エラーが発生しました (${res.status})` });
      }
    } catch (e) {
      if ((e as Error)?.name === 'AbortError') {
        setResolveResult({ success: false, message: 'タイムアウトしました。再試行してください' });
      } else {
        setResolveResult({ success: false, message: '通信エラーが発生しました' });
      }
    } finally {
      clearTimeout(timeoutId);
      setResolving(false);
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" onClick={onClose}>
      <div className="bg-white w-full max-w-lg max-h-[80vh] rounded-xl shadow-xl flex flex-col" onClick={e => e.stopPropagation()}>
        <div className="flex items-center justify-between p-4 border-b border-surface-border">
          <h3 className="text-sm font-semibold text-gray-800">崩しルート一覧（{routes.length}件）</h3>
          <button onClick={onClose} className="text-xs text-gray-500 hover:text-gray-700">閉じる</button>
        </div>
        <div className="flex-1 overflow-y-auto p-3 space-y-2">
          {loading && !autoResolve && <p className="text-xs text-gray-500 p-2">読み込み中...</p>}
          {routes.map(r => (
            <div key={r.routeId} className="p-3 rounded-lg border border-surface-border space-y-1.5">
              <div className="flex items-center justify-between">
                <span className="text-xs font-semibold text-gray-800">
                  {r.routeName}{r.isAutofix && <span className="ml-1 text-[10px] text-primary-600">（autofix）</span>}
                </span>
                <span className={`text-[10px] px-1.5 py-0.5 rounded ${PRIORITY_STYLE[r.priority]}`}>
                  {PRIORITY_LABEL[r.priority]}
                </span>
              </div>
              <p className="text-[11px] text-gray-600 font-mono">
                候補 +{r.effect.candidates}名 / ペナルティ {r.effect.penalty}
              </p>
              {r.steps.map(s => (
                <p key={s.step} className="text-[11px] text-gray-600">Step{s.step}: {s.description}</p>
              ))}
              <button
                type="button"
                onClick={() => setApplyingRoute(r)}
                className="w-full mt-1 px-3 py-1.5 text-xs font-medium rounded-lg bg-primary-600 text-white hover:bg-primary-700 transition-colors"
              >
                このルートで修正する
              </button>
            </div>
          ))}
        </div>
        {autoResolve && (
          <div className="p-3 border-t border-surface-border space-y-1.5">
            {!resolving && !resolveResult && (
              <>
                <p className="text-xs text-gray-600">最適な崩しルートを自動選択して適用します。実行は1回のみです。</p>
                <button
                  type="button"
                  onClick={applyOptimalRoute}
                  className="w-full px-3 py-1.5 text-xs font-medium rounded-lg bg-primary-600 text-white hover:bg-primary-700 transition-colors"
                >
                  この最適ルートを適用する
                </button>
              </>
            )}
            {resolving && <p className="text-xs text-gray-600">最適な崩しを適用しています...</p>}
            {!resolving && resolveResult && (
              <>
                {resolveResult.established && !resolveResult.applied && (
                  <p className="text-xs font-semibold text-emerald-700">崩しは不要です（全日成立しています）</p>
                )}
                {resolveResult.success && (
                  <>
                    <p className="text-xs font-semibold text-emerald-700">
                      {resolveResult.scheduleEstablished ? '✅ 適用完了：勤務表が成立しました' : '⚠️ 適用完了：残り不足があります'}
                    </p>
                    {resolveResult.optimalRoute && (
                      <p className="text-[11px] text-gray-600">
                        {resolveResult.optimalRoute.routeName}（候補 +{resolveResult.optimalRoute.effect.candidates}名）
                      </p>
                    )}
                    {resolveResult.optimalRoute?.selectionReason && (
                      <p className="text-[11px] text-blue-700">
                        選択理由: {resolveResult.optimalRoute.selectionReason}
                      </p>
                    )}
                    {(resolveResult.appliedSteps ?? []).length > 0 && (
                      <div className="space-y-0.5">
                        <p className="text-[11px] font-semibold text-gray-700">変更点:</p>
                        {resolveResult.appliedSteps!.map((s, i) => (
                          <p key={i} className="text-[11px] text-gray-600 font-mono">・{s.detail}</p>
                        ))}
                      </div>
                    )}
                    <p className="text-[11px] text-gray-600">
                      適用ステップ数: {resolveResult.stepsApplied ?? 0}
                      {typeof resolveResult.remainingConflicts === 'number' && `／残り: ${resolveResult.remainingConflicts}件`}
                    </p>
                  </>
                )}
                {!resolveResult.success && !resolveResult.established && (
                  <p className="text-xs text-amber-700">{resolveResult.message ?? '適用できませんでした'}</p>
                )}
              </>
            )}
          </div>
        )}
      </div>
      {applyingRoute && (
        <RouteStepper
          month={month}
          route={applyingRoute}
          onClose={() => setApplyingRoute(null)}
        />
      )}
    </div>
  );
}
