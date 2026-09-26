<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
//use App\Traits\HasCampusScope;

class Community extends Model
{
    use HasFactory;
    //use HasCampusScope;
    
    protected $table = 'communities';
    protected $primaryKey = 'id';
    
    public $timestamps = true;
    
    protected $fillable = [
        'id',
        'community_name',
        'latitude',
        'longitude',
        'campus_id',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * A community belongs to one campus.
     * Reverse: Campus has many communities.
     * Foreign Key: communities.campus_id references campus.cid
     */
    public function campus()
    {
        return $this->belongsTo(Campus::class, 'campus_id', 'cid');
    }

    /**
     * A community has many first-timer records.
     * Reverse: FirstTimer belongs to Community.
     * Foreign Key: first_timer.community_id references communities.id
     */
    public function firstTimers()
    {
        return $this->hasMany(FirstTimer::class, 'community_id', 'id');
    }

    /**
     * A community has many members who reside in it.
     * Reverse: TiuMember belongs to Community.
     * Foreign Key: tiu_member.community_id references communities.id
     */
    public function members()
    {
        return $this->hasMany(TiuMember::class, 'community_id', 'id');
    }

    /**
     * A community has many departments.
     * Reverse: Department belongs to Community.
     * Foreign Key: department.community_id references communities.id
     */
    public function departments()
    {
        return $this->hasMany(Department::class, 'community_id', 'id');
    }
}
