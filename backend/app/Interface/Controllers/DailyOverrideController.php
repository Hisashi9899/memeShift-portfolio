<?php
namespace App\Interface\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DailyOverrideController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->query('month_year', date('Y-m'));
        return response()->json(['data' => \App\Models\DailyOverride::where('target_date', 'like', $month . '-%')->orderBy('target_date')->get()]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'overrides' => 'required|array',
            'overrides.*.id' => 'nullable|integer', 'overrides.*.target_date' => 'required|date',
            'overrides.*.shift_type_id' => 'required|integer|exists:shift_types,id',
            'overrides.*.count' => 'required|integer|min:0',
        ]);
        foreach ($data['overrides'] as $item) {
            \App\Models\DailyOverride::updateOrCreate(
                ['target_date' => $item['target_date'], 'shift_type_id' => $item['shift_type_id']],
                ['count' => $item['count']]
            );
        }
        return response()->json(['data' => \App\Models\DailyOverride::where('target_date', 'like', substr($data['overrides'][0]['target_date'], 0, 7) . '%')->get()]);
    }
}
