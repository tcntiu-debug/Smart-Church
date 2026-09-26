<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
//use App\Traits\HasCampusScope;

class TiuMember extends Authenticatable
{
    use HasFactory;
    //use HasCampusScope;
    
    protected $table = 'tiu_member';
    protected $primaryKey = 'tiu_member_id';
    public $timestamps = false;
    
    protected $fillable = [
        'tiu_member_id',
        'first_name',
        'last_name',
        'phone_number',
        'email',
        'gender',
        'marital_status',
        'age',
        'community_id',
        'community_other',
        'occupation',
        'church_type_id',
        'department_name',
        'cluster',
        'house_fellowship',
        'residential_address',
        'next_of_kin_name',
        'next_of_kin_phone',
        'status',
        'member_role',
        'subgroup',
        'oversight_extra1',
        'picture_part',
        'password',
        'email_verified',
        'date_registered',
        'accountability_extra3',
        'bus_stop_id',
        'parent_gaudian',
        'Phone',
        'Relationship',
        'campus_id'
    ];
    
    protected $hidden = ['password'];
    
    // ==========================================
    // RELATIONSHIPS
    // ==========================================
    
    /**
     * A member has one birthday record.
     * Reverse: Birthday belongs to TiuMember.
     * Foreign Key: birthday.tiu_member_id references tiu_member.tiu_member_id
     */
    public function birthday()
    {
        return $this->hasOne(Birthday::class, 'tiu_member_id', 'tiu_member_id');
    }
    
    /**
     * A member belongs to one community.
     * Reverse: Community has many members.
     * Foreign Key: tiu_member.community_id references communities.id
     */
    public function community()
    {
        return $this->belongsTo(Community::class, 'community_id', 'id');
    }
    
    /**
     * A member belongs to one church type (service type they primarily attend).
     * Reverse: ChurchType has many members.
     * Foreign Key: tiu_member.church_type_id references church_type.id
     */
    public function churchType()
    {
        return $this->belongsTo(ChurchType::class, 'church_type_id', 'id');
    }
    
    /**
     * A member belongs to one campus.
     * Reverse: Campus has many members.
     * Foreign Key: tiu_member.campus_id references campus.cid
     */
    public function campus()
    {
        return $this->belongsTo(Campus::class, 'campus_id', 'cid');
    }
    
    /**
     * A member belongs to one bus stop (their preferred pick-up point).
     * Reverse: TransportStop has many members.
     * Foreign Key: tiu_member.bus_stop_id references transport_stops.stop_id
     */
    public function busStop()
    {
        return $this->belongsTo(TransportStop::class, 'bus_stop_id', 'stop_id');
    }
    
    /**
     * A member has many bus attendance records (as the rider).
     * Reverse: BusAttendance belongs to TiuMember.
     * Foreign Key: bus_attendance.tiu_member_id references tiu_member.tiu_member_id
     */
    public function busAttendances()
    {
        return $this->hasMany(BusAttendance::class, 'tiu_member_id', 'tiu_member_id');
    }
    
    /**
     * A member has many bus attendance records they marked (as the attendance taker).
     * Reverse: BusAttendance (marked_by) belongs to TiuMember.
     * Foreign Key: bus_attendance.marked_by references tiu_member.tiu_member_id
     */
    public function markedAttendances()
    {
        return $this->hasMany(BusAttendance::class, 'marked_by', 'tiu_member_id');
    }
    
    /**
     * A member has many marketplace businesses they own.
     * Reverse: MarketplaceBusiness belongs to TiuMember.
     * Foreign Key: marketplace_businesses.tiu_member_id references tiu_member.tiu_member_id
     */
    public function marketplaceBusinesses()
    {
        return $this->hasMany(MarketplaceBusiness::class, 'tiu_member_id', 'tiu_member_id');
    }
    
    /**
     * A member has uploaded many shared resources.
     * Reverse: SharedResource belongs to TiuMember (uploader).
     * Foreign Key: shared_resources.uploader_id references tiu_member.tiu_member_id
     */
    public function sharedResources()
    {
        return $this->hasMany(SharedResource::class, 'uploader_id', 'tiu_member_id');
    }
    
    /**
     * A member has received many resource shares.
     * Reverse: ResourceShare belongs to TiuMember (recipient).
     * Foreign Key: resource_shares.member_id references tiu_member.tiu_member_id
     */
    public function resourceShares()
    {
        return $this->hasMany(ResourceShare::class, 'member_id', 'tiu_member_id');
    }
    
    /**
     * A member has many login records.
     * Reverse: TiuMemberLogin belongs to TiuMember.
     * Foreign Key: tiu_member_login.tiu_member_id references tiu_member.tiu_member_id
     */
    public function logins()
    {
        return $this->hasMany(TiuMemberLogin::class, 'tiu_member_id', 'tiu_member_id');
    }
    
    /**
     * A member has many policy acknowledgment records.
     * Reverse: Policy belongs to TiuMember.
     * Foreign Key: policy.tiu_member_id references tiu_member.tiu_member_id
     */
    public function policies()
    {
        return $this->hasMany(Policy::class, 'tiu_member_id', 'tiu_member_id');
    }
    
    /**
     * A member has many airtime recharge history records.
     * Reverse: AirtimeHistory belongs to TiuMember.
     * Foreign Key: airtime_history.user_id references tiu_member.tiu_member_id
     */
    public function airtimeHistories()
    {
        return $this->hasMany(AirtimeHistory::class, 'user_id', 'tiu_member_id');
    }
    
    /**
     * A member leads many departments.
     * Reverse: Department belongs to TiuMember (lead).
     * Foreign Key: department.dept_lead_id references tiu_member.tiu_member_id
     */
    public function departmentsLed()
    {
        return $this->hasMany(Department::class, 'dept_lead_id', 'tiu_member_id');
    }
    
    /**
     * A member leads many sub-groups.
     * Reverse: SubGroup belongs to TiuMember (lead).
     * Foreign Key: sub_group.lead_id references tiu_member.tiu_member_id
     */
    public function subGroupsLed()
    {
        return $this->hasMany(SubGroup::class, 'lead_id', 'tiu_member_id');
    }
    
    /**
     * A member leads many church types (service types).
     * Reverse: ChurchType belongs to TiuMember (lead).
     * Foreign Key: church_type.church_type_lead_id references tiu_member.tiu_member_id
     */
    public function churchTypesLed()
    {
        return $this->hasMany(ChurchType::class, 'church_type_lead_id', 'tiu_member_id');
    }
    
    /**
     * A member has many tracking followup records they performed.
     * Reverse: MemberTrackingFollowup belongs to TiuMember.
     * Foreign Key: member_tracking_followup.tiu_member_id references tiu_member.tiu_member_id
     */
    public function trackingFollowups()
    {
        return $this->hasMany(MemberTrackingFollowup::class, 'tiu_member_id', 'tiu_member_id');
    }
    
    // ==========================================
    // ACCESSORS FOR JSON FIELDS
    // ==========================================
    
    public function getDepartmentNameAttribute($value)
    {
        if (empty($value)) return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
    
    public function getClusterAttribute($value)
    {
        if (empty($value)) return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
    
    public function getHouseFellowshipAttribute($value)
    {
        if (empty($value)) return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
    
    public function getOversightExtra1Attribute($value)
    {
        if (empty($value)) return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
    
    // ==========================================
    // MUTATORS FOR JSON FIELDS
    // ==========================================
    
    public function setDepartmentNameAttribute($value)
    {
        $this->attributes['department_name'] = json_encode($value);
    }
    
    public function setClusterAttribute($value)
    {
        $this->attributes['cluster'] = json_encode($value);
    }
    
    public function setHouseFellowshipAttribute($value)
    {
        $this->attributes['house_fellowship'] = json_encode($value);
    }
    
    public function setOversightExtra1Attribute($value)
    {
        $this->attributes['oversight_extra1'] = json_encode($value);
    }
    
    // ==========================================
    // ACCESSORS
    // ==========================================
    
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }
    
    // ==========================================
    // HELPER METHODS
    // ==========================================
    
    public function isAdmin()
    {
        return in_array($this->member_role, ['Super User', 'Admin']);
    }
    
    public function isActive()
    {
        return $this->status == 1;
    }
    
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
    
    public function scopeByCampus($query, $campusId)
    {
        return $query->where('campus_id', $campusId);
    }
    
    public function scopeByChurchType($query, $churchTypeId)
    {
        return $query->where('church_type_id', $churchTypeId);
    }
}
