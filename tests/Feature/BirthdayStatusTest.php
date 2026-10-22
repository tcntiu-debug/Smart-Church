<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\TiuMember;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The reminder status page (/birthdays/status).
 *
 * It exists because a digest can fail silently: the scheduler never runs, the
 * mailer is still "log", or the Welcome Center has no address. The page answers
 * those three questions and previews today's digest through the command's own
 * --dry-run, so it must never send or record anything.
 *
 * Deliberately no RefreshDatabase: the suite runs against the application's real
 * connection and the page reads it. Every test opens a transaction in setUp()
 * and rolls it back in tearDown().
 */
class BirthdayStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();

        parent::tearDown();
    }

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

    /**
     * A viewer holding the Super User role.
     *
     * The role is set in memory and never saved: the status page is gated on the
     * role alone, and the database under test may hold no Super User row. The
     * member picked still has a complete profile, so the `profile.complete`
     * middleware lets the request through.
     */
    private function superUser()
    {
        $member = $this->memberQuery()->first();

        if (! $member) {
            $this->markTestSkipped('No member with a complete profile available.');
        }

        $superUser = clone $member;
        $superUser->member_role = 'Super User';

        return $superUser;
    }

    public function test_a_guest_is_sent_to_the_login_page()
    {
        $this->get(route('birthdays.status'))->assertRedirect(route('login'));
    }

    public function test_an_ordinary_member_is_refused()
    {
        $member = $this->memberQuery()
            ->whereNotIn('member_role', ['Super User', 'Admin', 'Admins', 'Workers'])
            ->first();

        if (! $member) {
            $this->markTestSkipped('No ordinary member with a complete profile available.');
        }

        $this->actingAs($member)
            ->get(route('birthdays.status'))
            ->assertRedirect('/aoverview');
    }

    /**
     * The members' list (/celebrations): every signed-in member reads it, and it
     * carries no reminder status - neither the button nor a link to the page.
     * The staff roles read the same list from /birthdays; the split is the
     * status page, not the birthdays themselves.
     */
    public function test_the_members_list_is_open_to_everyone_and_carries_no_reminder_status()
    {
        $member = $this->memberQuery()->first();

        if (! $member) {
            $this->markTestSkipped('No member with a complete profile available.');
        }

        foreach (['Member', 'Worker', 'Workers', 'Admins', 'Admin', 'Super User'] as $role) {
            $probe = clone $member;
            $probe->member_role = $role;

            $response = $this->actingAs($probe)
                ->get(route('birthdays.celebrations'))
                ->assertOk();

            $response->assertSee('Member Birthdays');
            $response->assertDontSee('Reminder status');
        }
    }

    /**
     * A plain member reads birthdays on the members' list, and is still turned
     * away from the staff list. Two pages, two doors - not a hidden link.
     */
    public function test_a_plain_member_reads_the_members_list_not_the_staff_list()
    {
        $member = $this->memberQuery()->first();

        if (! $member) {
            $this->markTestSkipped('No member with a complete profile available.');
        }

        $probe = clone $member;
        $probe->member_role = 'Member';

        $this->actingAs($probe)->get(route('birthdays.index'))->assertRedirect('/aoverview');

        $this->actingAs($probe)
            ->get(route('birthdays.celebrations'))
            ->assertOk()
            ->assertSee('Member Birthdays')
            ->assertDontSee('Reminder status');
    }

    /**
     * The dashboard's Birthdays card goes to the list the reader can open:
     * /birthdays for staff, /celebrations for everyone else. It used to point
     * every role at /birthdays, which answers a plain Member "Access Denied".
     */
    public function test_the_dashboard_card_points_each_role_at_the_list_it_can_read()
    {
        $member = $this->memberQuery()->first();

        if (! $member) {
            $this->markTestSkipped('No member with a complete profile available.');
        }

        // The same four roles BirthdayController::STAFF_ROLES admits, plus the
        // roles below them that read the members' list instead.
        $lists = [
            'Super User' => 'birthdays.index',
            'Admin' => 'birthdays.index',
            'Admins' => 'birthdays.index',
            'Workers' => 'birthdays.index',
            'Worker' => 'birthdays.celebrations',
            'Member' => 'birthdays.celebrations',
            'Lead' => 'birthdays.celebrations',
        ];

        foreach ($lists as $role => $routeName) {
            $probe = clone $member;
            $probe->member_role = $role;

            $html = $this->actingAs($probe)->get(route('home'))->assertOk()->getContent();

            $this->assertStringContainsString(
                '<a href="'.route($routeName).'" class="action-card">',
                $html,
                'The dashboard card sent a '.$role.' to the wrong birthday list.'
            );
        }
    }

    /**
     * The Admin Console offers the reminder status page to the Super User only.
     * Admin reads that console too, so a row that would answer them "Access
     * Denied" must not be there - and the menu has to agree with the gate.
     */
    public function test_only_the_super_user_is_offered_reminder_status_in_the_admin_console()
    {
        $member = $this->memberQuery()->first();

        if (! $member) {
            $this->markTestSkipped('No member with a complete profile available.');
        }

        foreach (['Super User' => true, 'Admin' => false, 'Admins' => false, 'Workers' => false, 'Member' => false] as $role => $offered) {
            $probe = clone $member;
            $probe->member_role = $role;

            $this->actingAs($probe);
            session(['member_role' => $role, 'department_name' => '[]']);

            $nav = view('partials.nav')->render();

            if ($offered) {
                $this->assertStringContainsString('Admin Console', $nav);
                $this->assertStringContainsString(
                    route('birthdays.status'),
                    $nav,
                    'The Super User was not offered the reminder status page.'
                );

                continue;
            }

            $this->assertStringNotContainsString(
                route('birthdays.status'),
                $nav,
                'A '.$role.' was offered the reminder status page.'
            );

            if (in_array($role, \App\Http\Controllers\BirthdayController::STAFF_ROLES, true)) {
                // The Welcome Center block that carries /birthdays is the Admin
                // and Super User one, so a synthetic `Admins`/`Workers` role has
                // no staff link to look for here. Only the gate is asserted.
                continue;
            }

            // Below the staff roles the menu offers the members' list, never the
            // staff list.
            $this->assertStringContainsString(route('birthdays.celebrations'), $nav);
            $this->assertStringNotContainsString(route('birthdays.index'), $nav);
        }
    }

    /**
     * The Reset button belongs to the page the reader started from: a member
     * resets to /celebrations, staff reset to /birthdays. Otherwise "Reset"
     * would bounce a member off a page they are allowed to read.
     */
    public function test_the_reset_button_points_back_at_the_list_the_reader_is_on()
    {
        $member = $this->memberQuery()->first();

        if (! $member) {
            $this->markTestSkipped('No member with a complete profile available.');
        }

        $probe = clone $member;
        $probe->member_role = 'Member';

        $html = $this->actingAs($probe)->get(route('birthdays.celebrations'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '#<a href="[^"]*/celebrations" class="btn btn-sm btn-secondary">Reset</a>#',
            $html
        );

        // The month dropdown must not bounce a member to the staff list either.
        $html = $this->actingAs($probe)
            ->get(route('birthdays.filter', ['month' => now()->month]))
            ->assertOk()
            ->getContent();
        $this->assertMatchesRegularExpression(
            '#<a href="[^"]*/celebrations" class="btn btn-sm btn-secondary">Reset</a>#',
            $html
        );

        $probe = clone $member;
        $probe->member_role = 'Admin';

        $html = $this->actingAs($probe)->get(route('birthdays.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '#<a href="[^"]*/birthdays" class="btn btn-sm btn-secondary">Reset</a>#',
            $html
        );
    }

    /**
     * The status page is the Super User's: it lists the recipients and their
     * addresses, the mailer and its transport, and the raw log tail. A Welcome
     * Center Worker keeps the birthday list itself, without the button.
     */
    public function test_a_worker_reads_the_birthdays_list_but_not_the_reminder_status()
    {
        $member = $this->memberQuery()->whereIn('member_role', ['Member', 'Worker', 'Workers'])->first()
            ?: $this->memberQuery()->first();

        if (! $member) {
            $this->markTestSkipped('No member with a complete profile available.');
        }

        // The role is set in memory only, never saved: the gate is what is under
        // test, not the data. `Workers` is a role the Birthdays list admits.
        $worker = clone $member;
        $worker->member_role = 'Workers';

        $this->actingAs($worker)
            ->get(route('birthdays.index'))
            ->assertOk()
            ->assertDontSee('Reminder status');

        $this->actingAs($worker)
            ->get(route('birthdays.status'))
            ->assertRedirect('/aoverview');
    }

    /**
     * Every role other than Super User is refused - Admin and the two plural
     * staff roles included, because a stored `Worker` and a stored `Admin` are
     * both below the one role that may read every recipient's address.
     */
    public function test_the_status_page_refuses_every_role_below_super_user()
    {
        $member = $this->memberQuery()->first();

        if (! $member) {
            $this->markTestSkipped('No member with a complete profile available.');
        }

        foreach (['Admin', 'Admins', 'Workers', 'Worker', 'Member', 'Lead', 'TIU Guest', ''] as $role) {
            $probe = clone $member;
            $probe->member_role = $role;

            $this->actingAs($probe)
                ->get(route('birthdays.status'))
                ->assertRedirect('/aoverview');
        }
    }

    /**
     * The button and the route agree: only a Super User is offered it, and an
     * Admin still reads the list - just without the button on it.
     */
    public function test_only_a_super_user_is_offered_the_reminder_status_button()
    {
        $this->actingAs($this->superUser())
            ->get(route('birthdays.index'))
            ->assertOk()
            ->assertSee('Reminder status')
            ->assertSee(route('birthdays.status'), false);

        $member = $this->memberQuery()->first();

        // The three roles that may still read the list itself. A plain Member
        // never reaches it (BirthdayController::canManageReminders), so there is
        // no button to hide from them.
        foreach (['Admin', 'Admins', 'Workers'] as $role) {
            $probe = clone $member;
            $probe->member_role = $role;

            $this->actingAs($probe)
                ->get(route('birthdays.index'))
                ->assertOk()
                ->assertDontSee('Reminder status');
        }
    }

    public function test_a_super_user_sees_the_preview_and_the_scheduler_hint()
    {
        $before = [
            'birthday_reminder_logs' => DB::table('birthday_reminder_logs')->count(),
            'app_notifications' => DB::table('app_notifications')->count(),
        ];

        $response = $this->actingAs($this->superUser())->get(route('birthdays.status'));

        $response->assertOk()
            ->assertSee('Birthday reminder status')
            ->assertSee('Preview (dry run)')
            ->assertSee('birthdays:remind')
            ->assertSee('schedule:run')
            // The preview is a dry run, so it must not read as a delivery.
            ->assertSee('would be sent')
            ->assertDontSee('digest(s) sent');

        // The preview is a dry run: the page must not send a digest nor write a
        // log row, no matter how often it is opened.
        $this->assertSame(
            $before['birthday_reminder_logs'],
            DB::table('birthday_reminder_logs')->count(),
            'Opening the status page must not record a digest.'
        );

        $this->assertSame(
            $before['app_notifications'],
            DB::table('app_notifications')->count(),
            'Opening the status page must not notify anybody.'
        );
    }

    /**
     * The page names the transport the digest will really leave on, so a dev
     * host can tell "shared MAIL_*" from a dedicated birthday mailbox without
     * reading .env or a log.
     */
    public function test_the_page_shows_the_mailer_and_from_the_digest_uses()
    {
        config([
            'birthday.mailer' => 'birthday',
            'mail.mailers.birthday.host' => 'smtp.digest.test',
            'birthday.from.address' => 'welcome@example.test',
            'birthday.from.name' => 'Welcome Center',
        ]);

        $this->actingAs($this->superUser())
            ->get(route('birthdays.status'))
            ->assertOk()
            ->assertSee('Digest mailer')
            ->assertSee('BIRTHDAY_MAIL_MAILER')
            ->assertSee('smtp.digest.test')
            ->assertSee('welcome@example.test');
    }

    /**
     * The page names the milestone list the app is really using, and the env
     * var behind it. A digest that fires weeks early ("a birthday in 60 days")
     * can then be traced to BIRTHDAY_DAYS without opening a shell.
     */
    public function test_the_page_shows_the_configured_milestones()
    {
        config(['birthday.days' => [3, 2, 1]]);

        $this->actingAs($this->superUser())
            ->get(route('birthdays.status'))
            ->assertOk()
            ->assertSee('Milestones:')
            ->assertSee('BIRTHDAY_DAYS')
            ->assertSee('3, 2, 1');
    }

    /**
     * A milestone more than a week out is called out, because that is how the
     * Welcome Center ends up being asked to wish somebody a happy birthday two
     * months in advance.
     */
    public function test_a_milestone_beyond_a_week_is_reported()
    {
        config(['birthday.days' => [60, 3, 2, 1]]);

        $this->actingAs($this->superUser())
            ->get(route('birthdays.status'))
            ->assertOk()
            ->assertSee('BIRTHDAY_DAYS contains 60 day(s) before the birthday')
            ->assertSee('set BIRTHDAY_DAYS=3,2,1');
    }

    public function test_the_dry_run_the_page_runs_sends_nothing()
    {
        // The command the page calls, isolated: same assertions, no view.
        $before = DB::table('birthday_reminder_logs')->count();

        Artisan::call('birthdays:remind', ['--dry-run' => true]);

        $this->assertSame($before, DB::table('birthday_reminder_logs')->count());
    }
}
