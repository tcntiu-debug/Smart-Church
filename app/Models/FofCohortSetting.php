<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasCampusScope;

class FofCohortSetting extends Model
{
    use HasFactory;
    use HasCampusScope;

    protected $table = 'fof_cohort_setting';
    protected $primaryKey = 'cohort_id';
    public $timestamps = false;

    protected $fillable = [
        'cohort_name',
        'cohort_status',
        'campus_id',
    ];

    public function campus()
    {
        return $this->belongsTo(Campus::class, 'campus_id', 'cid');
    }

    public function registrations()
    {
        return $this->hasMany(FofRegister::class, 'cohort_id', 'cohort_id');
    }
}
