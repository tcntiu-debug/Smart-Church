<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FirstTimerHangout extends Model
{
    use HasFactory;

    protected $table = 'first_timer_hangout';
    protected $primaryKey = 'h_id';
    public $timestamps = true;

    protected $fillable = [
        'h_id',
        'first_timer_id',
        'first_name',
        'last_name',
        'phone_number',
        'occupation',
        'occupation2',
        'hangout_date',
        'member_status',
        'department',
        'extra2',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * A first-timer hangout registration belongs to a first-timer record.
     * Reverse: FirstTimer has many hangout registrations.
     * Foreign Key: first_timer_hangout.first_timer_id references first_timer.first_timer_id
     */
    public function firstTimer()
    {
        return $this->belongsTo(FirstTimer::class, 'first_timer_id', 'first_timer_id');
    }
}
