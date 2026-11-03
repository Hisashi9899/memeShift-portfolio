<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SchedulePattern extends Model
{
    protected $fillable = ['month_year', 'pattern_number', 'is_base', 'label'];
    protected $casts = ['is_base' => 'boolean'];

    public function cells() {
        return $this->hasMany(ScheduleCell::class);
    }
}
