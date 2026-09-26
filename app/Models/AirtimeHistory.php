<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AirtimeHistory extends Model
{
    use HasFactory;

    protected $table = 'airtime_history';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'id',
        'user_id',
        'user_name',
        'user_phone',
        'year',
        'week',
        'requested_at',
        'credited',
        'credited_at',
        'status',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * An airtime history record belongs to the member who requested the recharge.
     * Reverse: TiuMember has many airtime history records.
     * Foreign Key: airtime_history.user_id references tiu_member.tiu_member_id
     */
    public function user()
    {
        return $this->belongsTo(TiuMember::class, 'user_id', 'tiu_member_id');
    }
}
