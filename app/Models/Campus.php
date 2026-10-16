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
     * `campus_id`, and new rows leave the column empty. Only the column itself
     * is kept, so a second campus can be added later without rewriting 22
     * controllers. The filters themselves are not deleted - they all go through
     * ->forCurrentCampus() / Campus::filterId(), so SINGLE_CAMPUS=false in .env
     * brings every one of them back.
     */
    public static function isSingle(): bool
    {
        return (bool) config('campus.single', true);
    }

    /**
     * The campus the church runs. Nothing is stamped with it any more
     * (writeId() returns null), so it is what everything that still has to name
     * a campus uses: the birthday digest, the campus table fallback, the undo
     * step of the data retirement - and what SINGLE_CAMPUS=false goes back to
     * stamping on new rows.
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
     * Campus id to store on a new row, or null while the church runs a single
     * campus: there is no second campus to stamp, so the column is left empty
     * (the columns were made nullable and the data retired with the
     * 2026_10_14_000002 / _000003 migrations) instead of refilling it with a
     * campus nobody picks any more. Switching SINGLE_CAMPUS=false turns the
     * stamping back on, exactly like the filters.
     *
     * @param  mixed  $requested
     */
    public static function writeId($requested = null): ?int
    {
        if (self::isSingle()) {
            return null;
        }

        $requested = (int) $requested;

        return $requested > 0 ? $requested : self::defaultId();
    }

    /**
     * Match a query's campus column against the value the app would write into
     * it (see writeId()). This is row identity, not a filter: it is what a
     * read-then-rewrite needs (fetch the row you are about to update or delete),
     * so it never softens and it survives the retired (NULL) campus data.
     *
     * `where('campus_id', null)` happens to build `campus_id is null` as well -
     * Laravel turns a null value into whereNull() - but relying on that in a
     * delete path reads like a bug, so the identity is written out.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @param  mixed  $requested
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public static function matchQuery($query, string $column = 'campus_id', $requested = null)
    {
        $campusId = self::writeId($requested);

        return $campusId === null ? $query->whereNull($column) : $query->where($column, $campusId);
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
