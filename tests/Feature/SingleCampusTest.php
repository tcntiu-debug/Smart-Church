<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Campus;
use App\Models\FirstTimer;
use App\Models\User;
use App\Services\BirthdayReminderService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Single campus mode (`config/campus.php`, SINGLE_CAMPUS=true).
 *
 * TCN Ikorodu is one church again, so the campus filter layer is retired: the
 * ~70 call sites still read - and still work - as campus filters
 * (`->forCurrentCampus()`), but they no longer narrow anything, and no new row
 * can be stamped with a campus the church does not run. The `campus_id` columns
 * (and this switch) are kept so a second campus can be opened later:
 * SINGLE_CAMPUS=false puts every filter back without touching a controller.
 *
 * These are the assertions that hold that in place. Read-only against the live
 * rows, exactly like BirthdayReminderTest.
 */
class SingleCampusTest extends TestCase
{
    /**
     * The models that carry the global campus scope (FirstTimer, Announcement
     * today) and the rows of every campus in the data are all visible again.
     */
    public function test_the_rows_of_every_campus_are_visible_again()
    {
        $all = DB::table('first_timer')->count();

        $this->assertSame($all, FirstTimer::query()->count(), 'The global campus scope still narrows FirstTimer.');
        $this->assertSame(
            $all,
            FirstTimer::query()->forCurrentCampus('ft.campus_id', Campus::defaultId() + 999)->count(),
            'A query filtered to a campus the church does not run came back narrower.'
        );

        $this->assertSame(
            DB::table('announcements')->count(),
            Announcement::query()->count(),
            'The global campus scope still narrows Announcement.'
        );

        $this->assertNotEmpty(
            DB::table('first_timer')->whereNotNull('campus_id')->distinct()->pluck('campus_id')->all(),
            'No first timer row carries a campus at all, so this test proves nothing.'
        );
    }

    /**
     * New rows are stamped with the one campus the church runs, whatever a stale
     * form field still posts - the columns are NOT NULL and must not drift.
     */
    public function test_new_rows_are_stamped_with_the_one_campus()
    {
        $this->assertSame(Campus::defaultId(), Campus::writeId());
        $this->assertSame(Campus::defaultId(), Campus::writeId(null));
        $this->assertSame(Campus::defaultId(), Campus::writeId(''));
        $this->assertSame(Campus::defaultId(), Campus::writeId(0));
        $this->assertSame(
            Campus::defaultId(),
            Campus::writeId(2),
            'A posted campus id is still stamping new rows with a campus the church does not run.'
        );
    }

    /**
     * The birthday digest is the whole church now: every campus id resolves to the
     * same birthdays, whatever campus the digest is asked for.
     */
    public function test_the_birthday_digest_is_not_split_over_campuses()
    {
        $campusIds = $this->campusIds();

        if (count($campusIds) < 2) {
            $this->markTestSkipped('Only one campus row exists, so there is nothing to split the digest over.');
        }

        $service = app(BirthdayReminderService::class);
        $today = Carbon::today();

        for ($offset = 0; $offset <= 60; $offset++) {
            $target = $today->copy()->addDays($offset);
            $expected = $service->birthdaysOn($campusIds[0], $target);

            foreach (array_slice($campusIds, 1) as $campusId) {
                $this->assertEqualsCanonicalizing(
                    array_column($expected, 'member_id'),
                    array_column($service->birthdaysOn($campusId, $target), 'member_id'),
                    'Campus '.$campusId.' sees a different digest than campus '.$campusIds[0].' on '.$target->toDateString().'.'
                );
            }
        }
    }

    /**
     * ... and the command names one campus, not two. Campus 2 (Yaba) used to be
     * processed with no recipients and be skipped every single day.
     */
    public function test_the_reminder_command_only_processes_the_campus_the_church_runs()
    {
        $campuses = DB::table('campus')->orderBy('cid')->pluck('cname', 'cid')->all();

        if (count($campuses) < 2) {
            $this->markTestSkipped('Only one campus row exists.');
        }

        $exitCode = Artisan::call('birthdays:remind', ['--days' => '0', '--dry-run' => true]);
        $output = Artisan::output();
        $defaultName = (string) DB::table('campus')->where('cid', Campus::defaultId())->value('cname');

        $this->assertSame(0, $exitCode, 'The birthday command failed.');
        $this->assertStringContainsString($defaultName, $output, 'The digest no longer names the campus the church runs.');

        foreach ($campuses as $cid => $name) {
            if ((int) $cid === Campus::defaultId() || $name === '' || strpos($defaultName, $name) !== false) {
                continue;
            }

            $this->assertStringNotContainsString(
                $name,
                $output,
                'Campus '.$cid.' ('.$name.') is still being processed by the digest.'
            );
        }
    }

    /**
     * The switch itself: one campus, Ikorodu, which must exist as a row.
     */
    public function test_single_campus_mode_is_the_default_and_names_ikorodu()
    {
        $this->assertTrue(
            Campus::isSingle(),
            'Single campus mode is off, so every campus filter in the app is narrowing queries again.'
        );

        $this->assertSame(1, Campus::defaultId(), 'New rows are no longer stamped with campus 1 (Ikorodu).');
        $this->assertSame('Ikorodu', Campus::defaultName());
        $this->assertTrue(
            DB::table('campus')->where('cid', Campus::defaultId())->exists(),
            'The campus stamped on new rows ('.Campus::defaultId().') has no campus table row.'
        );
    }

    /**
     * The macro has to exist on the Eloquent builder (models) as well as the
     * query builder (DB::table), because both kinds of call site were converted.
     */
    public function test_the_campus_filter_macro_is_registered_on_both_builders()
    {
        $this->assertTrue(
            QueryBuilder::hasMacro('forCurrentCampus'),
            'DB::table() queries cannot call ->forCurrentCampus().'
        );
        $this->assertTrue(
            EloquentBuilder::hasGlobalMacro('forCurrentCampus'),
            'Eloquent models cannot call ->forCurrentCampus().'
        );
    }

    /**
     * Nothing resolves to a campus while single campus mode is on - not even an
     * explicit id - and a filtered query builds the same SQL as an unfiltered one.
     */
    public function test_no_query_is_narrowed_by_a_campus_while_single_campus_mode_is_on()
    {
        $this->assertNull(Campus::filterId(), 'A campus filter is still being resolved.');
        $this->assertNull(Campus::filterId(2), 'An explicit campus id still narrows a query.');
        $this->assertNull(Campus::currentId(), 'The logged-in user\'s campus still narrows a query.');

        $plain = DB::table('tiu_member')->toSql();

        $this->assertSame($plain, DB::table('tiu_member')->forCurrentCampus()->toSql());
        $this->assertSame(
            $plain,
            DB::table('tiu_member')->forCurrentCampus('campus_id', 2)->toSql(),
            'A DB::table() query is still filtered by campus.'
        );
        $this->assertSame(
            $plain,
            DB::table('tiu_member')->forCurrentCampus('tm.campus_id', 2)->toSql(),
            'A joined query is still filtered by campus.'
        );

        $eloquent = FirstTimer::query()->toSql();

        $this->assertSame($eloquent, FirstTimer::query()->forCurrentCampus()->toSql());
        $this->assertSame(
            $eloquent,
            FirstTimer::query()->forCurrentCampus('ft.campus_id', 2)->toSql(),
            'An Eloquent query is still filtered by campus.'
        );
    }

    /**
     * Every converted filter still runs. A typo in a converted SQL fragment -
     * the charts, the map, the pending-task board, the children church list -
     * fails here instead of in church.
     */
    public function test_the_pages_with_campus_filters_still_answer()
    {
        $this->actingAs($this->signedInViewer());

        $pages = [
            '/pending-tasks',
            '/report-barcharts',
            '/report-piecharts',
            '/weeklist',
            '/etask',
            '/birthdays',
            '/config-community',
            '/manage-departments',
            '/children-church/manage',
            '/admin-assign-dragdrop',
        ];

        foreach ($pages as $page) {
            $response = $this->get($page);

            $this->assertLessThan(
                500,
                $response->getStatusCode(),
                $page.' answered '.$response->getStatusCode().' (a converted campus filter broke the query).'
            );
        }
    }

    /**
     * The other half of the promise: SINGLE_CAMPUS=false brings the whole campus
     * layer back - the macro filters again, and the global scope narrows again for
     * a campus user, while a Super User still sees everything.
     */
    public function test_the_campus_layer_comes_back_when_the_switch_is_off()
    {
        config(['campus.single' => false]);

        try {
            $this->assertFalse(Campus::isSingle());
            $this->assertSame(2, Campus::filterId(2), 'An explicit campus id no longer filters.');
            $this->assertSame(2, Campus::writeId(2), 'An explicit campus id is no longer written.');
            $this->assertSame(Campus::defaultId(), Campus::writeId(null));

            $this->assertSame(
                DB::table('tiu_member')->where('campus_id', 2)->toSql(),
                DB::table('tiu_member')->forCurrentCampus('campus_id', 2)->toSql(),
                'The macro no longer applies the campus filter.'
            );

            // A campus user is scoped to their own campus again - in the macro and
            // in the global scope.
            $this->actingAs($this->memberOnCampus(2));

            $this->assertSame(2, Campus::currentId());
            $this->assertStringContainsString(
                'campus_id',
                Announcement::query()->toSql(),
                'The global campus scope no longer narrows a campus user.'
            );

            // A Super User was never scoped - not by the macro, not by the scope.
            $this->actingAs($this->memberOnCampus(2, 'Super User'));

            $this->assertNull(Campus::currentId(), 'A Super User is being scoped to a campus.');
            $this->assertStringNotContainsString(
                'campus_id',
                Announcement::query()->toSql(),
                'The global campus scope narrows a Super User.'
            );
        } finally {
            config(['campus.single' => true]);
        }
    }

    /**
     * Campus ids that exist in the campus table, in id order.
     *
     * @return array<int, int>
     */
    private function campusIds(): array
    {
        return array_map('intval', DB::table('campus')->orderBy('cid')->pluck('cid')->all());
    }

    /**
     * An in-memory auth user (tiu_member) on a campus - never saved.
     */
    private function memberOnCampus(int $campusId, string $role = 'Admin'): User
    {
        $user = new User();
        $user->tiu_member_id = 999999;
        $user->first_name = 'Campus';
        $user->last_name = 'Test';
        $user->member_role = $role;
        $user->campus_id = $campusId;

        return $user;
    }

    /**
     * A real user carrying every field CheckProfileComplete asks for, so the
     * pages in the smoke test are reached instead of the profile redirect. Super
     * Users first: they pass every role gate the pages apply.
     */
    private function signedInViewer(): User
    {
        $roles = ['Super User', 'Admin', 'Admins', 'Worker', 'Workers'];

        $viewer = $this->completeProfileQuery()->where('member_role', 'Super User')->first()
            ?: $this->completeProfileQuery()->whereIn('member_role', $roles)->first();

        if (! $viewer) {
            $this->markTestSkipped('No user with a complete profile available.');
        }

        return $viewer;
    }

    /**
     * Members whose profile satisfies CheckProfileComplete (campus excluded: it
     * is no longer required while single campus mode is on).
     */
    private function completeProfileQuery()
    {
        return User::query()
            ->whereNotNull('marital_status')
            ->whereNotNull('age')
            ->whereNotNull('occupation')
            ->whereNotNull('next_of_kin_name')
            ->whereNotNull('next_of_kin_phone')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('birthday')
                    ->whereColumn('birthday.tiu_member_id', 'tiu_member.tiu_member_id')
                    ->whereNotNull('birthday.birthday');
            });
    }
}
