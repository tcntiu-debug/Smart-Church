<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCampusScope;

class FofMarkAttendance extends Model
{
    use HasFactory;
    use HasCampusScope;

    protected $table = 'fof_mark_attendance_table';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'cohort',
        'week',
        'attendance_date',
        'status',
        'finished',
        'campus_id',
    ];

    public function student()
    {
        return $this->belongsTo(FofRegister::class, 'student_id', 'id');
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class, 'campus_id', 'cid');
    }
}
