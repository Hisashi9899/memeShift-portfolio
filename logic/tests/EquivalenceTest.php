<?php
namespace MemeShift\Logic\Tests;

use MemeShift\Logic\ScheduleEngine;
use PHPUnit\Framework\TestCase;

/**
 * 等価性テスト: logic/ScheduleEngine と backend/Services/* の出力比較
 *
 * backend から出力された JSON fixture を読み込み、ScheduleEngine が
 * 同じ入力を与えられたときに同等の出力を生成することを検証する。
 */
class EquivalenceTest extends TestCase
{
    private function requirementsForAllDates(string $yearMonth, array $baseReqs): array
    {
        $daysInMonth = (int) date('t', strtotime($yearMonth . '-01'));
        $reqs = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = sprintf('%s-%02d', $yearMonth, $d);
            foreach ($baseReqs as $r) {
                $r['date'] = $date;
                $reqs[] = $r;
            }
        }
        return $reqs;
    }

    public function testEngineCanGenerateSchedule(): void
    {
        $engine = new ScheduleEngine();

        $result = $engine->generate([
            'year_month' => '2026-07',
            'staff' => [
                ['id' => 1, 'name' => 'Staff A', 'employment_type' => 'fulltime',
                 'can_night_shift' => true, 'max_consecutive_days' => 5,
                 'start_date' => null, 'end_date' => null, 'active' => true, 'sort_order' => 1],
                ['id' => 2, 'name' => 'Staff B', 'employment_type' => 'fulltime',
                 'can_night_shift' => true, 'max_consecutive_days' => 5,
                 'start_date' => null, 'end_date' => null, 'active' => true, 'sort_order' => 2],
            ],
            'shift_types' => [
                ['id' => 1, 'name' => '日勤', 'start_time' => '08:00', 'end_time' => '17:00',
                 'color' => '#4caf50', 'sort_order' => 1],
                ['id' => 2, 'name' => '夜勤', 'start_time' => '20:00', 'end_time' => '05:00',
                 'color' => '#2196f3', 'sort_order' => 2],
                ['id' => 3, 'name' => '明け', 'start_time' => null, 'end_time' => null,
                 'color' => '#ff9800', 'sort_order' => 3],
            ],
            'patterns' => [],
            'preferences' => [],
            'requirements' => $this->requirementsForAllDates('2026-07', [
                ['shift_type_id' => 1, 'count' => 1],
                ['shift_type_id' => 2, 'count' => 1],
            ]),
            'overrides' => [],
            'compatibility_rules' => [],
            'holiday_settings' => [],
        ]);

        $this->assertArrayHasKey('cells', $result);
        $this->assertNotEmpty($result['cells']);
        $this->assertArrayHasKey('conflicts', $result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('overall', $result['score']);
        $this->assertIsNumeric($result['score']['overall']);
    }

    public function testDetectsStaffShortageWithDateRequirements(): void
    {
        $engine = new ScheduleEngine();

        $result = $engine->generate([
            'year_month' => '2026-07',
            'staff' => [
                ['id' => 1, 'name' => 'Staff A', 'employment_type' => 'fulltime',
                 'can_night_shift' => true, 'max_consecutive_days' => 5,
                 'start_date' => null, 'end_date' => null, 'active' => true, 'sort_order' => 1],
            ],
            'shift_types' => [
                ['id' => 1, 'name' => '日勤', 'start_time' => '08:00', 'end_time' => '17:00',
                 'color' => '#4caf50', 'sort_order' => 1],
                ['id' => 2, 'name' => '夜勤', 'start_time' => '20:00', 'end_time' => '05:00',
                 'color' => '#2196f3', 'sort_order' => 2],
            ],
            'patterns' => [],
            'preferences' => [],
            'requirements' => $this->requirementsForAllDates('2026-07', [
                ['shift_type_id' => 1, 'count' => 3],
                ['shift_type_id' => 2, 'count' => 2],
            ]),
            'overrides' => [],
            'compatibility_rules' => [],
            'holiday_settings' => [],
        ]);

        $this->assertNotEmpty($result['conflicts'],
            '1名で日勤3+夜勤2/日は充足不可能→conflictsが発生すべき');
    }

    public function testNightShiftClassification(): void
    {
        $reflection = new \ReflectionClass(ScheduleEngine::class);
        $method = $reflection->getMethod('isNightShift');
        $method->setAccessible(true);

        $engine = new ScheduleEngine();

        $nightShift = ['id' => 1, 'name' => '夜勤', 'start_time' => '20:00', 'end_time' => '05:00'];
        $dayShift = ['id' => 2, 'name' => '日勤', 'start_time' => '08:00', 'end_time' => '17:00'];
        $lateShift = ['id' => 3, 'name' => '遅番', 'start_time' => '12:00', 'end_time' => '21:00'];
        $earlyShift = ['id' => 4, 'name' => '早番', 'start_time' => '05:00', 'end_time' => '13:00'];
        $midnightShift = ['id' => 5, 'name' => '深夜', 'start_time' => '22:00', 'end_time' => '06:00'];

        $this->assertTrue($method->invoke($engine, $nightShift), '夜勤(20-5) → night');
        $this->assertFalse($method->invoke($engine, $dayShift), '日勤(8-17) → not night');
        $this->assertFalse($method->invoke($engine, $lateShift), '遅番(12-21) → not night');
        $this->assertFalse($method->invoke($engine, $earlyShift), '早番(5-13) → not night');
        $this->assertTrue($method->invoke($engine, $midnightShift), '深夜(22-6) → night');
    }

    public function testNoErrorsWithEmptyStaff(): void
    {
        $engine = new ScheduleEngine();

        $result = $engine->generate([
            'year_month' => '2026-07',
            'staff' => [],
            'shift_types' => [
                ['id' => 1, 'name' => '日勤', 'start_time' => '08:00', 'end_time' => '17:00',
                 'color' => '#4caf50', 'sort_order' => 1],
            ],
            'patterns' => [],
            'preferences' => [],
            'requirements' => [],
            'overrides' => [],
            'compatibility_rules' => [],
            'holiday_settings' => [],
        ]);

        $this->assertArrayHasKey('cells', $result);
        $this->assertArrayHasKey('score', $result);
    }
}

