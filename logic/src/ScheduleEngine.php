<?php

namespace MemeShift\Logic;

class ScheduleEngine
{
    private array $staff = [];
    private array $shiftTypes = [];
    private array $patterns = [];
    private array $preferences = [];
    private array $requirements = [];
    private array $overrides = [];
    private array $compatibilityRules = [];
    private array $holidaySettings = [];

    private array $monthlySchedule = [];
    private array $staffCounts = [];
    private array $nightShiftIds = [];
    private array $dayShiftIds = [];
    private array $allDates = [];
    private array $publicHolidays = [];
    private string $yearMonth;

    private const NIGHT_THRESHOLD_START = 12;
    private const NIGHT_THRESHOLD_END = 18;

    public function generate(array $params): array
    {
        $this->yearMonth = $params['year_month'];
        $this->staff = $params['staff'] ?? [];
        $this->shiftTypes = $params['shift_types'] ?? [];
        $this->patterns = $params['patterns'] ?? [];
        $this->preferences = $params['preferences'] ?? [];
        $this->requirements = $params['requirements'] ?? [];
        $this->overrides = $params['overrides'] ?? [];
        $this->compatibilityRules = $params['compatibility_rules'] ?? [];
        $this->holidaySettings = $params['holiday_settings'] ?? [];

        $this->classifyShiftTypes();
        $this->buildAllDates();
        $this->extractPublicHolidays();
        $this->initializeTracking();

        $this->assignExistingCells();
        $this->distributePublicHolidays();
        $this->handleDayOffPreferences();
        $this->assignNightShifts();
        $this->assignDayShifts();

        $conflicts = $this->detectConflicts();
        $autoFixResults = $this->autoFixInternal($conflicts);

        return [
            'cells' => $this->monthlySchedule,
            'conflicts' => $conflicts,
            'auto_fix' => $autoFixResults,
            'score' => $this->calculateScore(),
            'stats' => $this->computeStats(),
        ];
    }

    public function autoFixExternal(array $cells, array $requirements, array $compatibilityRules): array
    {
        $this->monthlySchedule = $cells;
        $this->requirements = $requirements;
        $this->compatibilityRules = $compatibilityRules;

        $this->classifyShiftTypes();
        $this->initializeTracking();
        $this->rebuildTrackingFromCells();

        $conflicts = $this->detectConflicts();

        for ($i = 0; $i < 5; $i++) {
            $newConflicts = $this->attemptFixPass($conflicts);
            if (empty($newConflicts)) {
                return ['fixed' => true, 'cells' => $this->monthlySchedule, 'passes' => $i + 1];
            }
            $conflicts = $newConflicts;
        }

        return ['fixed' => false, 'cells' => $this->monthlySchedule, 'remaining_conflicts' => $conflicts];
    }

    public function autoFixInternal(array $conflicts): array
    {
        if (empty($conflicts)) {
            return ['fixed' => true, 'cells' => $this->monthlySchedule, 'passes' => 0];
        }
        for ($i = 0; $i < 5; $i++) {
            $newConflicts = $this->attemptFixPass($conflicts);
            if (empty($newConflicts)) {
                return ['fixed' => true, 'cells' => $this->monthlySchedule, 'passes' => $i + 1];
            }
            $conflicts = $newConflicts;
        }
        return ['fixed' => false, 'cells' => $this->monthlySchedule, 'remaining_conflicts' => $conflicts];
    }

    public function analyze(array $cells, array $staff, array $shiftTypes): array
    {
        $this->monthlySchedule = $cells;
        $this->staff = $staff;
        $this->shiftTypes = $shiftTypes;
        $this->classifyShiftTypes();

        $stats = $this->computeStats();
        $balancingIssues = $this->analyzeBalancing();
        $preferenceAdherence = $this->analyzePreferenceAdherence();

        return [
            'stats' => $stats,
            'balancing_issues' => $balancingIssues,
            'preference_adherence' => $preferenceAdherence,
        ];
    }

    private function classifyShiftTypes(): void
    {
        $this->nightShiftIds = [];
        $this->dayShiftIds = [];
        foreach ($this->shiftTypes as $st) {
            if ($this->isNightShift($st)) {
                $this->nightShiftIds[] = $st['id'];
            } else {
                $this->dayShiftIds[] = $st['id'];
            }
        }
    }

    private function isNightShift(array $st): bool
    {
        if (isset($st['type']) && $st['type'] === 'night') return true;
        if (isset($st['name']) && preg_match('/夜|深夜|night/i', $st['name'])) return true;
        if (isset($st['start_time'])) {
            $startHour = (int) explode(':', $st['start_time'])[0];
            if ($startHour >= self::NIGHT_THRESHOLD_START && $startHour <= self::NIGHT_THRESHOLD_END) return false;
            if ($startHour >= 17 || $startHour < 5) return true;
        }
        return false;
    }

    private function buildAllDates(): void
    {
        $year = (int) substr($this->yearMonth, 0, 4);
        $month = (int) substr($this->yearMonth, 5, 2);
        $daysInMonth = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
        $this->allDates = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $this->allDates[] = sprintf('%04d-%02d-%02d', $year, $month, $d);
        }
    }

    private function extractPublicHolidays(): void
    {
        $this->publicHolidays = [];
        foreach ($this->allDates as $date) {
            foreach ($this->holidaySettings as $h) {
                if ($h['date'] === $date) {
                    $this->publicHolidays[$date] = $h;
                    break;
                }
            }
        }
    }

    private function initializeTracking(): void
    {
        $this->monthlySchedule = [];
        $this->staffCounts = [];
        foreach ($this->staff as $s) {
            $sid = $s['id'];
            $this->staffCounts[$sid] = [
                'total' => 0,
                'night' => 0,
                'day' => 0,
                'by_type' => [],
            ];
            foreach ($this->allDates as $date) {
                $key = $sid . '-' . $date;
                $this->monthlySchedule[$key] = null;
            }
        }
    }

    private function rebuildTrackingFromCells(): void
    {
        $this->staffCounts = [];
        foreach ($this->monthlySchedule as $key => $cell) {
            if ($cell === null) continue;
            $parts = explode('-', $key);
            $sid = $parts[0];
            if (!isset($this->staffCounts[$sid])) {
                $this->staffCounts[$sid] = ['total' => 0, 'night' => 0, 'day' => 0, 'by_type' => []];
            }
            $this->staffCounts[$sid]['total']++;
            $stId = $cell['shift_type_id'];
            if (in_array($stId, $this->nightShiftIds)) {
                $this->staffCounts[$sid]['night']++;
            } else {
                $this->staffCounts[$sid]['day']++;
            }
            if (!isset($this->staffCounts[$sid]['by_type'][$stId])) {
                $this->staffCounts[$sid]['by_type'][$stId] = 0;
            }
            $this->staffCounts[$sid]['by_type'][$stId]++;
        }
    }

    private function getStaffById(int $id): ?array
    {
        foreach ($this->staff as $s) {
            if ($s['id'] === $id) return $s;
        }
        return null;
    }

    private function getShiftTypeById(int $id): ?array
    {
        foreach ($this->shiftTypes as $st) {
            if ($st['id'] === $id) return $st;
        }
        return null;
    }

    private function getPatternForStaff(int $staffId): ?array
    {
        foreach ($this->patterns as $p) {
            if ($p['staff_id'] === $staffId) return $p;
        }
        return null;
    }

    private function getRequirementsForDate(string $date): array
    {
        $result = [];
        foreach ($this->requirements as $r) {
            if ($r['date'] === $date) {
                $result[] = $r;
            }
        }
        return $result;
    }

    private function getPreferencesForStaff(int $staffId, string $date): array
    {
        $result = [];
        foreach ($this->preferences as $p) {
            if ($p['staff_id'] === $staffId && $p['date'] === $date) {
                $result[] = $p;
            }
        }
        return $result;
    }

    private function getOverrideForDate(string $date): ?array
    {
        foreach ($this->overrides as $o) {
            if ($o['date'] === $date) return $o;
        }
        return null;
    }

    private function isWeekend(string $date): bool
    {
        $dayOfWeek = (int) date('w', strtotime($date));
        return $dayOfWeek === 0 || $dayOfWeek === 6;
    }

    private function assignExistingCells(): void
    {
        foreach ($this->monthlySchedule as $key => $cell) {
            if ($cell !== null && isset($cell['is_existing']) && $cell['is_existing']) {
                $parts = explode('-', $key);
                $sid = $parts[0];
                $stId = $cell['shift_type_id'];
                $this->staffCounts[$sid]['total']++;
                if (in_array($stId, $this->nightShiftIds)) {
                    $this->staffCounts[$sid]['night']++;
                } else {
                    $this->staffCounts[$sid]['day']++;
                }
                if (!isset($this->staffCounts[$sid]['by_type'][$stId])) {
                    $this->staffCounts[$sid]['by_type'][$stId] = 0;
                }
                $this->staffCounts[$sid]['by_type'][$stId]++;
            }
        }
    }

    private function distributePublicHolidays(): void
    {
        if (empty($this->publicHolidays)) return;

        foreach ($this->publicHolidays as $date => $holiday) {
            $dayOfWeek = (int) date('w', strtotime($date));
            $isWeekend = $dayOfWeek === 0 || $dayOfWeek === 6;

            $candidates = array_filter($this->staff, function ($s) use ($date) {
                $prefs = $this->getPreferencesForStaff($s['id'], $date);
                foreach ($prefs as $p) {
                    if ($p['type'] === 'off' && ($p['is_approved'] ?? false)) return false;
                }
                return true;
            });

            if ($isWeekend) {
                $holidayRequirement = $holiday['weekend_requirement'] ?? 0;
            } else {
                $holidayRequirement = $holiday['weekday_requirement'] ?? 0;
            }

            if ($holidayRequirement <= 0) continue;

            $assigned = 0;
            foreach ($candidates as $c) {
                if ($assigned >= $holidayRequirement) break;
                $key = $c['id'] . '-' . $date;
                if ($this->monthlySchedule[$key] !== null) continue;

                $compatibleType = $this->findCompatibleShiftType($date, $c['id']);
                if ($compatibleType === null) continue;

                $this->monthlySchedule[$key] = [
                    'staff_id' => $c['id'],
                    'date' => $date,
                    'shift_type_id' => $compatibleType,
                    'is_public_holiday' => true,
                    'is_auto_generated' => true,
                ];
                $this->staffCounts[$c['id']]['total']++;
                if (in_array($compatibleType, $this->nightShiftIds)) {
                    $this->staffCounts[$c['id']]['night']++;
                } else {
                    $this->staffCounts[$c['id']]['day']++;
                }
                if (!isset($this->staffCounts[$c['id']]['by_type'][$compatibleType])) {
                    $this->staffCounts[$c['id']]['by_type'][$compatibleType] = 0;
                }
                $this->staffCounts[$c['id']]['by_type'][$compatibleType]++;
                $assigned++;
            }
        }
    }

    private function handleDayOffPreferences(): void
    {
        foreach ($this->staff as $s) {
            $sid = $s['id'];
            foreach ($this->allDates as $date) {
                $key = $sid . '-' . $date;
                if ($this->monthlySchedule[$key] !== null) continue;

                $prefs = $this->getPreferencesForStaff($sid, $date);
                foreach ($prefs as $p) {
                    if ($p['type'] === 'off' && ($p['is_approved'] ?? false)) {
                        $this->monthlySchedule[$key] = [
                            'staff_id' => $sid,
                            'date' => $date,
                            'shift_type_id' => null,
                            'is_off' => true,
                            'is_preference' => true,
                            'is_auto_generated' => true,
                        ];
                        break;
                    }
                }
            }
        }
    }

    private function findCompatibleShiftType(string $date, int $staffId): ?int
    {
        $pattern = $this->getPatternForStaff($staffId);
        if ($pattern === null) {
            $types = $this->getRequirementsForDate($date);
            return !empty($types) ? $types[0]['shift_type_id'] : null;
        }

        $rotationKey = $this->resolveRotationKey($pattern, $date);
        if ($rotationKey !== null && isset($pattern['rotation'][$rotationKey])) {
            return $pattern['rotation'][$rotationKey]['shift_type_id'];
        }

        $types = $this->getRequirementsForDate($date);
        return !empty($types) ? $types[0]['shift_type_id'] : null;
    }

    private function resolveRotationKey(array $pattern, string $date): ?int
    {
        $baseDate = $pattern['base_date'] ?? $pattern['start_date'] ?? $this->yearMonth . '-01';
        $baseTs = strtotime($baseDate);
        $currentTs = strtotime($date);
        $diffDays = (int) (($currentTs - $baseTs) / 86400);
        if ($diffDays < 0) return null;

        $rotation = $pattern['rotation'] ?? [];
        if (empty($rotation)) return null;

        $cycleDays = $pattern['cycle_days'] ?? count($rotation);
        if ($cycleDays <= 0) $cycleDays = count($rotation);

        $index = $diffDays % $cycleDays;
        $keys = array_keys($rotation);
        return $keys[$index] ?? null;
    }

    private function assignNightShifts(): void
    {
        if (empty($this->nightShiftIds)) return;

        foreach ($this->allDates as $date) {
            $requirements = $this->getRequirementsForDate($date);
            $nightRequirements = array_filter($requirements, function ($r) {
                return in_array($r['shift_type_id'], $this->nightShiftIds);
            });

            foreach ($nightRequirements as $req) {
                $stId = $req['shift_type_id'];
                $needed = $req['count'];
                $assigned = $this->countAssignedForType($date, $stId);
                $needed -= $assigned;

                if ($needed <= 0) continue;
                $this->fillNightShift($date, $stId, $needed, $req);
            }
        }
    }

    private function fillNightShift(string $date, int $stId, int $needed, array $req): void
    {
        $override = $this->getOverrideForDate($date);
        $available = array_filter($this->staff, function ($s) use ($date, $stId, $override) {
            $key = $s['id'] . '-' . $date;
            if ($this->monthlySchedule[$key] !== null) return false;

            $prefs = $this->getPreferencesForStaff($s['id'], $date);
            foreach ($prefs as $p) {
                if ($p['type'] === 'off' && ($p['is_approved'] ?? false)) return false;
                if ($p['type'] === 'cannot_night') return false;
                if ($p['type'] === 'avoid_shift' && ($p['shift_type_id'] ?? null) === $stId) return false;
            }

            $st = $this->getShiftTypeById($stId);
            $excludeNightOnly = $override['exclude_night_only'] ?? false;
            foreach ($prefs as $p) {
                if ($p['type'] === 'night_only' && $excludeNightOnly && $this->isNightShift($st)) return false;
            }

            return true;
        });

        $available = array_values($available);
        usort($available, function ($a, $b) use ($date, $stId) {
            $aCount = $this->staffCounts[$a['id']]['night'] ?? 0;
            $bCount = $this->staffCounts[$b['id']]['night'] ?? 0;
            if ($aCount !== $bCount) return $aCount - $bCount;

            $aTotal = $this->staffCounts[$a['id']]['total'] ?? 0;
            $bTotal = $this->staffCounts[$b['id']]['total'] ?? 0;
            return $aTotal - $bTotal;
        });

        $assignedCount = 0;
        foreach ($available as $cand) {
            if ($assignedCount >= $needed) break;
            $key = $cand['id'] . '-' . $date;

            $this->monthlySchedule[$key] = [
                'staff_id' => $cand['id'],
                'date' => $date,
                'shift_type_id' => $stId,
                'is_auto_generated' => true,
            ];
            $this->staffCounts[$cand['id']]['total']++;
            $this->staffCounts[$cand['id']]['night']++;
            if (!isset($this->staffCounts[$cand['id']]['by_type'][$stId])) {
                $this->staffCounts[$cand['id']]['by_type'][$stId] = 0;
            }
            $this->staffCounts[$cand['id']]['by_type'][$stId]++;
            $assignedCount++;
        }
    }

    private function assignDayShifts(): void
    {
        if (empty($this->dayShiftIds)) return;

        foreach ($this->allDates as $date) {
            $requirements = $this->getRequirementsForDate($date);
            $dayRequirements = array_filter($requirements, function ($r) {
                return in_array($r['shift_type_id'], $this->dayShiftIds);
            });

            foreach ($dayRequirements as $req) {
                $stId = $req['shift_type_id'];
                $needed = $req['count'];
                $assigned = $this->countAssignedForType($date, $stId);
                $needed -= $assigned;

                if ($needed <= 0) continue;
                $this->fillDayShift($date, $stId, $needed, $req);
            }
        }
    }

    private function fillDayShift(string $date, int $stId, int $needed, array $req): void
    {
        $available = array_filter($this->staff, function ($s) use ($date, $stId) {
            $key = $s['id'] . '-' . $date;
            if ($this->monthlySchedule[$key] !== null) return false;

            $prefs = $this->getPreferencesForStaff($s['id'], $date);
            foreach ($prefs as $p) {
                if ($p['type'] === 'off' && ($p['is_approved'] ?? false)) return false;
                if ($p['type'] === 'avoid_shift' && ($p['shift_type_id'] ?? null) === $stId) return false;
                if ($p['type'] === 'night_only') return false;
            }

            return true;
        });

        $available = array_values($available);
        usort($available, function ($a, $b) use ($date, $stId) {
            $aTotal = $this->staffCounts[$a['id']]['total'] ?? 0;
            $bTotal = $this->staffCounts[$b['id']]['total'] ?? 0;
            if ($aTotal !== $bTotal) return $aTotal - $bTotal;

            $aDay = $this->staffCounts[$a['id']]['day'] ?? 0;
            $bDay = $this->staffCounts[$b['id']]['day'] ?? 0;
            return $aDay - $bDay;
        });

        $assignedCount = 0;
        foreach ($available as $cand) {
            if ($assignedCount >= $needed) break;
            $key = $cand['id'] . '-' . $date;

            $this->monthlySchedule[$key] = [
                'staff_id' => $cand['id'],
                'date' => $date,
                'shift_type_id' => $stId,
                'is_auto_generated' => true,
            ];
            $this->staffCounts[$cand['id']]['total']++;
            $this->staffCounts[$cand['id']]['day']++;
            if (!isset($this->staffCounts[$cand['id']]['by_type'][$stId])) {
                $this->staffCounts[$cand['id']]['by_type'][$stId] = 0;
            }
            $this->staffCounts[$cand['id']]['by_type'][$stId]++;
            $assignedCount++;
        }
    }

    private function countAssignedForType(string $date, int $stId): int
    {
        $count = 0;
        foreach ($this->monthlySchedule as $key => $cell) {
            if ($cell !== null && ($cell['shift_type_id'] ?? null) === $stId && $cell['date'] === $date) {
                $count++;
            }
        }
        return $count;
    }

    private function detectConflicts(): array
    {
        $conflicts = [];

        foreach ($this->allDates as $date) {
            $requirements = $this->getRequirementsForDate($date);
            foreach ($requirements as $req) {
                $stId = $req['shift_type_id'];
                $needed = $req['count'];
                $assigned = $this->countAssignedForType($date, $stId);

                if ($assigned < $needed) {
                    $st = $this->getShiftTypeById($stId);
                    $conflicts[] = $this->buildConflict($date, $st ?? ['id' => $stId, 'name' => 'Unknown'], $needed, $assigned);
                }
            }
        }

        foreach ($this->monthlySchedule as $key => $cell) {
            if ($cell === null) continue;
            $parts = explode('-', $key);
            $sid = $parts[0];

            $prefs = $this->getPreferencesForStaff((int) $sid, $cell['date']);
            foreach ($prefs as $p) {
                if ($p['type'] === 'off' && ($p['is_approved'] ?? false) && $cell['shift_type_id'] !== null) {
                    $st = $this->getShiftTypeById($cell['shift_type_id']);
                    $s = $this->getStaffById((int) $sid);
                    $conflicts[] = [
                        'type' => 'preference_violation',
                        'date' => $cell['date'],
                        'staff_id' => $sid,
                        'staff_name' => $s['name'] ?? 'Unknown',
                        'shift_type' => $st['name'] ?? 'Unknown',
                        'message' => ($s['name'] ?? 'Unknown') . ' は ' . $cell['date'] . ' に休み希望がありますが、勤務が割り当てられています。',
                    ];
                }
            }
        }

        return $conflicts;
    }

    private function buildConflict(string $date, array $st, int $needed, int $assigned): array
    {
        $affectedStaff = [];
        $reasons = [];

        foreach ($this->staff as $s) {
            $key = $s['id'] . '-' . $date;
            $prefs = $this->getPreferencesForStaff($s['id'], $date);

            foreach ($prefs as $p) {
                $reason = null;
                switch ($p['type']) {
                    case 'off':
                        $reason = '休み希望';
                        break;
                    case 'avoid_shift':
                        if (($p['shift_type_id'] ?? null) === $st['id']) $reason = $st['name'] . 'を避けたい';
                        break;
                    case 'cannot_night':
                        if ($this->isNightShift($st)) $reason = '夜勤不可';
                        break;
                    case 'night_only':
                        if (!$this->isNightShift($st)) $reason = '夜勤のみ可（この勤務は夜勤ではない）';
                        break;
                }
                if ($reason) {
                    $affectedStaff[] = $s['name'];
                    $reasons[] = $reason;
                    break;
                }
            }
        }

        $affectedStaff = array_values(array_unique($affectedStaff));
        $reasons = array_values(array_unique($reasons));

        $message = sprintf(
            '%sの%sは%d名必要ですが、%d名しか割り当てられませんでした。',
            $date, $st['name'], $needed, $assigned
        );
        if (!empty($affectedStaff)) {
            $message .= ' ' . implode('、', $affectedStaff) . 'が「' . implode('／', $reasons) . '」のため利用できません。';
            $message .= sprintf(
                ' 解決策: 該当者の希望を変更するか、%sの必要人数を調整してください。',
                $st['name']
            );
        } else {
            $message .= ' 十分な人数のスタッフがいません。スタッフを追加するか、必要人数を調整してください。';
        }

        return [
            'date' => $date,
            'shift_type' => $st['name'],
            'required' => $needed,
            'assigned' => $assigned,
            'shortfall' => $needed - $assigned,
            'affected_staff' => $affectedStaff,
            'message' => $message,
        ];
    }

    private function attemptFixPass(array $conflicts): array
    {
        $remaining = [];

        foreach ($conflicts as $conflict) {
            $date = $conflict['date'];
            $stName = $conflict['shift_type'];

            $st = null;
            foreach ($this->shiftTypes as $stCandidate) {
                if ($stCandidate['name'] === $stName) {
                    $st = $stCandidate;
                    break;
                }
            }
            if ($st === null) {
                $remaining[] = $conflict;
                continue;
            }

            $stId = $st['id'];
            $shortfall = $conflict['shortfall'];
            $assigned = $this->attemptReassign($date, $stId, $shortfall);
            if ($assigned < $shortfall) {
                $needed = $conflict['required'];
                $currentAssigned = $this->countAssignedForType($date, $stId);
                $remaining[] = $this->buildConflict($date, $st, $needed, $currentAssigned);
            }
        }

        return $remaining;
    }

    private function attemptReassign(string $date, int $stId, int $needed): int
    {
        $assigned = 0;
        $isNight = in_array($stId, $this->nightShiftIds);

        $candidates = array_filter($this->staff, function ($s) use ($date, $stId, $isNight) {
            $key = $s['id'] . '-' . $date;
            if ($this->monthlySchedule[$key] !== null) return false;

            $prefs = $this->getPreferencesForStaff($s['id'], $date);
            foreach ($prefs as $p) {
                if ($p['type'] === 'off' && ($p['is_approved'] ?? false)) return false;
                if ($p['type'] === 'avoid_shift' && ($p['shift_type_id'] ?? null) === $stId) return false;
                if ($p['type'] === 'cannot_night' && $isNight) return false;
                if ($p['type'] === 'night_only' && !$isNight) return false;
            }
            return true;
        });

        $candidates = array_values($candidates);
        usort($candidates, function ($a, $b) {
            $aTotal = $this->staffCounts[$a['id']]['total'] ?? 0;
            $bTotal = $this->staffCounts[$b['id']]['total'] ?? 0;
            return $aTotal - $bTotal;
        });

        foreach ($candidates as $cand) {
            if ($assigned >= $needed) break;
            $key = $cand['id'] . '-' . $date;

            $compatibilityRules = $this->getCompatibilityRulesForDate($date);
            $compatible = true;
            foreach ($compatibilityRules as $rule) {
                if ($rule['staff_id'] === $cand['id'] && $rule['shift_type_id'] === $stId && !($rule['compatible'] ?? true)) {
                    $compatible = false;
                    break;
                }
            }
            if (!$compatible) continue;

            $this->monthlySchedule[$key] = [
                'staff_id' => $cand['id'],
                'date' => $date,
                'shift_type_id' => $stId,
                'is_auto_generated' => true,
                'is_auto_fix' => true,
            ];
            $this->staffCounts[$cand['id']]['total']++;
            if ($isNight) {
                $this->staffCounts[$cand['id']]['night']++;
            } else {
                $this->staffCounts[$cand['id']]['day']++;
            }
            if (!isset($this->staffCounts[$cand['id']]['by_type'][$stId])) {
                $this->staffCounts[$cand['id']]['by_type'][$stId] = 0;
            }
            $this->staffCounts[$cand['id']]['by_type'][$stId]++;
            $assigned++;
        }

        return $assigned;
    }

    private function getCompatibilityRulesForDate(string $date): array
    {
        $result = [];
        foreach ($this->compatibilityRules as $rule) {
            if (!isset($rule['date']) || $rule['date'] === $date) {
                $result[] = $rule;
            }
        }
        return $result;
    }

    private function calculateScore(): array
    {
        $totalCells = 0;
        $filledCells = 0;
        $nightBalanceScore = 0;
        $totalStaff = count($this->staff);

        foreach ($this->monthlySchedule as $key => $cell) {
            if ($cell === null) continue;
            $totalCells++;
            if (isset($cell['shift_type_id']) && $cell['shift_type_id'] !== null) {
                $filledCells++;
            }
        }

        $preferenceStats = $this->computePreferenceStats();
        $preferenceSatisfied = $preferenceStats['satisfied'];
        $totalPreferences = $preferenceStats['total'];

        $nightCounts = [];
        foreach ($this->staff as $s) {
            $nightCounts[$s['id']] = $this->staffCounts[$s['id']]['night'] ?? 0;
        }
        if (!empty($nightCounts) && $totalStaff > 0) {
            $avg = array_sum($nightCounts) / $totalStaff;
            $variance = 0;
            foreach ($nightCounts as $nc) {
                $variance += ($nc - $avg) ** 2;
            }
            $variance /= $totalStaff;
            $nightBalanceScore = max(0, 100 - ($variance * 10));
        } else {
            $nightBalanceScore = 100;
        }

        $coverageScore = $totalCells > 0 ? ($filledCells / $totalCells) * 100 : 0;
        $preferenceScore = $totalPreferences > 0 ? ($preferenceSatisfied / $totalPreferences) * 100 : 100;

        return [
            'coverage' => round($coverageScore, 2),
            'preference' => round($preferenceScore, 2),
            'night_balance' => round($nightBalanceScore, 2),
            'overall' => round(($coverageScore + $preferenceScore + $nightBalanceScore) / 3, 2),
            'filled_cells' => $filledCells,
            'total_cells' => $totalCells,
            'preferences_satisfied' => $preferenceSatisfied,
            'total_preferences' => $totalPreferences,
        ];
    }

    private function computePreferenceStats(): array
    {
        $satisfied = 0;
        $total = 0;

        foreach ($this->preferences as $pref) {
            $total++;
            $key = $pref['staff_id'] . '-' . $pref['date'];
            $cell = $this->monthlySchedule[$key] ?? null;

            if ($pref['type'] === 'off') {
                if ($cell === null || ($cell['shift_type_id'] ?? null) === null) {
                    $satisfied++;
                }
            } elseif ($pref['type'] === 'wish') {
                if ($cell !== null && ($cell['shift_type_id'] ?? null) === ($pref['shift_type_id'] ?? null)) {
                    $satisfied++;
                }
            } elseif ($pref['type'] === 'avoid_shift') {
                if ($cell === null || ($cell['shift_type_id'] ?? null) !== ($pref['shift_type_id'] ?? null)) {
                    $satisfied++;
                }
            } elseif ($pref['type'] === 'cannot_night') {
                $st = $cell !== null ? $this->getShiftTypeById($cell['shift_type_id'] ?? 0) : null;
                if ($st === null || !$this->isNightShift($st)) {
                    $satisfied++;
                }
            } elseif ($pref['type'] === 'night_only') {
                $st = $cell !== null ? $this->getShiftTypeById($cell['shift_type_id'] ?? 0) : null;
                if ($st === null || $this->isNightShift($st)) {
                    $satisfied++;
                }
            } else {
                $satisfied++;
            }
        }

        return ['satisfied' => $satisfied, 'total' => $total];
    }

    private function computeStats(): array
    {
        $stats = [];
        foreach ($this->staff as $s) {
            $sid = $s['id'];
            $stats[] = [
                'staff_id' => $sid,
                'staff_name' => $s['name'],
                'total_shifts' => $this->staffCounts[$sid]['total'] ?? 0,
                'night_shifts' => $this->staffCounts[$sid]['night'] ?? 0,
                'day_shifts' => $this->staffCounts[$sid]['day'] ?? 0,
                'by_type' => $this->staffCounts[$sid]['by_type'] ?? [],
            ];
        }

        $totals = ['night' => 0, 'day' => 0, 'total' => 0];
        foreach ($stats as $st) {
            $totals['night'] += $st['night_shifts'];
            $totals['day'] += $st['day_shifts'];
            $totals['total'] += $st['total_shifts'];
        }

        return [
            'per_staff' => $stats,
            'totals' => $totals,
        ];
    }

    private function analyzeBalancing(): array
    {
        $issues = [];
        $nightCounts = [];
        $totalCounts = [];

        foreach ($this->staff as $s) {
            $sid = $s['id'];
            $nightCounts[$sid] = $this->staffCounts[$sid]['night'] ?? 0;
            $totalCounts[$sid] = $this->staffCounts[$sid]['total'] ?? 0;
        }

        if (count($nightCounts) > 1) {
            $nightAvg = array_sum($nightCounts) / count($nightCounts);
            foreach ($nightCounts as $sid => $nc) {
                if (abs($nc - $nightAvg) > 2) {
                    $s = $this->getStaffById($sid);
                    $issues[] = [
                        'type' => 'night_balance',
                        'staff_id' => $sid,
                        'staff_name' => $s['name'] ?? 'Unknown',
                        'count' => $nc,
                        'average' => round($nightAvg, 1),
                        'message' => ($s['name'] ?? 'Unknown') . ' の夜勤回数(' . $nc . ')が平均(' . round($nightAvg, 1) . ')から大きく乖離しています。',
                    ];
                }
            }
        }

        if (count($totalCounts) > 1) {
            $totalAvg = array_sum($totalCounts) / count($totalCounts);
            foreach ($totalCounts as $sid => $tc) {
                if (abs($tc - $totalAvg) > 3) {
                    $s = $this->getStaffById($sid);
                    $issues[] = [
                        'type' => 'total_balance',
                        'staff_id' => $sid,
                        'staff_name' => $s['name'] ?? 'Unknown',
                        'count' => $tc,
                        'average' => round($totalAvg, 1),
                        'message' => ($s['name'] ?? 'Unknown') . ' の総勤務数(' . $tc . ')が平均(' . round($totalAvg, 1) . ')から大きく乖離しています。',
                    ];
                }
            }
        }

        return $issues;
    }

    private function analyzePreferenceAdherence(): array
    {
        $byStaff = [];

        foreach ($this->preferences as $pref) {
            $sid = $pref['staff_id'];
            if (!isset($byStaff[$sid])) {
                $s = $this->getStaffById($sid);
                $byStaff[$sid] = [
                    'staff_id' => $sid,
                    'staff_name' => $s['name'] ?? 'Unknown',
                    'total_preferences' => 0,
                    'satisfied_preferences' => 0,
                ];
            }
            $byStaff[$sid]['total_preferences']++;

            $key = $sid . '-' . $pref['date'];
            $cell = $this->monthlySchedule[$key] ?? null;

            $satisfied = false;
            if ($pref['type'] === 'off') {
                if ($cell === null || ($cell['shift_type_id'] ?? null) === null) $satisfied = true;
            } elseif ($pref['type'] === 'wish') {
                if ($cell !== null && ($cell['shift_type_id'] ?? null) === ($pref['shift_type_id'] ?? null)) $satisfied = true;
            } elseif ($pref['type'] === 'avoid_shift') {
                if ($cell === null || ($cell['shift_type_id'] ?? null) !== ($pref['shift_type_id'] ?? null)) $satisfied = true;
            } elseif ($pref['type'] === 'cannot_night') {
                $st = $cell !== null ? $this->getShiftTypeById($cell['shift_type_id'] ?? 0) : null;
                if ($st === null || !$this->isNightShift($st)) $satisfied = true;
            } elseif ($pref['type'] === 'night_only') {
                $st = $cell !== null ? $this->getShiftTypeById($cell['shift_type_id'] ?? 0) : null;
                if ($st === null || $this->isNightShift($st)) $satisfied = true;
            } else {
                $satisfied = true;
            }

            if ($satisfied) $byStaff[$sid]['satisfied_preferences']++;
        }

        $overall = ['satisfied' => 0, 'total' => 0];
        foreach ($byStaff as $bs) {
            $overall['satisfied'] += $bs['satisfied_preferences'];
            $overall['total'] += $bs['total_preferences'];
        }

        return [
            'by_staff' => array_values($byStaff),
            'overall' => $overall,
            'adherence_rate' => $overall['total'] > 0
                ? round(($overall['satisfied'] / $overall['total']) * 100, 2)
                : 100.0,
        ];
    }
}
