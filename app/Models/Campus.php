<?php
// app/Models/Campus.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campus extends Model
{
    protected $table = 'campus';
    protected $primaryKey = 'cid';
    
    public $timestamps = true;
    
    protected $fillable = [
        'cid',
        'cname'
    ];
    
    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * A campus has many users (Laravel auth users).
     * Reverse: User belongs to Campus.
     * Foreign Key: users.campus_id references campus.cid
     */
    public function users()
    {
        return $this->hasMany(User::class, 'campus_id', 'cid');
    }

    /**
     * A campus has many first-timer records.
     * Reverse: FirstTimer belongs to Campus.
     * Foreign Key: first_timer.campus_id references campus.cid
     */
    public function firstTimers()
    {
        return $this->hasMany(FirstTimer::class, 'campus_id', 'cid');
    }

    /**
     * A campus has many departments.
     * Reverse: Department belongs to Campus.
     * Foreign Key: department.campus_id references campus.cid
     */
    public function departments()
    {
        return $this->hasMany(Department::class, 'campus_id', 'cid');
    }

    /**
     * A campus has many communities.
     * Reverse: Community belongs to Campus.
     * Foreign Key: communities.campus_id references campus.cid
     */
    public function communities()
    {
        return $this->hasMany(Community::class, 'campus_id', 'cid');
    }

    /**
     * A campus has many members (TIU members).
     * Reverse: TiuMember belongs to Campus.
     * Foreign Key: tiu_member.campus_id references campus.cid
     */
    public function members()
    {
        return $this->hasMany(TiuMember::class, 'campus_id', 'cid');
    }

    /**
     * A campus has many church types (service types) associated with it.
     * Foreign Key: church_type.campus_id (if applicable)
     */
    public function churchTypes()
    {
        return $this->hasMany(ChurchType::class, 'campus_id', 'cid');
    }

    /**
     * A campus has many sub-groups.
     * Reverse: SubGroup belongs to Campus.
     * Foreign Key: sub_group.campus_id references campus.cid
     */
    public function subGroups()
    {
        return $this->hasMany(SubGroup::class, 'campus_id', 'cid');
    }

    /**
     * A campus has many announcements.
     * Reverse: Announcement belongs to Campus.
     * Foreign Key: announcements.campus_id references campus.cid
     */
    public function announcements()
    {
        return $this->hasMany(Announcement::class, 'campus_id', 'cid');
    }

    /**
     * A campus has many church attendance records.
     * Reverse: ChurchAttendance belongs to Campus.
     */
    public function churchAttendances()
    {
        return $this->hasMany(ChurchAttendance::class, 'campus_id', 'cid');
    }

    /**
     * A campus has many first-timer view limit settings.
     * Reverse: FirstTimeViewLimit belongs to Campus.
     * Foreign Key: first_time_view_limit.campus_id references campus.cid
     */
    public function firstTimeViewLimits()
    {
        return $this->hasMany(FirstTimeViewLimit::class, 'campus_id', 'cid');
    }
}
