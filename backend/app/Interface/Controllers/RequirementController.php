<?php
namespace App\Interface\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RequirementController extends Controller
{
    public function index()
    {
        return response()->json(['data' => \App\Models\Requirement::with('shiftType')->orderBy('shift_type_id')->get()]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'requirements' => 'required|array',
            'requirements.*.id' => 'nullable|integer', 'requirements.*.shift_type_id' => 'required|integer|exists:shift_types,id',
            'requirements.*.count' => 'required|integer|min:0',
        ]);
        \App\Models\Requirement::whereNotIn('shift_type_id', array_column($data['requirements'], 'shift_type_id'))->delete();
        foreach ($data['requirements'] as $item) {
            \App\Models\Requirement::updateOrCreate(['shift_type_id' => $item['shift_type_id']], ['count' => $item['count']]);
        }
        return response()->json(['data' => \App\Models\Requirement::with('shiftType')->orderBy('shift_type_id')->get()]);
    }
}
