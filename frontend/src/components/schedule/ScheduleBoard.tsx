'use client';

import { useState, useEffect, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import toast from 'react-hot-toast';
import { api } from '@/lib/api';
import { Loading } from '@/components/ui/Loading';
import { ConstraintsPanel, DEFAULT_CONSTRAINTS, Constraints } from '@/components/schedule/ConstraintsPanel';
import { ScheduleToolbar } from '@/components/schedule/ScheduleToolbar';
import PatternTabs from '@/components/schedule/PatternTabs';
import { Button } from '@/components/ui/Button';
import { ConflictPanel } from '@/components/schedule/ConflictPanel';
import { BreakRoutesPanel } from '@/components/schedule/BreakRoutesPanel';
import { ApproachSelector } from '@/components/schedule/ApproachSelector';

import { ScheduleTable } from '@/components/schedule/ScheduleTable';

import type {
  AppliedChange,
  ApproachResult,
  Conflict,
  ManualFixSuggestion,
  SchedulePattern,
  Staff,
  ShiftType,
} from '@/types';

export function ScheduleBoard({ month }: { month: string }) {
  const router = useRouter();
  const [patterns, setPatterns] = useState<SchedulePattern[]>([]);
  const [staff, setStaff] = useState<Staff[]>([]);
  const [shiftTypes, setShiftTypes] = useState<ShiftType[]>([]);
  const [activePattern, setActivePattern] = useState(0);
  const [loading, setLoading] = useState(true);
  const [generating, setGenerating] = useState(false);
  const [preserveFixed, setPreserveFixed] = useState(true);
  const [swapMode, setSwapMode] = useState(false);
  const [swapSelection, setSwapSelection] = useState<{staffId: number; date: string} | null>(null);
  const [tableKey, setTableKey] = useState(0);
  const [constraints, setConstraints] = useState<Constraints>(DEFAULT_CONSTRAINTS);
  const [showApproachSelector, setShowApproachSelector] = useState(false);
  const [conflicts, setConflicts] = useState<Conflict[]>([]);
  const [autoFixing, setAutoFixing] = useState(false);
  const [appliedApproaches, setAppliedApproaches] = useState<string[]>([]);
  const [recommendation, setRecommendation] = useState<{
    approach: string;
    description: string;
    effect?: string;
    confidence?: number;
  } | null>(null);
  const [showRecommendation, setShowRecommendation] = useState(false);
  const [manualFixSummary, setManualFixSummary] = useState<string | null>(null);
  const [manualFixSuggestions, setManualFixSuggestions] = useState<ManualFixSuggestion[] | null>(null);
  const [approachResults, setApproachResults] = useState<ApproachResult[] | null>(null);
  const [appliedChanges, setAppliedChanges] = useState<AppliedChange[] | null>(null);
  const [autoResolveOpen, setAutoResolveOpen] = useState(false);
  // 今回の崩し適用で変更されたセルキー（`${staffId}-${YYYY-MM-DD}`）のハイライト用
  const [highlightCells, setHighlightCells] = useState<Set<string>>(new Set());

  // ---------------------------
  // 初期データロード
  // ---------------------------
  const loadData = useCallback(async () => {
    try {
      setLoading(true);
      const [scheduleRes, staffRes, shiftTypesRes] = await Promise.all([
        api.getSchedule(month),
        api.getStaff(),
        api.getShiftTypes(),
      ]);
      console.log("ScheduleBoard load expected keys:", Object.keys(scheduleRes));
      console.log("ScheduleBoard patterns count:", scheduleRes.patterns?.length);
      if (scheduleRes.patterns?.[0]) {
        console.log("ScheduleBoard first pattern keys:", Object.keys(scheduleRes.patterns[0]));
        console.log("ScheduleBoard first pattern month_year:", scheduleRes.patterns[0].month_year);
        console.log("ScheduleBoard first pattern cells count:", scheduleRes.patterns[0].cells?.length);
        if (scheduleRes.patterns[0].cells?.[0]) {
          console.log("ScheduleBoard first cell:", JSON.stringify(scheduleRes.patterns[0].cells[0]));
        }
      }
      setPatterns(scheduleRes.patterns);
      setStaff(staffRes.data);
      setShiftTypes(shiftTypesRes.data);
      setLoading(false);
    } catch (err) {
      console.error(err);
      toast.error('データの取得に失敗しました');
      setLoading(false);
    }
  }, [month]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  // 崩しルート適用後の再反映（Break Routes UI からの通知を受け取る）
  useEffect(() => {
    const handler = (e: Event) => {
      const detail = (e as CustomEvent).detail as { conflicts?: Conflict[]; highlight?: string[] } | Conflict[] | undefined;
      const conflictsArr = Array.isArray(detail) ? detail : detail?.conflicts;
      if (Array.isArray(conflictsArr)) setConflicts(conflictsArr);
      const hl = !Array.isArray(detail) ? detail?.highlight : undefined;
      setHighlightCells(new Set(Array.isArray(hl) ? hl : []));
      loadData();
    };
    window.addEventListener('memeshift:schedule-regenerated', handler);
    return () => window.removeEventListener('memeshift:schedule-regenerated', handler);
  }, [loadData]);

  // patterns が空になったら conflicts も自動クリア（リセット等の後始末）
  useEffect(() => {
    if (patterns.length === 0) {
      setConflicts([]);
      setAppliedApproaches([]);
      setRecommendation(null);
      setShowRecommendation(false);
      setManualFixSummary(null);
      setManualFixSuggestions(null);
      setApproachResults(null);
      setAppliedChanges(null);
    }
  }, [patterns]);

  // ---------------------------
  // 勤務表自動生成
  // ---------------------------
  const generateSchedule = async () => {
    try {
      setGenerating(true);
      setHighlightCells(new Set());
      setAppliedApproaches([]);
      setRecommendation(null);
      setShowRecommendation(false);
      setManualFixSummary(null);
      setManualFixSuggestions(null);
      setApproachResults(null);
      setAppliedChanges(null);
      const res = await api.generate(month, preserveFixed, constraints);
      setPatterns(res.patterns);
      setConflicts(res.conflicts ?? []);
      setGenerating(false);
      if (!res.conflicts || res.conflicts.length === 0) {
        toast.success('勤務表を生成しました');
      }
    } catch (err) {
      console.error(err);
      toast.error('勤務表の生成に失敗しました');
      setGenerating(false);
    }
  };

  // ---------------------------
  // セル変更
  // ---------------------------
  const handleCellChange = async (staffId: number, date: string, shiftTypeId: number | null) => {
    const patternId = patterns[activePattern]?.id;
    if (!patternId) return;
    try {
      await api.updateCell(month, {
        schedulePatternId: patternId,
        staffId,
        date,
        shiftTypeId,
      });
      await loadData();
    } catch (err) {
      console.error(err);
      toast.error('セルの更新に失敗しました');
    }
  };

  // ---------------------------
  // 交換モード
  // ---------------------------
  const handleToggleSwapMode = useCallback(() => {
    setSwapSelection(null);
    setSwapMode((prev) => !prev);
  }, []);

  const handleCellClick = useCallback(async (staffId: number, date: string) => {
    if (!swapSelection) {
      setSwapSelection({ staffId, date });
      return;
    }
    if (swapSelection.staffId === staffId && swapSelection.date === date) {
      setSwapSelection(null);
      return;
    }
    const patternId = patterns[activePattern]?.id;
    if (!patternId) return;
    try {
      await api.swapSchedule(patternId, date, swapSelection.staffId, staffId);
      setSwapSelection(null);
      setSwapMode(false);
      await loadData();
      toast.success('交換しました');
    } catch (err) {
      console.error(err);
      toast.error('交換に失敗しました');
    }
  }, [swapSelection, patterns, activePattern, loadData]);

  // ---------------------------
  // エクスポート / 印刷 / リセット
  // ---------------------------
  const handleExportSchedule = useCallback(async () => {
    const patternId = patterns[activePattern]?.id;
    if (!patternId) return;
    await api.exportSchedule(patternId);
  }, [patterns, activePattern]);

  const handleExportPdf = useCallback(async () => {
    const patternId = patterns[activePattern]?.id;
    if (!patternId) return;
    await api.exportSchedulePdf(patternId);
  }, [patterns, activePattern]);

  const handlePrint = useCallback(() => {
    window.print();
  }, []);

  const handleReset = useCallback(async () => {
    try {
      await api.resetSchedule(month);
      setConflicts([]);
      setAppliedApproaches([]);
      setRecommendation(null);
      setShowRecommendation(false);
      setManualFixSummary(null);
      setManualFixSuggestions(null);
      setApproachResults(null);
      setAppliedChanges(null);
      setActivePattern(0);
      toast.success('勤務表をリセットしました');
      loadData();
    } catch (err) {
      console.error(err);
      toast.error('リセットに失敗しました');
    }
  }, [month, loadData]);

  // ---------------------------
  // 該当箇所へジャンプ
  // ---------------------------
  const handleJump = (suggestion: ManualFixSuggestion) => {
    const { jump_type, date, jump_payload } = suggestion;

    switch (jump_type) {
      case 'settings': {
        const params = new URLSearchParams();
        if (jump_payload?.shift_type_id) params.set('shift_type_id', String(jump_payload.shift_type_id));
        const qs = params.toString();
        router.push(qs ? `/settings?${qs}` : '/settings');
        return;
      }
      case 'preferences': {
        const params = new URLSearchParams();
        if (jump_payload?.staff_id) params.set('staff_id', String(jump_payload.staff_id));
        if (jump_payload?.date) params.set('date', jump_payload.date);
        const qs = params.toString();
        router.push(qs ? `/preferences?${qs}` : '/preferences');
        return;
      }
      case 'constraints': {
        const panel = document.querySelector('[data-testid="constraints-panel"]');
        if (panel) {
          panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
          panel.classList.add('ring-2', 'ring-orange-400', 'transition-all', 'duration-1000');
          setTimeout(() => panel.classList.remove('ring-2', 'ring-orange-400'), 3000);
        }
        // Highlight specific constraint row
        if (jump_payload?.constraint_key) {
          const row = document.querySelector(`[data-constraint-key="${jump_payload.constraint_key}"]`);
          if (row) {
            row.classList.add('ring-2', 'ring-orange-400', 'bg-orange-50', 'transition-all', 'duration-1000');
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => row.classList.remove('ring-2', 'ring-orange-400', 'bg-orange-50'), 3000);
          }
        }
        return;
      }
      default: {
        if (!date) return;
        const day = parseInt(date.split('-')[2], 10);
        if (isNaN(day)) return;

        const table = document.querySelector('table');
        if (!table) return;

        const headerCells = table.querySelectorAll('thead th');
        let targetIndex = -1;
        headerCells.forEach((th, i) => {
          const dayDiv = th.querySelector('div');
          if (dayDiv && dayDiv.textContent?.trim() === String(day)) {
            targetIndex = i;
          }
        });
        if (targetIndex < 0) return;

        const headerCell = headerCells[targetIndex] as HTMLElement;
        headerCell.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });

        const highlightClass = 'bg-yellow-200';
        const cellsToHighlight: HTMLElement[] = [headerCell];
        table.querySelectorAll('tbody tr').forEach(row => {
          const cell = row.children[targetIndex] as HTMLElement;
          if (cell) cellsToHighlight.push(cell);
        });
        cellsToHighlight.forEach(el => el.classList.add(highlightClass, 'transition-colors', 'duration-500'));
        setTimeout(() => {
          cellsToHighlight.forEach(el => el.classList.remove(highlightClass));
        }, 2000);
      }
    }
  };

  // ---------------------------
  // AutoFix
  // ---------------------------
  const handleAutoFix = () => {
    setShowApproachSelector(true);
  };

  const handleQuickFix = async () => {
    try {
      setAutoFixing(true);
      setAppliedApproaches([]);
      setRecommendation(null);
      setShowRecommendation(false);
      setManualFixSummary(null);
      setManualFixSuggestions(null);
      setApproachResults(null);
      setAppliedChanges(null);
      const res = await api.autoFixOrchestrator(month, preserveFixed, constraints);
      setPatterns(res.patterns);
      setConflicts(res.conflicts ?? []);
      setAppliedApproaches(res.applied_approaches ?? []);
      setShowRecommendation(res.show_recommendation ?? false);
      setRecommendation(res.recommended_approach ? {
        approach: res.recommended_approach,
        description: res.recommended_description ?? '',
        effect: res.recommended_effect,
        confidence: res.recommended_confidence,
      } : null);
      setApproachResults(res.approach_results ?? null);
      setAppliedChanges(res.applied_changes ?? null);
      if (res.auto_fix_limit_reached) {
        setManualFixSummary(res.manual_fix_summary ?? null);
        setManualFixSuggestions(res.manual_fix_suggestions ?? null);
      }
      setAutoFixing(false);
      if (res.success) {
        toast.success('自動修正を適用しました');
      } else {
        toast.error('最適な修正案を提示しました。');
      }
    } catch (err) {
      console.error(err);
      toast.error('自動修正に失敗しました');
      setAutoFixing(false);
    }
  };

  const handleExecuteRecommended = async (approach: string) => {
    try {
      setAutoFixing(true);
      setApproachResults(null);
      setAppliedChanges(null);
      const res = await api.autoFixApproach(month, preserveFixed, constraints, approach);
      setPatterns(res.patterns);
      setConflicts(res.conflicts ?? []);
      setAppliedApproaches(res.approach_applied ? [res.approach_applied] : []);
      setAutoFixing(false);
      if (res.conflicts && res.conflicts.length > 0) {
        toast.error(res.conflicts[0]?.message || '修正を適用できませんでした');
        return;
      }
      setRecommendation(null);
      setShowRecommendation(false);
      toast.success(res.approach_description || '推奨修正を適用しました');
    } catch (err) {
      console.error(err);
      toast.error('修正の適用に失敗しました');
      setAutoFixing(false);
    }
  };

  const handleExecuteApproach = async (approach: string) => {
    try {
      setAutoFixing(true);
      setApproachResults(null);
      setAppliedChanges(null);
      const res = await api.autoFixApproach(month, preserveFixed, constraints, approach);
      setPatterns(res.patterns);
      setConflicts(res.conflicts ?? []);
      setAppliedApproaches(res.approach_applied ? [res.approach_applied] : []);
      setAutoFixing(false);
      if (res.conflicts && res.conflicts.length > 0) {
        toast.error(res.conflicts[0]?.message || 'このアプローチでは解決できませんでした。別の方法をお試しください。');
        return;
      }
      setShowApproachSelector(false);
      toast.success(res.approach_description || '自動修正を適用しました');
    } catch (err) {
      console.error(err);
      toast.error('自動修正に失敗しました');
      setAutoFixing(false);
    }
  };

  if (loading) {
    return (
      <div className="flex justify-center items-center h-64">
        <Loading />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <ScheduleToolbar
        generating={generating}
        preserveFixed={preserveFixed}
        swapMode={swapMode}
        patterns={patterns}
        activePattern={activePattern}
        onGenerate={generateSchedule}
        onTogglePreserveFixed={setPreserveFixed}
        onToggleSwapMode={handleToggleSwapMode}
        onExportSchedule={handleExportSchedule}
        onExportPdf={handleExportPdf}
        onPrint={handlePrint}
        onReset={handleReset}
      />

      <div className="flex items-center justify-between">
        <PatternTabs
          patterns={patterns}
          selectedIndex={activePattern}
          onSelectPattern={setActivePattern}
        />
        {/* 完全2択UX: 崩し操作は「最適な崩しを適用する」に集約するため非表示（復活時はこのラッパーを外す） */}
        {false && (
        <Button variant="ghost" size="sm" onClick={handleAutoFix}>
          高度な自動修正
        </Button>
        )}
      </div>

      <ConstraintsPanel
        constraints={constraints}
        onChange={setConstraints}
      />

      <ConflictPanel
        conflicts={conflicts}
        hideQuickActions
        onQuickFix={conflicts.length > 0 ? handleQuickFix : undefined}
        onExecuteRecommended={handleExecuteRecommended}
        onJump={handleJump}
        autoFixing={autoFixing}
        appliedApproaches={appliedApproaches}
        showRecommendation={showRecommendation}
        recommendation={recommendation}
        manualFixSummary={manualFixSummary}
        manualFixSuggestions={manualFixSuggestions}
        approachResults={approachResults}
        appliedChanges={appliedChanges}
      />

      {conflicts.length > 0 && (
        <div className="flex justify-end">
          <Button variant="primary" size="sm" onClick={() => setAutoResolveOpen(true)}>
            最適な崩しを適用する
          </Button>
        </div>
      )}
      {autoResolveOpen && (
        <BreakRoutesPanel conflicts={conflicts} autoResolve onClose={() => { setAutoResolveOpen(false); setHighlightCells(new Set()); }} />
      )}

      <ScheduleTable
        patterns={patterns}
        staff={staff}
        shiftTypes={shiftTypes}
        activePattern={activePattern}
        tableKey={tableKey}
        highlightCells={highlightCells}
        swapMode={swapMode}
        swapSelection={swapSelection}
        onCellClick={handleCellClick}
        onCellChange={handleCellChange}
      />

      <ApproachSelector
        open={showApproachSelector}
        onClose={() => setShowApproachSelector(false)}
        onExecute={handleExecuteApproach}
        executing={autoFixing}
      />

    </div>
  );
}
