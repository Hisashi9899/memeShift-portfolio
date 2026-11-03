'use client';
import { useState, useEffect } from 'react';
import toast from 'react-hot-toast';
import { api } from '@/lib/api';
import { Staff } from '@/types';
import { Button } from '@/components/ui/Button';
import { Loading } from '@/components/ui/Loading';

function StaffRow({ data, onChange, onRemove }: { data: Partial<Staff>; onChange: (d: Partial<Staff>) => void; onRemove: () => void }) {
  const [showDates, setShowDates] = useState(!!data.start_date || !!data.end_date);
  return (
    <div className="p-3 bg-gray-50 rounded-xl border border-gray-200 space-y-2">
      <div className="flex items-center gap-3">
        <div className="flex-1">
          <label className="text-xs font-bold text-gray-500 block mb-1">氏名</label>
          <input type="text" value={data.name || ''} onChange={e => onChange({ ...data, name: e.target.value })}
            className="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm" placeholder="例: 山田 花子" />
        </div>
        <div className="w-[120px]">
          <label className="text-xs font-bold text-gray-500 block mb-1">雇用区分</label>
          <select value={data.employment_type || 'fulltime'} onChange={e => onChange({ ...data, employment_type: e.target.value as any })}
            className="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm bg-white">
            <option value="fulltime">常勤</option><option value="parttime">非常勤</option>
          </select>
        </div>
        <div className="w-[80px]">
          <label className="text-xs font-bold text-gray-500 block mb-1">最大連勤</label>
          <input type="number" value={data.max_consecutive_days || 5} onChange={e => onChange({ ...data, max_consecutive_days: parseInt(e.target.value) || 5 })}
            className="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm text-center" min={1} max={31} />
        </div>
        <div>
          <label className="text-xs font-bold text-gray-500 block mb-1">&nbsp;</label>
          <div className="flex gap-2">
            <label className="flex items-center gap-1 text-xs px-2 py-1.5 border border-gray-200 rounded-lg bg-white cursor-pointer whitespace-nowrap">
              <input type="checkbox" checked={!!data.can_night_shift} onChange={e => onChange({ ...data, can_night_shift: e.target.checked })} />
              夜勤可
            </label>
            <label className="flex items-center gap-1 text-xs px-2 py-1.5 border border-gray-200 rounded-lg bg-white cursor-pointer whitespace-nowrap">
              <input type="checkbox" checked={data.active !== false} onChange={e => onChange({ ...data, active: e.target.checked })} />
              在籍
            </label>
          </div>
        </div>
        <div className="flex flex-col gap-1 pt-5">
          <button onClick={() => setShowDates(v => !v)} className="text-xs text-gray-400 hover:text-gray-600 whitespace-nowrap">
            {showDates ? '期間を隠す' : '期間設定'}
          </button>
          <button onClick={onRemove} className="text-xs text-red-400 hover:text-red-600">削除</button>
        </div>
      </div>
      {showDates && (
        <div className="flex items-center gap-3 pl-1">
          <div className="w-[200px]">
            <label className="text-xs font-bold text-gray-500 block mb-1">勤務開始日（任意）</label>
            <input type="date" value={data.start_date || ''} onChange={e => onChange({ ...data, start_date: e.target.value || null })}
              className="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm" />
          </div>
          <div className="w-[200px]">
            <label className="text-xs font-bold text-gray-500 block mb-1">勤務終了日（任意）</label>
            <input type="date" value={data.end_date || ''} onChange={e => onChange({ ...data, end_date: e.target.value || null })}
              className="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm" />
          </div>
          <span className="text-[10px] text-gray-400 pt-5">未設定の場合は制限なし</span>
        </div>
      )}
    </div>
  );
}

export function StaffEditor({ month, onSave }: { month: string; onSave?: () => void }) {
  const [staff, setStaff] = useState<Staff[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => { load(); }, []);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.getStaff();
      setStaff(res.data || []);
    } catch (e: any) { toast.error(e.message); }
    finally { setLoading(false); }
  };

  const addRow = () => {
    setStaff(prev => [{ name: '', employment_type: 'fulltime', can_night_shift: true, max_consecutive_days: 5, start_date: null, end_date: null, active: true, sort_order: prev.length + 1 }, ...prev]);
  };

  const updateRow = (index: number, data: Partial<Staff>) => {
    setStaff(prev => prev.map((s, i) => i === index ? { ...s, ...data } : s));
  };

  const removeRow = (index: number) => {
    setStaff(prev => prev.filter((_, i) => i !== index));
  };

  const save = async () => {
    try {
      await api.saveStaff({ staff });
      toast.success('保存しました');
      await load();
      onSave?.();
    } catch (e: any) { toast.error(e.message); }
  };

  if (loading) return <Loading />;

  return (
    <div>
      <div className="space-y-3 max-h-[420px] overflow-y-auto mb-3">
        {staff.map((s, i) => (
          <StaffRow key={s.id || 'new-' + i} data={s} onChange={d => updateRow(i, d)} onRemove={() => removeRow(i)} />
        ))}
        {staff.length === 0 && <p className="text-gray-400 text-sm text-center py-4">職員データがありません。「+ 追加」から登録してください。</p>}
      </div>
      <div className="flex gap-3 pt-3 border-t border-gray-200">
        <Button onClick={addRow}>+ 追加</Button>
        <Button variant="primary" onClick={save}>一括保存</Button>
      </div>
    </div>
  );
}
