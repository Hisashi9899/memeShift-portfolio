<?php
namespace App\Interface\Controllers;

use App\Models\CompatibilityRule;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CompatibilityRuleController extends Controller
{
    public function index()
    {
        $rules = CompatibilityRule::with('staff1', 'staff2')->get();
        $rules = $rules->filter(fn($r) => $r->staff1 && $r->staff2)->values();
        return response()->json(['data' => $rules]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'staff1_id' => 'required|integer|exists:staff,id',
            'staff2_id' => 'required|integer|exists:staff,id',
            'rule_type' => 'required|in:must_together,must_separate,can_together,can_separate',
        ]);
        return response()->json(['data' => CompatibilityRule::create($data)], 201);
    }

    public function destroy(int $id)
    {
        CompatibilityRule::findOrFail($id)->delete();
        return response()->json(['message' => '削除しました']);
    }
}
