<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PublicHolidaySetting extends Model
{
    protected $fillable = ['month_year', 'count'];
    protected $casts = ['count' => 'integer'];
}
