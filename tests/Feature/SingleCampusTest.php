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
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Single campus mode (`config/campus.php`, SINGLE_CAMPUS=true).
 *
 * TCN Ikorodu is one church again, so the campus layer is retired: the ~70 call
 * sites still read - and still work - as campus filters (`->forCurrentCampus()`),
 * but they no longer narrow anything, new rows are left without a campus
 * (`Campus::writeId()` is null) and the campus_id that was already on the rows is
 * emptied: 2026_10_14_000002 makes the columns nullable, 2026_10_14_000003
 * retires the data, and both ship through `app:sync-schema` / `migrate`. The
 * column (and this switch) are kept so a second campus can be opened later:
 * SINGLE_CAMPUS=false puts every filter and every stamp back without touching a
 * controller.
 *
 * These are the assertions that hold that in place. Mostly read-only against the
 * live rows, exactly like BirthdayReminderTest - the exceptions are the task row
 * and the member the row-identity and registration tests create, and they clean
 * up after themselves.
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
    }

    /**
     * New rows are left without a campus: the picker is gone, so there is nothing
     * to stamp - not for a blank field and not for a stale one that still posts a
     * campus the church does not run (the columns allow the emptiness, see
     * 2026_10_14_000002).
     */
    public function test_new_rows_are_left_without_a_campus()
    {
        $this->assertNull(Campus::writeId(), 'A new row is still being stamped with a campus.');
        $this->assertNull(Campus::writeId(null));
        $this->assertNull(Campus::writeId(''));
        $this->assertNull(Campus::writeId(0));
        $this->assertNull(
            Campus::writeId(2),
            'A posted campus id is still stamping new rows with a campus the church does not run.'
        );
    }

    /**
     * The other half of leaving the column empty: the schema has to allow it, and
     * the data that was already there has to be retired - otherwise row identity
     * that reads a row back by campus looks for a campus no write produces.
     *
     * Both halves ship as migrations (2026_10_14_000002 makes the columns
     * nullable, _000003 empties them), applied by `app:sync-schema` / `migrate`
     * before the code that writes NULL runs.
     */
    public function test_the_campus_columns_and_data_are_retired()
    {
        foreach ($this->campusColumns() as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $this->assertSame(
                'YES',
                $this->nullableFlag($table),
                "{$table}.campus_id is still NOT NULL, so a row written without a campus is rejected."
            );
        }

        foreach (['tiu_member', 'first_timer', 'task', 'communities', 'department'] as $table) {
            $left = DB::table($table)->whereNotNull('campus_id')->count();

            $this->assertSame(
                0,
                $left,
                "{$table} still carries a campus on {$left} row(s): run the campus retirement migration (2026_10_14_000003)."
            );
        }
    }

    /**
     * The map lists the communities of the church. It used to run
     * `AND c.campus_id = ?`, which against the retired (NULL) column matches
     * nothing - the page would come back empty.
     */
    public function test_the_map_lists_every_community_after_the_campus_came_out()
    {
        $expected = DB::table('communities')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->count();

        if ($expected < 1) {
            $this->markTestSkipped('No community carries coordinates, so the map has nothing to draw.');
        }

        $response = $this->actingAs($this->signedInViewer())->get('/map-view');

        $response->assertOk();

        $communities = json_decode($response->original->getData()['communities_json'] ?? '[]', true);

        $this->assertCount(
            $expected,
            $communities,
            'The map lost communities when the campus data was retired.'
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

        // Row identity does not soften: the row this app writes carries no campus,
        // so that - and not a campus that no longer exists - is what a
        // read-then-rewrite has to match.
        $this->assertSame(
            DB::table('task')->whereNull('campus_id')->toSql(),
            Campus::matchQuery(DB::table('task'), 'campus_id', 2)->toSql(),
            'Row identity is matched against a campus that no longer exists.'
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
            '/profile',
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
     * A week-task row written without a campus is the row the delete finds. This
     * is row identity, not a filter: looking the row up by the user's (empty)
     * campus still has to match it, while a row that does carry a campus belongs
     * to somebody else and must survive.
     */
    public function test_a_task_written_without_a_campus_is_the_row_that_gets_deleted()
    {
        $mine = 900;   // no real week uses these wids
        $other = 901;
        $departmentId = 999;

        DB::table('task')->insert([
            ['wid' => $mine, 'department_id' => $departmentId, 'campus_id' => null, 'questions' => json_encode(['1' => ['text' => 'Mine']])],
            ['wid' => $other, 'department_id' => $departmentId, 'campus_id' => Campus::defaultId(), 'questions' => json_encode(['1' => ['text' => 'Somebody else']])],
        ]);

        try {
            $this->actingAs($this->signedInViewer());

            // campus_id is empty, exactly as the (campus-less) form posts it.
            $response = $this->get('/etask/delete?week='.$mine.'&question=1&campus_id=&department_id='.$departmentId);

            $response->assertRedirect();
            $this->assertSame(
                'Question deleted successfully.',
                $response->getSession()->get('success'),
                'The delete did not find the row it wrote itself.'
            );
            $this->assertSame(0, DB::table('task')->where('wid', $mine)->count(), 'The task row survived the delete.');

            $survivor = $this->get('/etask/delete?week='.$other.'&question=1&campus_id=&department_id='.$departmentId);

            $survivor->assertRedirect();
            $this->assertSame(
                1,
                DB::table('task')->where('wid', $other)->count(),
                'A row that still carries a campus was deleted as if it were the blank one.'
            );
        } finally {
            DB::table('task')->whereIn('wid', [$mine, $other])->delete();
        }
    }

    /**
     * A registration goes through without a campus: neither form posts one, the
     * server does not demand one, and the member lands in the table with the
     * column empty. The profile form drops the picker too (the select2 init that
     * still names the selector is harmless).
     */
    public function test_a_member_is_registered_without_a_campus()
    {
        $publicForm = $this->get('/mregister');
        $publicForm->assertOk();
        $publicForm->assertDontSee('campus_id', false);
        $publicForm->assertDontSee('campusId', false);

        $adminForm = $this->actingAs($this->signedInViewer())->get(route('member.register'));
        $adminForm->assertOk();
        $adminForm->assertDontSee('campus_id', false);

        $profileForm = $this->get('/profile');
        $profileForm->assertOk();
        $profileForm->assertDontSee('<select name="campus_id"', false);

        $churchTypeId = DB::table('church_type')->where('church_type_name', 'Adult')->value('id')
            ?: DB::table('church_type')->value('id');
        $communityId = DB::table('communities')->value('id');

        if (! $churchTypeId || ! $communityId) {
            $this->markTestSkipped('No church type / community to register against.');
        }

        $email = 'single-campus-'.uniqid().'@example.test';

        try {
            $response = $this->post('/mregister', [
                'first_name' => 'Campus',
                'last_name' => 'Retired',
                'phone_number' => '08000000000',
                'email' => $email,
                'gender' => 'Male',
                'residential_address' => 'Test address',
                'church_type_id' => $churchTypeId,
                'community_id' => $communityId,
            ]);

            $response->assertOk();
            $this->assertTrue(
                (bool) ($response->json('success') ?? false),
                'Registering without a campus was rejected: '.json_encode($response->json())
            );

            $member = DB::table('tiu_member')->where('email', $email)->first();

            $this->assertNotNull($member, 'The registration did not write the member.');
            $this->assertNull($member->campus_id, 'The registration stamped a campus on the new member.');
        } finally {
            DB::table('tiu_member')->where('email', $email)->delete();
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

            // Row identity matches on the campus that gets written again, blank
            // field or not.
            $this->assertSame(
                DB::table('task')->where('campus_id', Campus::defaultId())->toSql(),
                Campus::matchQuery(DB::table('task'), 'campus_id', null)->toSql(),
                'Row identity no longer matches on the campus that gets written.'
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
     * Every table that carries a campus_id column, in the order the migration
     * that made them nullable lists them.
     *
     * @return array<int, string>
     */
    private function campusColumns(): array
    {
        return [
            'announcements',
            'birthday_reminder_logs',
            'children_church',
            'communities',
            'department',
            'first_timer',
            'first_time_view_limit',
            'sub_group',
            'tiu_member',
        ];
    }

    /**
     * What information_schema says about `$table.campus_id`: 'YES' when the
     * column accepts a blank campus, 'NO' while it is still NOT NULL.
     */
    private function nullableFlag(string $table): ?string
    {
        $column = DB::selectOne(
            'SELECT IS_NULLABLE as nullable FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, 'campus_id']
        );

        return $column->nullable ?? null;
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
