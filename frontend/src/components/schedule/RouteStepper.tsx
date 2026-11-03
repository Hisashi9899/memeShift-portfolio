'use client';
import { useEffect, useState } from 'react';
import { tokenStorage } from '@/lib/token';

interface BreakRoute {
  routeId: string;
  routeName: string;
  priority: 'high' | 'medium' | 'low';
  isAutofix: boolean;
  steps: { step: number; description: string }[];
  effect: { candidates: number; penalty: number; will_establish: boolean };
}

interface RouteDiff {
  routeId: string;
  before: { assigned: number; required: number; status: string };
  after: { assigned: number; required: number; status: string };
  differences: { assigned: number; status: string };
}

interface ApplyResult {
  success: boolean;
  message?: string;
  stepsApplied?: number;
  appliedSteps?: { action: string; detail: string }[];
  scheduleEstablished?: boolean;
  remainingConflicts?: number;
  conflicts?: { date: string; shift_type: string; required: number; assigned: number; message: string }[];
}

const PRIORITY_LABEL: Record<string, string> = { high: '優先度 高', medium: '優先度 中', low: '優先度 低' };

export function RouteStepper({ month, route, onClose }: { month: string; route: BreakRoute; onClose: () => void }) {
  const [diff, setDiff] = useState<RouteDiff | null>(null);
  const [applying, setApplying] = useState(false);
  const [result, setResult] = useState<ApplyResult | null>(null);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const token = tokenStorage.get();
        const res = await fetch(`/api/route-diff?month=${month}&routeId=${encodeURIComponent(route.routeId)}`, {
          credentials: 'include',
          headers: token ? { Authorization: `Bearer ${token}` } : undefined,
        });
        if (res.ok && !cancelled) setDiff(await res.json());
      } catch { /* diff is optional information */ }
    })();
    return () => { cancelled = true; };
  }, [month, route.routeId]);

  const apply = async () => {
    setApplying(true);
    try {
      const token = tokenStorage.get();
      const res = await fetch('/api/apply-route', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
        body: JSON.stringify({ month, routeId: route.routeId }),
      });
      const data: ApplyResult = await res.json();
      setResult(data);
      if (res.ok && data.success) {
        window.dispatchEvent(new CustomEvent('memeshift:schedule-regenerated', { detail: data.conflicts ?? [] }));
      }
    } catch {
      setResult({ success: false, message: '通信エラーが発生しました' });
    } finally {
      setApplying(false);
    }
  };

  return (
    <div className="fixed inset-0 z-[60] bg-black/50 flex items-center justify-center p-4" onClick={onClose}>
      <div className="bg-white w-full max-w-md rounded-xl shadow-xl flex flex-col max-h-[85vh]" onClick={e => e.stopPropagation()}>
        <div className="flex items-center justify-between p-4 border-b border-surface-border">
          <div>
            <h3 className="text-sm font-semibold text-gray-800">{route.routeName}</h3>
            <span className="text-[10px] text-gray-500">{PRIORITY_LABEL[route.priority]} / 候補 +{route.effect.candidates}名</span>
          </div>
          <button onClick={onClose} className="text-xs text-gray-500 hover:text-gray-700">閉じる</button>
        </div>

        <div className="flex-1 overflow-y-auto p-4 space-y-3">
          {diff && (
            <div className="p-3 rounded-lg bg-blue-50 border border-blue-200 space-y-1">
              <p className="text-xs font-semibold text-blue-800">適用前後の差分（予測）</p>
              <p className="text-[11px] text-blue-700 font-mono">
                割当: {diff.before.assigned}名 → {diff.after.assigned}名（必要 {diff.before.required}名）
              </p>
              <p className="text-[11px] text-blue-700">
                状態: {diff.before.status === 'established' ? '成立' : '不足'} → {diff.after.status === 'established' ? '成立' : '不足'}
                {diff.differences.assigned > 0 && ` （+${diff.differences.assigned}名）`}
              </p>
            </div>
          )}

          <div className="space-y-1">
            <p className="text-xs font-semibold text-gray-700">適用ステップ</p>
            {route.steps.map(s => (
              <p key={s.step} className="text-[11px] text-gray-600">Step{s.step}: {s.description}</p>
            ))}
          </div>

          {result && (
            <div className={`p-3 rounded-lg border space-y-1 ${result.success ? 'bg-emerald-50 border-emerald-200' : 'bg-amber-50 border-amber-200'}`}>
              <p className={`text-xs font-semibold ${result.success ? 'text-emerald-800' : 'text-amber-800'}`}>
                {result.success
                  ? result.scheduleEstablished ? '✅ 適用完了：勤務表が成立しました' : '⚠️ 適用完了：残り不足があります'
                  : '⚠️ 適用できませんでした'}
              </p>
              {!result.success && result.message && (
                <p className="text-[11px] text-amber-700 leading-relaxed">{result.message}</p>
              )}
              {result.success && (
                <>
                  <p className="text-[11px] text-emerald-700">適用ステップ数: {result.stepsApplied}</p>
                  {typeof result.remainingConflicts === 'number' && (
                    <p className="text-[11px] text-emerald-700">残りコンフリクト: {result.remainingConflicts}件</p>
                  )}
                  <p className="text-[11px] text-emerald-600">勤務表を再生成しました。</p>
                </>
              )}
            </div>
          )}
        </div>

        <div className="p-4 border-t border-surface-border">
          {!result?.success ? (
            <button
              type="button"
              onClick={apply}
              disabled={applying}
              className="w-full px-3 py-2 text-sm font-medium rounded-lg bg-primary-600 text-white hover:bg-primary-700 disabled:opacity-40 transition-colors"
            >
              {applying ? '適用中...（勤務表を再生成しています）' : 'このルートを適用する'}
            </button>
          ) : (
            <button
              type="button"
              onClick={onClose}
              className="w-full px-3 py-2 text-sm font-medium rounded-lg bg-gray-200 text-gray-800 hover:bg-gray-300 transition-colors"
            >
              閉じる
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
