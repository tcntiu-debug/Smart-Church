<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * In-app notification centre (the navbar bell). Rows are written by
 * App\Services\NotificationService (assignment alerts) and
 * App\Services\BirthdayReminderService (birthday digests); the panel is generic
 * enough to show any future notification type.
 *
 * Reading an alert deletes it. The bell's modal (partials/notification-modal) and
 * the page behind it list what still needs attention, so "read" and "gone from
 * the list" are the same act (docs/NOTIFICATIONS.md); `read_at` survives only so
 * the rows an older build marked read stay out of the way.
 */
class NotificationController extends Controller
{
    /**
     * Font Awesome icon and button label per type. The page renders them in Blade
     * and the modal receives them through feed(), so the two surfaces cannot
     * drift apart.
     */
    public const ICONS = [
        NotificationService::TYPE_CALLER_ASSIGNMENT => 'fa-phone',
        NotificationService::TYPE_GUIDE_ASSIGNMENT => 'fa-handshake',
        NotificationService::TYPE_WEEKLY_TASK => 'fa-tasks',
        NotificationService::TYPE_ANNOUNCEMENT => 'fa-bullhorn',
        'birthday_reminder' => 'fa-birthday-cake',
    ];

    public const ACTIONS = [
        NotificationService::TYPE_CALLER_ASSIGNMENT => 'Open call list',
        NotificationService::TYPE_GUIDE_ASSIGNMENT => 'Open my tasks',
        NotificationService::TYPE_WEEKLY_TASK => 'Open my tasks',
        NotificationService::TYPE_ANNOUNCEMENT => 'Open notice board',
        'birthday_reminder' => 'View birthdays',
    ];

    /**
     * The colour family a row's icon is painted in (resources/views/layouts/app.blade.php
     * styles `notif-icon-<tone>`). Keeping it a name of its own means a new type can
     * borrow an existing family without touching the stylesheet.
     */
    public const TONES = [
        NotificationService::TYPE_CALLER_ASSIGNMENT => 'caller',
        NotificationService::TYPE_GUIDE_ASSIGNMENT => 'guide',
        NotificationService::TYPE_WEEKLY_TASK => 'task',
        NotificationService::TYPE_ANNOUNCEMENT => 'announcement',
        'birthday_reminder' => 'birthday',
    ];

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
     * List the signed-in member's unread alerts (the page behind the bell).
     *
     * The rows arrive in the same shape the modal receives (present()), so icon,
     * wording and timestamps have one definition and the two surfaces cannot drift
     * apart.
     */
    public function index()
    {
        $memberId = (int) Auth::id();

        $notifications = $this->notifications->unreadFor($memberId, 100)->map(function ($notification) {
            return $this->present($notification);
        })->values();

        return view('notifications.index', [
            'notifications' => $notifications,
            'unread' => $this->notifications->unreadCount($memberId),
        ]);
    }

    /**
     * The unread alerts as JSON - what the bell's modal renders, and what the
     * layout polls (resources/views/layouts/app.blade.php) so an open page on a
     * phone or a laptop turns a new caller / guide alert into a browser
     * notification.
     *
     * `limit` lets the modal ask for more rows than the poll needs (capped at 50,
     * and never below 1).
     */
    public function feed(Request $request)
    {
        $memberId = (int) Auth::id();
        $requested = $request->query('limit');
        $limit = is_numeric($requested) ? (int) $requested : (int) config('notifications.feed_limit', 10);

        $notifications = $this->notifications->unreadFor($memberId, max(1, min(50, $limit)));

        return response()->json([
            'unread' => $this->notifications->unreadCount($memberId),
            'notifications' => $notifications->map(function ($notification) {
                return $this->present($notification);
            })->values(),
        ]);
    }

    /**
     * Mark one alert as read - which clears it - without taking the member off the
     * page they are on.
     *
     * The bell's modal posts here with an XHR and gets JSON back, so the row can
     * slide out and the badge can drop without a page load; the plain page posts a
     * form and is redirected back with a flash message.
     */
    public function markRead(Request $request, $id)
    {
        if (! $this->tableReady()) {
            return $this->cleared($request, 0, 'Notifications are not set up on this server yet.');
        }

        $deleted = (int) DB::table('app_notifications')
            ->where('id', (int) $id)
            ->where('tiu_member_id', Auth::id())
            ->whereNull('read_at')
            ->delete();

        return $this->cleared(
            $request,
            $deleted,
            $deleted > 0 ? 'Notification cleared.' : 'That notification was already cleared.'
        );
    }

    /**
     * Mark every unread alert as read: the list the member is looking at empties.
     */
    public function markAllRead(Request $request)
    {
        if (! $this->tableReady()) {
            return $this->cleared($request, 0, 'Notifications are not set up on this server yet.');
        }

        $deleted = (int) DB::table('app_notifications')
            ->where('tiu_member_id', Auth::id())
            ->whereNull('read_at')
            ->delete();

        return $this->cleared($request, $deleted, $deleted.' notification(s) cleared.');
    }

    /**
     * One row as the browser renders it: everything the modal shows, so the JS
     * never has to know a type's icon, wording or timestamp format.
     */
    protected function present($notification): array
    {
        $type = (string) ($notification->type ?? '');
        $createdAt = $notification->created_at ?? null;
        $at = $createdAt ? Carbon::parse($createdAt) : null;

        return [
            'id' => (int) $notification->id,
            'type' => $type,
            'title' => (string) $notification->title,
            'body' => $notification->body,
            'url' => $this->notifications->urlFor($notification),
            'created_at' => (string) $createdAt,
            'created_at_exact' => $at ? $at->format('D, d M Y g:i A') : '',
            'ago' => $at ? $at->diffForHumans() : '',
            'icon' => self::ICONS[$type] ?? 'fa-bell',
            'action_label' => self::ACTIONS[$type] ?? 'Open',
            'tone' => self::TONES[$type] ?? 'caller',
        ];
    }

    /**
     * The shared answer for both callers: JSON for the modal, a redirect with a
     * flash message for a plain form post.
     *
     * @param  int  $deleted  How many rows were cleared
     */
    protected function cleared(Request $request, int $deleted, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'deleted' => $deleted,
                'unread' => $this->notifications->unreadCount((int) Auth::id()),
                'message' => $message,
            ]);
        }

        return redirect()->back()->with($deleted > 0 ? 'success' : 'error', $message);
    }

    /**
     * A web request must not run a migration (see NotificationService), so a
     * missing table is reported instead of thrown.
     */
    protected function tableReady(): bool
    {
        return Schema::hasTable('app_notifications');
    }
}
