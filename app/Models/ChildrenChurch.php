<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChildrenChurch extends Model
{
    use HasFactory;

    protected $table = 'children_church';
    protected $primaryKey = 'child_id';
    public $timestamps = true;

    protected $fillable = [
        'parent_name',
        'child_name',
        'dob',
        'parent_phone',
        'campus_id',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * A child belongs to one campus.
     * Reverse: Campus has many children.
     * Foreign Key: children_church.campus_id references campus.cid
     */
    public function campus()
    {
        return $this->belongsTo(Campus::class, 'campus_id', 'cid');
    }

    /**
     * A child has many attendance records.
     * Reverse: ChildrenChurchAttendance belongs to ChildrenChurch.
     * Foreign Key: children_church_attendance.child_id references children_church.child_id
     */
    public function attendances()
    {
        return $this->hasMany(ChildrenChurchAttendance::class, 'child_id', 'child_id');
    }

    /**
     * Check if child has already been marked for a specific date.
     */
    public function scopeMarkedToday($query, $date = null)
    {
        $date = $date ?? now()->format('Y-m-d');
        return $query->whereHas('attendances', function ($q) use ($date) {
            $q->where('attendance_date', $date);
        });
    }

    /**
     * Scope query to filter by campus.
     */
    public function scopeByCampus($query, $campusId)
    {
        return $query->where('campus_id', $campusId);
    }
}
