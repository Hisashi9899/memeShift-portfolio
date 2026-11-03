<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Requirement extends Model
{
    protected $fillable = ['shift_type_id', 'count'];
    protected $casts = ['count' => 'integer'];

    public function shiftType() {
        return $this->belongsTo(ShiftType::class);
    }
}
