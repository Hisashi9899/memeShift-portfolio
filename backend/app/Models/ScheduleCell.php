<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ScheduleCell extends Model
{
    protected $fillable = ['schedule_pattern_id', 'staff_id', 'target_date', 'shift_type_id', 'is_fixed'];
    protected $casts = ['is_fixed' => 'boolean'];

    public function pattern() { return $this->belongsTo(SchedulePattern::class); }
    public function staff() { return $this->belongsTo(Staff::class); }
    public function shiftType() { return $this->belongsTo(ShiftType::class); }
}
