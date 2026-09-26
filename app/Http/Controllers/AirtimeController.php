<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AirtimeController extends Controller
{
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
        
        // Restrict between 1PM and 5PM (13:00 - 17:00)
        if ($current_hour < 13 || $current_hour >= 17) {
            return response()->json([
                'success' => false,
                'message' => "Airtime requests are only allowed between 1PM and 5PM (WAT). Current time: {$current_time}"
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
            return response()->json([
                'success' => false,
                'message' => 'You have already requested airtime this week. Only one request per week is allowed.'
            ]);
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
            Log::error('Airtime request error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ]);
        }
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
        $is_time_allowed = ($current_hour >= 13 && $current_hour < 17);
        
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
                'next_available_time' => '1:00 PM',
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
