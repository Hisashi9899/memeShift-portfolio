'use client';
import { useState } from 'react';
import { Button } from '@/components/ui/Button';

interface Approach {
  key: string;
  label: string;
  description: string;
}

interface Props {
  open: boolean;
  onClose: () => void;
  onExecute: (approach: string) => Promise<void>;
  executing?: boolean;
}

const APPROACHES: Approach[] = [
  {
    key: 'reduce_requirements',
    label: '必要人数の削減',
    description: '夜勤の必要人数を1減らし、足りない分を日勤に振り替えます',
  },
  {
    key: 'equalize_preferences',
    label: '希望休の均等配置',
    description: '希望休を月全体に均等に再配置します',
  },
  {
    key: 'relax_consecutive_days',
    label: '連続勤務日数の緩和',
    description: '最大連続勤務日数を1日増やします',
  },
  {
    key: 'add_dummy_night_staff',
    label: '夜勤専用スタッフの追加（仮）',
    description: '夜勤専用の仮スタッフを1名追加します',
  },
  {
    key: 'relax_night_chain',
    label: '夜勤連鎖制約の緩和',
    description: '夜勤→明け→休みの制約を「推奨」に緩和します',
  },
];

export function ApproachSelector({ open, onClose, onExecute, executing }: Props) {
  const [selected, setSelected] = useState<string | null>(null);

  if (!open) return null;

  const handleExecute = async () => {
    if (!selected) return;
    await onExecute(selected);
  };

  return (
    <div className="print-hide fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div className="bg-white rounded-2xl shadow-xl p-6 max-w-md w-full mx-4 space-y-4">
        <h3 className="text-lg font-bold text-gray-900">自動修正の方向性を選択</h3>
        <p className="text-sm text-gray-600">
          勤務が組めない理由を解消するための修正方法を選択してください。
        </p>
        <div className="space-y-2">
          {APPROACHES.map(a => (
            <label
              key={a.key}
              className={`flex items-start gap-3 px-3 py-2.5 rounded-lg border transition-colors cursor-pointer ${
                selected === a.key
                  ? 'border-amber-400 bg-amber-50'
                  : 'border-gray-200 bg-white hover:bg-gray-50'
              }`}
            >
              <input
                type="radio"
                name="approach"
                value={a.key}
                checked={selected === a.key}
                onChange={() => setSelected(a.key)}
                className="mt-1 w-4 h-4 text-amber-500 border-gray-300 focus:ring-amber-400/40"
              />
              <div>
                <p className="text-sm font-medium text-gray-800">{a.label}</p>
                <p className="text-xs text-gray-500 mt-0.5">{a.description}</p>
              </div>
            </label>
          ))}
        </div>
        <div className="flex gap-3 justify-end">
          <Button variant="ghost" size="sm" onClick={onClose} disabled={executing}>
            キャンセル
          </Button>
          <Button
            variant="primary"
            size="sm"
            onClick={handleExecute}
            disabled={!selected || executing}
          >
            {executing ? '実行中...' : '実行'}
          </Button>
        </div>
      </div>
    </div>
  );
}
