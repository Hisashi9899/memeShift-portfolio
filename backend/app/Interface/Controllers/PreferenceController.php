<?php
namespace App\Interface\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PreferenceController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->query('month_year', date('Y-m'));
        return response()->json(['data' => \App\Models\Preference::where('target_date', 'like', $month . '-%')
            ->with('staff')->orderBy('target_date')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'staff_id' => 'nullable|integer|exists:staff,id',
            'staff_ids' => 'nullable|array',
            'staff_ids.*' => 'integer|exists:staff,id',
            'target_date' => 'required|date',
            'type' => 'required|in:off,want_shift,avoid_shift,night_only,cannot_night,fixed_shift',
            'shift_type_id' => 'nullable|integer|exists:shift_types,id', 'priority' => 'nullable|in:high,medium,low',
            'note' => 'nullable|string',
        ]);

        $staffIds = $data['staff_ids'] ?? ($data['staff_id'] !== null ? [$data['staff_id']] : []);
        if (empty($staffIds)) {
            return response()->json(['message' => 'staff_id または staff_ids が必要です'], 422);
        }

        $created = [];
        foreach ($staffIds as $sid) {
            $pref = \App\Models\Preference::firstOrNew([
                'staff_id' => $sid, 'target_date' => $data['target_date'], 'type' => $data['type'],
            ]);
            $fillData = [
                'shift_type_id' => $data['shift_type_id'] ?? null,
            ];
            if (isset($data['priority'])) $fillData['priority'] = $data['priority'];
            if (isset($data['note'])) $fillData['note'] = $data['note'];
            $pref->fill($fillData);
            $pref->staff_id = $sid;
            $pref->save();
            $created[] = $pref->load('staff');
        }
        return response()->json(['data' => count($created) === 1 ? $created[0] : $created]);
    }

    public function destroy(int $id)
    {
        \App\Models\Preference::findOrFail($id)->delete();
        return response()->json(['message' => '削除しました']);
    }

    public function reset(Request $request)
    {
        $data = $request->validate(['month_year' => 'required|string']);
        \App\Models\Preference::where('target_date', 'like', $data['month_year'] . '-%')->delete();
        return response()->json(['message' => 'リセットしました']);
    }
}
