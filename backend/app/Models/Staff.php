<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    protected $fillable = ['name', 'employment_type', 'can_night_shift', 'max_consecutive_days', 'start_date', 'end_date', 'active', 'sort_order'];
    protected $casts = [
        'can_night_shift' => 'boolean',
        'active' => 'boolean',
        'max_consecutive_days' => 'integer',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];
}
