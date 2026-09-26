<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCampusScope;

class FirstTimer extends Model
{
    use HasFactory;
    use HasCampusScope;
    
    protected $table = 'first_timer';
    protected $primaryKey = 'first_timer_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;
    
    protected $fillable = [
        'first_timer_id',
        'first_name',
        'last_name',
        'phone_number',
        'email',
        'gender',
        'age',
        'marital_status',
        'occupation',
        'attendant_type',
        'how_did_you_hear',
        'address',
        'born_again',
        'water_baptism',
        'holy_ghost_baptism',
        'church_type_id',
        'registered_by',
        'parent_name',
        'parent_phone',
        'relationship',
        'attendance_count',
        'status',
        'register_date',
        'status_change_date',
        'community_id',
        'campus_id'
    ];
    
    protected $casts = [
        'register_date' => 'datetime',
        'status_change_date' => 'datetime',
    ];
    
    // ==========================================
    // RELATIONSHIPS
    // ==========================================
    
    public function community()
    {
        return $this->belongsTo(Community::class, 'community_id', 'id');
    }
    
    public function campus()
    {
        return $this->belongsTo(Campus::class, 'campus_id', 'id');
    }
    
    public function churchType()
    {
        return $this->belongsTo(ChurchType::class, 'church_type_id', 'id');
    }
    
    public function updates()
    {
        return $this->hasOne(FirstTimersUpdate::class, 'first_timer_id', 'first_timer_id');
    }
    
    public function trackingFollowups()
    {
        return $this->hasMany(MemberTrackingFollowup::class, 'first_timer_id', 'first_timer_id');
    }
    
    // ==========================================
    // SCOPES
    // ==========================================
    
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
    
    public function scopeRegisteredBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('register_date', [$startDate, $endDate]);
    }
    
    // ==========================================
    // ACCESSORS & MUTATORS
    // ==========================================
    
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }
    
    public function getAttendantTypeLabelAttribute()
    {
        $types = [
            'Visiting' => 'Visiting',
            'New To TCN (Will Join TCN)' => 'Will Join TCN',
            'New To TCN (May Join TCN)' => 'May Join TCN',
            'TCN Member (Relocating to Ikd)' => 'Relocating',
            'TCN Member (In Transit)' => 'In Transit'
        ];
        
        return $types[$this->attendant_type] ?? $this->attendant_type;
    }
    
    // ==========================================
    // HELPER METHODS
    // ==========================================
    
    public function isAssigned()
    {
        return $this->status === 'Assigned';
    }
    
    public function markAsAssigned()
    {
        $this->update(['status' => 'Assigned']);
    }
    
    public function incrementAttendance()
    {
        $this->increment('attendance_count');
    }
}
