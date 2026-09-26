<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\PrayerSuggestion;

class HomeController extends Controller
{
    /**
     * Show the dashboard (home) page for any authenticated user.
     * This serves as the landing page after login, irrespective of role.
     */
    public function index()
    {
        $user = Auth::user();

        // Determine greeting
        $hour = now()->hour;
        if ($hour < 12) {
            $greeting = 'Good Morning';
        } elseif ($hour < 17) {
            $greeting = 'Good Afternoon';
        } else {
            $greeting = 'Good Evening';
        }

        // Retrieve last login time
        $lastLogin = DB::table('tiu_member_login')
            ->where('tiu_member_id', $user->tiu_member_id)
            ->orderBy('date_logged_in', 'desc')
            ->first();

        // Get today's date for attendance
        $todayAttendance = DB::table('church_attendance')
            ->where('member_id', $user->tiu_member_id)
            ->whereDate('attendance_date', today())
            ->exists();

        return view('home', compact(
            'user',
            'greeting',
            'lastLogin',
            'todayAttendance'
        ));
    }

    /**
     * Store a prayer request.
     */
    public function storePrayer(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        try {
            PrayerSuggestion::create([
                'tiu_member_id' => Auth::id(),
                'type'          => 'prayer',
                'message'       => $request->message,
                'status'        => 'pending',
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Your prayer request has been submitted!']);
            }

            return redirect()->route('home')->with('success', 'Your prayer request has been submitted!');
        } catch (\Exception $e) {
            Log::error('Prayer submission error: ' . $e->getMessage());

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);
            }

            return redirect()->route('home')->with('error', 'An error occurred. Please try again.');
        }
    }

    /**
     * Store a suggestion.
     */
    public function storeSuggestion(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        try {
            PrayerSuggestion::create([
                'tiu_member_id' => Auth::id(),
                'type'          => 'suggestion',
                'message'       => $request->message,
                'status'        => 'pending',
            ]);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Thank you for your suggestion!']);
            }

            return redirect()->route('home')->with('success', 'Thank you for your suggestion!');
        } catch (\Exception $e) {
            Log::error('Suggestion submission error: ' . $e->getMessage());

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);
            }

            return redirect()->route('home')->with('error', 'An error occurred. Please try again.');
        }
    }

    /**
     * Mark church attendance for today.
     */
    public function markAttendance(Request $request)
    {
        $user = Auth::user();

        // Check if already marked
        $alreadyMarked = DB::table('church_attendance')
            ->where('member_id', $user->tiu_member_id)
            ->whereDate('attendance_date', today())
            ->exists();

        if ($alreadyMarked) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'You have already registered your attendance today.']);
            }
            return redirect()->route('home')->with('error', 'You have already registered your attendance today.');
        }

        DB::table('church_attendance')->insert([
            'member_id'       => $user->tiu_member_id,
            'member_type'     => 'tiu_member',
            'full_name'       => $user->first_name . ' ' . $user->last_name,
            'church_type_id'  => $user->church_type_id ?? null,
            'attendance_date' => today(),
            'date_created'    => now(),
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Your attendance has been registered!']);
        }

        return redirect()->route('home')->with('success', 'Your attendance has been registered!');
    }

    /**
     * Admin view for listing all prayer requests and suggestions with filters.
     */
    public function adminList(Request $request)
    {
        $query = PrayerSuggestion::with('member');

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $items = $query->orderBy('created_at', 'desc')->paginate(20);

        // Preserve filter query strings in pagination
        $items->appends($request->only(['type', 'status', 'date_from', 'date_to']));

        return view('home-admin-list', compact('items'));
    }

    /**
     * Update status of a prayer/suggestion item (admin).
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,reviewed,approved',
        ]);

        $item = PrayerSuggestion::findOrFail($id);
        $item->status = $request->status;
        $item->save();

        return redirect()->route('home.admin-list')->with('success', 'Status updated successfully.');
    }
}
