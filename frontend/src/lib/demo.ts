import type {
  AutoFixApproachResponse,
  AutoFixResponse,
  CompatibilityRule,
  Conflict,
  DailyOverride,
  GenerateResponse,
  GrowthStatsResponse,
  HolidaySetting,
  ListResponse,
  LoginResponse,
  OrchestratorResponse,
  Preference,
  Requirement,
  ScheduleListResponse,
  SchedulePattern,
  ScheduleCell,
  SaveResponse,
  ShiftType,
  Staff,
  User,
  YearlyStatsResponse,
} from '@/types';

/**
 * Deterministic demo data for the static GitHub Pages preview.
 *
 * Enabled only when NEXT_PUBLIC_DEMO_MODE=1 at build time. The normal app
 * always talks to the Laravel API. No real staff, facility, account, or
 * database is involved in demo mode.
 */
export const DEMO_MODE = process.env.NEXT_PUBLIC_DEMO_MODE === '1';

export const DEMO_USER: User = {
  id: 1,
  username: 'demo',
  display_name: 'Demo Admin',
};

const STAFF_NAMES = [
  'Staff A', 'Staff B', 'Staff C', 'Staff D', 'Staff E',
  'Staff F', 'Staff G', 'Staff H', 'Staff I', 'Staff J',
];

export const DEMO_STAFF: Staff[] = STAFF_NAMES.map((name, i) => ({
  id: i + 1,
  name,
  employment_type: i % 4 === 3 ? 'parttime' : 'fulltime',
  can_night_shift: i % 5 !== 4,
  max_consecutive_days: 4 + (i % 3),
  active: true,
  sort_order: i + 1,
  start_date: null,
  end_date: null,
}));

const SHIFT_DEFS: Omit<ShiftType, 'id'>[] = [
  { name: '日勤', start_time: '08:30', end_time: '17:30', color: '#1976d2', sort_order: 1 },
  { name: '早番', start_time: '07:00', end_time: '16:00', color: '#e67e22', sort_order: 2 },
  { name: '遅番', start_time: '10:00', end_time: '20:00', color: '#8e44ad', sort_order: 3 },
  { name: '夜勤', start_time: '17:00', end_time: '09:00', color: '#2c3e50', sort_order: 4 },
  { name: '明け', start_time: '', end_time: '', color: '#16a085', sort_order: 5 },
];

export const DEMO_SHIFT_TYPES: ShiftType[] = SHIFT_DEFS.map((s, i) => ({ ...s, id: i + 1 }));

export const DEMO_REQUIREMENTS: Requirement[] = [
  { id: 1, shift_type_id: 1, shift_type_name: '日勤', count: 3, shiftType: DEMO_SHIFT_TYPES[0] },
  { id: 2, shift_type_id: 2, shift_type_name: '早番', count: 2, shiftType: DEMO_SHIFT_TYPES[1] },
  { id: 3, shift_type_id: 3, shift_type_name: '遅番', count: 2, shiftType: DEMO_SHIFT_TYPES[2] },
  { id: 4, shift_type_id: 4, shift_type_name: '夜勤', count: 1, shiftType: DEMO_SHIFT_TYPES[3] },
];

export const DEMO_DAILY_OVERRIDES: DailyOverride[] = [
  { id: 1, target_date: '2026-12-24', shift_type_id: 4, count: 2 },
  { id: 2, target_date: '2026-12-25', shift_type_id: 1, count: 2 },
];

export const DEMO_RULES: CompatibilityRule[] = [
  { id: 1, staff1_id: 1, staff2_id: 2, rule_type: 'must_separate' },
];

function shiftName(id?: number | null): string | undefined {
  return DEMO_SHIFT_TYPES.find((s) => s.id === id)?.name;
}

export function buildPreferences(month: string): Preference[] {
  const rows: Array<[number, number, Preference['type'], number | null, Preference['priority'], string]> = [
    [1, 4, 'off', null, 'high', 'Family event'],
    [2, 9, 'off', null, 'medium', 'Requested day off'],
    [3, 14, 'night_only', 4, 'medium', 'Night shift preferred'],
    [4, 18, 'fixed_shift', 1, 'high', 'Fixed day shift'],
    [5, 22, 'off', null, 'low', 'Preferred day off'],
    [6, 26, 'avoid_shift', 4, 'medium', 'Avoid night shift'],
  ];
  return rows.map(([staffId, day, type, shiftId, priority, note], i) => ({
    id: i + 1,
    staff_id: staffId,
    target_date: `${month}-${String(day).padStart(2, '0')}`,
    type,
    shift_type_id: shiftId,
    priority,
    note,
    staff: { id: staffId, name: STAFF_NAMES[staffId - 1] },
  }));
}

export function daysInMonth(month: string): number {
  const [y, m] = month.split('-').map(Number);
  return y && m ? new Date(y, m, 0).getDate() : 30;
}

function isoDate(month: string, day: number): string {
  return `${month}-${String(day).padStart(2, '0')}`;
}

function buildCells(month: string, patternId: number, variant: number): ScheduleCell[] {
  const days = daysInMonth(month);
  const cells: ScheduleCell[] = [];
  const slots = [1, 1, 1, 2, 2, 3, 3]; // day x3, early x2, late x2

  for (let day = 1; day <= days; day++) {
    const nightIdx = (day - 1 + variant) % STAFF_NAMES.length;
    const postNightIdx = day >= 2 ? (day - 2 + variant) % STAFF_NAMES.length : -1;
    const restIdx = day >= 3 ? (day - 3 + variant) % STAFF_NAMES.length : -1;
    const date = isoDate(month, day);
    const available = STAFF_NAMES.map((_, i) => i)
      .filter((i) => i !== nightIdx && i !== postNightIdx && i !== restIdx);

    const assigned = new Map<number, number>();
    assigned.set(nightIdx, 4);
    if (postNightIdx >= 0) assigned.set(postNightIdx, 5);

    const offset = (day + variant) % slots.length;
    available.forEach((staffIdx, i) => {
      assigned.set(staffIdx, slots[(i + offset) % slots.length]);
    });

    STAFF_NAMES.forEach((_, i) => {
      const shiftTypeId = assigned.get(i) ?? null;
      cells.push({
        id: patternId * 10000 + day * 10 + i,
        schedule_pattern_id: patternId,
        staff_id: i + 1,
        target_date: date,
        shift_type_id: shiftTypeId,
        is_fixed: shiftTypeId === 4,
      });
    });
  }
  return cells;
}

export function buildPatterns(month: string): SchedulePattern[] {
  const labels = ['Balanced', 'Night-heavy', 'Preference-first'];
  return labels.map((label, i) => ({
    id: i + 1,
    month_year: month,
    pattern_number: i + 1,
    is_base: i === 0,
    label,
    cells: buildCells(month, i + 1, i),
  }));
}

export function buildConflicts(month: string): Conflict[] {
  const days = daysInMonth(month);
  const d1 = Math.min(12, days);
  const d2 = Math.min(21, days);
  return [
    {
      date: isoDate(month, d1),
      shift_type: shiftName(4) || 'Night',
      required: 1,
      assigned: 0,
      shortfall: 1,
      affected_staff: [],
      message: '夜勤の必要人数を確保できませんでした。夜勤可能なスタッフが不足しています。',
    },
    {
      date: isoDate(month, d2),
      shift_type: shiftName(1) || 'Day',
      required: 3,
      assigned: 2,
      shortfall: 1,
      affected_staff: ['Staff C'],
      message: '日勤の必要人数に対して1名不足しています。希望休の集中が原因です。',
    },
  ];
}

const DEMO_ROUTES = [
  {
    routeId: 'combo_preference_night',
    routeName: '希望休の移動 + 夜勤の再配置',
    priority: 'high' as const,
    isAutofix: false,
    steps: [
      { step: 1, description: '希望休を1日移動して夜勤可能人数を確保' },
      { step: 2, description: '夜勤回数を均等化するよう再配置' },
    ],
    effect: { candidates: 2, penalty: -120, will_establish: true },
  },
  {
    routeId: 'local_fixed_shift',
    routeName: '固定シフト崩しルート',
    priority: 'medium' as const,
    isAutofix: false,
    steps: [{ step: 1, description: '不足日の固定シフトを一時解除して再生成' }],
    effect: { candidates: 3, penalty: -200, will_establish: false },
  },
];

export function demoRoutes() {
  return { routes: DEMO_ROUTES };
}

export function demoRouteDiff() {
  return {
    routeId: 'combo_preference_night',
    before: { assigned: 2, required: 3, status: 'shortfall' },
    after: { assigned: 3, required: 3, status: 'established' },
    differences: { assigned: 1, status: 'improved' },
  };
}

export function demoAutoResolve(month: string) {
  return {
    success: true,
    established: true,
    applied: true,
    routeId: 'combo_preference_night',
    message: 'Applied the optimal break route.',
    stepsApplied: 2,
    scheduleEstablished: true,
    remainingConflicts: 0,
    appliedSteps: [
      { action: 'move_preference', detail: 'Moved one requested day off' },
      { action: 'equalize_night', detail: 'Rebalanced night shifts across staff' },
    ],
    optimalRoute: {
      routeId: 'combo_preference_night',
      routeName: '希望休の移動 + 夜勤の再配置',
      priority: 'high',
      selectionReason: 'Resolves both shortfalls with the smallest penalty.',
      effect: { candidates: 2, penalty: -120, will_establish: true },
    },
    conflicts: [],
  };
}

function series(base: number, amp: number, offset = 0): number[] {
  return Array.from({ length: 12 }, (_, i) =>
    Number((base + Math.sin((i + offset) / 1.8) * amp).toFixed(1)),
  );
}

function perStaffSeries(base: number, amp: number): Record<number, number[]> {
  return Object.fromEntries(
    DEMO_STAFF.map((s, i) => [s.id as number, series(base + i * 0.15, amp, i)]),
  );
}

export function buildYearlyStats(year: number): YearlyStatsResponse {
  const months = Array.from({ length: 12 }, (_, i) => `${year}-${String(i + 1).padStart(2, '0')}`);
  const staff = DEMO_STAFF.map((s) => ({ id: s.id as number, name: s.name }));

  const burden = {
    night_shift_by_month: { label: '夜勤回数', unit: '回', data: series(28, 4), per_staff: perStaffSeries(3, 1) },
    night_to_ake_next_day: { label: '夜勤→明け', unit: '回', data: series(26, 3), per_staff: perStaffSeries(3, 0.8) },
    avg_consecutive_days: { label: '平均連勤日数', unit: '日', data: series(4.2, 0.4), per_staff: perStaffSeries(4, 0.5) },
    early_late_imbalance: { label: '早番/遅番の偏り', unit: 'pt', data: series(1.8, 0.5), per_staff: perStaffSeries(2, 0.6) },
    avg_holiday_days: { label: '平均公休日数', unit: '日', data: series(9, 0.8), per_staff: perStaffSeries(9, 0.7) },
  };

  const fairness = {
    shift_type_stddev: { label: '勤務種別の標準偏差', unit: 'σ', data: series(1.6, 0.3), per_staff: perStaffSeries(1.5, 0.3) },
    night_stddev: { label: '夜勤回数の標準偏差', unit: 'σ', data: series(1.2, 0.25), per_staff: perStaffSeries(1.1, 0.25) },
    preference_achievement_rate: { label: '希望休の達成率', unit: '%', data: series(86, 6), per_staff: perStaffSeries(85, 5) },
    change_request_count: { label: 'シフト変更依頼', unit: '件', data: series(6, 2), per_staff: perStaffSeries(1, 0.5) },
  };

  const safety = {
    danger_pattern_count: { label: '危険パターン', unit: '件', data: series(2, 1), per_staff: perStaffSeries(0.4, 0.2) },
    night_consecutive_max_avg: { label: '夜勤連続最大', unit: '日', data: series(1.4, 0.2), per_staff: perStaffSeries(1.3, 0.2) },
    short_interval_count: { label: '短時間インターバル', unit: '件', data: series(3, 1), per_staff: perStaffSeries(0.5, 0.3) },
    newcomer_night_ratio: { label: '新人の夜勤比率', unit: '%', data: series(18, 4), per_staff: perStaffSeries(18, 4) },
  };

  const growth = {
    experience_score: { label: '経験スコア', unit: 'pt', data: series(72, 5), per_staff: perStaffSeries(70, 6) },
    aptitude_score: { label: '適性スコア', unit: 'pt', data: series(78, 4), per_staff: perStaffSeries(76, 5) },
    tolerance_score: { label: '負担耐性スコア', unit: 'pt', data: series(69, 6), per_staff: perStaffSeries(68, 5) },
    night_aptitude: { label: '夜勤適性', unit: 'pt', data: series(74, 5), per_staff: perStaffSeries(72, 5) },
    early_aptitude: { label: '早番適性', unit: 'pt', data: series(80, 4), per_staff: perStaffSeries(78, 4) },
    late_aptitude: { label: '遅番適性', unit: 'pt', data: series(76, 4), per_staff: perStaffSeries(74, 4) },
  };

  const per_staff_monthly = DEMO_STAFF.map((s, idx) => ({
    staff_id: s.id as number,
    name: s.name,
    monthly: {
      night: months.map((_, i) => 2 + ((i + idx) % 3)),
      early: months.map((_, i) => 4 + ((i + idx) % 2)),
      late: months.map((_, i) => 3 + ((i + idx) % 2)),
      day: months.map((_, i) => 8 + ((i + idx) % 3)),
      total: months.map(() => 20 + (idx % 3)),
      consecutive_max: months.map((_, i) => 4 + ((i + idx) % 2)),
    },
  }));

  return {
    year,
    months,
    staff,
    shift_types: DEMO_SHIFT_TYPES,
    burden,
    fairness,
    safety,
    growth,
    per_staff_monthly,
  };
}

export function buildGrowthStats(year: number): GrowthStatsResponse {
  const months = Array.from({ length: 12 }, (_, i) => `${year}-${String(i + 1).padStart(2, '0')}`);
  return {
    year,
    months,
    staff: DEMO_STAFF.map((s) => ({ id: s.id as number, name: s.name })),
    growth: {
      experience_score: { label: '経験スコア', unit: 'pt', data: series(70, 6) },
      aptitude_score: { label: '適性スコア', unit: 'pt', data: series(76, 5) },
      tolerance_score: { label: '負担耐性スコア', unit: 'pt', data: series(68, 5) },
      night_aptitude: { label: '夜勤適性', unit: 'pt', data: series(72, 6) },
      early_aptitude: { label: '早番適性', unit: 'pt', data: series(79, 4) },
      late_aptitude: { label: '遅番適性', unit: 'pt', data: series(75, 4) },
    },
  };
}

function parseBody(options?: RequestInit): Record<string, unknown> {
  if (!options?.body) return {};
  try {
    return JSON.parse(String(options.body));
  } catch {
    return {};
  }
}

export async function demoRequest<T>(path: string, options?: RequestInit): Promise<T> {
  const [pathname, rawQuery = ''] = path.split('?');
  const query = new URLSearchParams(rawQuery);
  const method = (options?.method || 'GET').toUpperCase();
  const body = parseBody(options);
  const month = String(body.month_year || query.get('month') || query.get('month_year') || '2026-11');
  const year = Number(query.get('year') || new Date().getFullYear());

  await new Promise((resolve) => setTimeout(resolve, 120));

  if (pathname === '/login') {
    return { token: 'demo-token', user: DEMO_USER } as T;
  }
  if (pathname === '/logout') {
    return { message: 'Logged out (demo).' } as T;
  }
  if (pathname === '/user') {
    return DEMO_USER as T;
  }
  if (pathname === '/staff') {
    return { data: DEMO_STAFF } as T;
  }
  if (pathname === '/shift-types') {
    return { data: DEMO_SHIFT_TYPES } as T;
  }
  if (pathname === '/requirements') {
    return { data: DEMO_REQUIREMENTS } as T;
  }
  if (pathname === '/daily-overrides') {
    return { data: DEMO_DAILY_OVERRIDES } as T;
  }
  if (pathname === '/settings/holiday-count') {
    return (method === 'POST' ? { message: 'Saved (demo).' } : { count: 9 }) as T;
  }
  if (pathname === '/compatibility-rules') {
    if (method === 'POST') {
      return { id: 99, ...(body as object) } as T;
    }
    return { data: DEMO_RULES } as T;
  }
  if (pathname.startsWith('/compatibility-rules/')) {
    return { message: 'Deleted (demo).' } as T;
  }
  if (pathname === '/preferences') {
    if (method === 'POST') {
      return { id: 999, ...(body as object) } as T;
    }
    return { data: buildPreferences(month) } as T;
  }
  if (pathname.startsWith('/preferences/')) {
    return { message: 'Updated (demo).' } as T;
  }
  if (pathname === '/schedule') {
    return { patterns: buildPatterns(month) } as T;
  }
  if (pathname === '/schedule/generate') {
    return {
      candidates: [],
      patterns: buildPatterns(month),
      conflicts: buildConflicts(month),
    } as T;
  }
  if (pathname === '/schedule/auto-fix') {
    return {
      candidates: [],
      patterns: buildPatterns(month),
      conflicts: [],
      auto_fix: { applied: true, changes: [] },
    } as T;
  }
  if (pathname === '/schedule/auto-fix-approach') {
    return {
      candidates: [],
      patterns: buildPatterns(month),
      conflicts: [],
      approach_applied: String(body.approach || 'equalize_preferences'),
      approach_description: 'Applied a balanced demo schedule.',
    } as T;
  }
  if (pathname === '/schedule/auto-fix-orchestrator') {
    return {
      candidates: [],
      patterns: buildPatterns(month),
      conflicts: [],
      success: true,
      show_recommendation: true,
      applied_approaches: ['equalize_preferences'],
      approach_results: [
        { name: 'equalize_preferences', description: 'Rebalanced requested days off.', conflict_count: 0, applied: true },
        { name: 'relax_night_chain', description: 'Night chain relaxed.', conflict_count: 1, applied: false },
      ],
      applied_changes: [
        { action: '希望休の移動', detail: 'Staff B: day 9 → day 11', target: 'Staff B' },
        { action: '夜勤の再配置', detail: 'Night shift reassigned to Staff F', target: 'Staff F' },
      ],
      recommended_approach: 'equalize_preferences',
      recommended_description: 'Move one requested day off and rebalance night shifts.',
      recommended_effect: '2 conflicts resolved / penalty -120',
      recommended_confidence: 0.82,
    } as T;
  }
  if (pathname === '/schedule/yearly-stats') {
    return buildYearlyStats(year) as T;
  }
  if (pathname === '/schedule/growth-stats') {
    return buildGrowthStats(year) as T;
  }
  if (pathname === '/schedule/save' || pathname === '/schedule/update-cell' || pathname === '/schedule/swap' || pathname === '/schedule/reset') {
    return { message: 'Saved (demo).' } as T;
  }
  if (pathname === '/break-routes') {
    return demoRoutes() as T;
  }
  if (pathname === '/route-diff') {
    return demoRouteDiff() as T;
  }
  if (pathname === '/auto-resolve') {
    return demoAutoResolve(month) as T;
  }
  if (pathname === '/apply-route') {
    return {
      success: true,
      stepsApplied: 1,
      appliedSteps: [{ action: 'move_preference', detail: 'Moved one requested day off' }],
      scheduleEstablished: true,
      remainingConflicts: 0,
      conflicts: [],
    } as T;
  }
  if (pathname === '/staff/bulk' || pathname === '/shift-types' || pathname === '/requirements' || pathname === '/daily-overrides') {
    return { message: 'Saved (demo).' } as T;
  }

  throw new Error(`Demo mode: endpoint not available (${method} ${pathname})`);
}

export async function demoFetch(input: RequestInfo | URL, init?: RequestInit): Promise<Response> {
  const raw = typeof input === 'string' ? input : input instanceof URL ? input.toString() : input.url;
  const url = new URL(raw, 'http://demo.local');
  const path = `${url.pathname.replace(/^\/api/, '')}${url.search}`;
  const data = await demoRequest(path, init);
  return new Response(JSON.stringify(data), {
    status: 200,
    headers: { 'Content-Type': 'application/json' },
  });
}

export function demoBlob(path: string): Blob {
  const [pathname] = path.split('?');
  if (pathname === '/schedule/export') {
    const rows = [
      'staff,date,shift',
      ...DEMO_STAFF.slice(0, 3).flatMap((s) =>
        [1, 2, 3].map((d) => `${s.name},2026-11-0${d},日勤`),
      ),
    ];
    return new Blob([rows.join('\n')], { type: 'text/csv;charset=utf-8' });
  }
  const pdf = [
    '%PDF-1.4',
    '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
    '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
    '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj',
    '4 0 obj << /Length 44 >> stream',
    'BT /F1 18 Tf 72 770 Td (memeShift demo PDF) Tj ET',
    'endstream endobj',
    '5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
    'trailer << /Root 1 0 R /Size 6 >>',
    '%%EOF',
  ].join('\n');
  return new Blob([pdf], { type: 'application/pdf' });
}
