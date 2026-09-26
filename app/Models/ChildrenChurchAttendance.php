<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChildrenChurchAttendance extends Model
{
    use HasFactory;

    protected $table = 'children_church_attendance';
    protected $primaryKey = 'attendance_id';
    public $timestamps = true;

    protected $fillable = [
        'child_id',
        'attendance_date',
        'marked_by',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * An attendance record belongs to a child.
     * Reverse: ChildrenChurch has many attendance records.
     * Foreign Key: children_church_attendance.child_id references children_church.child_id
     */
    public function child()
    {
        return $this->belongsTo(ChildrenChurch::class, 'child_id', 'child_id');
    }

    /**
     * An attendance record was marked by an admin (TIU member).
     * Reverse: TiuMember has many attendance records they marked.
     * Foreign Key: children_church_attendance.marked_by references tiu_member.tiu_member_id
     */
    public function markedBy()
    {
        return $this->belongsTo(TiuMember::class, 'marked_by', 'tiu_member_id');
    }
}
