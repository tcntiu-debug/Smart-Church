<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class VerifyApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json(['success' => false, 'message' => 'Unauthorized - No token'], 401);
        }
        
        $decoded = base64_decode($token);
        $parts = explode('|', $decoded);
        $userId = $parts[0] ?? null;
        
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Invalid token format'], 401);
        }
        
        // Verify user exists
        $user = DB::selectOne("SELECT tiu_member_id FROM tiu_member WHERE tiu_member_id = ?", [$userId]);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 401);
        }
        
        // Attach user ID to request
        $request->merge(['user_id' => $userId]);
        $request->attributes->set('user_id', $userId);
        
        // Also manually login the user for Auth::user()
        Auth::loginUsingId($userId);
        
        return $next($request);
    }
}