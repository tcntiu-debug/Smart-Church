<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AirtimeController extends Controller
{
    /**
     * Daily top-up window, WAT (Africa/Lagos): 7AM until 6PM. The end hour is
     * exclusive, so the last request slot is 5:59 PM and 6:00 PM is closed.
     * Used by both the request endpoint and the availability check.
     */
    private const WINDOW_START_HOUR = 7;
    private const WINDOW_END_HOUR = 18;
    private const WINDOW_LABEL = '7AM and 6PM (WAT)';
    private const WINDOW_OPENS_AT = '7:00 AM';

    /**
     * The church's wall clock. `requested_at` keeps the application timezone
     * (UTC) like every other table; only the window and the wording members
     * read are expressed in Lagos time.
     */
    private const TIMEZONE = 'Africa/Lagos';

    public function request(Request $request)
    {
        // Get user_id: first try from request param (API), then fallback to Auth (web)
        $userId = $request->user_id;
        
        if (!$userId && Auth::check()) {
            $user = Auth::user();
            $userId = $user->tiu_member_id ?? $user->id;
        }
        
        if (!$userId) {
            return response()->json([
                'success' => false, 
                'message' => 'User not authenticated'
            ], 401);
        }
        
        // Get user details from database
        $user = DB::selectOne("SELECT first_name, last_name, phone_number, tiu_member_id FROM tiu_member WHERE tiu_member_id = ?", [$userId]);
        
        if (!$user) {
            return response()->json([
                'success' => false, 
                'message' => 'User not found'
            ], 404);
        }
        
        $userName = ($user->first_name ?? '') . ' ' . ($user->last_name ?? '');
        $userPhone = $user->phone_number ?? 'N/A';
        
        $now = $this->nowInChurchTime();

        $current_hour = (int) $now->format('H');
        $current_time = $now->format('h:i A');

        // Restrict between 7AM and 6PM (07:00 - 18:00)
        if (! $this->isWithinRequestWindow($current_hour)) {
            return response()->json([
                'success' => false,
                'message' => 'Airtime requests are only allowed between ' . self::WINDOW_LABEL . ". Current time: {$current_time}"
            ]);
        }

        [$current_year, $current_week] = $this->currentWeek($now);

        // Check if already requested this week. The row that is already on file
        // is quoted back to the member, so a refusal can be checked against the
        // admin list instead of being an unexplained "no".
        $existing = $this->existingRequestForWeek($userId, $current_year, $current_week);

        if ($existing) {
            $this->logRepeatRequest($userId, $existing, 'pre-check');

            return $this->alreadyRequestedResponse($existing);
        }
        
        try {
            DB::table('airtime_history')->insert([
                'user_id' => $userId,
                'user_name' => $userName,
                'user_phone' => $userPhone,
                'year' => $current_year,
                'week' => $current_week,
                'requested_at' => now(),
                'credited' => 0,
                'status' => 'pending'
            ]);
            
            $this->sendTelegramNotification($userName, $userPhone, $userId, $now);
            
            return response()->json([
                'success' => true,
                'message' => 'Request submitted successfully! You will be credited ₦200 within the next few minutes.'
            ]);
            
        } catch (\Exception $e) {
            // The check above cannot stop two requests that arrive together (a
            // double click, a second tab, the app and the browser): both read
            // "no row" and both insert. `unique_user_week` keeps the first row
            // and rejects the second with a duplicate-key error - that is the
            // weekly limit doing its job, so answer it like any other repeat
            // request instead of echoing the SQLSTATE to the member.
            if ($e instanceof QueryException && $this->isDuplicateWeek($e)) {
                $existing = $this->existingRequestForWeek($userId, $current_year, $current_week);

                $this->logRepeatRequest($userId, $existing, 'unique_user_week');

                return $this->alreadyRequestedResponse($existing);
            }

            Log::error('Airtime request error: ' . $e->getMessage(), ['user_id' => $userId]);

            return response()->json([
                'success' => false,
                'message' => 'We could not submit your airtime request right now. Please try again in a moment.'
            ]);
        }
    }

    /**
     * The clock the members and the window run on. `requested_at` is still
     * written with `now()` (the application timezone) so it stays comparable
     * with every other table.
     *
     * @return \Illuminate\Support\Carbon
     */
    private function nowInChurchTime()
    {
        return Carbon::now(self::TIMEZONE);
    }

    /**
     * The (ISO year, ISO week) pair the weekly limit keys on.
     *
     * `o` must be paired with `W`, never `Y`: from 29 December to 3 January the
     * calendar year and the ISO year disagree. With `Y` the week of 29 Dec 2025
     * was filed as 2025-W01 and collided with the first week of January 2025,
     * so members who requested in the first week of the year were told in late
     * December that they had already requested.
     *
     * @param  \Illuminate\Support\Carbon|null  $now
     * @return array{0: int, 1: int}
     */
    private function currentWeek($now = null)
    {
        $now = $now ?: $this->nowInChurchTime();

        return [(int) $now->format('o'), (int) $now->format('W')];
    }

    /**
     * The member's request for the given year and week, if there is one.
     *
     * @param  int|string  $userId
     * @param  int  $year
     * @param  int  $week
     * @return object|null
     */
    private function existingRequestForWeek($userId, $year, $week)
    {
        return DB::table('airtime_history')
            ->where('user_id', $userId)
            ->where('year', $year)
            ->where('week', $week)
            ->first(['id', 'year', 'week', 'requested_at', 'status']);
    }

    /**
     * Leave a trail for support: who was refused, and which row refused them.
     *
     * @param  int|string  $userId
     * @param  object|null  $existing
     * @param  string  $path  pre-check or unique_user_week
     * @return void
     */
    private function logRepeatRequest($userId, $existing, $path)
    {
        Log::info('Airtime request blocked: this member already has a request for the week', [
            'user_id' => $userId,
            'checked_via' => $path,
            'row_id' => $existing->id ?? null,
            'year' => $existing->year ?? null,
            'week' => $existing->week ?? null,
            'requested_at' => $existing->requested_at ?? null,
            'status' => $existing->status ?? null,
        ]);
    }

    /**
     * The member already has a row for this year and ISO week.
     *
     * The date of the request that is on file is part of the message: a member
     * who says they never asked can compare it with the admin list, and a double
     * click reads as "that one request is already in" instead of a bare failure.
     *
     * @param  object|null  $existing
     * @return \Illuminate\Http\JsonResponse
     */
    private function alreadyRequestedResponse($existing = null)
    {
        $when = '';

        if ($existing && ! empty($existing->requested_at)) {
            $when = ' on ' . Carbon::parse($existing->requested_at, config('app.timezone', 'UTC'))
                ->timezone(self::TIMEZONE)
                ->format('D, j M Y \a\t g:i A');
        }

        return response()->json([
            'success' => false,
            'already_requested' => true,
            'requested_at' => $existing->requested_at ?? null,
            'message' => 'You already requested airtime this week' . $when
                . '. Only one request per week is allowed.',
        ]);
    }

    /**
     * Whether a wall-clock hour (WAT) falls inside the daily top-up window.
     *
     * Kept in one place so the request endpoint and `/airtime-check` can never
     * advertise a different window from the one that actually accepts requests.
     *
     * @param  int  $hour  24-hour clock, e.g. 7 for 7AM and 17 for 5PM
     * @return bool
     */
    private function isWithinRequestWindow($hour)
    {
        return $hour >= self::WINDOW_START_HOUR && $hour < self::WINDOW_END_HOUR;
    }

    /**
     * Only a clash on `unique_user_week` is a repeat request.
     *
     * MySQL reports a duplicate key as SQLSTATE 23000 / driver error 1062 and
     * names the index (some servers name the `(user, year, week)` tuple instead);
     * SQLite (throwaway databases) names the columns. A duplicate on any other
     * key is a real fault: it has to be logged and must never be reworded into
     * "you already asked this week", which is what a blanket "Duplicate entry"
     * match did once the table carried a second unique index.
     */
    private function isDuplicateWeek(QueryException $e)
    {
        $message = $e->getMessage();

        return strpos($message, 'unique_user_week') !== false
            || preg_match("/Duplicate entry '\d+-\d+-\d+'/", $message) === 1
            || strpos($message, 'UNIQUE constraint failed: airtime_history.user_id') !== false;
    }
    
    /**
     * Web route for admin airtime page
     */
    public function admin()
    {
        return redirect('/aoverview')->with('info', 'Airtime admin page - coming soon');
    }
    
    public function checkAvailability(Request $request)
    {
        $userId = $request->user_id;
        
        if (!$userId) {
            return response()->json([
                'success' => false, 
                'message' => 'User not authenticated'
            ], 401);
        }
        
        $now = $this->nowInChurchTime();
        $is_time_allowed = $this->isWithinRequestWindow((int) $now->format('H'));
        
        [$current_year, $current_week] = $this->currentWeek($now);
        
        $existing = $this->existingRequestForWeek($userId, $current_year, $current_week);
        
        return response()->json([
            'success' => true,
            'data' => [
                'can_request' => ($is_time_allowed && ! $existing),
                'time_allowed' => $is_time_allowed,
                'has_requested_this_week' => (bool) $existing,
                'requested_at' => $existing->requested_at ?? null,
                'next_available_time' => self::WINDOW_OPENS_AT,
                'current_time' => $now->format('h:i A'),
                // The week the server is enforcing, so a support call can be
                // answered without guessing.
                'week' => $current_year . '-W' . str_pad((string) $current_week, 2, '0', STR_PAD_LEFT),
            ]
        ]);
    }
    
    public function history(Request $request)
    {
        $userId = $request->user_id;
        
        if (!$userId) {
            return response()->json([
                'success' => false, 
                'message' => 'User not authenticated'
            ], 401);
        }
        
        $history = DB::table('airtime_history')
            ->where('user_id', $userId)
            ->orderBy('requested_at', 'desc')
            ->paginate(20);
        
        return response()->json([
            'success' => true,
            'data' => $history
        ]);
    }
    
    /**
     * Alert the admin group about a new request.
     *
     * The credentials are read through `config('services.telegram.*')` on
     * purpose. Production runs `config:cache`, and a cached config means .env is
     * never loaded: reading TELEGRAM_* from the environment here returned null
     * on the live site, so every alert was skipped and logged as a quiet
     * "not configured" info line while the request itself still went through.
     *
     * @param  string  $userName
     * @param  string  $userPhone
     * @param  int|string  $userId
     * @param  \Illuminate\Support\Carbon|null  $now
     * @return void
     */
    private function sendTelegramNotification($userName, $userPhone, $userId, $now = null)
    {
        $botToken = trim((string) config('services.telegram.bot_token', ''));
        $chatId = trim((string) config('services.telegram.chat_id', ''));

        if ($botToken === '' || $chatId === '') {
            Log::warning('Airtime request saved, but the Telegram alert was skipped: services.telegram is not configured.', [
                'user_id' => $userId,
                'bot_token_configured' => $botToken !== '',
                'chat_id_configured' => $chatId !== '',
            ]);

            return;
        }

        $now = $now ?: $this->nowInChurchTime();

        $message = "🪙 *NEW AIRTIME REQUEST*\n\n";
        $message .= "👤 *User:* {$userName}\n";
        $message .= "🆔 *User ID:* {$userId}\n";
        $message .= "📱 *Phone:* {$userPhone}\n";
        $message .= "📅 *Date:* " . $now->format('Y-m-d H:i:s') . "\n";
        $message .= "💰 *Amount:* ₦200\n";
        $message .= "⏰ *Time:* " . $now->format('h:i A') . " (WAT)\n\n";
        $message .= "_Pending approval_";

        try {
            $response = Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown'
            ]);

            if ($response->successful()) {
                Log::info('Telegram notification sent successfully for user: ' . $userId);
            } else {
                Log::error('Telegram API error for airtime request', [
                    'user_id' => $userId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Telegram notification failed: ' . $e->getMessage(), ['user_id' => $userId]);
        }
    }
}
