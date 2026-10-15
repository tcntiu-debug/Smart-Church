<?php
// app/Models/Campus.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campus extends Model
{
    protected $table = 'campus';
    protected $primaryKey = 'cid';
    
    public $timestamps = true;
    
    protected $fillable = [
        'cid',
        'cname'
    ];
    
    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * A campus has many users (Laravel auth users).
     * Reverse: User belongs to Campus.
     * Foreign Key: users.campus_id references campus.cid
     */
    public function users()
    {
        return $this->hasMany(User::class, 'campus_id', 'cid');
    }

    /**
     * A campus has many first-timer records.
     * Reverse: FirstTimer belongs to Campus.
     * Foreign Key: first_timer.campus_id references campus.cid
     */
    public function firstTimers()
    {
        return $this->hasMany(FirstTimer::class, 'campus_id', 'cid');
    }

    /**
     * A campus has many departments.
     * Reverse: Department belongs to Campus.
     * Foreign Key: department.campus_id references campus.cid
     */
    public function departments()
    {
        return $this->hasMany(Department::class, 'campus_id', 'cid');
    }

    /**
     * A campus has many communities.
     * Reverse: Community belongs to Campus.
     * Foreign Key: communities.campus_id references campus.cid
     */
    public function communities()
    {
        return $this->hasMany(Community::class, 'campus_id', 'cid');
    }

    /**
     * A campus has many members (TIU members).
     * Reverse: TiuMember belongs to Campus.
     * Foreign Key: tiu_member.campus_id references campus.cid
     */
    public function members()
    {
        return $this->hasMany(TiuMember::class, 'campus_id', 'cid');
    }

    /**
     * A campus has many church types (service types) associated with it.
     * Foreign Key: church_type.campus_id (if applicable)
     */
    public function churchTypes()
    {
        return $this->hasMany(ChurchType::class, 'campus_id', 'cid');
    }

    /**
     * A campus has many sub-groups.
     * Reverse: SubGroup belongs to Campus.
     * Foreign Key: sub_group.campus_id references campus.cid
     */
    public function subGroups()
    {
        return $this->hasMany(SubGroup::class, 'campus_id', 'cid');
    }

    /**
     * A campus has many announcements.
     * Reverse: Announcement belongs to Campus.
     * Foreign Key: announcements.campus_id references campus.cid
     */
    public function announcements()
    {
        return $this->hasMany(Announcement::class, 'campus_id', 'cid');
    }

    /**
     * A campus has many church attendance records.
     * Reverse: ChurchAttendance belongs to Campus.
     */
    public function churchAttendances()
    {
        return $this->hasMany(ChurchAttendance::class, 'campus_id', 'cid');
    }

    /**
     * A campus has many first-timer view limit settings.
     * Reverse: FirstTimeViewLimit belongs to Campus.
     * Foreign Key: first_time_view_limit.campus_id references campus.cid
     */
    public function firstTimeViewLimits()
    {
        return $this->hasMany(FirstTimeViewLimit::class, 'campus_id', 'cid');
    }

    // ==========================================
    // SINGLE CAMPUS MODE
    // ==========================================

    /**
     * Is the app running as a single-campus church?
     *
     * When true every campus filter is inert: queries are no longer narrowed by
     * `campus_id`, only the column (and the value written into it) is kept, so a
     * second campus can be added later without rewriting 22 controllers. The
     * filters themselves are not deleted - they all go through
     * ->forCurrentCampus() / Campus::filterId(), so SINGLE_CAMPUS=false in .env
     * brings every one of them back.
     */
    public static function isSingle(): bool
    {
        return (bool) config('campus.single', true);
    }

    /**
     * Campus stamped on rows created while the campus pickers are gone.
     */
    public static function defaultId(): int
    {
        return (int) config('campus.default_id', 1);
    }

    /**
     * Campus name used when the campus table has no usable row.
     */
    public static function defaultName(): string
    {
        return (string) config('campus.default_name', 'Ikorodu');
    }

    /**
     * The campus the logged-in user is limited to, or null when there is
     * nothing to filter by: single campus mode, no logged-in user, a Super User
     * (who always saw every campus) or a user with no campus of their own.
     */
    public static function currentId(): ?int
    {
        if (self::isSingle()) {
            return null;
        }

        $user = auth()->user();

        if (!$user) {
            return null;
        }

        if (isset($user->member_role) && $user->member_role === 'Super User') {
            return null;
        }

        $campusId = (int) ($user->campus_id ?? 0);

        return $campusId > 0 ? $campusId : null;
    }

    /**
     * Campus id a query should be filtered by: an explicitly requested campus
     * wins in multi-campus mode, otherwise the logged-in user's campus. Null
     * means "do not filter at all".
     *
     * @param  mixed  $requested
     */
    public static function filterId($requested = null): ?int
    {
        if (self::isSingle()) {
            return null;
        }

        $requested = (int) $requested;

        return $requested > 0 ? $requested : self::currentId();
    }

    /**
     * Campus id to store on a new row. Always a real id because the columns are
     * NOT NULL - in single campus mode a stale form field can no longer stamp a
     * campus the church is not using.
     *
     * @param  mixed  $requested
     */
    public static function writeId($requested = null): int
    {
        $requested = (int) $requested;

        if ($requested > 0 && !self::isSingle()) {
            return $requested;
        }

        return self::defaultId();
    }

    /**
     * Narrow a query (Eloquent or DB::table) to a campus when this install
     * still has more than one. Returns the builder untouched in single campus
     * mode, which is how the whole campus layer was retired.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @param  mixed  $requested
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public static function filterQuery($query, string $column = 'campus_id', $requested = null)
    {
        $campusId = self::filterId($requested);

        return $campusId ? $query->where($column, $campusId) : $query;
    }
}
