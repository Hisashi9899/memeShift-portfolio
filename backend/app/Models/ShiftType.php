<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ShiftType extends Model
{
    protected $fillable = ['name', 'start_time', 'end_time', 'color', 'sort_order'];
}
