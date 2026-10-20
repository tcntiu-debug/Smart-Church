<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * One place to read and write tiu_member.subgroup.
 *
 * The column carries two historical shapes:
 *
 *   ["23::Touch Point 2"]   canonical: JSON array of "deptID::SubgroupName"
 *   Touch Point 2           legacy: a bare subgroup name (department unknown)
 *   "Touch Point 2"         legacy: a JSON-encoded scalar
 *
 * Readers accept all three; writers always emit the canonical array so that
 * /group, /mtouchpoint and the mobile API agree on what an assignment is.
 */
class SubgroupAssignmentService
{
    /** Department that owns the "Touch Point" concept. */
    public const TOUCHPOINT_DEPARTMENT_ID = 23;

    /** Name of that department as stored in the department table. */
    public const TOUCHPOINT_DEPARTMENT_NAME = 'Tracking and Integration';

    /**
     * Normalise any stored value into a flat, de-duplicated list of entries.
     *
     * @param  mixed  $raw
     * @return array
     */
    public static function decode($raw)
    {
        if (is_array($raw)) {
            $entries = $raw;
        } else {
            $raw = trim((string) $raw);

            if ($raw === '' || $raw === '[]' || $raw === 'null' || strtolower($raw) === 'unassigned') {
                return [];
            }

            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                $entries = $decoded;
            } elseif (is_string($decoded)) {
                // JSON scalar, e.g. "Touch Point 2" (with quotes in the column)
                $entries = [$decoded];
            } else {
                // Plain legacy string, e.g. Touch Point 2
                $entries = [$raw];
            }
        }

        $clean = [];
        foreach ($entries as $entry) {
            if (!is_string($entry) && !is_numeric($entry)) {
                continue;
            }

            $entry = trim((string) $entry);
            if ($entry === '' || strtolower($entry) === 'unassigned') {
                continue;
            }

            if (!in_array($entry, $clean, true)) {
                $clean[] = $entry;
            }
        }

        return $clean;
    }

    /**
     * Value for the subgroup column. Always a JSON array; '[]' when empty.
     *
     * @param  array  $entries
     * @return string
     */
    public static function encode(array $entries)
    {
        $clean = [];
        foreach ($entries as $entry) {
            $entry = trim((string) $entry);
            if ($entry !== '' && !in_array($entry, $clean, true)) {
                $clean[] = $entry;
            }
        }

        return json_encode(array_values($clean));
    }

    /**
     * Canonical key: "deptID::SubgroupName".
     *
     * @param  int|string  $departmentId
     * @param  string  $subGroupName
     * @return string
     */
    public static function key($departmentId, $subGroupName)
    {
        return (int) $departmentId . '::' . trim((string) $subGroupName);
    }

    /**
     * Split one entry into [departmentId|null, name].
     *
     * @param  string  $entry
     * @return array
     */
    public static function split($entry)
    {
        $entry = trim((string) $entry);

        if (strpos($entry, '::') !== false) {
            $parts = explode('::', $entry, 2);

            return [trim($parts[0]), trim($parts[1])];
        }

        return [null, $entry];
    }

    /**
     * Plain subgroup names a member holds inside one department.
     *
     * Bare legacy values carry no department, so they are reported for every
     * department. Callers that need certainty compare them against the real
     * group names, see isAssignedToAny().
     *
     * @param  mixed  $raw
     * @param  int|string  $departmentId
     * @return array
     */
    public static function namesForDepartment($raw, $departmentId)
    {
        $names = [];

        foreach (static::decode($raw) as $entry) {
            list($entryDept, $name) = static::split($entry);

            if ($name === '') {
                continue;
            }

            if ($entryDept === null || (string) $entryDept === (string) $departmentId) {
                if (!in_array($name, $names, true)) {
                    $names[] = $name;
                }
            }
        }

        return $names;
    }

    /**
     * Every plain name in the column, regardless of department.
     *
     * @param  mixed  $raw
     * @return array
     */
    public static function allNames($raw)
    {
        $names = [];

        foreach (static::decode($raw) as $entry) {
            list(, $name) = static::split($entry);

            if ($name !== '' && !in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * The touch-point name when the member has one, otherwise any assigned name.
     * The mobile profile endpoint expects a single plain name.
     *
     * @param  mixed  $raw
     * @return string
     */
    public static function primaryName($raw)
    {
        $touchpoints = static::namesForDepartment($raw, static::TOUCHPOINT_DEPARTMENT_ID);
        if (!empty($touchpoints)) {
            return $touchpoints[0];
        }

        $names = static::allNames($raw);

        return isset($names[0]) ? $names[0] : '';
    }

    /**
     * Is the member assigned to at least one of the department's groups?
     *
     * @param  mixed  $raw
     * @param  int|string  $departmentId
     * @param  array  $groupNames
     * @return bool
     */
    public static function isAssignedToAny($raw, $departmentId, array $groupNames)
    {
        $names = static::namesForDepartment($raw, $departmentId);

        if (empty($names) || empty($groupNames)) {
            return false;
        }

        $known = [];
        foreach ($groupNames as $groupName) {
            $known[strtolower(trim((string) $groupName))] = true;
        }

        foreach ($names as $name) {
            if (isset($known[strtolower($name)])) {
                return true;
            }
        }

        return false;
    }

    /**
     * LIKE patterns that find members assigned to one group in any stored shape.
     *
     * @param  int|string  $departmentId
     * @param  string  $subGroupName
     * @return array
     */
    public static function likePatterns($departmentId, $subGroupName)
    {
        $name = trim((string) $subGroupName);

        return [
            '%"' . (int) $departmentId . '::' . $name . '"%', // canonical JSON array entry
            '"' . $name . '"',                                 // JSON scalar
            $name,                                             // bare legacy value
        ];
    }

    /**
     * Constrain a query to the members assigned to one group, all shapes.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     * @param  int|string  $departmentId
     * @param  string  $subGroupName
     * @return mixed
     */
    public static function scopeAssignedTo($query, $departmentId, $subGroupName)
    {
        $patterns = static::likePatterns($departmentId, $subGroupName);

        return $query->where(function ($nested) use ($patterns) {
            foreach ($patterns as $pattern) {
                $nested->orWhere('subgroup', 'LIKE', $pattern);
            }
        });
    }

    /**
     * Subgroup names that exist for the department.
     *
     * Uses the query builder (not the model) so reads stay independent of the
     * authenticated user's campus, exactly like the assignment screens.
     *
     * @param  int|string  $departmentId
     * @return array
     */
    public static function groupNamesForDepartment($departmentId)
    {
        return DB::table('sub_group')
            ->where('department_id', $departmentId)
            ->orderBy('sub_group_name')
            ->pluck('sub_group_name')
            ->all();
    }

    /**
     * New value for the subgroup column: drop this department's old entry
     * (including legacy bare names that belong to the department) and append
     * the canonical key when a group is chosen.
     *
     * @param  mixed  $raw
     * @param  int|string  $departmentId
     * @param  string|null  $subGroupName
     * @param  array|null  $departmentGroupNames
     * @return string
     */
    public static function apply($raw, $departmentId, $subGroupName, array $departmentGroupNames = null)
    {
        $newName = trim((string) $subGroupName);

        // Tolerate a full key arriving from an old client.
        if (strpos($newName, '::') !== false) {
            list($entryDept, $plainName) = static::split($newName);

            if ($entryDept !== null && (string) $entryDept !== (string) $departmentId) {
                $departmentGroupNames = null;
            }

            $departmentId = $entryDept;
            $newName = $plainName;
        }

        return static::applyMany($raw, $departmentId, [$newName], $departmentGroupNames);
    }

    /**
     * Same as apply(), but for a whole selection: every entry of the department
     * is replaced by the chosen groups in one pass.
     *
     * A member may hold more than one touch point, and saving the profile form
     * must not turn that into "last selected wins". Entries from other
     * departments are kept exactly as apply() keeps them.
     *
     * Callers are expected to have filtered the names against the department's
     * real groups (see groupNamesForDepartment()); this method only normalises
     * blanks, duplicates and legacy "deptID::Name" keys.
     *
     * @param  mixed  $raw
     * @param  int|string  $departmentId
     * @param  array  $subGroupNames
     * @param  array|null  $departmentGroupNames
     * @return string
     */
    public static function applyMany($raw, $departmentId, array $subGroupNames, array $departmentGroupNames = null)
    {
        if ($departmentGroupNames === null) {
            $departmentGroupNames = static::groupNamesForDepartment($departmentId);
        }

        $known = [];
        foreach ($departmentGroupNames as $name) {
            $known[strtolower(trim((string) $name))] = true;
        }

        $newNames = [];
        foreach ($subGroupNames as $subGroupName) {
            if (!is_string($subGroupName) && !is_numeric($subGroupName)) {
                continue;
            }

            // Tolerate a full key arriving from an old client.
            list(, $name) = static::split((string) $subGroupName);
            $name = trim($name);

            if ($name === '' || in_array($name, $newNames, true)) {
                continue;
            }

            $newNames[] = $name;
        }

        $kept = [];

        foreach (static::decode($raw) as $entry) {
            list($entryDept, $name) = static::split($entry);

            if ($entryDept !== null) {
                // Replace only this department's entries; other departments stay untouched.
                if ((string) $entryDept === (string) $departmentId) {
                    continue;
                }

                $kept[] = $entry;
                continue;
            }

            // Legacy bare value: replaced only when it names a group of this department.
            if ($name !== '' && isset($known[strtolower($name)])) {
                continue;
            }

            $kept[] = $entry;
        }

        foreach ($newNames as $newName) {
            $kept[] = static::key($departmentId, $newName);
        }

        return static::encode($kept);
    }

    /**
     * Subgroup rows the member belongs to, in column order and de-duplicated.
     *
     * Bare legacy names are resolved against the real sub_group table, with the
     * touch-point department preferred when a name exists more than once.
     *
     * @param  mixed  $raw
     * @return \Illuminate\Support\Collection
     */
    public static function groupsForMember($raw)
    {
        $groups = collect();

        foreach (static::decode($raw) as $entry) {
            list($departmentId, $name) = static::split($entry);

            if ($name === '') {
                continue;
            }

            $query = DB::table('sub_group')->where('sub_group_name', $name);

            if ($departmentId !== null) {
                $query->where('department_id', $departmentId);
            } else {
                $query->orderByRaw(
                    'CASE WHEN department_id = ? THEN 0 ELSE 1 END',
                    [static::TOUCHPOINT_DEPARTMENT_ID]
                );
            }

            $group = $query->orderBy('subid')->first();

            if (!$group) {
                continue;
            }

            if (!$groups->contains('subid', $group->subid)) {
                $groups->push($group);
            }
        }

        return $groups;
    }

    /**
     * Add a department id to a department_name list, keeping string ids.
     *
     * @param  array  $departmentIds
     * @param  int|string  $departmentId
     * @return array
     */
    public static function withDepartment(array $departmentIds, $departmentId = self::TOUCHPOINT_DEPARTMENT_ID)
    {
        $ids = [];
        foreach ($departmentIds as $id) {
            $ids[] = (string) $id;
        }

        $wanted = (string) $departmentId;
        if (!in_array($wanted, $ids, true)) {
            $ids[] = $wanted;
        }

        return array_values(array_unique($ids));
    }
}
