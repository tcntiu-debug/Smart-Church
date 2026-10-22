<?php

namespace Tests\Feature;

use App\Http\Controllers\AirtimeController;
use App\Models\Campus;
use App\Models\TiuMember;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PDOException;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Airtime top-up (`/airtime-request`, one request per member per ISO week).
 *
 * The app layout used to carry its own copy of the top-up modal, button and
 * click handler on top of the copy in `partials/nav`, which the layout already
 * renders. Every page therefore had two `#confirmAirtimeBtn` buttons wired to
 * two delegated handlers: one click posted the request twice. The first insert
 * won, the second was rejected by `airtime_history.unique_user_week`, and the
 * member was shown the raw "Duplicate entry '1-2026-43'" SQLSTATE.
 *
 * Read-only like the other feature tests: the suite runs against the
 * application's real connection, so every write here happens inside a
 * transaction that is rolled back in a `finally` block.
 */
class AirtimeTopupTest extends TestCase
{
    /**
     * A member the profile-completeness middleware lets through (same query as
     * `MyTaskGuideContextTest`), so `/profile` renders instead of redirecting.
     */
    private function member()
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

        $member = $members->whereExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('birthday')
                ->whereColumn('birthday.tiu_member_id', 'tiu_member.tiu_member_id')
                ->whereNotNull('birthday.birthday');
        })->first();

        if (! $member) {
            $this->markTestSkipped('No member with a complete profile available to render the page.');
        }

        return $member;
    }

    /**
     * One modal, one button, one handler: two handlers would post twice.
     */
    public function test_the_topup_modal_and_handler_are_rendered_once_per_page()
    {
        $response = $this->actingAs($this->member())->get('/profile');

        $response->assertStatus(200);

        $html = $response->getContent();

        $this->assertSame(
            1,
            substr_count($html, 'id="airtimeModal"'),
            'The top-up modal is rendered twice (partials/nav plus a layout copy).'
        );
        $this->assertSame(
            1,
            substr_count($html, 'id="confirmAirtimeBtn"'),
            'The top-up button is rendered twice, so both copies can be clicked.'
        );
        $this->assertSame(
            1,
            substr_count($html, '$(document).on(\'click\', \'#confirmAirtimeBtn\''),
            'The top-up click handler is bound twice: one click posts two requests.'
        );

        $this->assertStringContainsString(
            'Airtime Topup Available 7am-6pm',
            $html,
            'The modal must advertise the same window the endpoint enforces (7AM-6PM WAT).'
        );
    }

    /**
     * The window is 07:00-18:00 WAT. The closing hour is exclusive, so 5:59 PM
     * still goes through and 6:00 PM is refused.
     */
    public function test_the_topup_window_runs_from_7am_to_6pm()
    {
        $controller = new AirtimeController();
        $method = new ReflectionMethod($controller, 'isWithinRequestWindow');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($controller, 6), '06:59 is before the window opens.');
        $this->assertTrue($method->invoke($controller, 7), '07:00 is the first allowed hour.');
        $this->assertTrue($method->invoke($controller, 12), 'Midday is inside the window.');
        $this->assertTrue($method->invoke($controller, 17), '17:59 is the last allowed hour.');
        $this->assertFalse($method->invoke($controller, 18), '18:00 closes the window.');
    }

    /**
     * The weekly limit is the unique index, not only the `exists()` check: the
     * check cannot see a request that is being inserted at the same moment.
     */
    public function test_the_database_still_allows_only_one_request_per_member_per_week()
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('unique_user_week is read from MySQL information_schema.');
        }

        $index = DB::select("SHOW INDEX FROM airtime_history WHERE Key_name = 'unique_user_week'");

        $this->assertNotEmpty($index, 'airtime_history lost its one-request-per-week guard.');

        $columns = collect($index)->sortBy('Seq_in_index')->pluck('Column_name')->values()->all();
        $unique = collect($index)->pluck('Non_unique')->unique()->values()->all();

        $this->assertSame(['user_id', 'year', 'week'], $columns, 'unique_user_week must cover member, year and week.');
        $this->assertSame([0], $unique, 'unique_user_week must stay unique, otherwise a race inserts duplicates.');
    }

    /**
     * The duplicate-key rejection is a repeat request, not a server fault: the
     * controller answers it with the same friendly message instead of echoing
     * the SQLSTATE to the member.
     */
    public function test_a_duplicate_key_is_answered_like_any_repeat_request()
    {
        $duplicate = new QueryException(
            'insert into `airtime_history` (`user_id`, `year`, `week`) values (?, ?, ?)',
            [1, 2026, 43],
            new PDOException("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1-2026-43' for key 'unique_user_week'")
        );

        $other = new QueryException(
            'insert into `airtime_history` (`user_id`, `year`, `week`) values (?, ?, ?)',
            [1, 2026, 43],
            new PDOException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'smart_church.airtime_history' doesn't exist")
        );

        $controller = new AirtimeController();
        $method = new ReflectionMethod($controller, 'isDuplicateWeek');
        $method->setAccessible(true);

        $this->assertTrue(
            $method->invoke($controller, $duplicate),
            'A duplicate week must be treated as "already requested", not as a database error.'
        );
        $this->assertFalse(
            $method->invoke($controller, $other),
            'A real database fault must not be reported as a repeat request.'
        );
    }

    /**
     * Whatever goes wrong, the member is never shown the SQL.
     */
    public function test_an_unknown_member_does_not_get_a_database_error()
    {
        $response = $this->actingAs($this->member())
            ->postJson('/airtime-request', ['user_id' => 999999999]);

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'User not found',
        ]);

        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('Database error', $response->getContent());
    }

    /**
     * Production runs `config:cache`. A cached config means Laravel never loads
     * .env, so `env('TELEGRAM_BOT_TOKEN')` returned NULL on the live site and
     * the controller skipped every airtime alert with a quiet info log - the
     * requests were saved, the church was simply never told about them.
     */
    public function test_the_airtime_alert_reads_its_credentials_through_the_config_cache()
    {
        $source = file_get_contents(app_path('Http/Controllers/AirtimeController.php'));

        $this->assertStringNotContainsString(
            'env(',
            $source,
            'The controller reads the environment directly again; with config:cache that returns NULL and the alert is dropped.'
        );

        $telegram = config('services.telegram');

        $this->assertIsArray($telegram, 'services.telegram must exist so the bot credentials survive config:cache.');
        $this->assertArrayHasKey('bot_token', $telegram, 'services.telegram.bot_token is missing.');
        $this->assertArrayHasKey('chat_id', $telegram, 'services.telegram.chat_id is missing.');
    }

    /**
     * "You already requested this week" is only believable when it names the
     * request. The member (and the admin answering the phone) can then compare
     * the date with /airtime_admin.
     */
    public function test_a_repeat_request_names_the_request_already_on_file()
    {
        $controller = new AirtimeController();
        $method = new ReflectionMethod($controller, 'alreadyRequestedResponse');
        $method->setAccessible(true);

        $response = $method->invoke($controller, (object) [
            'id' => 12,
            'year' => 2026,
            'week' => 43,
            'requested_at' => '2026-10-19 09:15:00', // stored in UTC
            'status' => 'pending',
        ]);

        $payload = $response->getData(true);

        $this->assertFalse($payload['success']);
        $this->assertTrue($payload['already_requested']);
        $this->assertSame('2026-10-19 09:15:00', $payload['requested_at']);
        $this->assertStringContainsString(
            'already requested airtime this week on Mon, 19 Oct 2026 at 10:15 AM',
            $payload['message'],
            'The repeat message must quote the existing request in Lagos time (UTC+1).'
        );
    }

    /**
     * The weekly limit is keyed on the ISO year (`o`), not the calendar year
     * (`Y`). Between 29 December and 3 January the two disagree, and pairing
     * `Y` with `W` filed week 1 of the new year under the old one - the exact
     * dates on which members were refused for a week they never asked for.
     */
    public function test_the_week_limit_keys_on_the_iso_year_not_the_calendar_year()
    {
        $controller = new AirtimeController();
        $method = new ReflectionMethod($controller, 'currentWeek');
        $method->setAccessible(true);

        $this->assertSame(
            [2026, 43],
            $method->invoke($controller, Carbon::parse('2026-10-22 10:00:00', 'Africa/Lagos')),
            '22 Oct 2026 is ISO week 43 of 2026.'
        );
        $this->assertSame(
            [2026, 1],
            $method->invoke($controller, Carbon::parse('2025-12-29 10:00:00', 'Africa/Lagos')),
            '29 Dec 2025 belongs to ISO week 1 of 2026, not week 1 of 2025.'
        );
        $this->assertSame(
            [2026, 53],
            $method->invoke($controller, Carbon::parse('2027-01-01 10:00:00', 'Africa/Lagos')),
            '1 Jan 2027 still falls in ISO week 53 of 2026.'
        );
    }

    /**
     * Only a clash on unique_user_week is a repeat request. Any other duplicate
     * key is a real fault: rewording it as "you already asked" hid the fault and
     * made the member look like a repeat requester.
     */
    public function test_a_duplicate_on_another_key_is_not_a_repeat_request()
    {
        $controller = new AirtimeController();
        $method = new ReflectionMethod($controller, 'isDuplicateWeek');
        $method->setAccessible(true);

        $otherKey = new QueryException(
            'insert into `airtime_history` (`user_id`, `year`, `week`) values (?, ?, ?)',
            [1, 2026, 43],
            new PDOException("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1' for key 'airtime_history.unique_some_other_key'")
        );

        $tupleOnly = new QueryException(
            'insert into `airtime_history` (`user_id`, `year`, `week`) values (?, ?, ?)',
            [1, 2026, 43],
            new PDOException("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1-2026-43'")
        );

        $this->assertFalse(
            $method->invoke($controller, $otherKey),
            'A duplicate on another index must be logged as a fault, not reported as a repeat request.'
        );
        $this->assertTrue(
            $method->invoke($controller, $tupleOnly),
            'A duplicate naming the (user, year, week) tuple is still the weekly limit.'
        );
    }

    /**
     * The whole click path, end to end: one click saves one row and sends one
     * alert; the second click says which request is on file instead of failing
     * mute. Everything is rolled back, so the database is left as it was found.
     */
    public function test_one_click_saves_one_request_and_the_second_click_names_it()
    {
        $member = $this->member();
        $now = Carbon::parse('2026-10-22 10:00:00', 'Africa/Lagos'); // inside 7AM-6PM WAT

        $year = (int) $now->format('o');
        $week = (int) $now->format('W');

        Http::fake();

        DB::beginTransaction();

        try {
            Carbon::setTestNow($now);

            // Start from a clean week for this member; the rollback below
            // restores whatever was here before.
            DB::table('airtime_history')
                ->where('user_id', $member->tiu_member_id)
                ->where('year', $year)
                ->where('week', $week)
                ->delete();

            $first = $this->actingAs($member)->postJson('/airtime-request');
            $first->assertStatus(200);
            $this->assertTrue(
                $first->json('success'),
                'The first click must be accepted, got: ' . $first->getContent()
            );

            $row = DB::table('airtime_history')
                ->where('user_id', $member->tiu_member_id)
                ->where('year', $year)
                ->where('week', $week)
                ->first();

            $this->assertNotNull($row, 'The accepted request was not written to airtime_history.');

            $second = $this->actingAs($member)->postJson('/airtime-request');
            $second->assertStatus(200);
            $this->assertFalse($second->json('success'));
            $this->assertTrue($second->json('already_requested'));
            $this->assertStringContainsString(
                'already requested airtime this week on Thu, 22 Oct 2026 at 10:00 AM',
                (string) $second->json('message')
            );

            $this->assertSame(
                1,
                DB::table('airtime_history')
                    ->where('user_id', $member->tiu_member_id)
                    ->where('year', $year)
                    ->where('week', $week)
                    ->count(),
                'The member must hold exactly one request for the week after two clicks.'
            );

            Http::assertSentCount(1);
        } finally {
            DB::rollBack();
            Carbon::setTestNow();
        }
    }
}
