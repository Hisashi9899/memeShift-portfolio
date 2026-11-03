<?php
namespace App\Interface\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SettingsController extends Controller
{
    public function getHolidayCount(Request $request)
    {
        $month = $request->query('month_year', date('Y-m'));
        $setting = \App\Models\PublicHolidaySetting::where('month_year', $month)->first();
        return response()->json(['count' => $setting ? (int)$setting->count : 9]);
    }

    public function saveHolidayCount(Request $request)
    {
        $data = $request->validate(['month_year' => 'required|string', 'count' => 'required|integer|min:0|max:31']);
        \App\Models\PublicHolidaySetting::updateOrCreate(['month_year' => $data['month_year']], ['count' => $data['count']]);
        return response()->json(['message' => '保存しました']);
    }
}
