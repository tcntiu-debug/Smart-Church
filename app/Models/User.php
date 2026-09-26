<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\HasCampusScope;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    use HasCampusScope;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tiu_member';
    
    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'tiu_member_id';
    
    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;
    
    /**
     * The data type of the primary key.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone_number',
        'password',
        'member_role',
        'status',
        'gender',
        'marital_status',
        'occupation',
        'residential_address',
        'campus_id',
        'community_id',
        'department_name',
        'cluster',
        'house_fellowship',
        'picture_part',
        'date_registered',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'date_registered' => 'datetime',
    ];
    
    /**
     * Get the user's full name.
     *
     * @return string
     */
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }
    
    /**
     * Get the user's role (alias for member_role).
     *
     * @return string
     */
    public function getRoleAttribute()
    {
        return $this->member_role ?? 'Member';
    }
    
    /**
     * Check if user is admin.
     *
     * @return bool
     */
    public function isAdmin()
    {
        return in_array($this->member_role, ['Super User', 'Admin']);
    }
    
    /**
     * Get the campus associated with the user.
     */
    public function campus()
    {
        return $this->belongsTo(Campus::class, 'campus_id', 'cid');
    }
    
    /**
     * Get the community associated with the user.
     */
    public function community()
    {
        return $this->belongsTo(Community::class, 'community_id', 'id');
    }
    
    /**
     * Get the first timers assigned to this user for tracking.
     */
    public function assignedFirstTimers()
    {
        return $this->belongsToMany(FirstTimer::class, 'member_tracking_followup', 'tiu_member_id', 'first_timer_id', 'tiu_member_id', 'first_timer_id');
    }
    
    /**
     * Get tracking assignments for this user.
     */
    public function trackingAssignments()
    {
        return $this->hasMany(MemberTrackingFollowup::class, 'tiu_member_id', 'tiu_member_id');
    }
    
    /**
     * Scope query to filter by role.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|array $roles
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithRole($query, $roles)
    {
        if (is_array($roles)) {
            return $query->whereIn('member_role', $roles);
        }
        return $query->where('member_role', $roles);
    }
    
    /**
     * Scope query for active users only.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
