'use client';
import { useState } from 'react';

export interface Constraints {
  night_to_ake: boolean;
  late_to_early_forbidden: boolean;
  day_to_early_forbidden: boolean;
  night_ake_rest_recommended: boolean;
  no_consecutive_night: boolean;
  max_consecutive_days: number;
  equalize_night_shifts: boolean;
}

const DEFAULT_CONSTRAINTS: Constraints = {
  night_to_ake: true,
  late_to_early_forbidden: true,
  day_to_early_forbidden: true,
  night_ake_rest_recommended: true,
  no_consecutive_night: true,
  max_consecutive_days: 5,
  equalize_night_shifts: true,
};

export { DEFAULT_CONSTRAINTS };

const CARD_DEFS: { key: keyof Constraints; label: string; desc: string; hasInput?: false }[] = [
  { key: 'night_to_ake', label: '夜勤 → 明け（必須）', desc: '夜勤の翌日は必ず明けにする' },
  { key: 'late_to_early_forbidden', label: '遅番 → 早番（禁止）', desc: '遅番の翌日に早番を入れない' },
  { key: 'day_to_early_forbidden', label: '日勤 → 早番（禁止）', desc: '日勤の翌日に早番を入れない' },
  { key: 'night_ake_rest_recommended', label: '夜勤 → 明け → 休み（推奨）', desc: '夜勤明けの翌日は休みを優先する' },
  { key: 'no_consecutive_night', label: '夜勤連続禁止', desc: '夜勤を連続で入れない' },
  { key: 'equalize_night_shifts', label: '夜勤回数の均等化', desc: 'スタッフ間で夜勤回数を均等にする' },
];

export function ConstraintsPanel({
  constraints,
  onChange,
}: {
  constraints: Constraints;
  onChange: (c: Constraints) => void;
}) {
  const [open, setOpen] = useState(false);

  const toggle = (key: keyof Constraints) => {
    if (key === 'max_consecutive_days') return;
    onChange({ ...constraints, [key]: !constraints[key] });
  };

  return (
    <div data-testid="constraints-panel" className="bg-surface-card border border-surface-border rounded-xl shadow-card print-hide">
      <button
        onClick={() => setOpen(v => !v)}
        className="w-full flex items-center justify-between px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors rounded-xl"
      >
        <span>勤務パターン制約</span>
        <span className={`text-gray-400 transition-transform duration-200 ${open ? 'rotate-180' : ''}`}>▼</span>
      </button>
      {open && (
        <div className="px-4 pb-4 space-y-2 border-t border-surface-border pt-3">
          {CARD_DEFS.map(card => (
            <div
              key={card.key}
              data-constraint-key={card.key}
              className="flex items-center justify-between px-3 py-2.5 rounded-lg border transition-colors duration-150"
              style={{
                borderColor: constraints[card.key] ? '#4ade80' : '#e5e7eb',
                backgroundColor: constraints[card.key] ? '#f0fdf4' : '#fafafa',
              }}
            >
              <div className="flex-1 min-w-0">
                <div className="text-sm font-medium text-gray-800">{card.label}</div>
                <div className="text-[11px] text-gray-500 mt-px">{card.desc}</div>
              </div>
              <label className="relative inline-flex items-center cursor-pointer ml-3 flex-shrink-0">
                <input
                  type="checkbox"
                  checked={constraints[card.key] as boolean}
                  onChange={() => toggle(card.key)}
                  className="sr-only peer"
                />
                <div className="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-400" />
              </label>
            </div>
          ))}
          {/* Max consecutive days */}
          <div className="flex items-center justify-between px-3 py-2.5 rounded-lg border border-gray-200 bg-gray-50">
            <div className="flex-1 min-w-0">
              <div className="text-sm font-medium text-gray-800">最大連続勤務日数</div>
              <div className="text-[11px] text-gray-500 mt-px">連続勤務は<span className="font-semibold">{constraints.max_consecutive_days}</span>日まで</div>
            </div>
            <input
              type="number"
              value={constraints.max_consecutive_days}
              onChange={e => onChange({ ...constraints, max_consecutive_days: Math.max(1, Math.min(31, parseInt(e.target.value) || 5)) })}
              className="w-16 px-2 py-1.5 text-sm text-center border border-gray-300 rounded-lg bg-white ml-3"
              min={1}
              max={31}
            />
          </div>

        </div>
      )}
    </div>
  );
}
