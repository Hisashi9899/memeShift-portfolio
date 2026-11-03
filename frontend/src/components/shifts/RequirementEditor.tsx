'use client';
import { useState, useEffect } from 'react';
import toast from 'react-hot-toast';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/Button';
import { Loading } from '@/components/ui/Loading';

export function RequirementEditor() {
  const [items, setItems] = useState<any[]>([]);
  const [shiftTypes, setShiftTypes] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => { load(); }, []);

  useEffect(() => {
    const handler = () => load();
    window.addEventListener('shift-types-saved', handler);
    return () => window.removeEventListener('shift-types-saved', handler);
  }, []);

  const load = async () => {
    setLoading(true);
    try {
      const [reqRes, stRes] = await Promise.all([api.getRequirements(), api.getShiftTypes()]);
      const stList = (stRes.data || []).filter((st: any) => st.name !== '明け');
      const reqMap = new Map<number, any>((reqRes.data || []).map((r: any) => [r.shift_type_id, r]));
      setShiftTypes(stList);
      setItems(stList.map((st: any) => ({
        id: reqMap.get(st.id)?.id ?? undefined,
        shift_type_id: st.id,
        count: reqMap.get(st.id)?.count ?? 1,
      })));
    } catch (e: any) { toast.error(e.message); }
    finally { setLoading(false); }
  };

  const updateCount = (i: number, count: number) =>
    setItems(prev => prev.map((s, idx) => idx === i ? { ...s, count } : s));

  const save = async () => {
    try {
      await api.saveRequirements(items.filter(r => r.count > 0));
      toast.success('保存しました');
      await load();
    } catch (e: any) { toast.error(e.message); }
  };

  if (loading) return <Loading />;

  return (
    <div>
      <p className="text-sm text-gray-500 mb-3">各勤務種別（日勤・夜勤など）に1日あたり何人必要かを設定します。</p>
      <div className="space-y-2 mb-3">
        {items.map((r, i) => (
          <div key={r.shift_type_id} className="flex items-center gap-3 p-3 bg-gray-50 rounded-xl border border-gray-200">
            <span className="flex-1 text-sm font-medium text-gray-700">
              {shiftTypes.find(st => st.id === r.shift_type_id)?.name || '不明'}
            </span>
            <input type="number" value={r.count || 0}
              onChange={e => updateCount(i, parseInt(e.target.value) || 1)}
              className="w-20 px-3 py-1.5 border border-gray-300 rounded-lg text-sm text-center" min={0} />
            <span className="text-sm text-gray-500">人</span>
          </div>
        ))}
      </div>
      <div className="flex gap-3 pt-3 border-t border-gray-200">
        <Button variant="primary" onClick={save}>保存</Button>
      </div>
    </div>
  );
}
