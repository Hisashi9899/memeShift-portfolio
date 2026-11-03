<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
@page { size: A4 landscape; margin: 12mm; }
body { font-family: 'IPAGothic', 'DejaVu Sans', sans-serif; font-size: 7pt; color: #333; }
h1 { text-align: center; font-size: 11pt; margin-bottom: 6px; }
.subtitle { text-align: center; font-size: 7pt; color: #888; margin-bottom: 10px; }
table { width: 100%; border-collapse: collapse; }
th, td { border: 1px solid #ccc; padding: 2px 3px; text-align: center; vertical-align: middle; }
th { background: #f5f5f5; font-weight: bold; color: #555; font-size: 6.5pt; }
td.name { text-align: left; font-weight: bold; color: #333; white-space: nowrap; width: 50px; }
td.attr { text-align: center; font-size: 5.5pt; color: #999; width: 28px; }
td.weekend { background: #fff5f5; }
td.off { color: #e74c3c; font-weight: bold; }
td.range-end { color: #bbb; background: #fafafa; }
.footer { text-align: right; font-size: 6pt; color: #aaa; margin-top: 6px; }
td .fixed-dot { font-size: 5pt; color: #999; }
</style>
</head>
<body>
<h1>{{ $title }}</h1>
<p class="subtitle">勤務スケジュール {{ $month }}</p>
<table>
<thead>
<tr>
<th rowspan="2" style="width:50px">職員</th>
<th rowspan="2" style="width:28px">属性</th>
@for ($d = 1; $d <= $daysInMonth; $d++)
<th style="{{ $weekends[$d] ?? false ? 'background:#fee' : '' }}">{{ $d }}</th>
@endfor
</tr>
<tr>
@for ($d = 1; $d <= $daysInMonth; $d++)
<th style="{{ $weekends[$d] ?? false ? 'background:#fee' : '' }}; font-size:5.5pt">{{ $weekdayLabels[$d] ?? '' }}</th>
@endfor
</tr>
</thead>
<tbody>
@foreach ($staff as $s)
<tr>
<td class="name">{{ $s['name'] }}</td>
<td class="attr">{{ $s['attr'] }}</td>
@for ($d = 1; $d <= $daysInMonth; $d++)
@php
$cell = $cellsMap[$d . '_' . $s['id']] ?? null;
$isInRange = $s['in_range'][$d] ?? true;
$class = '';
if (!$isInRange) $class = 'range-end';
elseif ($weekends[$d] ?? false) $class = 'weekend';
$label = '';
$fixed = '';
if (!$isInRange) { $label = '-'; }
elseif ($cell && $cell['shift_type_id']) {
$label = $cell['abbr'];
$fixed = $cell['is_fixed'] ? '●' : '';
} else { $label = '休'; $class .= ' off'; }
@endphp
<td class="{{ trim($class) }}">{{ $label }}<span class="fixed-dot">{{ $fixed }}</span></td>
@endfor
</tr>
@endforeach
</tbody>
</table>
<p class="footer">MeMeシフト 自動生成</p>
</body>
</html>
