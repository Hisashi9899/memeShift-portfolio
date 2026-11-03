'use client';
import { useState, useEffect } from 'react';
import toast from 'react-hot-toast';
import { api } from '@/lib/api';
import { CompatibilityRule } from '@/types';
import { Button } from '@/components/ui/Button';
import { Loading } from '@/components/ui/Loading';

export function CompatibilityRuleEditor() {
  const [rules, setRules] = useState<any[]>([]);
  const [staff, setStaff] = useState<any[]>([]);
  const [staff1, setStaff1] = useState('');
  const [staff2, setStaff2] = useState('');
  const [ruleType, setRuleType] = useState<CompatibilityRule['rule_type']>('must_together');
  const [loading, setLoading] = useState(true);

  useEffect(() => { load(); }, []);

  const load = async () => {
    setLoading(true);
    try {
      const [rulesRes, staffRes] = await Promise.all([api.getRules(), api.getStaff()]);
      setRules(rulesRes.data || []);
      setStaff(staffRes.data || []);
    } catch (e: any) { toast.error(e.message); }
    finally { setLoading(false); }
  };

  const addRule = async () => {
    if (!staff1 || !staff2) return;
    try {
      await api.addRule({ staff1_id: parseInt(staff1), staff2_id: parseInt(staff2), rule_type: ruleType });
      toast.success('追加しました');
      setStaff1(''); setStaff2(''); setRuleType('must_together');
      await load();
    } catch (e: any) { toast.error(e.message); }
  };

  const deleteRule = async (id: number) => {
    try {
      await api.deleteRule(id);
      toast.success('削除しました');
      await load();
    } catch (e: any) { toast.error(e.message); }
  };

  if (loading) return <Loading />;

  const ruleLabels: Record<string, string> = {
    must_together: '必ず一緒', must_separate: '必ず別日',
    can_together: '一緒でも可', can_separate: '別日でも可',
  };

  return (
    <div>
      <div className="grid grid-cols-[1fr_1fr_1.2fr_auto] gap-3 mb-4">
        <select value={staff1} onChange={e => setStaff1(e.target.value)} className="px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
          <option value="">選択してください</option>
          {staff.map((s: any) => <option key={s.id} value={s.id}>{s.name}</option>)}
        </select>
        <select value={ruleType} onChange={e => setRuleType(e.target.value as CompatibilityRule['rule_type'])} className="px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
          {Object.entries(ruleLabels).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
        </select>
        <select value={staff2} onChange={e => setStaff2(e.target.value)} className="px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
          <option value="">選択してください</option>
          {staff.map((s: any) => <option key={s.id} value={s.id}>{s.name}</option>)}
        </select>
        <Button variant="primary" onClick={addRule} className="self-end">相性ルールを追加</Button>
      </div>
      <p className="text-xs text-gray-400 mb-3">「必ず一緒」「必ず別日」は確実に守られるルールです。「一緒でも可」「別日でも可」は可能な範囲で考慮されます。</p>
      <div className="space-y-2">
        {rules.map((r: any) => (
          <div key={r.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-200">
            <span className="text-sm">
              {r.staff1?.name || '?'} <span className="text-blue-600 font-medium">{ruleLabels[r.rule_type]}</span> {r.staff2?.name || '?'}
            </span>
            <button onClick={() => deleteRule(r.id)} className="text-red-500 hover:text-red-700 text-sm">削除</button>
          </div>
        ))}
        {rules.length === 0 && <p className="text-gray-400 text-sm text-center py-4">ルールが登録されていません。</p>}
      </div>
    </div>
  );
}
