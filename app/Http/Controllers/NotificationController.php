<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * In-app notification centre (the navbar bell). Rows are written by
 * App\Services\NotificationService (assignment alerts) and
 * App\Services\BirthdayReminderService (birthday digests); the page is generic
 * enough to show any future notification type.
 */
class NotificationController extends Controller
{
    /**
     * @var \App\Services\NotificationService
     */
    protected $notifications;

    public function __construct(NotificationService $notifications)
    {
        $this->notifications = $notifications;

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
     * The unread notifications as JSON.
     *
     * Polled by the layout (resources/views/layouts/app.blade.php) so an open page
     * on a phone or a laptop turns a new caller / guide alert into a browser
     * notification. Read-only: the rows are only marked read from the centre.
     */
    public function feed()
    {
        $memberId = (int) Auth::id();
        $limit = (int) config('notifications.feed_limit', 10);

        $notifications = $this->notifications->unreadFor($memberId, $limit);

        return response()->json([
            'unread' => $this->notifications->unreadCount($memberId),
            'notifications' => $notifications->map(function ($notification) {
                return [
                    'id' => (int) $notification->id,
                    'type' => (string) $notification->type,
                    'title' => (string) $notification->title,
                    'body' => $notification->body,
                    'url' => $this->notifications->urlFor($notification),
                    'created_at' => (string) $notification->created_at,
                ];
            })->values(),
        ]);
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
