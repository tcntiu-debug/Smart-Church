<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FirstTimersUpdate extends Model
{
    protected $table = 'first_timers_updates';
    protected $primaryKey = 'id';
    public $timestamps = true;
    
    protected $fillable = [
        'id',
        'first_timer_id',
        'department',
        'cluster',
        'house_felloship',
        'foundation_of_faith',
        'water_baptism',
        'holy_ghost_baptism',
        'birthday',
        'created_date',
        'community',
    ];
    
    protected $casts = [
        'created_date' => 'datetime',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * A first-timer update belongs to a first-timer record.
     * Reverse: FirstTimer has one update record.
     * Foreign Key: first_timers_updates.first_timer_id references first_timers.first_timer_id
     */
    public function firstTimer()
    {
        return $this->belongsTo(FirstTimer::class, 'first_timer_id', 'first_timer_id');
    }
}
