export interface Staff {
  id?: number;
  name: string;
  employment_type: 'fulltime' | 'parttime';
  can_night_shift: boolean;
  max_consecutive_days: number;
  start_date?: string | null;
  end_date?: string | null;
  active: boolean;
  sort_order?: number;
}

export interface ShiftType {
  id?: number;
  name: string;
  start_time?: string;
  end_time?: string;
  color: string;
  sort_order?: number;
}

export interface ScheduleCell {
  id?: number;
  schedule_pattern_id?: number;
  staff_id: number;
  target_date: string;
  shift_type_id?: number | null;
  is_fixed: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface SchedulePattern {
  id: number;
  month_year: string;
  pattern_number: number;
  is_base: boolean;
  label?: string;
  cells: ScheduleCell[];
}

export interface Preference {
  id?: number;
  staff_id?: number;
  staff_ids?: number[];
  target_date: string;
  type: 'off' | 'want_shift' | 'avoid_shift' | 'night_only' | 'cannot_night' | 'fixed_shift';
  shift_type_id?: number | null;
  priority?: 'high' | 'medium' | 'low';
  note?: string;
  staff?: { id: number; name: string };
}

export interface CompatibilityRule {
  id?: number;
  staff1_id: number;
  staff2_id: number;
  rule_type: 'must_together' | 'must_separate' | 'can_together' | 'can_separate';
}

export interface Requirement {
  id?: number;
  shift_type_id: number;
  shift_type_name?: string;
  count: number;
  shiftType?: ShiftType;
}

export interface User {
  id: number;
  username: string;
  display_name: string;
}

export interface Conflict {
  date: string;
  shift_type: string;
  required: number;
  assigned: number;
  shortfall: number;
  affected_staff: string[];
  message: string;
}

export interface AutoFixChange {
  staff_name: string;
  date: string;
  preference_type: string;
  detail: string;
}

export interface Candidate {
  id: string;
  schedule: SchedulePattern;
  features: any[];
}

export interface DailyOverride {
  id?: number;
  target_date: string;
  shift_type_id: number;
  count: number;
}

export interface HolidaySetting {
  count: number;
}

// API Response types
export interface ScheduleListResponse {
  patterns: SchedulePattern[];
}

export interface GenerateResponse {
  candidates: Candidate[];
  patterns: SchedulePattern[];
  conflicts: Conflict[];
  relaxed_constraints?: string[];
}

export interface AutoFixResponse {
  candidates: Candidate[];
  patterns: SchedulePattern[];
  conflicts: Conflict[];
  auto_fix: {
    applied: boolean;
    changes: AutoFixChange[];
  };
}

export interface AutoFixApproachResponse {
  candidates: Candidate[];
  patterns: SchedulePattern[];
  conflicts: Conflict[];
  approach_applied: string;
  approach_description: string;
  temporary_staff?: { id: number; name: string; can_night_shift: boolean; active: boolean; employment_type: string };
}

export interface ApproachResult {
  name: string;
  description: string;
  conflict_count: number;
  applied: boolean;
}

export interface AppliedChange {
  action: string;
  detail: string;
  target: string;
}

export interface JumpPayload {
  constraint_key?: string;
  staff_id?: number;
  staff_name?: string;
  shift_type_id?: number;
  shift_type_name?: string;
  date?: string;
}

export interface ManualFixSuggestion {
  shift: string;
  date: string;
  reason: string;
  suggestion: string;
  jump_type?: string;
  impact?: number;
  jump_payload?: JumpPayload;
}

export interface OrchestratorResponse {
  candidates: Candidate[];
  patterns: SchedulePattern[];
  conflicts: Conflict[];
  success: boolean;
  applied_approaches: string[];
  approach_results?: ApproachResult[];
  applied_changes?: AppliedChange[];
  recommended_approach?: string;
  recommended_description?: string;
  recommended_effect?: string;
  recommended_confidence?: number;
  auto_fix_limit_reached?: boolean;
  manual_fix_summary?: string;
  manual_fix_suggestions?: ManualFixSuggestion[];
}

export interface SaveResponse {
  message: string;
}

export interface ListResponse<T> {
  data: T[];
}

export interface YearlyStatsResponse {
  year: number;
  months: string[];
  staff: { id: number; name: string }[];
  shift_types: ShiftType[];
  burden: Record<string, MetricSet>;
  fairness: Record<string, MetricSet>;
  safety: Record<string, MetricSet>;
  growth: Record<string, MetricSet>;
  per_staff_monthly: PerStaffMonthly[];
}

export interface MetricSet {
  label: string;
  unit: string;
  data: number[];
  per_staff?: Record<number, number[]>;
}

export interface PerStaffMonthly {
  staff_id: number;
  name: string;
  monthly: {
    night: number[];
    early: number[];
    late: number[];
    day: number[];
    total: number[];
    consecutive_max: number[];
  };
}

export interface GrowthStatsResponse {
  year: number;
  months: string[];
  staff: { id: number; name: string }[];
  growth: Record<string, MetricSet>;
}

export interface LoginResponse {
  token: string;
  user: User;
}
