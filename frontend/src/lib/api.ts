import { tokenStorage } from './token';
import type {
  AutoFixApproachResponseDTO,
  AutoFixResponseDTO,
  GenerateResponseDTO,
  GrowthStatsResponseDTO,
  ListResponseDTO,
  LoginResponseDTO,
  OrchestratorResponseDTO,
  SaveResponseDTO,
  ScheduleListResponseDTO,
  YearlyStatsResponseDTO,
  StaffDTO as Staff,
  ShiftTypeDTO as ShiftType,
  RequirementDTO as Requirement,
  DailyOverrideDTO as DailyOverride,
  PreferenceDTO as Preference,
  CompatibilityRuleDTO as CompatibilityRule,
  HolidaySettingDTO as HolidaySetting,
  AutoFixChangeDTO as AutoFixChange,
} from '@shared/types';

const API = process.env.NEXT_PUBLIC_API_URL || '/api';

function authHeaders(): Record<string, string> {
  const token = tokenStorage.get();
  if (!token) return {};
  return { Authorization: `Bearer ${token}` };
}

async function request<T>(path: string, options?: RequestInit): Promise<T> {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    ...authHeaders(),
    ...(options?.headers as Record<string, string>),
  };
  const res = await fetch(`${API}${path}`, { headers, ...options });
  if (!res.ok) {
    const body = await res.json().catch(() => ({ message: res.statusText }));
    throw new Error(body.message || `API error ${res.status}`);
  }
  return res.json();
}

async function requestBlob(path: string): Promise<Blob> {
  const headers: Record<string, string> = {
    ...authHeaders(),
  };
  const res = await fetch(`${API}${path}`, { headers });
  if (!res.ok) {
    const body = await res.json().catch(() => ({ message: res.statusText }));
    throw new Error(body.message || `API error ${res.status}`);
  }
  return res.blob();
}

function downloadBlob(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

interface MessageResponse {
  message: string;
}

export const api = {
  login: (username: string, password: string) =>
    request<LoginResponseDTO>('/login', {
      method: 'POST',
      body: JSON.stringify({ username, password }),
    }),
  logout: () => request<MessageResponse>('/logout', { method: 'POST' }),
  user: () => request<{ id: number; username: string; display_name: string }>('/user'),

  getStaff: () => request<ListResponseDTO<Staff>>(`/staff?_=${Date.now()}`),
  saveStaff: (data: { staff: Staff[] }) =>
    request<SaveResponseDTO>('/staff/bulk', { method: 'POST', body: JSON.stringify(data) }),

  getShiftTypes: () => request<ListResponseDTO<ShiftType>>(`/shift-types?_=${Date.now()}`),
  saveShiftTypes: (data: ShiftType[]) =>
    request<SaveResponseDTO>('/shift-types', { method: 'POST', body: JSON.stringify({ types: data }) }),

  getRequirements: () => request<ListResponseDTO<Requirement>>('/requirements'),
  saveRequirements: (data: Requirement[]) =>
    request<SaveResponseDTO>('/requirements', { method: 'POST', body: JSON.stringify({ requirements: data }) }),

  getDailyOverrides: (month: string) =>
    request<ListResponseDTO<DailyOverride>>(`/daily-overrides?month_year=${month}`),
  saveDailyOverrides: (payload: { overrides: DailyOverride[]; month?: string }) =>
    request<SaveResponseDTO>('/daily-overrides', { method: 'POST', body: JSON.stringify({ overrides: payload.overrides, month_year: payload.month }) }),

  getHolidayCount: (month: string) =>
    request<HolidaySetting>(`/settings/holiday-count?month_year=${month}`),
  saveHolidayCount: (month: string, count: number) =>
    request<MessageResponse>('/settings/holiday-count', {
      method: 'POST',
      body: JSON.stringify({ month_year: month, count }),
    }),

  getRules: () => request<ListResponseDTO<CompatibilityRule>>('/compatibility-rules'),
  addRule: (data: Omit<CompatibilityRule, 'id'>) =>
    request<CompatibilityRule>('/compatibility-rules', {
      method: 'POST',
      body: JSON.stringify(data),
    }),
  deleteRule: (id: number) =>
    request<MessageResponse>(`/compatibility-rules/${id}`, { method: 'DELETE' }),

  generate: (month: string, preserveFixed: boolean, constraints?: Record<string, any>, patternId?: number) =>
    request<GenerateResponseDTO>('/schedule/generate', {
      method: 'POST',
      body: JSON.stringify({ month_year: month, preserve_fixed: preserveFixed, constraints, pattern_id: patternId }),
    }),

  autoFix: (month: string, preserveFixed: boolean, constraints?: Record<string, any>) =>
    request<AutoFixResponseDTO>('/schedule/auto-fix', {
      method: 'POST',
      body: JSON.stringify({ month_year: month, preserve_fixed: preserveFixed, constraints }),
    }),

  autoFixApproach: (month: string, preserveFixed: boolean, constraints: Record<string, any>, approach: string) =>
    request<AutoFixApproachResponseDTO>('/schedule/auto-fix-approach', {
      method: 'POST',
      body: JSON.stringify({ month_year: month, preserve_fixed: preserveFixed, constraints, approach }),
    }),

  autoFixOrchestrator: (month: string, preserveFixed: boolean, constraints?: Record<string, any>) =>
    request<OrchestratorResponseDTO>('/schedule/auto-fix-orchestrator', {
      method: 'POST',
      body: JSON.stringify({ month_year: month, preserve_fixed: preserveFixed, constraints }),
    }),

  getSchedule: (month: string) => request<ScheduleListResponseDTO>(`/schedule?month=${month}`),
  saveSchedule: (data: { cells: any[] }) =>
    request<SaveResponseDTO>('/schedule/save', { method: 'POST', body: JSON.stringify(data) }),

  updateCell: (month: string, data: { schedulePatternId: number; staffId: number; date: string; shiftTypeId: number | null }) =>
    request<SaveResponseDTO>('/schedule/update-cell', {
      method: 'POST',
      body: JSON.stringify({
        schedule_pattern_id: data.schedulePatternId,
        staff_id: data.staffId,
        target_date: data.date,
        shift_type_id: data.shiftTypeId,
        is_fixed: true,
        month_year: month,
      }),
    }),

  swapSchedule: (patternId: number, date: string, staffIdA: number, staffIdB: number) =>
    request<SaveResponseDTO>('/schedule/swap', {
      method: 'POST',
      body: JSON.stringify({ pattern_id: patternId, date, staff_id_a: staffIdA, staff_id_b: staffIdB }),
    }),

  resetSchedule: (month: string) =>
    request<SaveResponseDTO>('/schedule/reset', {
      method: 'POST',
      body: JSON.stringify({ month_year: month }),
    }),

  getYearlyStats: (year: number) =>
    request<YearlyStatsResponseDTO>(`/schedule/yearly-stats?year=${year}`),

  getGrowthStats: (year: number) =>
    request<GrowthStatsResponseDTO>(`/schedule/growth-stats?year=${year}`),

  exportSchedulePdf: async (patternId: number) => {
    const blob = await requestBlob(`/schedule/export-pdf?pattern_id=${patternId}`);
    downloadBlob(blob, `schedule_${patternId}.pdf`);
  },

  exportSchedule: async (patternId: number) => {
    const blob = await requestBlob(`/schedule/export?pattern_id=${patternId}`);
    downloadBlob(blob, `schedule_${patternId}.csv`);
  },

  getPreferences: (month: string) =>
    request<ListResponseDTO<Preference>>(`/preferences?month_year=${month}&_=${Date.now()}`),
  savePreference: (data: Omit<Preference, 'id'>) =>
    request<Preference>('/preferences', { method: 'POST', body: JSON.stringify(data) }),
  deletePreference: (id: number) =>
    request<SaveResponseDTO>(`/preferences/${id}/delete`, { method: 'POST' }),
  resetPreferences: (month: string) =>
    request<SaveResponseDTO>('/preferences/reset', {
      method: 'POST',
      body: JSON.stringify({ month_year: month }),
    }),
};
