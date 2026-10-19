<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/**
 * The session a signed-in member's pages are rendered from.
 *
 * Signing in is more than `Auth::login()`: `partials/nav.blade.php` renders the
 * whole left menu from session keys - `member_role` decides which admin blocks
 * exist, `department_name` (stored as the JSON string the column holds) picks the
 * department menus, `lead_of_dept` / `lead_of_church_type` / `userIsHODOrLead`
 * open the HOD views - and `layouts/app.blade.php` greets the member with
 * `first_name` / `last_name`.
 *
 * Only the login form used to write those keys, so the password reset - which
 * signs in through `ResetsPasswords::resetPassword()` - landed on /home as
 * "Welcome Guest" with the plain member menu, and the full menu only came back
 * after signing out and in again. Both doors fill the session here now.
 *
 * `$user` is either the stdClass `CustomAuthController` queries or a `TiuMember`;
 * the column names are the same.
 */
class MemberSessionService
{
    /**
     * Write the session a successful sign-in leaves behind.
     *
     * @param  object  $user  a tiu_member row (stdClass or TiuMember)
     * @return void
     */
    public function start($user)
    {
        $userId = $user->tiu_member_id;

        // The departments and church types this member leads.
        $leadDeptIds = DB::table('department')->where('dept_lead_id', $userId)->pluck('dept_id')->toArray();
        $leadHouseFellowshipIds = DB::table('department')->where('dept_lead_id', $userId)->where('dept_type', 'House Fellowship')->pluck('dept_id')->toArray();
        $leadClusterIds = DB::table('department')->where('dept_lead_id', $userId)->where('dept_type', 'Cluster')->pluck('dept_id')->toArray();

        // Church types the member leads.
        $leadChurchTypeIds = DB::table('church_type')->where('church_type_lead_id', $userId)->pluck('id')->toArray();

        Session::put('lead_of_dept', $leadDeptIds);
        Session::put('lead_of_house_fellowship', $leadHouseFellowshipIds);
        Session::put('lead_of_cluster', $leadClusterIds);
        Session::put('lead_of_church_type', $leadChurchTypeIds);

        $memberRole = $this->raw($user, 'member_role');

        $isHOD = count($leadDeptIds) > 0
            || count($leadHouseFellowshipIds) > 0
            || count($leadClusterIds) > 0
            || count($leadChurchTypeIds) > 0
            || in_array($memberRole, ['Super User', 'Admin']);
        Session::put('userIsHODOrLead', $isHOD);

        // Set the values the views read directly.
        Session::put('first_name', $this->raw($user, 'first_name'));
        Session::put('last_name', $this->raw($user, 'last_name'));
        Session::put('tiu_member_id', $userId);
        Session::put('member_role', $memberRole);
        Session::put('subgroup', $this->raw($user, 'subgroup') ?? '');
        Session::put('church_type_id', $this->raw($user, 'church_type_id'));
        Session::put('campus_id', $this->raw($user, 'campus_id'));
        Session::put('department_name', (string) $this->raw($user, 'department_name'));
        Session::put('house_fellowship', (string) $this->raw($user, 'house_fellowship'));
        Session::put('cluster', (string) $this->raw($user, 'cluster'));
        Session::put('login', true);
    }

    /**
     * The two rules the login form applies before it signs anybody in:
     * `status` 2 is a disabled account, `email_verified` must be 1.
     *
     * @param  object  $user  a tiu_member row (stdClass or TiuMember)
     * @return bool
     */
    public function canSignIn($user)
    {
        return $user->status != 2 && $user->email_verified == 1;
    }

    /**
     * What the login form tells a member it will not sign in.
     *
     * @param  object  $user  a tiu_member row (stdClass or TiuMember)
     * @return string
     */
    public function blockedMessage($user)
    {
        return $user->status == 2
            ? 'Account is currently disabled. Please contact Admin'
            : 'Email not yet verified. Contact Admin';
    }

    /**
     * The stored value of $column, without the model's accessors.
     *
     * `TiuMember` decodes `department_name`, `cluster` and `house_fellowship`
     * into arrays; the session holds the JSON string the login form has always
     * put there (and `partials/nav.blade.php` decodes it again).
     *
     * @param  object  $user
     * @param  string  $column
     * @return mixed
     */
    protected function raw($user, $column)
    {
        return $user instanceof Model
            ? $user->getRawOriginal($column)
            : $user->{$column};
    }
}
