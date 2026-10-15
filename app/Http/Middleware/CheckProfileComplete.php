<?php

namespace App\Http\Middleware;

use App\Models\Campus;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CheckProfileComplete
{
    /**
     * Handle an incoming request.
     * If the authenticated user's profile is missing required fields,
     * redirect them to the profile page with an error message.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Skip check for the profile pages to avoid redirect loops
            if ($request->routeIs('profile.index') || $request->routeIs('profile.update')) {
                return $next($request);
            }

            // Check if required fields are missing
            $missing = [];

            if (empty($user->marital_status)) {
                $missing[] = 'Marital Status';
            }
            if (empty($user->age)) {
                $missing[] = 'Age';
            }
            if (empty($user->occupation)) {
                $missing[] = 'Occupation';
            }
            if (empty($user->next_of_kin_name) || empty($user->next_of_kin_phone)) {
                $missing[] = 'Next of Kin';
            }
            // The campus is only a profile requirement while the church runs more
            // than one (config/campus.php): in single campus mode it is stamped on
            // writes, so it must not block anybody.
            if (empty($user->campus_id) && ! Campus::isSingle()) {
                $missing[] = 'Campus';
            }

            // Check birthday in birthday table
            $birthday = DB::table('birthday')->where('tiu_member_id', $user->tiu_member_id ?? $user->id)->first();
            if (!$birthday || empty($birthday->birthday)) {
                $missing[] = 'Birthday';
            }

            if (!empty($missing)) {
                $missingList = implode(', ', $missing);
                return redirect()->route('profile.index', ['msg' => 'Please complete your profile (' . $missingList . ')']);
            }
        }

        return $next($request);
    }
}
