'use client';
import { Button } from '@/components/ui/Button';
import type { SchedulePattern } from '@/types';

interface Props {
  generating: boolean;
  preserveFixed: boolean;
  swapMode: boolean;
  patterns: SchedulePattern[];
  activePattern: number;
  onGenerate: () => void;
  onTogglePreserveFixed: (v: boolean) => void;
  onToggleSwapMode: () => void;
  onExportSchedule: () => void;
  onExportPdf: () => void;
  onPrint: () => void;
  onReset: () => void;
}

export function ScheduleToolbar({
  generating, preserveFixed, swapMode, patterns, activePattern,
  onGenerate, onTogglePreserveFixed, onToggleSwapMode,
  onExportSchedule, onExportPdf, onPrint, onReset,
}: Props) {
  return (
    <div className="flex items-center justify-between print-hide">
      <div className="flex items-center gap-3">
        <Button variant="primary" size="sm" onClick={onGenerate} disabled={generating}>
          {generating ? '作成中...' : '勤務自動作成'}
        </Button>
        <label className="flex items-center gap-1.5 text-xs text-gray-500 cursor-pointer select-none hover:text-gray-700 transition-colors">
          <input
            type="checkbox"
            checked={preserveFixed}
            onChange={e => onTogglePreserveFixed(e.target.checked)}
            className="w-3.5 h-3.5 rounded border-surface-border text-primary-500 focus:ring-primary-400/40 cursor-pointer"
          />
          手動編集を残す
        </label>
        <Button
          variant={swapMode ? 'primary' : 'ghost'}
          size="sm"
          onClick={onToggleSwapMode}
        >
          交換{swapMode ? '中' : ''}
        </Button>
      </div>
      {patterns.length > 0 && (
        <div className="flex items-center gap-2">
          <Button variant="ghost" size="sm" onClick={onPrint}>印刷</Button>
          <Button variant="ghost" size="sm" onClick={onExportSchedule}>Excel</Button>
          <Button variant="ghost" size="sm" onClick={onExportPdf}>PDF</Button>
          <span className="w-px h-4 bg-gray-200" />
          <Button variant="ghost" size="sm" onClick={onReset}>リセット</Button>
        </div>
      )}
    </div>
  );
}
