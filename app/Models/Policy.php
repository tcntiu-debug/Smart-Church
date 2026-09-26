<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Policy extends Model
{
    use HasFactory;

    protected $table = 'policy';
    protected $primaryKey = 'pid';
    public $timestamps = true;

    protected $fillable = [
        'pid',
        'tiu_member_id',
        'name',
        'signature',
        'date_signed',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * A policy acknowledgment record belongs to a member.
     * Reverse: TiuMember has many policy acknowledgment records.
     * Foreign Key: policy.tiu_member_id references tiu_member.tiu_member_id
     */
    public function member()
    {
        return $this->belongsTo(TiuMember::class, 'tiu_member_id', 'tiu_member_id');
    }
}
