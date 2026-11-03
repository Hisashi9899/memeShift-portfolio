'use client';
import { useState, useEffect } from 'react';
import toast from 'react-hot-toast';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/Button';
import { Loading } from '@/components/ui/Loading';

export function DailyOverrideEditor({ month }: { month: string }) {
  const [items, setItems] = useState<any[]>([]);
  const [shiftTypes, setShiftTypes] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => { load(); }, [month]);

  const load = async () => {
    setLoading(true);
    try {
      const [reqRes, stRes] = await Promise.all([api.getDailyOverrides(month), api.getShiftTypes()]);
      setItems(reqRes.data || []);
      setShiftTypes(stRes.data || []);
    } catch (e: any) { toast.error(e.message); }
    finally { setLoading(false); }
  };

  const updateRow = (i: number, d: any) => setItems(prev => prev.map((s, idx) => idx === i ? { ...s, ...d } : s));
  const removeRow = (i: number) => setItems(prev => prev.filter((_, idx) => idx !== i));

  const addRow = () => {
    if (!shiftTypes.length) return;
    setItems(prev => [...prev, { target_date: month + '-01', shift_type_id: shiftTypes[0].id, count: 1 }]);
  };

  const save = async () => {
    try {
      await api.saveDailyOverrides({ overrides: items, month });
      toast.success('保存しました');
      await load();
    } catch (e: any) { toast.error(e.message); }
  };

  if (loading) return <Loading />;

  return (
    <div>
      <p className="text-sm text-gray-500 mb-3">特定の日付だけ通常と異なる人数が必要な場合に設定します（例: 祝日は日勤を少なめにするなど）。</p>
      <div className="space-y-2 mb-3">
        {items.map((r, i) => (
          <div key={r.id || 'new-' + i} className="flex items-center gap-3 p-3 bg-gray-50 rounded-xl border border-gray-200">
            <input type="date" value={r.target_date || ''} onChange={e => updateRow(i, { ...r, target_date: e.target.value })}
              className="flex-1 px-3 py-1.5 border border-gray-300 rounded-lg text-sm" />
            <select value={r.shift_type_id || ''} onChange={e => updateRow(i, { ...r, shift_type_id: parseInt(e.target.value) })}
              className="flex-1 px-3 py-1.5 border border-gray-300 rounded-lg text-sm bg-white">
              {shiftTypes.map(st => <option key={st.id} value={st.id}>{st.name}</option>)}
            </select>
            <input type="number" value={r.count || 0} onChange={e => updateRow(i, { ...r, count: parseInt(e.target.value) || 1 })}
              className="w-20 px-3 py-1.5 border border-gray-300 rounded-lg text-sm text-center" min={0} />
            <span className="text-sm text-gray-500">人</span>
            <button onClick={() => removeRow(i)} className="text-red-500 hover:text-red-700 text-sm">削除</button>
          </div>
        ))}
      </div>
      <div className="flex gap-3 pt-3 border-t border-gray-200">
        <Button onClick={addRow}>+ 追加</Button>
        <Button variant="primary" onClick={save}>保存</Button>
      </div>
    </div>
  );
}
