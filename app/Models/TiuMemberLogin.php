<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TiuMemberLogin extends Model
{
    use HasFactory;

    protected $table = 'tiu_member_login';
    protected $primaryKey = 'login_id';
    public $timestamps = true;

    protected $fillable = [
        'login_id',
        'tiu_member_id',
        'date_logged_in',
        'source_address',
        // Legacy column: only kept so the row matches the table. The dark/light
        // display mode is now a per-device cookie (see public/assets/js/theme.js).
        'theme_settings',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * A login record belongs to a member.
     * Reverse: TiuMember has many login records.
     * Foreign Key: tiu_member_login.tiu_member_id references tiu_member.tiu_member_id
     */
    public function member()
    {
        return $this->belongsTo(TiuMember::class, 'tiu_member_id', 'tiu_member_id');
    }
}
