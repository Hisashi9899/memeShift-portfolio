'use client';
import { useState, useEffect } from 'react';
import toast from 'react-hot-toast';
import { api } from '@/lib/api';
import { ShiftType } from '@/types';
import { Button } from '@/components/ui/Button';
import { Loading } from '@/components/ui/Loading';

function ShiftRow({ data, onChange, onRemove }: { data: Partial<ShiftType>; onChange: (d: Partial<ShiftType>) => void; onRemove: () => void }) {
  return (
    <div className="grid grid-cols-[1.5fr_150px_150px_70px_auto] gap-3 items-center p-3 bg-gray-50 rounded-xl border border-gray-200">
      <div>
        <label className="text-xs font-bold text-gray-500 block mb-1">勤務名</label>
        <input type="text" value={data.name || ''} onChange={e => onChange({ ...data, name: e.target.value })}
          className="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm" />
      </div>
      <div>
        <label className="text-xs font-bold text-gray-500 block mb-1">開始</label>
        <input type="time" value={data.start_time || ''} onChange={e => onChange({ ...data, start_time: e.target.value })}
          className="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm" />
      </div>
      <div>
        <label className="text-xs font-bold text-gray-500 block mb-1">終了</label>
        <input type="time" value={data.end_time || ''} onChange={e => onChange({ ...data, end_time: e.target.value })}
          className="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm" />
      </div>
      <div>
        <label className="text-xs font-bold text-gray-500 block mb-1">色</label>
        <input type="color" value={data.color || '#1976d2'} onChange={e => onChange({ ...data, color: e.target.value })}
          className="w-full h-[38px] p-1 border border-gray-300 rounded-lg cursor-pointer" />
      </div>
      <div className="pt-5">
        <button onClick={onRemove} className="text-red-500 hover:text-red-700 text-sm">削除</button>
      </div>
    </div>
  );
}

export function ShiftTypeEditor() {
  const [items, setItems] = useState<ShiftType[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => { load(); }, []);

  const load = async () => {
    setLoading(true);
    try {
      const res = await api.getShiftTypes();
      setItems(res.data || []);
    } catch (e: any) { toast.error(e.message); }
    finally { setLoading(false); }
  };

  const addRow = () => setItems(prev => [...prev, { name: '', start_time: '', end_time: '', color: '#1976d2' }]);
  const updateRow = (i: number, d: Partial<ShiftType>) => setItems(prev => prev.map((s, idx) => idx === i ? { ...s, ...d } : s));
  const removeRow = (i: number) => setItems(prev => prev.filter((_, idx) => idx !== i));

  const save = async () => {
    try {
      await api.saveShiftTypes(items);
      toast.success('保存しました');
      await load();
      window.dispatchEvent(new CustomEvent('shift-types-saved'));
    } catch (e: any) { toast.error(e.message); }
  };

  if (loading) return <Loading />;

  return (
    <div>
      <div className="space-y-3 mb-3">
        {items.map((s, i) => (
          <ShiftRow key={s.id || 'new-' + i} data={s} onChange={d => updateRow(i, d)} onRemove={() => removeRow(i)} />
        ))}
      </div>
      <div className="flex gap-3 pt-3 border-t border-gray-200">
        <Button onClick={addRow}>+ 追加</Button>
        <Button variant="primary" onClick={save}>保存</Button>
      </div>
    </div>
  );
}
