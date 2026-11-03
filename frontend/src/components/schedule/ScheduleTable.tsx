'use client';
import { useMemo } from 'react';
import type { SchedulePattern, Staff, ShiftType, ScheduleCell } from '@/types';

const WEEKDAY_LABELS = ['日', '月', '火', '水', '木', '金', '土'];

function getWeekday(dateStr: string): number {
  return new Date(dateStr).getDay();
}

function isWeekend(day: number): boolean {
  return day === 0 || day === 6;
}

function isInRange(s: Staff, date: string): boolean {
  if (s.start_date && date < s.start_date) return false;
  if (s.end_date && date > s.end_date) return false;
  return true;
}

interface CellMap {
  [date: string]: { [staffId: number]: ScheduleCell };
}

interface Props {
  patterns: SchedulePattern[];
  staff: Staff[];
  shiftTypes: ShiftType[];
  activePattern: number;
  tableKey: number;
  highlightCells?: Set<string>;
  swapMode: boolean;
  swapSelection: { staffId: number; date: string } | null;
  onCellClick: (staffId: number, dateStr: string) => void;
  onCellChange: (staffId: number, date: string, shiftTypeId: number | null) => void;
}

export function ScheduleTable({
  patterns, staff, shiftTypes, activePattern, tableKey,
  highlightCells,
  swapMode, swapSelection,
  onCellClick, onCellChange,
}: Props) {
  const [year, mon] = patterns[0]?.month_year?.split('-') || ['', ''];
  const daysInMonth = useMemo(() => {
    const y = parseInt(year);
    const m = parseInt(mon);
    return y && m ? new Date(y, m, 0).getDate() : 0;
  }, [year, mon]);

  const dateList = useMemo(
    () => Array.from({ length: daysInMonth }, (_, i) => {
      const d = i + 1;
      const dateStr = `${patterns[0]?.month_year}-${String(d).padStart(2, '0')}`;
      return { day: d, dateStr, weekday: getWeekday(dateStr) };
    }),
    [daysInMonth, patterns]
  );

  const cellsByDate = (patternIdx: number): CellMap => {
    const cells = patterns[patternIdx]?.cells || [];
    const map: CellMap = {};
    cells.forEach((c) => {
      if (!map[c.target_date]) map[c.target_date] = {};
      map[c.target_date][c.staff_id] = c;
    });
    return map;
  };

  const currentCells = cellsByDate(activePattern);

  const activeStaff = useMemo(() => staff.filter((s) => s.active), [staff]);

  if (patterns.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center py-16 text-center">
        <div className="w-12 h-12 rounded-xl bg-primary-50 flex items-center justify-center mb-4">
          <span className="text-xl text-primary-400/70">&#9744;</span>
        </div>
        <p className="text-sm text-gray-700 mb-1">勤務表がまだ作成されていません。</p>
        <p className="text-xs text-gray-500">「勤務自動作成」をクリックして勤務表を生成してください。</p>
      </div>
    );
  }

  return (
    <div className="rounded-xl border border-surface-border shadow-card">
      <table key={tableKey} className="w-full table-fixed border-collapse text-[10px]">
        <thead>
          <tr>
            <th className="w-[76px] min-w-[76px] bg-gray-50 p-1.5 border-b border-r border-surface-border text-left font-semibold text-gray-700">
              職員
            </th>
            {dateList.map(({ day, dateStr, weekday }) => (
              <th
                key={day}
                className={`p-1 border-b border-surface-border text-center font-medium ${
                  isWeekend(weekday) ? 'bg-primary-50/50 text-primary-700' : 'text-gray-500 bg-gray-50/50'
                }`}
              >
                <div className="text-[10px] leading-tight">{day}</div>
                <div className={`text-[8px] leading-tight mt-px ${isWeekend(weekday) ? 'text-primary-500/80' : 'text-gray-400'}`}>
                  {WEEKDAY_LABELS[weekday]}
                </div>
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {activeStaff.map((s, si) => (
            <tr key={s.id} className={`${si % 2 === 1 ? 'bg-gray-50/30' : 'bg-white'} hover-ice`}>
              <td className="w-[76px] min-w-[76px] p-1.5 border-b border-r border-surface-border font-medium text-gray-800 text-[10px] leading-tight"
                style={{ background: si % 2 === 1 ? 'inherit' : undefined }}>
                {s.name}
                <span className="block text-[8px] text-gray-500 font-normal">
                  {s.employment_type === 'parttime' ? '非常勤' : '常勤'}
                </span>
              </td>
              {dateList.map(({ day, dateStr, weekday }) => {
                const sid = s.id;
                if (sid == null) return <td key={dateStr} className="p-0.5 border-b border-surface-border text-center bg-gray-100"><span className="text-[8px] text-gray-400 font-medium">-</span></td>;
                const inRange = isInRange(s, dateStr);
                const cell = currentCells[dateStr]?.[sid];
                const st = shiftTypes.find((st) => st.id === cell?.shift_type_id);
                const showBg = isWeekend(weekday);
                const isSelected = swapSelection?.staffId === sid && swapSelection?.date === dateStr;
                const highlighted = !!highlightCells?.has(`${sid}-${dateStr}`);
                let cellBg = '';
                if (!inRange) cellBg = 'bg-gray-100';
                else if (isSelected) cellBg = 'bg-primary-200 ring-2 ring-primary-400';
                else if (highlighted) cellBg = 'bg-sky-100';
                else if (showBg) cellBg = 'bg-primary-50/20';
                const abbrMap: Record<string, string> = { '日勤': '日', '早番': '早', '遅番': '遅', '夜勤': '夜' };
                const abbr = st ? (abbrMap[st.name] || st.name.slice(0, 1)) : '';
                return (
                  <td
                    key={dateStr}
                    title={highlighted ? '今回の適用で変更' : undefined}
                    onClick={inRange && swapMode ? () => onCellClick(sid, dateStr) : undefined}
                    className={`p-0.5 border-b border-surface-border text-center relative ${!inRange ? 'opacity-40' : ''} ${swapMode && inRange ? 'cursor-pointer' : ''} group ${cellBg}`}
                  >
                    <div className="relative min-h-[22px] flex items-center justify-center">
                      {!inRange ? (
                        <span className="text-[8px] text-gray-400 font-medium">-</span>
                      ) : cell?.shift_type_id ? (
                        <span className="text-[11px] font-bold leading-none" style={{ color: st?.color }}>
                          {abbr}
                        </span>
                      ) : (
                        <span className="text-[11px] font-bold leading-none" style={{ color: '#f472b6' }}>休</span>
                      )}
                      {inRange && !swapMode && (
                        <select
                          value={cell?.shift_type_id || ''}
                          onChange={e => onCellChange(sid, dateStr, e.target.value ? parseInt(e.target.value) : null)}
                          className="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                        >
                          <option value="">休み</option>
                          {shiftTypes.map((st) => (
                            <option key={st.id} value={st.id}>{st.name}</option>
                          ))}
                        </select>
                      )}
                      <div className="absolute -bottom-px right-px flex items-center gap-px">
                        {cell?.is_fixed && <span className="text-[6px] text-gray-400" title="固定">●</span>}
                      </div>
                    </div>
                  </td>
                );
              })}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
