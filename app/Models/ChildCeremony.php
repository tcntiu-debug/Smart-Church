<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChildCeremony extends Model
{
    use HasFactory;

    protected $table = 'child_ceremonies';

    protected $fillable = [
        'member_id',
        'campus_id',
        'ceremony_type',
        'status',
        'naming_address',
        'naming_landmarks',
        'child_gender',
        'child_position',
        'date_of_delivery',
        'proposed_naming_date',
        'proposed_naming_time',
        'proposed_child_names',
        'parent_background',
        'father_name',
        'mother_name',
        'dedication_child_name',
        'dedication_date',
    ];

    /**
     * A ceremony belongs to a member.
     */
    public function member()
    {
        return $this->belongsTo(TiuMember::class, 'member_id', 'tiu_member_id');
    }
}
