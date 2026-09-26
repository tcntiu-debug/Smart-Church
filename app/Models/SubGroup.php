<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCampusScope;

class SubGroup extends Model
{
    use HasFactory;
    use HasCampusScope;
    
    protected $table = 'sub_group';
    protected $primaryKey = 'subid';
    public $timestamps = true;
    
    protected $fillable = [
        'subid',
        'department_name',
        'sub_group_name',
        'lead_id',
        'campus_id',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================
    
    /**
     * A sub-group belongs to one campus.
     * Reverse: Campus has many sub-groups.
     * Foreign Key: sub_group.campus_id references campus.cid
     */
    public function campus()
    {
        return $this->belongsTo(Campus::class, 'campus_id', 'cid');
    }

    /**
     * A sub-group has a lead member.
     * Reverse: TiuMember leads many sub-groups.
     * Foreign Key: sub_group.lead_id references tiu_member.tiu_member_id
     */
    public function lead()
    {
        return $this->belongsTo(TiuMember::class, 'lead_id', 'tiu_member_id');
    }
}
