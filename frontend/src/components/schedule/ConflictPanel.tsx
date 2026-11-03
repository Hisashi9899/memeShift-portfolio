'use client';
import { useState } from 'react';
import { Button } from '@/components/ui/Button';
import { BreakRoutesButton } from '@/components/schedule/BreakRoutesButton';
import type { AppliedChange, ApproachResult, Conflict, ManualFixSuggestion } from '@/types';

const APPROACH_LABELS: Record<string, string> = {
  reduce_requirements: '必要人数の削減',
  equalize_preferences: '希望休の均等配置',
  relax_consecutive_days: '連続勤務日数の緩和',
  relax_night_chain: '夜勤連鎖制約の緩和',
  add_dummy_night_staff: '夜勤専用スタッフの追加（仮）',
};

interface Recommendation {
  approach: string;
  description: string;
  effect?: string;
  confidence?: number;
}

interface Props {
  conflicts: Conflict[];
  onQuickFix?: () => void;
  onExecuteRecommended?: (approach: string) => void;
  onJump?: (suggestion: ManualFixSuggestion) => void;
  autoFixing?: boolean;
  appliedApproaches?: string[];
  showRecommendation?: boolean;
  recommendation?: Recommendation | null;
  manualFixSummary?: string | null;
  manualFixSuggestions?: ManualFixSuggestion[] | null;
  approachResults?: ApproachResult[] | null;
  appliedChanges?: AppliedChange[] | null;
  hideQuickActions?: boolean;
}

export function ConflictPanel({ conflicts, onQuickFix, onExecuteRecommended, onJump, autoFixing, appliedApproaches, showRecommendation, recommendation, manualFixSummary, manualFixSuggestions, approachResults, appliedChanges, hideQuickActions }: Props) {
  if (conflicts.length === 0 && (!appliedApproaches || appliedApproaches.length === 0)) return null;

  const [showAllApproaches, setShowAllApproaches] = useState(false);
  const genFailure = conflicts.find(c => c.shift_type === '生成失敗');
  const otherConflicts = conflicts.filter(c => c.shift_type !== '生成失敗');

  return (
    <div className="print-hide space-y-2">
      {(genFailure || otherConflicts.length > 0) && (
        <div className="flex items-start justify-between gap-4 p-3.5 rounded-xl bg-amber-50 border border-amber-200">
          <div className="space-y-1 min-w-0">
            <span className="text-sm font-medium text-amber-800">
              ⚠️ 勤務が組めない理由があります（{conflicts.length}件）
            </span>
            {genFailure && (
              <p className="text-xs text-amber-700 leading-relaxed" title={genFailure.message} style={{ display: '-webkit-box', WebkitLineClamp: 4, WebkitBoxOrient: 'vertical', overflow: 'hidden' }}>{genFailure.message}</p>
            )}
          </div>
          <div className="flex gap-2 shrink-0">
            {!hideQuickActions && <BreakRoutesButton conflicts={conflicts} />}
            {onQuickFix && !hideQuickActions && (
              <Button variant="primary" size="sm" onClick={onQuickFix} disabled={autoFixing}>
                {autoFixing ? '自動修正中...' : '最短で解決する'}
              </Button>
            )}
          </div>
        </div>
      )}

      {showRecommendation && recommendation && (
        <div className="p-3.5 rounded-xl bg-blue-50 border border-blue-200 space-y-2">
          <div className="flex items-start gap-3">
            <svg viewBox="0 0 32 32" className="w-7 h-7 shrink-0 mt-0.5" fill="none" aria-hidden="true">
              <circle cx="16" cy="16" r="14" fill="#1a1a1a" />
              <circle cx="10" cy="13" r="3.5" fill="white" />
              <circle cx="22" cy="13" r="3.5" fill="white" />
              <circle cx="11" cy="13" r="1.8" fill="#1a1a1a" />
              <circle cx="23" cy="13" r="1.8" fill="#1a1a1a" />
              <ellipse cx="10" cy="10" rx="2" ry="0.8" fill="#333" />
              <ellipse cx="22" cy="10" rx="2" ry="0.8" fill="#333" />
              <ellipse cx="16" cy="19" rx="4" ry="3" fill="#2a2a2a" />
              <ellipse cx="16" cy="19" rx="2" ry="1.2" fill="#1a1a1a" />
              <ellipse cx="16" cy="20" rx="1.2" ry="0.8" fill="#555" />
              <path d="M14 15 Q16 17 18 15" stroke="#444" strokeWidth="0.6" fill="none" />
            </svg>
            <div className="space-y-2 min-w-0 flex-1">
          <p className="text-sm font-medium text-gray-800">
            最短で勤務表を作成するための推奨修正
          </p>
          <p className="text-xs text-blue-700 leading-relaxed">{recommendation.description}</p>
          {recommendation.effect && (
            <p className="text-xs text-blue-600 font-mono bg-blue-100/50 px-2 py-1 rounded">
              {recommendation.effect}
            </p>
          )}
          {onExecuteRecommended && (
            <Button variant="primary" size="sm" onClick={() => onExecuteRecommended(recommendation.approach)} disabled={autoFixing}>
              {autoFixing ? '実行中...' : 'この修正を実行する'}
            </Button>
          )}
          </div>
          </div>
        </div>
      )}

      {manualFixSuggestions && manualFixSuggestions.length > 0 && (
        <div className="p-3.5 rounded-xl bg-orange-50 border border-orange-200 space-y-3">
          <div className="flex items-start gap-3">
            <svg viewBox="0 0 32 32" className="w-7 h-7 shrink-0 mt-0.5" fill="none" aria-hidden="true">
              <circle cx="16" cy="16" r="14" fill="#1a1a1a" />
              <circle cx="10" cy="13" r="3.5" fill="white" />
              <circle cx="22" cy="13" r="3.5" fill="white" />
              <circle cx="11" cy="13" r="1.8" fill="#1a1a1a" />
              <circle cx="23" cy="13" r="1.8" fill="#1a1a1a" />
              <ellipse cx="10" cy="10" rx="2" ry="0.8" fill="#333" />
              <ellipse cx="22" cy="10" rx="2" ry="0.8" fill="#333" />
              <ellipse cx="16" cy="19" rx="4" ry="3" fill="#2a2a2a" />
              <ellipse cx="16" cy="19" rx="2" ry="1.2" fill="#1a1a1a" />
              <ellipse cx="16" cy="20" rx="1.2" ry="0.8" fill="#555" />
              <path d="M14 15 Q16 17 18 15" stroke="#444" strokeWidth="0.6" fill="none" />
            </svg>
            <div className="space-y-2 min-w-0 flex-1">
              <p className="text-sm font-semibold text-orange-900">
                手動で修正が必要です
              </p>
              {manualFixSummary && (
                <p className="text-xs text-orange-800 leading-relaxed whitespace-pre-line">{manualFixSummary}</p>
              )}
            </div>
          </div>
          <div className="space-y-2">
            {[...manualFixSuggestions]
              .sort((a, b) => (b.impact ?? 0) - (a.impact ?? 0))
              .map((s, i) => {
                const impact = s.impact ?? 50;
                const barColor = impact >= 70 ? 'bg-red-400' : impact >= 40 ? 'bg-orange-400' : 'bg-yellow-400';
                const textColor = impact >= 70 ? 'text-red-700' : impact >= 40 ? 'text-orange-700' : 'text-yellow-700';
                return (
                <div key={i} className="flex items-start gap-2.5 p-2.5 rounded-lg bg-white/60 border border-orange-100">
                  <div className="min-w-0 flex-1 space-y-0.5">
                    <div className="flex items-center gap-2 text-xs font-medium text-orange-800">
                      {s.date && <span>{s.date}</span>}
                      {s.shift && <span className="px-1.5 py-0.5 rounded bg-orange-100 text-orange-700">{s.shift}</span>}
                      <span className={`ml-auto text-[10px] font-semibold ${textColor}`}>影響度 {impact}%</span>
                    </div>
                    <div className="w-full h-1.5 rounded-full bg-gray-200 overflow-hidden">
                      <div className={`h-full rounded-full ${barColor} transition-all`} style={{ width: `${impact}%` }} />
                    </div>
                    <p className="text-xs text-orange-700 leading-relaxed">{s.reason}</p>
                    <p className="text-xs text-orange-600 leading-relaxed">→ {s.suggestion}</p>
                  </div>
                  {onJump && (
                    <button
                      onClick={() => onJump(s)}
                      className="shrink-0 self-start px-2 py-1 text-[10px] font-medium text-orange-600 bg-orange-100 hover:bg-orange-200 rounded transition-colors"
                    >
                      該当箇所へ
                    </button>
                  )}
                </div>
              )})}
          </div>
        </div>
      )}

      {!recommendation && !manualFixSuggestions && otherConflicts.map((c, i) => (
        <div key={i} className="flex items-start gap-3 p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-sm">
          <span className="text-amber-500 text-base leading-none mt-0.5">⚠️</span>
          <div>
            <p className="font-medium text-amber-900">
              {c.date} {c.shift_type}（{c.assigned}/{c.required}名）
            </p>
            <p className="text-amber-700 text-xs mt-1 leading-relaxed">{c.message}</p>
          </div>
        </div>
      ))}
      {appliedApproaches && appliedApproaches.length > 0 && (
        <div className="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 space-y-2">
          <p className="text-sm font-medium text-emerald-800">自動修正を適用しました</p>
          <ul className="text-xs text-emerald-700 space-y-1">
            {appliedApproaches.map(a => (
              <li key={a} className="flex items-start gap-1.5">
                <span className="text-emerald-500 mt-0.5">✓</span>
                <div>
                  <span className="font-medium">{APPROACH_LABELS[a] || a}</span>
                  {approachResults?.find(r => r.name === a)?.description && (
                    <p className="text-emerald-600 mt-0.5">{approachResults.find(r => r.name === a)!.description}</p>
                  )}
                </div>
              </li>
            ))}
          </ul>
          {appliedChanges && appliedChanges.length > 0 && (
            <div className="border-t border-emerald-200 pt-2 mt-1">
              <p className="text-[11px] font-medium text-emerald-700 mb-1">変更内容:</p>
              <ul className="text-[11px] text-emerald-600 space-y-0.5">
                {appliedChanges.map((c, i) => (
                  <li key={i} className="flex items-start gap-1.5">
                    <span className="text-emerald-400 mt-0.5">・</span>
                    <span><span className="font-medium">{c.action}:</span> {c.detail}</span>
                  </li>
                ))}
              </ul>
            </div>
          )}
          {approachResults && approachResults.length > 1 && (
            <div className="border-t border-emerald-200 pt-2 mt-1">
              <button
                onClick={() => setShowAllApproaches(v => !v)}
                className="flex items-center gap-1 text-[11px] font-medium text-emerald-600 hover:text-emerald-800 transition-colors"
              >
                <span className={`transition-transform duration-200 ${showAllApproaches ? 'rotate-90' : ''}`}>▶</span>
                試行した全アプローチ（{approachResults.length}件）
              </button>
              {showAllApproaches && (
                <div className="mt-1.5 space-y-1">
                  {approachResults.map((ar, i) => {
                    const isApplied = ar.applied;
                    return (
                      <div key={i} className={`flex items-start gap-2 px-2 py-1.5 rounded text-[11px] ${isApplied ? 'bg-emerald-100/50' : 'bg-gray-50'}`}>
                        <span className={`mt-0.5 ${isApplied ? 'text-emerald-500' : 'text-gray-300'}`}>
                          {isApplied ? '✓' : '−'}
                        </span>
                        <div className="flex-1 min-w-0">
                          <div className={`font-medium ${isApplied ? 'text-emerald-800' : 'text-gray-500'}`}>
                            {APPROACH_LABELS[ar.name] || ar.name}
                            {!isApplied && <span className="ml-1 text-gray-400 font-normal">（試行済み）</span>}
                          </div>
                          <div className={`${isApplied ? 'text-emerald-700' : 'text-gray-400'}`}>
                            残り{ar.conflict_count}件のコンフリクト
                          </div>
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
