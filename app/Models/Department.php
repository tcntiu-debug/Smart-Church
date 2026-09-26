<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
// use App\Traits\HasCampusScope;  // Comment this out temporarily

class Department extends Model
{
    use HasFactory;
    // use HasCampusScope;  // Comment this out temporarily
    
    protected $table = 'department';
    protected $primaryKey = 'dept_id';
    
    public $timestamps = false;  // Change to false since your table doesn't have timestamps
    
    protected $fillable = [
        'dept_id',
        'dept_type',
        'dept_name',
        'dept_address',
        'community_id',
        'dept_lead',
        'dept_lead_id',
        'campus_id',
    ];
    
    // Relationships
    public function campus()
    {
        return $this->belongsTo(Campus::class, 'campus_id', 'cid');
    }

    public function community()
    {
        return $this->belongsTo(Community::class, 'community_id', 'id');
    }

    public function lead()
    {
        return $this->belongsTo(TiuMember::class, 'dept_lead_id', 'tiu_member_id');
    }
}