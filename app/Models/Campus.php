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
     * A campus has many transport routes.
     * Reverse: TransportRoute belongs to Campus.
     * Foreign Key: transport_routes.campus_id references campus.cid
     */
    public function transportRoutes()
    {
        return $this->hasMany(TransportRoute::class, 'campus_id', 'cid');
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
     * A campus has many FOF cohort settings.
     * Reverse: FofCohortSetting belongs to Campus.
     * Foreign Key: fof_cohort_setting.campus_id references campus.cid
     */
    public function fofCohortSettings()
    {
        return $this->hasMany(FofCohortSetting::class, 'campus_id', 'cid');
    }

    /**
     * A campus has many FOF student registrations.
     * Reverse: FofRegister belongs to Campus.
     * Foreign Key: fof_register_table.campus_id references campus.cid
     */
    public function fofRegisters()
    {
        return $this->hasMany(FofRegister::class, 'campus_id', 'cid');
    }

    /**
     * A campus has many FOF attendance records.
     * Reverse: FofMarkAttendance belongs to Campus.
     * Foreign Key: fof_mark_attendance_table.campus_id references campus.cid
     */
    public function fofMarkAttendances()
    {
        return $this->hasMany(FofMarkAttendance::class, 'campus_id', 'cid');
    }

    /**
     * A campus has many transport stops (indirectly through routes).
     */
    public function transportStops()
    {
        return $this->hasManyThrough(TransportStop::class, TransportRoute::class, 'campus_id', 'route_id', 'cid', 'route_id');
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
