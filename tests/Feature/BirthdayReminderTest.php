<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Services\BirthdayReminderService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Welcome Center birthday reminders (`php artisan birthdays:remind`).
 *
 * The digest has to reach every Admin / Super User of department 28 (Welcome
 * Center), and nobody else. That is what the assertions below pin down, against
 * the live rows, so the test keeps passing when the team changes.
 *
 * Which members and recipients a campus id resolves to depends on the switch in
 * config/campus.php: in single campus mode (the default, and what Ikorodu runs)
 * the digest is the whole church and the campus id is only carried along for the
 * notification and log rows. Every mirror query below therefore goes through the
 * same ->forCurrentCampus() switch as the service, so this test holds in both
 * modes.
 *
 * Read-only on purpose: the suite runs against the application's real
 * connection, so nothing here may write a `birthday_reminder_logs` row, an
 * `app_notifications` row, an e-mail or a Telegram message. The one command that
 * is run is the dry run.
 */
class BirthdayReminderTest extends TestCase
{
    private function service(): BirthdayReminderService
    {
        return app(BirthdayReminderService::class);
    }

    /**
     * Is the app running as a one-campus church? (config/campus.php)
     */
    private function single(): bool
    {
        return Campus::isSingle();
    }

    /**
     * Roles that count as an admin recipient, from config.
     */
    private function recipientRoles(): array
    {
        return (array) config('birthday.recipient_roles', ['Super User', 'Admin']);
    }

    /**
     * The department ids whose admins are reminded (28 = Welcome Center).
     */
    private function departmentIds(): array
    {
        return array_values(array_filter(array_map('intval', (array) config('birthday.department_ids', [28]))));
    }

    /**
     * Neither a deleted member (status 3) nor a missing status is a recipient.
     */
    private function notDeleted($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('status')->orWhere('status', '!=', '3');
        });
    }

    /**
     * Members of the configured departments, whatever their role.
     */
    private function inConfiguredDepartments($query)
    {
        $departmentIds = $this->departmentIds();

        if (empty($departmentIds)) {
            return $query;
        }

        return $query->where(function ($q) use ($departmentIds) {
            foreach ($departmentIds as $departmentId) {
                $q->orWhere('department_name', 'like', '%"'.$departmentId.'"%');
            }
        });
    }

    /**
     * The campus ids a digest can be sent for.
     */
    private function campusIds(): array
    {
        return array_map('intval', DB::table('campus')->orderBy('cid')->pluck('cid')->all());
    }

    private function ids(array $rows): array
    {
        return array_map(function ($row) {
            // Both tiu_member rows (stdClass) and the service's recipient arrays
            // are handed to this helper.
            $row = (array) $row;

            return (int) ($row['member_id'] ?? $row['tiu_member_id'] ?? 0);
        }, $rows);
    }

    /**
     * The people who get the digest are exactly the Welcome Center admins of the
     * campus the birthdays belong to - no more (no Member of the department, no
     * deleted member, no other campus) and no fewer.
     */
    public function test_the_recipients_are_the_welcome_center_admins_of_that_campus()
    {
        $campusIds = $this->campusIds();

        if (empty($campusIds)) {
            $this->markTestSkipped('No campus row available.');
        }

        foreach ($campusIds as $campusId) {
            // Written out independently of the service: admins of the configured
            // departments on this campus that are still alive.
            $expected = $this->ids(
                $this->inConfiguredDepartments(
                    $this->notDeleted(
                        DB::table('tiu_member')
                            ->forCurrentCampus('campus_id', $campusId)
                            ->whereIn('member_role', $this->recipientRoles())
                    )
                )->get(['tiu_member_id'])->all()
            );

            $recipients = $this->service()->recipientsFor($campusId);
            $actual = $this->ids($recipients);

            sort($expected);
            sort($actual);

            $this->assertSame(
                $expected,
                $actual,
                'The digest does not reach exactly the Welcome Center admins of campus '.$campusId.'.'
            );

            foreach ($recipients as $recipient) {
                $row = DB::table('tiu_member')
                    ->where('tiu_member_id', $recipient['member_id'])
                    ->first(['first_name', 'last_name', 'member_role', 'campus_id', 'department_name', 'status']);

                $this->assertNotNull($row, 'A recipient came back without a tiu_member row.');

                if (! $this->single()) {
                    $this->assertSame($campusId, (int) $row->campus_id, 'A member of another campus is being reminded.');
                }
                $this->assertContains($row->member_role, $this->recipientRoles(), 'A non-admin role is being reminded.');
                $this->assertNotSame('3', (string) $row->status, 'A deleted member is being reminded.');
                $this->assertSame(
                    trim($row->first_name.' '.$row->last_name),
                    $recipient['name'],
                    'The recipient name is not the member name.'
                );

                foreach ($this->departmentIds() as $departmentId) {
                    $this->assertStringContainsString(
                        '"'.$departmentId.'"',
                        (string) $row->department_name,
                        'A member outside department '.$departmentId.' is being reminded.'
                    );
                }
            }
        }
    }

    /**
     * Everyone else in the Welcome Center (a plain Member, a Worker) is not an
     * admin recipient - they only join in when their role is added to
     * BIRTHDAY_RECIPIENT_ROLES.
     */
    public function test_members_of_the_welcome_center_who_are_not_admins_are_skipped()
    {
        if (empty($this->departmentIds())) {
            $this->markTestSkipped('No department configured.');
        }

        $outsiders = DB::table('tiu_member')
            ->whereNotIn('member_role', $this->recipientRoles());

        // Single campus mode retired the campus_id data, so the population of the
        // Welcome Center is "everybody in it" rather than "everybody on a campus".
        if (! $this->single()) {
            $outsiders->whereNotNull('campus_id');
        }

        $outsider = $this->ids(
            $this->inConfiguredDepartments(
                $this->notDeleted($outsiders)
            )->get(['tiu_member_id'])->all()
        );

        if (empty($outsider)) {
            $this->markTestSkipped('Everybody in the Welcome Center is an admin right now.');
        }

        $row = DB::table('tiu_member')->where('tiu_member_id', $outsider[0])->first(['member_role', 'campus_id']);
        $recipients = $this->ids($this->service()->recipientsFor((int) $row->campus_id));

        $this->assertNotContains(
            (int) $outsider[0],
            $recipients,
            'A '.$row->member_role.' of the Welcome Center was treated as an admin recipient.'
        );
    }

    /**
     * A campus can have no Welcome Center admin of its own (Yaba today). That
     * must never blow up and must never invent recipients: the list a campus id
     * resolves to is at most the admins that campus has - and in single campus
     * mode it is the church-wide list, whatever id is asked for.
     */
    public function test_a_campus_without_recipients_never_resolves_to_more_than_its_admins()
    {
        $campusIds = $this->campusIds();

        if (empty($campusIds)) {
            $this->markTestSkipped('No campus row available.');
        }

        foreach ($campusIds as $campusId) {
            $recipients = $this->service()->recipientsFor($campusId);
            $adminsOnCampus = $this->notDeleted(
                DB::table('tiu_member')
                    ->forCurrentCampus('campus_id', $campusId)
                    ->whereIn('member_role', $this->recipientRoles())
            )->count();

            $this->assertLessThanOrEqual(
                $adminsOnCampus,
                count($recipients),
                'Campus '.$campusId.' resolved to more recipients than it has admins.'
            );
        }

        if (! $this->single()) {
            return;
        }

        // Single campus: the campus id no longer narrows anything, so every id
        // resolves to the same digest audience.
        $first = $this->ids($this->service()->recipientsFor($campusIds[0]));
        sort($first);

        foreach ($campusIds as $campusId) {
            $actual = $this->ids($this->service()->recipientsFor($campusId));
            sort($actual);

            $this->assertSame(
                $first,
                $actual,
                'Campus '.$campusId.' resolves to a different audience while single campus mode is on.'
            );
        }
    }

    /**
     * The match ignores the year: same month and day, every year - and never on
     * two different days.
     */
    public function test_the_birthday_match_is_by_month_and_day_not_by_year()
    {
        $service = $this->service();
        $found = null;

        foreach ($this->campusIds() as $campusId) {
            for ($offset = 0; $offset <= 90; $offset++) {
                $target = Carbon::today()->addDays($offset);
                $rows = $service->birthdaysOn($campusId, $target);

                if (! empty($rows)) {
                    $found = ['campus' => $campusId, 'target' => $target, 'rows' => $rows];
                    break 2;
                }
            }
        }

        if (! $found) {
            $this->markTestSkipped('No birthday in the next 90 days on any campus.');
        }

        $ids = array_values(array_unique($this->ids($found['rows'])));

        foreach ($found['rows'] as $row) {
            $this->assertNotSame('', $row['name'], 'A birthday row with no name was returned.');
            $this->assertSame($found['target']->format('d M'), $row['birthday_label']);
            $this->assertSame($found['target']->toDateString(), $row['birthday_date']);
            $this->assertNotNull(
                DB::table('birthday')->where('tiu_member_id', $row['member_id'])->value('birthday'),
                'A member was listed without a birthday row.'
            );
            if (! $this->single()) {
                $this->assertSame(
                    $found['campus'],
                    (int) DB::table('tiu_member')->where('tiu_member_id', $row['member_id'])->value('campus_id'),
                    'A birthday from another campus was listed.'
                );
            }
        }

        // Tomorrow is somebody else's birthday, never this one's.
        $tomorrow = $service->birthdaysOn($found['campus'], $found['target']->copy()->addDay());
        $this->assertEmpty(
            array_intersect($ids, $this->ids($tomorrow)),
            'A birthday is being reported on two different days.'
        );

        // ... and the same month/day comes round again next year (except 29 Feb).
        if (! ($found['target']->month === 2 && $found['target']->day === 29)) {
            $nextYear = $service->birthdaysOn($found['campus'], $found['target']->copy()->addYear());
            $this->assertEqualsCanonicalizing(
                $ids,
                array_values(array_unique($this->ids($nextYear))),
                'The same birthday should fall on the same day next year.'
            );
        }
    }

    /**
     * `--dry-run` reports what would happen and writes nothing at all: no
     * reminder log, no in-app notification, no e-mail, no Telegram message.
     */
    public function test_the_dry_run_delivers_nothing()
    {
        $logsBefore = DB::table('birthday_reminder_logs')->count();
        $notificationsBefore = DB::table('app_notifications')->where('type', 'birthday_reminder')->count();

        $exitCode = Artisan::call('birthdays:remind', ['--dry-run' => true]);

        $this->assertSame(0, $exitCode, 'The birthday command failed.');
        $this->assertStringContainsString('DRY RUN', Artisan::output(), 'The dry run did not announce itself.');

        $this->assertSame($logsBefore, DB::table('birthday_reminder_logs')->count(), 'The dry run wrote a reminder log.');
        $this->assertSame(
            $notificationsBefore,
            DB::table('app_notifications')->where('type', 'birthday_reminder')->count(),
            'The dry run raised an in-app notification.'
        );
    }

    /**
     * The "this milestone was already sent" guard is scoped by milestone and
     * birthday date, so tomorrow (or the next milestone) is not skipped after
     * today's digest. The campus is part of that guard only while the app still
     * runs more than one campus.
     */
    public function test_already_sent_is_scoped_to_milestone_and_date()
    {
        $campusIds = $this->campusIds();

        if (empty($campusIds)) {
            $this->markTestSkipped('No campus row available.');
        }

        $campusId = $campusIds[0];
        $milestone = (int) (config('birthday.days')[0] ?? 3);

        // A date far enough out that nothing can have been logged for it yet.
        $farFuture = Carbon::today()->addYears(5)->toDateString();

        $this->assertFalse(
            $this->service()->alreadySent($campusId, $milestone, $farFuture),
            'A milestone five years ahead is reported as already sent.'
        );

        // ... and the answer for today agrees with the log table itself.
        $today = Carbon::today()->toDateString();
        $logged = DB::table('birthday_reminder_logs')
            ->forCurrentCampus('campus_id', $campusId)
            ->where('days_before', $milestone)
            ->where('reminder_date', $today)
            ->where('status', 'sent')
            ->exists();

        $this->assertSame($logged, $this->service()->alreadySent($campusId, $milestone, $today));
    }

    /**
     * A wide lead time is unusual, so the command announces it: a leftover
     * `BIRTHDAY_DAYS=60` is exactly how the Welcome Center ends up with a
     * "1 member has a birthday in 60 days" digest.
     */
    public function test_a_milestone_beyond_a_week_is_announced()
    {
        $exitCode = Artisan::call('birthdays:remind', ['--days' => '60', '--dry-run' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, 'The birthday command failed: '.$output);
        $this->assertStringContainsString('Milestone(s) beyond 7 days requested: 60', $output);
        $this->assertStringContainsString('BIRTHDAY_DAYS', $output);
    }

    /**
     * A dry run must not read as a delivery. `/birthdays/status` prints this
     * output right above the real "Recent digests" table, so a "SENT ... 2 days
     * to go" preview made today's still-unsent digests look already delivered.
     */
    public function test_the_dry_run_reports_would_be_sent_not_sent()
    {
        $exitCode = Artisan::call('birthdays:remind', ['--dry-run' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, 'The birthday command failed: '.$output);
        $this->assertStringNotContainsString('SENT', $output, 'The dry run claims a digest was sent.');
        $this->assertStringContainsString('digest(s) would be sent', $output);
    }

    /**
     * The milestones are listed longest lead time first, in the same order the
     * status page shows them ("Milestones: 3, 2, 1"), not reversed to 1, 2, 3.
     */
    public function test_the_milestones_are_listed_longest_lead_time_first()
    {
        Artisan::call('birthdays:remind', ['--dry-run' => true]);

        $this->assertStringContainsString(
            'Milestones: 3 day(s) before, 2 day(s) before, 1 day(s) before',
            Artisan::output()
        );
    }
}
