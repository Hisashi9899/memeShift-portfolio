<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CompatibilityRule extends Model
{
    protected $fillable = ['staff1_id', 'staff2_id', 'rule_type'];

    public function staff1() { return $this->belongsTo(Staff::class, 'staff1_id'); }
    public function staff2() { return $this->belongsTo(Staff::class, 'staff2_id'); }
}
