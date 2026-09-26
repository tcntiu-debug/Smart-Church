<?php
// app/Models/MemberTrackingFollowup.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberTrackingFollowup extends Model
{
    protected $table = 'member_tracking_followup';
    protected $primaryKey = 'tracking_id';
    public $timestamps = true;
    
    protected $fillable = [
        'tracking_id',
        'tiu_member_id',
        'first_timer_id',
        'followup_response_new'
    ];
    
    protected $casts = [
        'followup_response_new' => 'array'
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * A tracking followup record belongs to a first-timer record.
     * Reverse: FirstTimer has many tracking followup records.
     * Foreign Key: member_tracking_followup.first_timer_id references first_timers.first_timer_id
     */
    public function firstTimer()
    {
        return $this->belongsTo(FirstTimer::class, 'first_timer_id', 'first_timer_id');
    }

    /**
     * A tracking followup record belongs to a member (the one who did the followup).
     * Reverse: TiuMember has many tracking followup records.
     * Foreign Key: member_tracking_followup.tiu_member_id references tiu_member.tiu_member_id
     */
    public function member()
    {
        return $this->belongsTo(TiuMember::class, 'tiu_member_id', 'tiu_member_id');
    }
}
