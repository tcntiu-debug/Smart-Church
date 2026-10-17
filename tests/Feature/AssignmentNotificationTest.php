<?php

namespace Tests\Feature;

use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The alerts an assignment produces (items 5(b) and 11).
 *
 * Item 5(b): when the week list hands first timers to the callers, every caller
 * who gained somebody gets an in-app notification (and, once the member allows it
 * on that browser, a system notification through
 * public/assets/js/notifications.js).
 *
 * Item 11: the same happens for a guide - `/admin-assign-dragdrop`, the list form,
 * "Generate pairings" and the "Assign all callers" button inside the Weekly Call
 * List modal all notify the guide about the first timer(s) they now follow up, and
 * the bell links them to /my-tasks.
 *
 * Deliberately no RefreshDatabase: the suite runs against the application's real
 * connection. Every test writes inside a transaction opened in setUp() and rolled
 * back in tearDown(), so no notification survives the run.
 */
class AssignmentNotificationTest extends TestCase
{
    private const CALLER_SUBGROUP = 'Touch Point 7';

    /**
     * A day in 2000, so the selected window holds the rows a test creates and
     * none of the real first timers.
     */
    private const WINDOW = ['start_date' => '2000-06-04', 'end_date' => '2000-06-04'];

    private const REGISTERED_AT = '2000-06-04 10:00:00';

    /** @var \App\Services\NotificationService */
    private $notifications;

    /**
     * The name of the last first timer createFirstTimer() made, so an assertion
     * can pick this test's alert out of the member's real notifications.
     */
    private $lastCreatedName = '';

    protected function setUp(): void
    {
        parent::setUp();

        DB::beginTransaction();

        $this->notifications = app(NotificationService::class);
    }

    protected function tearDown(): void
    {
        DB::rollBack();

        parent::tearDown();
    }

    /**
     * Members carrying every field CheckProfileComplete demands, otherwise the
     * request is redirected to the profile page before it reaches the controller.
     *
     * The campus is demanded only while the church runs more than one: in single
     * campus mode CheckProfileComplete stopped asking for it and the campus_id
     * data was retired (docs/SINGLE-CAMPUS.md), so requiring it here would find
     * nobody and skip the test instead of testing the page.
     */
    private function memberQuery()
    {
        $members = \App\Models\TiuMember::query()
            ->whereNotNull('marital_status')
            ->whereNotNull('age')
            ->whereNotNull('occupation')
            ->whereNotNull('next_of_kin_name')
            ->whereNotNull('next_of_kin_phone');

        if (! \App\Models\Campus::isSingle()) {
            $members->whereNotNull('campus_id');
        }

        return $members->whereExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('birthday')
                ->whereColumn('birthday.tiu_member_id', 'tiu_member.tiu_member_id')
                ->whereNotNull('birthday.birthday');
        });
    }

    /**
     * A Super User first, so the campus filter cannot hide the test's own rows.
     */
    private function admin()
    {
        $admin = $this->memberQuery()->where('member_role', 'Super User')->first()
            ?: $this->memberQuery()->whereIn('member_role', ['Admin', 'Admins'])->first();

        if (! $admin) {
            $this->markTestSkipped('No Admin/Super User with a complete profile available.');
        }

        return $admin;
    }

    /**
     * Any member at all: these tests only need somebody to receive a row.
     */
    private function aMemberId(): int
    {
        $memberId = DB::table('tiu_member')->orderBy('tiu_member_id')->value('tiu_member_id');

        if (! $memberId) {
            $this->markTestSkipped('No tiu_member row to receive a notification.');
        }

        return (int) $memberId;
    }

    private function callers()
    {
        return DB::table('tiu_member')
            ->where('subgroup', 'like', '%' . self::CALLER_SUBGROUP . '%')
            ->orderBy('first_name')
            ->get(['tiu_member_id', 'first_name', 'last_name', 'gender']);
    }

    /**
     * The caller the round-robin must pick, and the gender it matches on: the
     * test copies a real caller's lane instead of guessing at the mapping.
     *
     * @return array{0: int, 1: string}
     */
    private function callerLane(): array
    {
        $callers = $this->callers();

        if ($callers->isEmpty()) {
            $this->markTestSkipped('No members in the "' . self::CALLER_SUBGROUP . '" subgroup available.');
        }

        foreach ($callers as $caller) {
            $gender = strtolower(trim((string) $caller->gender));

            if (in_array($gender, ['male', 'm', 'female', 'f'], true)) {
                return [(int) $caller->tiu_member_id, (string) $caller->gender];
            }
        }

        $this->markTestSkipped('The caller pool has no recognisable gender lane.');
    }

    /**
     * A first timer with every NOT NULL column filled in, inside the test window.
     */
    private function createFirstTimer(string $gender, $campusId): int
    {
        $unique = (string) random_int(100000, 999999);

        $this->lastCreatedName = 'Notify Testcase' . $unique;

        return (int) DB::table('first_timer')->insertGetId([
            'first_name'       => 'Notify',
            'last_name'        => 'Testcase' . $unique,
            'phone_number'     => '000' . $unique,
            'email'            => 'notify' . $unique . '@example.test',
            'gender'           => $gender,
            'age'              => '25-34',
            'occupation'       => 'Tester',
            'attendant_type'   => 'Visiting',
            'attendance_count' => 1,
            'status'           => 'Unassigned',
            'register_date'    => self::REGISTERED_AT,
            'address'          => 'Test address',
            'campus_id'        => $campusId ?: 1,
        ]);
    }

    /**
     * The notification rows a member received, newest first.
     */
    private function rowsFor(int $memberId, string $type = null)
    {
        $query = DB::table('app_notifications')->where('tiu_member_id', $memberId);

        if ($type !== null) {
            $query->where('type', $type);
        }

        return $query->orderByDesc('id')->get();
    }

    /**
     * The row the bell and the browser alert both render.
     */
    public function test_a_caller_alert_is_stored_with_the_link_back_to_the_call_list()
    {
        $callerId = $this->aMemberId();

        $written = $this->notifications->notify(
            $callerId,
            NotificationService::TYPE_CALLER_ASSIGNMENT,
            'You have 2 new first timers to call',
            'Notify One, Notify Two',
            ['url' => '/weeklist'],
            1
        );

        $this->assertTrue($written, 'The alert was not stored.');

        $row = $this->rowsFor($callerId, NotificationService::TYPE_CALLER_ASSIGNMENT)->first();

        $this->assertNotNull($row);
        $this->assertSame('You have 2 new first timers to call', $row->title);
        $this->assertSame('Notify One, Notify Two', $row->body);
        $this->assertNull($row->read_at, 'A fresh alert must start unread.');
        $this->assertSame('/weeklist', $this->notifications->payload($row)['url'] ?? null);

        // What the page and the browser alert both use as the click target.
        $this->assertSame(url('/weeklist'), $this->notifications->urlFor($row));

        // And the unread badge counts it.
        $this->assertGreaterThanOrEqual(1, $this->notifications->unreadCount($callerId));
        $this->assertTrue(
            $this->notifications->unreadFor($callerId, 10)->contains(function ($notification) use ($row) {
                return (int) $notification->id === (int) $row->id;
            }),
            'The alert is missing from the unread feed.'
        );
    }

    /**
     * What public/assets/js/notifications.js polls.
     */
    public function test_the_feed_endpoint_returns_the_members_own_unread_alerts()
    {
        $reader = $this->memberQuery()->first();

        if (! $reader) {
            $this->markTestSkipped('No member with a complete profile available.');
        }

        $readerId = (int) $reader->tiu_member_id;
        $otherId = (int) DB::table('tiu_member')
            ->where('tiu_member_id', '!=', $readerId)
            ->orderBy('tiu_member_id')
            ->value('tiu_member_id');

        $this->notifications->notify($readerId, NotificationService::TYPE_GUIDE_ASSIGNMENT, 'Mine to follow up', 'Notify Reader');
        $this->notifications->notify($otherId, NotificationService::TYPE_GUIDE_ASSIGNMENT, 'Somebody elses', 'Notify Other');

        $response = $this->actingAs($reader)->getJson(route('notifications.feed'));

        $response->assertOk()->assertJsonStructure([
            'unread',
            'notifications' => [['id', 'type', 'title', 'body', 'url', 'created_at']],
        ]);

        $alerts = collect($response->json('notifications'));

        $this->assertTrue($alerts->contains('title', 'Mine to follow up'), 'The member\'s own alert is missing from the feed.');
        $this->assertFalse($alerts->contains('title', 'Somebody elses'), 'The feed leaked another member\'s alert.');
        $this->assertGreaterThanOrEqual(1, $response->json('unread'));

        $this->assertSame(url('/my-tasks'), $alerts->firstWhere('title', 'Mine to follow up')['url']);
    }

    /**
     * tiu_member_id = 0 is how a released guide is stored, and an empty title has
     * nothing to show: neither may create a row.
     */
    public function test_nobody_and_a_blank_title_are_never_stored()
    {
        $memberId = $this->aMemberId();

        $this->assertFalse($this->notifications->notify(0, NotificationService::TYPE_WEEKLY_TASK, 'For nobody'));
        $this->assertFalse($this->notifications->notify($memberId, NotificationService::TYPE_WEEKLY_TASK, '   '));

        $this->assertSame(0, $this->rowsFor($memberId, NotificationService::TYPE_WEEKLY_TASK)->count());
        $this->assertSame(0, DB::table('app_notifications')->where('title', 'For nobody')->count());
    }

    /**
     * config/notifications.php is the off switch: a single type can be dropped, or
     * the whole feature silenced, without touching a controller.
     */
    public function test_the_config_switches_silence_a_type_and_the_whole_feature()
    {
        $memberId = $this->aMemberId();

        config(['notifications.types' => [NotificationService::TYPE_GUIDE_ASSIGNMENT]]);

        $this->assertFalse($this->notifications->notify($memberId, NotificationService::TYPE_CALLER_ASSIGNMENT, 'Silenced type'));
        $this->assertTrue($this->notifications->notify($memberId, NotificationService::TYPE_GUIDE_ASSIGNMENT, 'Still allowed'));

        config(['notifications.enabled' => false]);

        $this->assertFalse($this->notifications->notify($memberId, NotificationService::TYPE_GUIDE_ASSIGNMENT, 'Master switch off'));

        $titles = $this->rowsFor($memberId)->pluck('title')->all();

        $this->assertContains('Still allowed', $titles);
        $this->assertNotContains('Silenced type', $titles);
        $this->assertNotContains('Master switch off', $titles);
    }

    /**
     * One alert per member, whatever the caller passes in.
     */
    public function test_a_batch_writes_one_alert_per_member_and_drops_duplicates()
    {
        $members = DB::table('tiu_member')
            ->orderBy('tiu_member_id')
            ->limit(2)
            ->pluck('tiu_member_id')
            ->map('intval')
            ->all();

        if (count($members) < 2) {
            $this->markTestSkipped('Needs two tiu_member rows.');
        }

        $written = $this->notifications->notifyMany(
            [$members[0], $members[0], (string) $members[1], 0],
            NotificationService::TYPE_CALLER_ASSIGNMENT,
            'You have 1 new first timer to call',
            'Notify Testcase',
            ['url' => '/weeklist']
        );

        $this->assertSame(2, $written);

        // The two members are real callers, so the bell may already hold alerts
        // this test must not count: only the rows written just now (the body is
        // this test's marker) are asserted on.
        $this->assertSame(
            1,
            $this->rowsFor($members[0], NotificationService::TYPE_CALLER_ASSIGNMENT)->where('body', 'Notify Testcase')->count()
        );
        $this->assertSame(
            1,
            $this->rowsFor($members[1], NotificationService::TYPE_CALLER_ASSIGNMENT)->where('body', 'Notify Testcase')->count()
        );
    }

    /**
     * Where the bell sends each type when the writer stored no url of its own.
     */
    public function test_each_type_points_at_the_page_that_needs_acting_on()
    {
        $memberId = $this->aMemberId();

        $this->notifications->notify($memberId, NotificationService::TYPE_GUIDE_ASSIGNMENT, 'Guide alert');
        $this->notifications->notify($memberId, 'birthday_reminder', 'Birthday alert');

        $rows = $this->rowsFor($memberId);

        $this->assertSame(url('/my-tasks'), $this->notifications->urlFor($rows->firstWhere('title', 'Guide alert')));
        $this->assertSame(route('notifications.index'), $this->notifications->urlFor($rows->firstWhere('title', 'Birthday alert')));
    }

    public function test_the_names_helper_summarises_a_long_list()
    {
        $this->assertSame('', $this->notifications->names([]));
        $this->assertSame('Ada', $this->notifications->names(['Ada', '  ', '']));
        $this->assertSame('Ada, Bola', $this->notifications->names(['Ada', ' Bola ']));
        $this->assertSame('Ada, Bola and 1 other', $this->notifications->names(['Ada', 'Bola', 'Chidi']));
        $this->assertSame('Ada, Bola and 3 others', $this->notifications->names(['Ada', 'Bola', 'Chidi', 'Debo', 'Eze'], 2));
    }

    /**
     * The centre itself: the alert, its action link, the one-time permission button
     * and the poller the layout loads.
     */
    public function test_the_notification_centre_offers_the_browser_alert_button()
    {
        $reader = $this->memberQuery()->first();

        if (! $reader) {
            $this->markTestSkipped('No member with a complete profile available.');
        }

        $this->notifications->notify(
            (int) $reader->tiu_member_id,
            NotificationService::TYPE_CALLER_ASSIGNMENT,
            'You have 1 new first timer to call',
            'Notify Testcase'
        );

        $this->actingAs($reader)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Enable browser alerts')
            ->assertSee('Open call list')
            ->assertSee('You have 1 new first timer to call')
            ->assertSee('assets/js/notifications.js');
    }

    /**
     * The bell reveals the list on the page the member is already on: the panel
     * ships with layouts/app on every authenticated page, docked to the right edge,
     * carrying the hooks public/assets/js/notifications.js fills from
     * /notifications/feed and the "mark all" button that clears it.
     */
    public function test_every_page_ships_the_bell_panel_that_lists_the_alerts()
    {
        $member = $this->memberQuery()->first();

        if (! $member) {
            $this->markTestSkipped('No member with a complete profile available.');
        }

        $response = $this->actingAs($member)->get('/home');

        $response->assertOk();

        // The bell carries the badge and points at the panel instead of navigating.
        $response->assertSee('class="ms-notif-bell ml-auto"', false);
        $response->assertSee('data-target="#notificationsModal"', false);
        $response->assertSee('id="notifBadge"', false);

        // The panel itself: the loading, empty and list hooks, plus "mark all".
        $response->assertSee('id="notificationsModal"', false);
        $response->assertSee('class="modal-dialog notif-dialog"', false);
        $response->assertSee('id="notifModalList"', false);
        $response->assertSee('id="notifModalLoading"', false);
        $response->assertSee('id="notifModalEmpty"', false);
        $response->assertSee('id="notifModalCount"', false);
        $response->assertSee('id="notifMarkAll"', false);

        // ...filled from the feed, by the script the layout loads.
        $response->assertSee(route('notifications.feed'), false);
        $response->assertSee('assets/js/notifications.js');

        // Docked to the right edge and sliding in from it, not centred on the page.
        $response->assertSee('#notificationsModal .notif-dialog', false);
        $response->assertSee('#notificationsModal.show .notif-dialog', false);
        $response->assertSee('transform: translateX(100%);', false);
    }

    /**
     * The panel is rendered *below* the script that fills it (layouts/app.blade.php
     * loads notifications.js, then includes the panel), so the script must not read
     * the panel's nodes while it is being parsed. Doing that left `canRenderPanel`
     * false, and the bell then opened a panel whose only content was
     * "Loading your alerts..." - the feed was never read, so the list never arrived.
     *
     * A static check of the asset, the way ThemeToggleTest checks theme.js.
     */
    public function test_the_bell_script_waits_for_the_document_before_it_reads_the_panel()
    {
        $script = file_get_contents(public_path('assets/js/notifications.js'));

        // The nodes are resolved once the page is ready...
        $this->assertStringContainsString('function locate()', $script);
        $this->assertStringContainsString('locate();', $script);
        $this->assertStringContainsString('canRenderPanel = !!(panel && list);', $script);

        // ...and never at parse time: a statement of the IIFE's body is indented four
        // spaces, the same lookup inside one of its functions is indented further.
        preg_match_all('/^ {4}var \w+ = document\.(?:getElementById|querySelector)\(/m', $script, $parseTimeLookups);

        $this->assertSame(
            [],
            $parseTimeLookups[0],
            'notifications.js must not resolve the bell / the panel before the document is parsed.'
        );

        // The list is read off Bootstrap's own open event, so the bell alone is
        // enough - there is no second button to press before the alerts appear.
        $this->assertStringContainsString("on('show.bs.modal', loadPanel)", $script);
    }

    /**
     * Item 5(b) end to end: "Assign all callers" on the week list tells the caller
     * who was handed the first timer.
     */
    public function test_assigning_the_whole_window_notifies_the_caller()
    {
        $admin = $this->admin();
        $lane = $this->callerLane();

        $firstTimerId = $this->createFirstTimer($lane[1], $admin->campus_id ?? 1);

        $response = $this->actingAs($admin)->postJson(route('weeklist.assign-callers'), self::WINDOW);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertGreaterThan(0, (int) $response->json('notified'), (string) $response->json('message'));

        $assignedCallerId = (int) DB::table('caller_assignments')
            ->where('first_timer_id', $firstTimerId)
            ->value('caller_member_id');

        $this->assertGreaterThan(0, $assignedCallerId, 'The bulk assignment saved no caller for the test first timer.');

        $row = DB::table('app_notifications')
            ->where('tiu_member_id', $assignedCallerId)
            ->where('type', NotificationService::TYPE_CALLER_ASSIGNMENT)
            ->where('body', 'like', '%' . $this->lastCreatedName . '%')
            ->first();

        $this->assertNotNull($row, 'The caller was not told about the first timer they were given.');
        $this->assertSame('/weeklist', $this->notifications->payload($row)['url'] ?? null);
        $this->assertStringContainsString('first timer', $row->title);
    }

    /**
     * Item 11 from the call list: "Assign all callers" inside the Weekly Call
     * List modal hands every first timer the modal shows to the caller in its
     * row, so that caller is told the way the guide of a dragged card is - once,
     * with a link to /my-tasks.
     */
    public function test_the_modal_bulk_assignment_notifies_the_guide()
    {
        $admin = $this->admin();
        $lane = $this->callerLane();

        $firstTimerId = $this->createFirstTimer($lane[1], $admin->campus_id ?? 1);

        $response = $this->actingAs($admin)->postJson(route('weeklist.assign-guides'), self::WINDOW);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertGreaterThan(0, (int) $response->json('notified'), (string) $response->json('message'));

        $guideId = (int) DB::table('member_tracking_followup')
            ->where('first_timer_id', $firstTimerId)
            ->value('tiu_member_id');

        $this->assertGreaterThan(0, $guideId, 'The bulk assignment gave the first timer no guide.');

        $row = DB::table('app_notifications')
            ->where('tiu_member_id', $guideId)
            ->where('type', NotificationService::TYPE_GUIDE_ASSIGNMENT)
            ->where('body', 'like', '%' . $this->lastCreatedName . '%')
            ->first();

        $this->assertNotNull($row, 'The guide was not told about the first timer they were given.');
        $this->assertSame('/my-tasks', $this->notifications->payload($row)['url'] ?? null);
        $this->assertStringContainsString('first timer', $row->title);

        // The same list again changes nothing for that guide, so nothing is sent.
        $this->actingAs($admin)
            ->postJson(route('weeklist.assign-guides'), self::WINDOW)
            ->assertOk()
            ->assertJson(['success' => true, 'notified' => 0]);

        $this->assertSame(
            1,
            DB::table('app_notifications')
                ->where('tiu_member_id', $guideId)
                ->where('type', NotificationService::TYPE_GUIDE_ASSIGNMENT)
                ->where('body', 'like', '%' . $this->lastCreatedName . '%')
                ->count(),
            'The guide was alerted twice about the same first timer.'
        );
    }

    /**
     * Picking the same caller again (re-opening the modal and saving) must not send
     * a second alert about the same person.
     */
    public function test_re_saving_the_same_caller_does_not_notify_twice()
    {
        $admin = $this->admin();
        $lane = $this->callerLane();
        $callerId = $lane[0];

        $firstTimerId = $this->createFirstTimer($lane[1], $admin->campus_id ?? 1);

        $payload = ['first_timer_id' => $firstTimerId, 'caller_member_id' => $callerId];

        $this->actingAs($admin)
            ->postJson(route('weeklist.assign-caller'), $payload)
            ->assertOk()
            ->assertJson(['success' => true, 'notified' => 1]);

        $mine = DB::table('app_notifications')
            ->where('tiu_member_id', $callerId)
            ->where('type', NotificationService::TYPE_CALLER_ASSIGNMENT)
            ->where('body', 'like', '%' . $this->lastCreatedName . '%');

        $this->assertSame(1, $mine->count());
        $this->assertStringContainsString('1 new first timer', $mine->first()->title);

        $this->actingAs($admin)
            ->postJson(route('weeklist.assign-caller'), $payload)
            ->assertOk()
            ->assertJson(['success' => true, 'notified' => 0]);

        $this->assertSame(1, $mine->count(), 'The same caller was alerted twice about the same first timer.');
    }

    /**
     * Item 11 end to end: the drag & drop assignment tells the guide, once.
     */
    public function test_the_drag_and_drop_assignment_notifies_the_guide()
    {
        $admin = $this->admin();
        $guideId = $this->aMemberId();

        $firstTimerId = $this->createFirstTimer('Male', $admin->campus_id ?? 1);

        $payload = [
            'Guild_name'     => $guideId,
            'first_timer_id' => $firstTimerId,
            'full_name'      => $this->lastCreatedName,
            'status'         => 'Assigned',
        ];

        $this->actingAs($admin)
            ->postJson(route('admin.assignments.ajax-assign'), $payload)
            ->assertOk()
            ->assertJson(['status' => 'success', 'notified' => true]);

        $row = DB::table('app_notifications')
            ->where('tiu_member_id', $guideId)
            ->where('type', NotificationService::TYPE_GUIDE_ASSIGNMENT)
            ->where('body', $this->lastCreatedName)
            ->first();

        $this->assertNotNull($row, 'The guide was not told about the first timer they were given.');
        $this->assertSame('/my-tasks', $this->notifications->payload($row)['url'] ?? null);
        $this->assertSame(
            'Assigned',
            DB::table('first_timer')->where('first_timer_id', $firstTimerId)->value('status')
        );

        // Saving the same guide again is silent (the alert would only repeat).
        $this->actingAs($admin)
            ->postJson(route('admin.assignments.ajax-assign'), $payload)
            ->assertOk()
            ->assertJson(['status' => 'success', 'notified' => false]);
    }

    /**
     * Reading an alert clears it: "read" and "gone from the list" are one act, so
     * the bell's modal can slide the row out and the badge can drop.
     */
    public function test_marking_an_alert_read_clears_it()
    {
        $reader = $this->memberQuery()->first();

        if (! $reader) {
            $this->markTestSkipped('No member with a complete profile available.');
        }

        $readerId = (int) $reader->tiu_member_id;

        $this->notifications->notify(
            $readerId,
            NotificationService::TYPE_WEEKLY_TASK,
            'Read me once',
            'Notify Testcase'
        );

        $row = DB::table('app_notifications')
            ->where('tiu_member_id', $readerId)
            ->where('title', 'Read me once')
            ->first();

        $this->assertNotNull($row, 'The alert under test was not written.');

        $this->actingAs($reader)
            ->postJson(route('notifications.read', $row->id))
            ->assertOk()
            ->assertJson(['deleted' => 1]);

        $this->assertSame(
            0,
            DB::table('app_notifications')->where('id', $row->id)->count(),
            'Reading an alert must clear the row.'
        );

        // ...and the feed the bell's modal reads no longer carries it.
        $alerts = collect(
            $this->actingAs($reader)->getJson(route('notifications.feed'))->json('notifications')
        );

        $this->assertFalse($alerts->contains('title', 'Read me once'));

        // Pressing it a second time (a stale panel, two open tabs) is harmless.
        $this->actingAs($reader)
            ->postJson(route('notifications.read', $row->id))
            ->assertOk()
            ->assertJson(['deleted' => 0]);
    }

    /**
     * "Mark all as read" empties the member's list in one request, which is what
     * the modal's footer button and the page's form both do.
     */
    public function test_mark_all_as_read_clears_every_unread_alert()
    {
        $reader = $this->memberQuery()->first();

        if (! $reader) {
            $this->markTestSkipped('No member with a complete profile available.');
        }

        $readerId = (int) $reader->tiu_member_id;

        $this->notifications->notify($readerId, NotificationService::TYPE_GUIDE_ASSIGNMENT, 'Clear me');
        $this->notifications->notify($readerId, NotificationService::TYPE_CALLER_ASSIGNMENT, 'Clear me too');

        $this->actingAs($reader)
            ->postJson(route('notifications.read-all'))
            ->assertOk()
            ->assertJson(['unread' => 0]);

        $this->assertSame(
            0,
            DB::table('app_notifications')->where('tiu_member_id', $readerId)->whereNull('read_at')->count(),
            'Marking all as read must leave nothing unread.'
        );
    }

    /**
     * The page behind the bell is the no-JavaScript fallback: it clears a row with
     * a plain POST form, not with the GET link it used to carry.
     */
    public function test_the_notification_page_clears_a_row_with_a_post_form()
    {
        $reader = $this->memberQuery()->first();

        if (! $reader) {
            $this->markTestSkipped('No member with a complete profile available.');
        }

        $readerId = (int) $reader->tiu_member_id;

        $this->notifications->notify(
            $readerId,
            NotificationService::TYPE_GUIDE_ASSIGNMENT,
            'A guide needs following up',
            'Notify Testcase'
        );

        $row = DB::table('app_notifications')
            ->where('tiu_member_id', $readerId)
            ->where('title', 'A guide needs following up')
            ->first();

        $response = $this->actingAs($reader)->get(route('notifications.index'));

        $response->assertOk()
            ->assertSee('A guide needs following up')
            ->assertSee('Open my tasks')
            ->assertSee('action="' . route('notifications.read', $row->id) . '"', false)
            ->assertSee('method="POST"', false);
    }

}
