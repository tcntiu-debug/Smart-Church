<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    /**
     * Store prayer request
     */
    public function storePrayer(Request $request)
    {
        $request->validate(['message' => 'required|string|max:5000']);

        $userId = $request->user_id;

        DB::table('prayer_suggestions')->insert([
            'tiu_member_id' => $userId,
            'type' => 'prayer',
            'message' => $request->message,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Prayer request submitted!']);
    }

    /**
     * Store suggestion
     */
    public function storeSuggestion(Request $request)
    {
        $request->validate(['message' => 'required|string|max:5000']);

        $userId = $request->user_id;

        DB::table('prayer_suggestions')->insert([
            'tiu_member_id' => $userId,
            'type' => 'suggestion',
            'message' => $request->message,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Suggestion submitted! Thank you!']);
    }
}
