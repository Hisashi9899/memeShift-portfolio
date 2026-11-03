<?php
namespace App\Interface\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->query('month_year');
        if ($month) {
            $parts = explode('-', $month);
            $daysInMonth = (int)\Carbon\Carbon::create((int)$parts[0], (int)$parts[1])->daysInMonth;
            $firstDate = $month . '-01';
            $lastDate = $month . '-' . str_pad((string)$daysInMonth, 2, '0', STR_PAD_LEFT);
            return response()->json(['data' => \App\Models\Staff::where('active', true)
                ->where(fn($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', $lastDate))
                ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $firstDate))
                ->orderBy('sort_order')->get()]);
        }
        return response()->json(['data' => \App\Models\Staff::orderBy('sort_order')->get()]);
    }

    public function bulkSave(Request $request)
    {
        $data = $request->validate([
            'staff' => 'required|array',
            'staff.*.id' => 'nullable|integer',
            'staff.*.name' => 'required|string',
            'staff.*.employment_type' => 'required|in:fulltime,parttime',
            'staff.*.can_night_shift' => 'boolean',
            'staff.*.max_consecutive_days' => 'integer',
            'staff.*.start_date' => 'nullable|date',
            'staff.*.end_date' => 'nullable|date',
            'staff.*.active' => 'boolean',
        ]);
        $saved = [];
        $savedIds = [];

        $existingIds = \App\Models\Staff::pluck('id')->toArray();

        foreach ($data['staff'] as $item) {
            if (!empty($item['id'])) {
                $staff = \App\Models\Staff::findOrFail($item['id']);
                $staff->update($item);
                $savedIds[] = (int) $item['id'];
            } else {
                $item['sort_order'] = \App\Models\Staff::max('sort_order') + 1;
                $staff = \App\Models\Staff::create($item);
                $savedIds[] = $staff->id;
            }
            $saved[] = $staff;
        }

        $toDelete = array_diff($existingIds, $savedIds);
        if (!empty($toDelete)) {
            \App\Models\Staff::whereIn('id', $toDelete)->delete();
        }

        return response()->json(['data' => $saved]);
    }
}
