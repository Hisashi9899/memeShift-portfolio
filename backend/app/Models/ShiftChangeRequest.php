<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class ShiftChangeRequest extends Model
{
    use HasFactory;
    protected $fillable = [
        "schedule_cell_id",
        "requester_staff_id",
        "target_staff_id",
        "type",
        "status",
        "request_date",
    ];
}