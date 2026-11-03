// ===== Shared DTOs (memeShift) =====
// Unified type definitions shared between frontend (Next.js), backend (Laravel), and logic engine.

// --- Staff Domain ---
export interface StaffDTO {
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

// --- ShiftType Domain ---
export interface ShiftTypeDTO {
  id?: number;
  name: string;
  start_time?: string;
  end_time?: string;
  color: string;
  sort_order?: number;
}

// --- Schedule Domain ---
export interface SchedulePatternDTO {
  id: number;
  month_year: string;
  pattern_number: number;
  is_base: boolean;
  cells: ScheduleCellDTO[];
}

export interface ScheduleCellDTO {
  id?: number;
  schedule_pattern_id?: number;
  staff_id: number;
  target_date: string;
  shift_type_id?: number | null;
  is_fixed: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface PreferenceDTO {
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

export interface CompatibilityRuleDTO {
  id?: number;
  staff1_id: number;
  staff2_id: number;
  rule_type: 'must_together' | 'must_separate' | 'can_together' | 'can_separate';
}

export interface RequirementDTO {
  id?: number;
  shift_type_id: number;
  shift_type_name?: string;
  count: number;
  shiftType?: ShiftTypeDTO;
}

export interface DailyOverrideDTO {
  id?: number;
  target_date: string;
  shift_type_id: number;
  count: number;
}

export interface HolidaySettingDTO {
  count: number;
}

// --- Conflict / AutoFix ---
export interface ConflictDTO {
  date: string;
  shift_type: string;
  required: number;
  assigned: number;
  shortfall: number;
  affected_staff: string[];
  message: string;
}

export interface AutoFixChangeDTO {
  staff_name: string;
  date: string;
  preference_type: string;
  detail: string;
}

export interface CandidateDTO {
  id: string;
  schedule: SchedulePatternDTO;
  features: any[];
}

// --- API Response DTOs ---
export interface ScheduleListResponseDTO {
  patterns: SchedulePatternDTO[];
}

export interface GenerateResponseDTO {
  candidates: CandidateDTO[];
  patterns: SchedulePatternDTO[];
  conflicts: ConflictDTO[];
  relaxed_constraints?: string[];
}

export interface AutoFixResponseDTO {
  candidates: CandidateDTO[];
  patterns: SchedulePatternDTO[];
  conflicts: ConflictDTO[];
  auto_fix: {
    applied: boolean;
    changes: AutoFixChangeDTO[];
  };
}

export interface AutoFixApproachResponseDTO {
  candidates: CandidateDTO[];
  patterns: SchedulePatternDTO[];
  conflicts: ConflictDTO[];
  approach_applied: string;
  approach_description: string;
  temporary_staff?: { id: number; name: string; can_night_shift: boolean; active: boolean; employment_type: string };
}

export interface ManualFixSuggestionDTO {
  shift: string;
  date: string;
  reason: string;
  suggestion: string;
  jump_type: string;
  jump_payload: Record<string, unknown>;
  impact: number;
}

export interface ApproachResultDTO {
  name: string;
  description: string;
  conflict_count: number;
  applied: boolean;
}

export interface AppliedChangeDTO {
  action: string;
  detail: string;
  target: string;
}

export interface OrchestratorResponseDTO {
  candidates: CandidateDTO[];
  patterns: SchedulePatternDTO[];
  conflicts: ConflictDTO[];
  success: boolean;
  show_recommendation?: boolean;
  applied_approaches: string[];
  approach_results: ApproachResultDTO[];
  applied_changes: AppliedChangeDTO[];
  recommended_approach?: string;
  recommended_description?: string;
  recommended_effect?: string;
  recommended_confidence?: number;
  auto_fix_limit_reached?: boolean;
  manual_fix_summary?: string;
  manual_fix_suggestions?: ManualFixSuggestionDTO[];
}

export interface SaveResponseDTO {
  message: string;
}

export interface ListResponseDTO<T> {
  data: T[];
}

export interface LoginResponseDTO {
  token: string;
  user: UserDTO;
}

export interface UserDTO {
  id: number;
  username: string;
  display_name: string;
}

// --- Statistics Domain ---
export interface MetricSetDTO {
  label: string;
  unit: string;
  data: number[];
  per_staff?: Record<number, number[]>;
}

export interface PerStaffMonthlyDTO {
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

export interface YearlyStatsResponseDTO {
  year: number;
  months: string[];
  staff: { id: number; name: string }[];
  shift_types: ShiftTypeDTO[];
  burden: Record<string, MetricSetDTO>;
  fairness: Record<string, MetricSetDTO>;
  safety: Record<string, MetricSetDTO>;
  growth: Record<string, MetricSetDTO>;
  per_staff_monthly: PerStaffMonthlyDTO[];
}

export interface GrowthStatsResponseDTO {
  year: number;
  months: string[];
  staff: { id: number; name: string }[];
  growth: Record<string, MetricSetDTO>;
}
