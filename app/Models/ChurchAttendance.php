<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChurchAttendance extends Model
{
    use HasFactory;

    protected $table = 'church_attendance';
    protected $primaryKey = 'attendance_id';
    public $timestamps = false;

    protected $fillable = [
        'attendance_id',
        'member_id',
        'member_type',
        'full_name',
        'church_type_id',
        'attendance_date',
        'date_created',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * A church attendance record belongs to a church type (service type).
     * Reverse: ChurchType has many church attendance records.
     * Foreign Key: church_attendance.church_type_id references church_type.id
     */
    public function churchType()
    {
        return $this->belongsTo(ChurchType::class, 'church_type_id', 'id');
    }
}
