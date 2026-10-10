<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * In-app notification centre (the navbar bell). Rows are written by
 * App\Services\BirthdayReminderService but the page is generic enough to show
 * any future notification type.
 */
class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * List the signed-in member's latest notifications.
     */
    public function index()
    {
        $memberId = Auth::id();

        $notifications = DB::table('app_notifications')
            ->where('tiu_member_id', $memberId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $unread = DB::table('app_notifications')
            ->where('tiu_member_id', $memberId)
            ->whereNull('read_at')
            ->count();

        return view('notifications.index', compact('notifications', 'unread'));
    }

    /**
     * Mark one notification as read, then return to the list.
     */
    public function markRead($id)
    {
        DB::table('app_notifications')
            ->where('id', $id)
            ->where('tiu_member_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return redirect()->route('notifications.index')->with('success', 'Notification marked as read.');
    }

    /**
     * Mark every unread notification as read.
     */
    public function markAllRead()
    {
        $updated = DB::table('app_notifications')
            ->where('tiu_member_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return redirect()->route('notifications.index')
            ->with('success', $updated.' notification(s) marked as read.');
    }
}
