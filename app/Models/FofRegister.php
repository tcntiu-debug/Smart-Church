<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCampusScope;

class FofRegister extends Model
{
    use HasFactory;
    use HasCampusScope;

    protected $table = 'fof_register_table';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'tiu_member_id',
        'first_timer_id',
        'first_name',
        'last_name',
        'email',
        'phone_number',
        'gender',
        'marital_status',
        'cohort_id',
        'smart_request',
        'commitment',
        'how_heard',
        'how_heard_other',
        'profile_photo',
        'registration_date',
        'campus_id',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function cohort()
    {
        return $this->belongsTo(FofCohortSetting::class, 'cohort_id', 'cohort_id');
    }

    public function attendances()
    {
        return $this->hasMany(FofMarkAttendance::class, 'student_id', 'id');
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class, 'campus_id', 'cid');
    }

    public function tiuMember()
    {
        return $this->belongsTo(TiuMember::class, 'tiu_member_id', 'tiu_member_id');
    }
}
