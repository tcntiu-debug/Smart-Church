<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminAirtimeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * ====================================================================
     * /airtime-admin — Credit History (Airtime Admin)
     * ====================================================================
     * Original: tiu/airtime_admin.php
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $memberRole = $user->member_role ?? '';

        if (!in_array($memberRole, ['Super User', 'Admin'])) {
            return redirect('/mytask')->with('error', 'Access Denied');
        }

        // Handle credit action
        if ($request->has('credit_id') && is_numeric($request->credit_id)) {
            $creditId = (int)$request->credit_id;
            DB::table('airtime_history')
                ->where('id', $creditId)
                ->where('credited', 0)
                ->update([
                    'credited' => 1,
                    'credited_at' => now(),
                ]);
            return redirect()->route('admin.airtime')->with('success', 'Airtime marked as credited.');
        }

        $requests = DB::table('airtime_history')
            ->orderBy('requested_at', 'desc')
            ->get();

        return view('admin.airtime', compact('requests'));
    }
}
