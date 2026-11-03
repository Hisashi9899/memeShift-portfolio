<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DailyOverride extends Model
{
    protected $fillable = ['target_date', 'shift_type_id', 'count'];
    protected $casts = ['target_date' => 'date', 'count' => 'integer'];

    public function shiftType() {
        return $this->belongsTo(ShiftType::class);
    }
}
