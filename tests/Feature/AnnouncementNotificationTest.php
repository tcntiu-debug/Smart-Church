<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\TiuMember;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A published notice rings the bell of every member.
 *
 * The notice board (/notice) is read-only: the alert is how a member learns a
 * new notice exists. Publishing - from the web form or the token API - is the
 * moment the church is told, and editing a notice afterwards must not shout a
 * second time.
 *
 * Deliberately no RefreshDatabase: the suite runs against the application's real
 * connection. Every test writes inside a transaction opened in setUp() and
 * rolled back in tearDown(), so neither the notice nor the alerts survive.
 */
class AnnouncementNotificationTest extends TestCase
{
    /** @var \App\Services\NotificationService */
    private $notifications;

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
     */
    private function memberQuery()
    {
        $members = TiuMember::query()
            ->whereNotNull('marital_status')
            ->whereNotNull('age')
            ->whereNotNull('occupation')
            ->whereNotNull('next_of_kin_name')
            ->whereNotNull('next_of_kin_phone');

        if (! Campus::isSingle()) {
            $members->whereNotNull('campus_id');
        }

        return $members;
    }

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
     * A title nobody else has used, so the assertions can pick this test's rows.
     */
    private function title(): string
    {
        return 'Notice testcase '.random_int(100000, 999999);
    }

    /**
     * The notice-board alert rows for a title, across the whole church.
     */
    private function alertsFor(string $title)
    {
        return DB::table('app_notifications')
            ->where('type', NotificationService::TYPE_ANNOUNCEMENT)
            ->where('title', $title)
            ->get();
    }

    public function test_publishing_a_notice_writes_an_alert_for_every_member()
    {
        $admin = $this->admin();
        $title = $this->title();

        $this->actingAs($admin)
            ->post(route('announcements.store'), [
                'title' => $title,
                'content' => "  The generator\n\nruns on Saturday.  ",
            ])
            ->assertRedirect();

        $announcement = DB::table('announcements')->where('title', $title)->orderByDesc('id')->first();

        $this->assertNotNull($announcement, 'The notice was not stored.');

        $audience = $this->notifications->announcementAudience(
            $announcement->campus_id !== null ? (int) $announcement->campus_id : null
        );

        $this->assertNotEmpty($audience, 'There is no member to notify.');

        $alerts = $this->alertsFor($title);

        $this->assertSame(
            count($audience),
            $alerts->count(),
            'Every member in the audience must receive exactly one alert.'
        );

        $this->assertSame(
            count($audience),
            $alerts->pluck('tiu_member_id')->unique()->count(),
            'No member may be written twice.'
        );

        // ...and the member who published it is among the recipients.
        $this->assertContains(
            (int) $admin->tiu_member_id,
            $alerts->pluck('tiu_member_id')->map(function ($id) {
                return (int) $id;
            })->all()
        );

        $alert = $alerts->first();

        $this->assertSame(
            'The generator runs on Saturday.',
            $alert->body,
            'The bell shows a single-paragraph version of the notice.'
        );

        $payload = json_decode((string) $alert->data, true);

        $this->assertSame((int) $announcement->id, $payload['announcement_id']);
        $this->assertSame('/notice', $payload['url']);
    }

    public function test_editing_a_notice_does_not_ring_the_bell_again()
    {
        $admin = $this->admin();
        $title = $this->title();

        $this->actingAs($admin)->post(route('announcements.store'), [
            'title' => $title,
            'content' => 'First version.',
        ])->assertRedirect();

        $announcement = DB::table('announcements')->where('title', $title)->orderByDesc('id')->first();
        $written = $this->alertsFor($title)->count();

        $this->assertGreaterThan(0, $written);

        $this->actingAs($admin)->post(route('announcements.update'), [
            'id' => $announcement->id,
            'title' => $title,
            'content' => 'Second version.',
        ])->assertRedirect();

        $this->assertSame(
            $written,
            $this->alertsFor($title)->count(),
            'Correcting a notice must not notify the church a second time.'
        );

        $this->assertSame(
            'Second version.',
            DB::table('announcements')->where('id', $announcement->id)->value('content')
        );
    }

    public function test_the_bell_links_the_member_to_the_notice_board()
    {
        $admin = $this->admin();
        $title = $this->title();

        $this->actingAs($admin)->post(route('announcements.store'), [
            'title' => $title,
            'content' => 'See you at the harvest.',
        ])->assertRedirect();

        $this->actingAs($admin)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee($title)
            ->assertSee('Open notice board');
    }
}

