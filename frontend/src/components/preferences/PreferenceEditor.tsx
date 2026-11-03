'use client';
import { useState, useEffect, useMemo, useCallback } from 'react';
import toast from 'react-hot-toast';
import { api } from '@/lib/api';
import { Preference } from '@/types';
import { Button } from '@/components/ui/Button';
import { Loading } from '@/components/ui/Loading';

const DAYS = ['日', '月', '火', '水', '木', '金', '土'];

const typeIndicator: Record<string, { short: string; color: string; bg: string }> = {
  off:           { short: '休', color: 'text-emerald-700', bg: 'bg-emerald-100' },
  want_shift:    { short: '入', color: 'text-blue-700',    bg: 'bg-blue-100' },
  avoid_shift:   { short: '避', color: 'text-orange-700',  bg: 'bg-orange-100' },
  night_only:    { short: '夜', color: 'text-purple-700',  bg: 'bg-purple-100' },
  cannot_night:  { short: '不', color: 'text-red-700',     bg: 'bg-red-100' },
  fixed_shift:   { short: '固', color: 'text-amber-700',   bg: 'bg-amber-100' },
};

const typeConfig: Record<string, { label: string; color: string; bg: string; border: string }> = {
  off:           { label: '休み希望',     color: 'text-emerald-700', bg: 'bg-emerald-50', border: 'border-emerald-200' },
  want_shift:    { label: '入りたい',      color: 'text-blue-700',    bg: 'bg-blue-50',    border: 'border-blue-200' },
  avoid_shift:   { label: '避けたい',      color: 'text-orange-700',  bg: 'bg-orange-50',  border: 'border-orange-200' },
  night_only:    { label: '夜勤のみ可',    color: 'text-purple-700',  bg: 'bg-purple-50',  border: 'border-purple-200' },
  cannot_night:  { label: '夜勤不可',      color: 'text-red-700',     bg: 'bg-red-50',     border: 'border-red-200' },
  fixed_shift:   { label: 'この勤務で固定',color: 'text-amber-700',   bg: 'bg-amber-50',   border: 'border-amber-200' },
};

export function PreferenceEditor({ month }: { month: string }) {
  const [prefs, setPrefs] = useState<any[]>([]);
  const [staff, setStaff] = useState<any[]>([]);
  const [shiftTypes, setShiftTypes] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const [selectedStaff, setSelectedStaff] = useState<number[]>([]);
  const [selectedDates, setSelectedDates] = useState<Set<string>>(new Set());
  const [type, setType] = useState<Preference['type']>('off');
  const [shiftTypeId, setShiftTypeId] = useState<number | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<any>(null);

  const [currentMonth, setCurrentMonth] = useState(month);
  useEffect(() => { setCurrentMonth(month); }, [month]);
  const [year, monthNum] = currentMonth.split('-').map(Number);

  const dateStr = (day: number) => `${currentMonth}-${String(day).padStart(2, '0')}`;

  const prefsByDate = useMemo(() => {
    const map: Record<string, any[]> = {};
    prefs.forEach(p => {
      const dk = p.target_date.substring(0, 10);
      if (!map[dk]) map[dk] = [];
      map[dk].push(p);
    });
    return map;
  }, [prefs]);

  const prefGrid = useMemo(() => {
    const grid: Record<number, Record<string, any>> = {};
    prefs.forEach((p: any) => {
      if (!grid[p.staff_id]) grid[p.staff_id] = {};
      const dk = p.target_date.substring(0, 10);
      grid[p.staff_id][dk] = p;
    });
    return grid;
  }, [prefs]);

  const fetchData = useCallback(async () => {
    const [prefRes, staffRes, stRes] = await Promise.all([
      api.getPreferences(currentMonth), api.getStaff(), api.getShiftTypes()
    ]);
    setPrefs(prefRes.data || []);
    setStaff(staffRes.data || []);
    setShiftTypes(stRes.data || []);
  }, [currentMonth]);

  useEffect(() => {
    setLoading(true);
    fetchData().finally(() => setLoading(false));
  }, [fetchData]);

  const shiftTypeMap = useMemo(() => {
    const map: Record<number, any> = {};
    shiftTypes.forEach(st => { map[st.id] = st; });
    return map;
  }, [shiftTypes]);

  const toggleDate = (day: number) => {
    const d = dateStr(day);
    setSelectedDates(prev => {
      const next = new Set(prev);
      if (next.has(d)) next.delete(d); else next.add(d);
      return next;
    });
  };

  const toggleStaff = (id: number) => {
    setSelectedStaff(prev =>
      prev.includes(id) ? prev.filter(s => s !== id) : [...prev, id]
    );
  };

  const saveAll = async () => {
    if (!selectedStaff.length) { toast.error('職員を選択してください'); return; }
    if (!selectedDates.size) { toast.error('日付を選択してください'); return; }
    const needs = !['off', 'night_only', 'cannot_night'].includes(type);
    if (needs && !shiftTypeId) { toast.error('勤務種別を選択してください'); return; }
    setSaving(true);
    try {
      for (const date of Array.from(selectedDates)) {
        await api.savePreference({
          staff_ids: selectedStaff,
          target_date: date,
          type,
          shift_type_id: needs ? shiftTypeId : null,
        });
      }
      toast.success(`${selectedStaff.length}名・${selectedDates.size}日分を登録しました`);
      setSelectedStaff([]);
      setSelectedDates(new Set());
      setShiftTypeId(null);
      await fetchData();
    } catch (e: any) { toast.error(e.message); }
    finally { setSaving(false); }
  };

  const deletePref = async (id: number | undefined) => {
    if (id == null) { toast.error('削除対象が不明です'); return; }
    try {
      await api.deletePreference(id);
      toast.success('削除しました');
      await fetchData();
    } catch (e: any) {
      toast.error(e?.message || '不明なエラー');
    }
  };

  const daysInMonth = new Date(year, monthNum, 0).getDate();
  const firstDay = new Date(year, monthNum - 1, 1).getDay();

  const calendarDays: (number | null)[][] = [];
  let week: (number | null)[] = [];
  for (let i = 0; i < firstDay; i++) week.push(null);
  for (let d = 1; d <= daysInMonth; d++) { week.push(d); if (week.length === 7) { calendarDays.push(week); week = []; } }
  if (week.length) calendarDays.push(week);

  if (loading) return <Loading />;

  const activeStaff = staff.filter((s: any) => s.active);

  const isInRange = (s: any, date: string) => {
    if (s.start_date && date < s.start_date) return false;
    if (s.end_date && date > s.end_date) return false;
    return true;
  };
  const needsShiftType = !['off', 'night_only', 'cannot_night'].includes(type);

  const dateList = Array.from({ length: daysInMonth }, (_, i) => i + 1);

  // Legend mapping for compact display
  const typeShort: Record<string, { short: string; label: string }> = {
    off: { short: '休', label: '休み希望' },
    want_shift: { short: '入', label: '入りたい' },
    avoid_shift: { short: '避', label: '避けたい' },
    night_only: { short: '夜', label: '夜勤のみ可' },
    cannot_night: { short: '不', label: '夜勤不可' },
    fixed_shift: { short: '固', label: '固定' },
  };

  return (
    <div className="space-y-5">

      {/* Calendar */}
      <div className="bg-surface-card border border-surface-border rounded-xl shadow-card p-4">
        <div className="flex items-center justify-between mb-3">
          <button onClick={() => { const d = new Date(year, monthNum - 2, 1); setCurrentMonth(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`); }}
            className="text-sm text-gray-400 hover:text-gray-700 transition-colors">&larr;</button>
          <h3 className="text-sm font-semibold text-gray-900">{year}年{monthNum}月</h3>
          <button onClick={() => { const d = new Date(year, monthNum, 1); setCurrentMonth(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`); }}
            className="text-sm text-gray-400 hover:text-gray-700 transition-colors">&rarr;</button>
        </div>
        <div className="grid grid-cols-7 gap-[2px]">
          {DAYS.map((d, i) => (
            <div key={i} className={`text-[10px] font-semibold text-center py-1 ${i === 0 || i === 6 ? 'text-red-300' : 'text-gray-400'}`}>{d}</div>
          ))}
          {calendarDays.flat().map((day, i) => {
            if (day === null) return <div key={`e-${i}`} className="aspect-square" />;
            const ds = dateStr(day);
            const sel = selectedDates.has(ds);
            const has = !!prefsByDate[ds]?.length;
            return (
              <button key={ds} onClick={() => toggleDate(day)}
                className={`relative aspect-square rounded-lg text-xs font-medium transition-all duration-100
                  ${sel ? 'bg-primary-500 text-white shadow-sm' : has ? 'bg-primary-50 text-primary-700 ring-1 ring-primary-200' : 'text-gray-600 hover:bg-gray-50'}`}>
                {day}
                {has && <span className={`absolute bottom-[2px] left-1/2 -translate-x-1/2 w-1 h-1 rounded-full ${sel ? 'bg-white' : 'bg-primary-300'}`} />}
              </button>
            );
          })}
        </div>
      </div>

      {/* Staff pills */}
      <div className="bg-surface-card border border-surface-border rounded-xl shadow-card p-4">
        <label className="text-xs font-semibold text-gray-500 block mb-2.5">職員を選択（複数可）</label>
        <div className="flex flex-wrap gap-1.5">
          {activeStaff.map((s: any) => {
            const sel = selectedStaff.includes(s.id);
            return (
              <button key={s.id} onClick={() => toggleStaff(s.id)}
                className={`px-3 py-1.5 rounded-full text-sm font-medium border transition-all duration-100
                  ${sel ? 'bg-primary-500 text-white border-primary-500 shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:border-primary-300 hover:text-primary-600'}`}>
                {s.name}
              </button>
            );
          })}
        </div>
      </div>

      {/* Type / Shift / Priority */}
      <div className="bg-surface-card border border-surface-border rounded-xl shadow-card p-4 space-y-4">
        <div>
          <label className="text-xs font-semibold text-gray-500 block mb-2">希望の種類</label>
          <div className="flex flex-wrap gap-1.5">
            {Object.entries(typeConfig).map(([k, c]) => (
              <button key={k} onClick={() => setType(k as Preference['type'])}
                className={`px-3 py-1.5 rounded-lg text-sm font-medium border transition-all duration-100
                  ${type === k ? `${c.bg} ${c.color} ${c.border} shadow-sm` : 'bg-white text-gray-500 border-gray-200 hover:border-gray-300'}`}>
                {c.label}
              </button>
            ))}
          </div>
        </div>
        {needsShiftType && (
          <div>
            <label className="text-xs font-semibold text-gray-500 block mb-2">対象勤務</label>
            <div className="flex flex-wrap gap-1.5">
              {shiftTypes.map((st: any) => (
                <button key={st.id} onClick={() => setShiftTypeId(st.id)}
                  className={`px-3 py-1.5 rounded-lg text-sm font-medium border transition-all duration-100
                    ${shiftTypeId === st.id ? 'shadow-sm' : 'bg-white text-gray-500 border-gray-200 hover:border-gray-300'}`}
                  style={shiftTypeId === st.id ? { backgroundColor: st.color + '20', borderColor: st.color, color: st.color } : {}}>
                  {st.name}
                  {st.start_time && st.end_time && <span className="ml-1 opacity-60">({st.start_time.slice(0, 5)}-{st.end_time.slice(0, 5)})</span>}
                </button>
              ))}
            </div>
          </div>
        )}
      </div>

      {/* Save */}
      <Button variant="primary" onClick={saveAll} disabled={saving} className="w-full py-2.5 text-sm">
        {saving ? '登録中...' : `選択した${selectedStaff.length}名・${selectedDates.size}日分を登録`}
      </Button>

      {/* Reset */}
      {prefs.length > 0 && (
        <div className="flex justify-end">
          <Button variant="ghost" size="sm" onClick={() => {
            if (window.confirm('希望入力をすべてリセットしますか？\nすべての希望が削除されます。')) {
              api.resetPreferences(currentMonth).then(() => fetchData());
            }
          }}>
            リセット
          </Button>
        </div>
      )}

      {/* Grid: existing preferences */}
      <div>
        <h3 className="text-sm font-semibold text-gray-900 mb-3">登録済みの希望一覧</h3>

        {activeStaff.length > 0 ? (
          <>
            <div className="overflow-x-auto rounded-xl border border-surface-border shadow-card">
              <table className="w-full border-collapse text-xs">
                <thead>
                  <tr>
                    <th className="sticky left-0 z-10 bg-gray-50 p-1.5 border-b border-r border-surface-border text-left font-semibold text-gray-600 min-w-[80px]">職員</th>
                    {dateList.map(day => (
                      <th key={day} className="p-1 border-b border-surface-border text-center font-medium text-gray-400 min-w-[28px]">{day}</th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {activeStaff.map((s: any, si: number) => {
                    const prefsForStaff = prefGrid[s.id] || {};
                    return (
                      <tr key={s.id} className={si % 2 === 1 ? 'bg-gray-50/30' : 'bg-white'}>
                        <td className="sticky left-0 z-10 p-1.5 border-b border-r border-surface-border font-medium text-gray-800 text-xs whitespace-nowrap"
                          style={{ background: si % 2 === 1 ? '#f8f9fb' : 'white' }}>
                          {s.name}
                        </td>
                        {dateList.map(day => {
                          const ds = dateStr(day);
                          const inRange = isInRange(s, ds);
                          const pref = prefsForStaff[ds];
                          const ind = pref ? (typeIndicator[pref.type] || null) : null;
                          const st = pref?.shift_type_id ? shiftTypeMap[pref.shift_type_id] : null;
                          return (
                            <td key={ds} className={`p-0.5 border-b border-surface-border text-center ${!inRange ? 'bg-gray-100 opacity-40' : ''}`}>
                              {!inRange ? (
                                <span className="text-[9px] text-gray-400">-</span>
                              ) : ind ? (
                                <button
                                  onClick={() => setDeleteTarget(pref)}
                                  className={`w-full h-full min-h-[24px] rounded text-[10px] font-bold ${ind.bg} ${ind.color} hover:ring-1 hover:ring-inset hover:ring-gray-300 transition-all cursor-pointer`}
                                  style={st?.color ? { color: st.color } : undefined}
                                  title={`${s.name} ${ds} ${typeShort[pref.type]?.label || pref.type}${st?.name ? ' (' + st.name + ')' : ''} - クリックで削除`}
                                >
                                  {st ? st.name : ind.short}
                                </button>
                              ) : null}
                            </td>
                          );
                        })}
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>

            {/* Delete confirmation */}
            {deleteTarget && (
              <div className="mt-3 flex items-center justify-between px-4 py-3 rounded-xl bg-red-50 border border-red-200 text-sm">
                <span className="text-red-800">
                  <strong>{deleteTarget.staff?.name}</strong> の <strong>{(deleteTarget.target_date || '').substring(0, 10)}</strong>「{typeShort[deleteTarget.type]?.label || deleteTarget.type}」
                  {deleteTarget.shift_type?.name && <span>（{deleteTarget.shift_type.name}）</span>} を削除しますか？
                </span>
                <div className="flex gap-2">
                  <button onClick={() => setDeleteTarget(null)} className="px-3 py-1.5 rounded-lg text-gray-600 font-medium hover:bg-gray-100 transition-colors">キャンセル</button>
                  <button onClick={async () => { await deletePref(deleteTarget.id); setDeleteTarget(null); }}
                    className="px-3 py-1.5 rounded-lg bg-red-500 text-white font-medium hover:bg-red-600 transition-colors">削除</button>
                </div>
              </div>
            )}
          </>
        ) : (
          <p className="text-gray-400 text-sm text-center py-6">まだ登録されていません</p>
        )}
      </div>
    </div>
  );
}
