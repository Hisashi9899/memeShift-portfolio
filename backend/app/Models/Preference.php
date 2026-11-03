<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Preference extends Model
{
    protected $fillable = ['staff_id', 'target_date', 'type', 'shift_type_id', 'priority', 'note'];
    protected $attributes = ['priority' => 'medium'];
    protected $casts = [];

    public function staff() { return $this->belongsTo(Staff::class); }
    public function shiftType() { return $this->belongsTo(ShiftType::class); }
}
