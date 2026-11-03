<?php
namespace App\Services;

use App\Models\SchedulePattern;
use App\Models\Staff;
use App\Models\ShiftType;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Response;

class ScheduleExportService
{
    public function exportCsv(int $patternId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $pattern = SchedulePattern::with('cells')->findOrFail($patternId);
        $staff = Staff::where('active', true)->orderBy('id')->get();
        $shiftTypes = ShiftType::all()->keyBy('id');

        $parts = explode('-', $pattern->month_year);
        if (count($parts) !== 2) {
            abort(400, 'Invalid month_year format');
        }
        [$year, $month] = $parts;
        $daysInMonth = \Carbon\Carbon::create((int)$year, (int)$month)->daysInMonth;

        $cellMap = [];
        foreach ($pattern->cells as $cell) {
            $dateKey = $cell->target_date instanceof \Carbon\Carbon
                ? $cell->target_date->format('Y-m-d')
                : $cell->target_date;
            $cellMap[$dateKey . '_' . $cell->staff_id] = $cell;
        }

        $rows = [];
        $header1 = ['職員', '属性'];
        $weekdayLabels = ['日', '月', '火', '水', '木', '金', '土'];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $header1[] = (string)$d;
        }
        $rows[] = $header1;

        $header2 = ['', ''];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = sprintf('%s-%02d', $pattern->month_year, $d);
            $w = (int)date('w', strtotime($dateStr));
            $header2[] = $weekdayLabels[$w];
        }
        $rows[] = $header2;

        foreach ($staff as $s) {
            $employment = $s->employment_type === 'parttime' ? '非常勤' : '常勤';
            $night = $s->can_night_shift ? '夜勤可' : '夜勤無';
            $row = [$s->name, $employment . '/' . $night];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dateStr = sprintf('%s-%02d', $pattern->month_year, $d);
                if (($s->start_date && $dateStr < $s->start_date) || ($s->end_date && $dateStr > $s->end_date)) {
                    $row[] = '-';
                    continue;
                }
                $key = $dateStr . '_' . $s->id;
                $cell = $cellMap[$key] ?? null;
                if ($cell && $cell->shift_type_id) {
                    $st = $shiftTypes->get($cell->shift_type_id);
                    $label = $st ? $st->name : '-';
                    $row[] = $cell->is_fixed ? $label . '※' : $label;
                } else {
                    $row[] = '休';
                }
            }
            $rows[] = $row;
        }

        $callback = function () use ($rows) {
            $fp = fopen('php://output', 'w');
            fwrite($fp, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($fp, $row);
            }
            fclose($fp);
        };

        $filename = 'schedule_' . $pattern->month_year . '.csv';
        return Response::stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportPdf(int $patternId): array
    {
        $pattern = SchedulePattern::with('cells')->findOrFail($patternId);
        $staff = Staff::where('active', true)->orderBy('id')->get();
        $shiftTypes = ShiftType::all()->keyBy('id');

        $parts = explode('-', $pattern->month_year);
        if (count($parts) !== 2) {
            abort(400, 'Invalid month_year format');
        }
        [$year, $monthNum] = $parts;
        $daysInMonth = \Carbon\Carbon::create((int)$year, (int)$monthNum)->daysInMonth;

        $cellMap = [];
        foreach ($pattern->cells as $cell) {
            $dateKey = $cell->target_date instanceof \Carbon\Carbon
                ? $cell->target_date->format('Y-m-d')
                : $cell->target_date;
            $day = (int)substr($dateKey, -2);
            $cellMap[$day . '_' . $cell->staff_id] = [
                'shift_type_id' => $cell->shift_type_id,
                'is_fixed' => $cell->is_fixed,
                'abbr' => $cell->shift_type_id ? ($shiftTypes->get($cell->shift_type_id)?->name ?? '') : '',
            ];
        }

        $weekdayLabels = [];
        $weekends = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = sprintf('%s-%02d', $pattern->month_year, $d);
            $w = (int)date('w', strtotime($dateStr));
            $labels = ['日', '月', '火', '水', '木', '金', '土'];
            $weekdayLabels[$d] = $labels[$w];
            $weekends[$d] = ($w === 0 || $w === 6);
        }

        $staffData = [];
        foreach ($staff as $s) {
            $employment = $s->employment_type === 'parttime' ? '非常勤' : '常勤';
            $night = $s->can_night_shift ? '夜勤可' : '夜勤無';
            $inRange = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dateStr = sprintf('%s-%02d', $pattern->month_year, $d);
                $inRange[$d] = !(($s->start_date && $dateStr < $s->start_date) || ($s->end_date && $dateStr > $s->end_date));
            }
            $staffData[] = [
                'id' => $s->id,
                'name' => $s->name,
                'attr' => $employment . '/' . $night,
                'in_range' => $inRange,
            ];
        }

        $abbrMap = ['日勤' => '日', '早番' => '早', '遅番' => '遅', '夜勤' => '夜', '明け' => '明'];
        foreach ($cellMap as $k => &$c) {
            $c['abbr'] = $abbrMap[$c['abbr']] ?? mb_substr($c['abbr'], 0, 1);
        }
        unset($c);

        $pdf = Pdf::loadView('pdf.schedule', [
            'title' => '勤務スケジュール',
            'month' => $pattern->month_year,
            'daysInMonth' => $daysInMonth,
            'weekdayLabels' => $weekdayLabels,
            'weekends' => $weekends,
            'staff' => $staffData,
            'cellsMap' => $cellMap,
        ]);

        $fontPath = storage_path('fonts/ipag.ttf');
        if (file_exists($fontPath)) {
            try {
                $fm = $pdf->getDomPDF()->getFontMetrics();
                $fm->registerFont(
                    ['family' => 'IPAGothic', 'style' => 'normal', 'weight' => 'normal'],
                    $fontPath
                );
                $fm->registerFont(
                    ['family' => 'IPAGothic', 'style' => 'normal', 'weight' => 'bold'],
                    $fontPath
                );
            } catch (\Exception $e) {
                \Log::warning('Font registration failed: ' . $e->getMessage());
            }
        }

        $pdf->setPaper('A4', 'landscape');
        return ['pdf' => $pdf, 'month_year' => $pattern->month_year];
    }
}
