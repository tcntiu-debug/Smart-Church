<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrayerSuggestion extends Model
{
    use HasFactory;

    protected $table = 'prayer_suggestions';

    protected $fillable = [
        'tiu_member_id',
        'type',
        'message',
        'status',
    ];

    /**
     * A prayer/suggestion belongs to a member.
     */
    public function member()
    {
        return $this->belongsTo(TiuMember::class, 'tiu_member_id', 'tiu_member_id');
    }
}
