<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChurchType extends Model
{
    use HasFactory;
    
    protected $table = 'church_type';
    protected $primaryKey = 'id';
    public $timestamps = true;
    
    protected $fillable = [
        'id',
        'church_type_name',
        'church_type_lead_id',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * A church type has a lead member (the person responsible for this service type).
     * Reverse: TiuMember leads many church types.
     * Foreign Key: church_type.church_type_lead_id references tiu_member.tiu_member_id
     */
    public function lead()
    {
        return $this->belongsTo(TiuMember::class, 'church_type_lead_id', 'tiu_member_id');
    }

    /**
     * A church type has many first-timer records for that service type.
     * Reverse: FirstTimer belongs to ChurchType.
     * Foreign Key: first_timer.church_type_id references church_type.id
     */
    public function firstTimers()
    {
        return $this->hasMany(FirstTimer::class, 'church_type_id', 'id');
    }

    /**
     * A church type has many members who attend this service type.
     * Reverse: TiuMember belongs to ChurchType.
     * Foreign Key: tiu_member.church_type_id references church_type.id
     */
    public function members()
    {
        return $this->hasMany(TiuMember::class, 'church_type_id', 'id');
    }

    /**
     * A church type has many church attendance records for this service type.
     * Reverse: ChurchAttendance belongs to ChurchType.
     * Foreign Key: church_attendance.church_type_id references church_type.id
     */
    public function churchAttendances()
    {
        return $this->hasMany(ChurchAttendance::class, 'church_type_id', 'id');
    }
}
