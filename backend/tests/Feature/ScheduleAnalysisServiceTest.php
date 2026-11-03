<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\SchedulePattern;
use App\Models\ScheduleCell;
use App\Models\Staff;
use App\Models\ShiftType;
use App\Models\PublicHolidaySetting;
use App\Services\ScheduleAnalysisService;
use App\Services\ScheduleGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ScheduleAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    private ScheduleAnalysisService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $generationService = $this->createMock(ScheduleGenerationService::class);
        $generationService->method('isNightShift')->willReturnCallback(function ($st) {
            if (!$st->start_time) return false;
            $hour = (int) substr($st->start_time, 0, 2);
            return $hour >= 17 || $hour < 5;
        });
        $generationService->method('isAkeShift')->willReturnCallback(function ($st) {
            return $st && $st->name === '明け';
        });
        $generationService->method('isStaffActiveOnDate')->willReturn(true);
        $this->service = new ScheduleAnalysisService($generationService);
    }

    private function createShiftTypes(): array
    {
        $types = [
            ['name' => '日勤', 'start_time' => '08:00', 'end_time' => '17:00', 'sort_order' => 1],
            ['name' => '早番', 'start_time' => '06:00', 'end_time' => '15:00', 'sort_order' => 2],
            ['name' => '遅番', 'start_time' => '12:00', 'end_time' => '21:00', 'sort_order' => 3],
            ['name' => '夜勤', 'start_time' => '22:00', 'end_time' => '05:00', 'sort_order' => 4],
            ['name' => '明け', 'start_time' => null, 'end_time' => null, 'sort_order' => 5],
        ];
        $models = [];
        foreach ($types as $t) {
            $models[$t['name']] = ShiftType::create($t);
        }
        return $models;
    }

    private function createStaff(): array
    {
        $names = ['職員A', '職員B', '職員C'];
        $staff = [];
        foreach ($names as $name) {
            $staff[$name] = Staff::create(['name' => $name, 'active' => true, 'sort_order' => count($staff)]);
        }
        return $staff;
    }

    private function createPattern(string $month): SchedulePattern
    {
        return SchedulePattern::create(['month_year' => $month, 'pattern_number' => 1, 'is_base' => true]);
    }

    public function test_getDailySummary_returns_correct_counts()
    {
        $month = '2026-06';
        $shiftTypes = $this->createShiftTypes();
        $staff = $this->createStaff();
        $pattern = $this->createPattern($month);

        ScheduleCell::create(['schedule_pattern_id' => $pattern->id, 'staff_id' => $staff['職員A']->id, 'target_date' => '2026-06-01', 'shift_type_id' => $shiftTypes['日勤']->id]);
        ScheduleCell::create(['schedule_pattern_id' => $pattern->id, 'staff_id' => $staff['職員B']->id, 'target_date' => '2026-06-01', 'shift_type_id' => $shiftTypes['早番']->id]);
        ScheduleCell::create(['schedule_pattern_id' => $pattern->id, 'staff_id' => $staff['職員C']->id, 'target_date' => '2026-06-01', 'shift_type_id' => $shiftTypes['夜勤']->id]);

        ScheduleCell::create(['schedule_pattern_id' => $pattern->id, 'staff_id' => $staff['職員A']->id, 'target_date' => '2026-06-02', 'shift_type_id' => $shiftTypes['明け']->id]);
        ScheduleCell::create(['schedule_pattern_id' => $pattern->id, 'staff_id' => $staff['職員B']->id, 'target_date' => '2026-06-02', 'shift_type_id' => $shiftTypes['遅番']->id]);

        $result = $this->service->getDailySummary($month);

        $this->assertCount(30, $result);

        $day1 = $result[0];
        $this->assertSame('2026-06-01', $day1['date']);
        $this->assertSame(1, $day1['day_shift']);
        $this->assertSame(1, $day1['early_shift']);
        $this->assertSame(0, $day1['late_shift']);
        $this->assertSame(1, $day1['night_shift']);
        $this->assertSame(0, $day1['after_night']);
        $this->assertSame(0, $day1['off']);

        $day2 = $result[1];
        $this->assertSame('2026-06-02', $day2['date']);
        $this->assertSame(0, $day2['day_shift']);
        $this->assertSame(0, $day2['early_shift']);
        $this->assertSame(1, $day2['late_shift']);
        $this->assertSame(0, $day2['night_shift']);
        $this->assertSame(1, $day2['after_night']);
        $this->assertSame(1, $day2['off']);
    }

    public function test_getDailySummary_returns_empty_when_no_pattern()
    {
        $result = $this->service->getDailySummary('2026-07');
        $this->assertSame([], $result);
    }

    public function test_getStaffTimeline_returns_correct_timeline()
    {
        $month = '2026-06';
        $shiftTypes = $this->createShiftTypes();
        $staff = $this->createStaff();
        $pattern = $this->createPattern($month);

        ScheduleCell::create(['schedule_pattern_id' => $pattern->id, 'staff_id' => $staff['職員A']->id, 'target_date' => '2026-06-01', 'shift_type_id' => $shiftTypes['日勤']->id]);
        ScheduleCell::create(['schedule_pattern_id' => $pattern->id, 'staff_id' => $staff['職員A']->id, 'target_date' => '2026-06-02', 'shift_type_id' => $shiftTypes['明け']->id]);
        ScheduleCell::create(['schedule_pattern_id' => $pattern->id, 'staff_id' => $staff['職員B']->id, 'target_date' => '2026-06-01', 'shift_type_id' => $shiftTypes['早番']->id]);

        $result = $this->service->getStaffTimeline($month);

        $this->assertCount(3, $result);

        $staffA = $result[0];
        $this->assertSame($staff['職員A']->id, $staffA['staff_id']);
        $this->assertSame('職員A', $staffA['name']);
        $this->assertSame('2026-06-01', $staffA['timeline'][0]['date']);
        $this->assertSame('日勤', $staffA['timeline'][0]['shift_type']);
        $this->assertSame('2026-06-02', $staffA['timeline'][1]['date']);
        $this->assertSame('明け', $staffA['timeline'][1]['shift_type']);
    }

    public function test_getStaffTimeline_returns_empty_when_no_pattern()
    {
        $result = $this->service->getStaffTimeline('2026-07');
        $this->assertSame([], $result);
    }

    public function test_getViolations_detects_night_to_day()
    {
        $month = '2026-06';
        $shiftTypes = $this->createShiftTypes();
        $staff = $this->createStaff();
        $pattern = $this->createPattern($month);

        ScheduleCell::create(['schedule_pattern_id' => $pattern->id, 'staff_id' => $staff['職員A']->id, 'target_date' => '2026-06-01', 'shift_type_id' => $shiftTypes['夜勤']->id]);
        ScheduleCell::create(['schedule_pattern_id' => $pattern->id, 'staff_id' => $staff['職員A']->id, 'target_date' => '2026-06-02', 'shift_type_id' => $shiftTypes['日勤']->id]);

        $violations = $this->service->getViolations($month);
        $nightToDay = array_values(array_filter($violations, fn($v) => $v['violation_type'] === 'night_to_day'));

        $this->assertCount(1, $nightToDay);
        $this->assertSame($staff['職員A']->id, $nightToDay[0]['staff_id']);
        $this->assertSame('2026-06-02', $nightToDay[0]['date']);
    }

    public function test_getViolations_detects_ake_to_work()
    {
        $month = '2026-06';
        $shiftTypes = $this->createShiftTypes();
        $staff = $this->createStaff();
        $pattern = $this->createPattern($month);

        ScheduleCell::create(['schedule_pattern_id' => $pattern->id, 'staff_id' => $staff['職員A']->id, 'target_date' => '2026-06-01', 'shift_type_id' => $shiftTypes['明け']->id]);
        ScheduleCell::create(['schedule_pattern_id' => $pattern->id, 'staff_id' => $staff['職員A']->id, 'target_date' => '2026-06-02', 'shift_type_id' => $shiftTypes['日勤']->id]);

        $violations = $this->service->getViolations($month);
        $akeToWork = array_values(array_filter($violations, fn($v) => $v['violation_type'] === 'ake_to_work'));

        $this->assertCount(1, $akeToWork);
        $this->assertSame($staff['職員A']->id, $akeToWork[0]['staff_id']);
        $this->assertSame('2026-06-02', $akeToWork[0]['date']);
    }

    public function test_getViolations_detects_max_consecutive_exceeded()
    {
        $month = '2026-06';
        $shiftTypes = $this->createShiftTypes();
        $staff = $this->createStaff();
        $pattern = $this->createPattern($month);

        for ($day = 1; $day <= 7; $day++) {
            $date = sprintf('2026-06-%02d', $day);
            ScheduleCell::create(['schedule_pattern_id' => $pattern->id, 'staff_id' => $staff['職員A']->id, 'target_date' => $date, 'shift_type_id' => $shiftTypes['日勤']->id]);
        }

        $violations = $this->service->getViolations($month);
        $consecutive = array_values(array_filter($violations, fn($v) => $v['violation_type'] === 'max_consecutive_exceeded' && $v['staff_id'] === $staff['職員A']->id));

        $this->assertCount(2, $consecutive);
        $this->assertSame('2026-06-06', $consecutive[0]['date']);
        $this->assertSame('2026-06-07', $consecutive[1]['date']);
    }

    public function test_getViolations_detects_insufficient_holiday()
    {
        $month = '2026-06';
        $shiftTypes = $this->createShiftTypes();
        $staff = $this->createStaff();
        $pattern = $this->createPattern($month);

        PublicHolidaySetting::create(['month_year' => $month, 'count' => 9]);

        for ($day = 1; $day <= 30; $day++) {
            $date = sprintf('2026-06-%02d', $day);
            ScheduleCell::create(['schedule_pattern_id' => $pattern->id, 'staff_id' => $staff['職員A']->id, 'target_date' => $date, 'shift_type_id' => $shiftTypes['日勤']->id]);
        }

        $violations = $this->service->getViolations($month);
        $insufficient = array_values(array_filter($violations, fn($v) => $v['violation_type'] === 'insufficient_holiday' && $v['staff_id'] === $staff['職員A']->id));

        $this->assertCount(1, $insufficient);
        $this->assertSame('2026-06-30', $insufficient[0]['date']);
    }

    public function test_getViolations_returns_empty_when_no_pattern()
    {
        $result = $this->service->getViolations('2026-07');
        $this->assertSame([], $result);
    }
}
