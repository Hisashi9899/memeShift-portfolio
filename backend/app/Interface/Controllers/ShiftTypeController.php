<?php
namespace App\Interface\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ShiftTypeController extends Controller
{
    public function index()
    {
        return response()->json(['data' => \App\Models\ShiftType::orderBy('sort_order')->get()]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'types' => 'required|array',
            'types.*.id' => 'nullable|integer', 'types.*.name' => 'required|string',
            'types.*.sort_order' => 'integer', 'types.*.start_time' => 'nullable|string',
            'types.*.end_time' => 'nullable|string', 'types.*.color' => 'string',
        ]);
        $saved = [];
        $savedIds = [];
        foreach ($data['types'] as $item) {
            if (!empty($item['id'])) {
                $type = \App\Models\ShiftType::findOrFail($item['id']);
                $type->update($item);
                $savedIds[] = (int) $item['id'];
            } else {
                $type = \App\Models\ShiftType::create($item);
                $savedIds[] = $type->id;
            }
            $saved[] = $type;
        }
        \App\Models\ShiftType::whereNotIn('id', $savedIds)->delete();
        return response()->json(['data' => $saved]);
    }
}
