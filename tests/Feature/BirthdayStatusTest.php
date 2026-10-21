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

    private function admin()
    {
        $admin = $this->memberQuery()->where('member_role', 'Super User')->first()
            ?: $this->memberQuery()->whereIn('member_role', ['Admin', 'Admins'])->first();

        if (! $admin) {
            $this->markTestSkipped('No Admin/Super User with a complete profile available.');
        }

        return $admin;
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

    public function test_an_admin_sees_the_preview_and_the_scheduler_hint()
    {
        $before = [
            'birthday_reminder_logs' => DB::table('birthday_reminder_logs')->count(),
            'app_notifications' => DB::table('app_notifications')->count(),
        ];

        $response = $this->actingAs($this->admin())->get(route('birthdays.status'));

        $response->assertOk()
            ->assertSee('Birthday reminder status')
            ->assertSee('Preview (dry run)')
            ->assertSee('birthdays:remind')
            ->assertSee('schedule:run');

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

        $this->actingAs($this->admin())
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

        $this->actingAs($this->admin())
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

        $this->actingAs($this->admin())
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
