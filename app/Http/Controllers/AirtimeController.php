<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
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
        
        date_default_timezone_set('Africa/Lagos');
        
        $current_hour = (int) date('H');
        $current_time = date('h:i A');
        
        // Restrict between 7AM and 6PM (07:00 - 18:00)
        if (! $this->isWithinRequestWindow($current_hour)) {
            return response()->json([
                'success' => false,
                'message' => 'Airtime requests are only allowed between ' . self::WINDOW_LABEL . ". Current time: {$current_time}"
            ]);
        }
        
        $current_year = (int) date('Y');
        $current_week = (int) date('W');
        
        // Check if already requested this week
        $exists = DB::table('airtime_history')
            ->where('user_id', $userId)
            ->where('year', $current_year)
            ->where('week', $current_week)
            ->exists();
        
        if ($exists) {
            return $this->alreadyRequestedResponse();
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
            
            $this->sendTelegramNotification($userName, $userPhone, $userId);
            
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
                Log::info('Airtime request blocked by unique_user_week', [
                    'user_id' => $userId,
                    'year' => $current_year,
                    'week' => $current_week,
                ]);

                return $this->alreadyRequestedResponse();
            }

            Log::error('Airtime request error: ' . $e->getMessage(), ['user_id' => $userId]);

            return response()->json([
                'success' => false,
                'message' => 'We could not submit your airtime request right now. Please try again in a moment.'
            ]);
        }
    }

    /**
     * The member already has a row for this year and ISO week.
     */
    private function alreadyRequestedResponse()
    {
        return response()->json([
            'success' => false,
            'message' => 'You have already requested airtime this week. Only one request per week is allowed.'
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
     * MySQL reports a duplicate key as SQLSTATE 23000 / driver error 1062 with
     * the index in the message; SQLite (throwaway databases) only says
     * "UNIQUE constraint failed".
     */
    private function isDuplicateWeek(QueryException $e)
    {
        $message = $e->getMessage();

        return strpos($message, 'unique_user_week') !== false
            || strpos($message, 'Duplicate entry') !== false
            || strpos($message, 'UNIQUE constraint failed') !== false;
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
        
        date_default_timezone_set('Africa/Lagos');
        $current_hour = (int) date('H');
        $is_time_allowed = $this->isWithinRequestWindow($current_hour);
        
        $current_year = (int) date('Y');
        $current_week = (int) date('W');
        
        $has_requested = DB::table('airtime_history')
            ->where('user_id', $userId)
            ->where('year', $current_year)
            ->where('week', $current_week)
            ->exists();
        
        return response()->json([
            'success' => true,
            'data' => [
                'can_request' => ($is_time_allowed && !$has_requested),
                'time_allowed' => $is_time_allowed,
                'has_requested_this_week' => $has_requested,
                'next_available_time' => self::WINDOW_OPENS_AT,
                'current_time' => date('h:i A')
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
    
    private function sendTelegramNotification($userName, $userPhone, $userId)
    {
        $botToken = env('TELEGRAM_BOT_TOKEN', '');
        $chatId = env('TELEGRAM_CHAT_ID', '');
        
        if (empty($botToken) || empty($chatId)) {
            Log::info('Telegram not configured, skipping notification');
            return;
        }
        
        $message = "🪙 *NEW AIRTIME REQUEST*\n\n";
        $message .= "👤 *User:* {$userName}\n";
        $message .= "🆔 *User ID:* {$userId}\n";
        $message .= "📱 *Phone:* {$userPhone}\n";
        $message .= "📅 *Date:* " . now()->format('Y-m-d H:i:s') . "\n";
        $message .= "💰 *Amount:* ₦200\n";
        $message .= "⏰ *Time:* " . date('h:i A') . "\n\n";
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
                Log::error('Telegram API error: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Telegram notification failed: ' . $e->getMessage());
        }
    }
}
