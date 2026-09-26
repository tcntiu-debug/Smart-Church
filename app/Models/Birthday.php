<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Birthday extends Model
{
    use HasFactory;
    
    protected $table = 'birthday';
    protected $primaryKey = 'bid';
    public $timestamps = false;
    
    protected $fillable = [
        'bid',
        'tiu_member_id',
        'Name',
        'birthday',
        'extra',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * A birthday record belongs to a member.
     * Reverse: TiuMember has one birthday record.
     * Foreign Key: birthday.tiu_member_id references tiu_member.tiu_member_id
     */
    public function member()
    {
        return $this->belongsTo(TiuMember::class, 'tiu_member_id', 'tiu_member_id');
    }
}
