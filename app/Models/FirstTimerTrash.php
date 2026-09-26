<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FirstTimerTrash extends Model
{
    use HasFactory;

    protected $table = 'first_timer_trash';
    protected $primaryKey = 'trash_id';
    public $timestamps = true;

    protected $fillable = [
        'trash_id',
        'first_name',
        'last_name',
        'phone_number',
        'email',
        'gender',
        'age',
        'occupation',
        'attendant_type',
        'attendance_count',
        'status',
        'register_date',
    ];
}
