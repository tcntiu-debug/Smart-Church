<?php

namespace Tests\Feature;

use App\Http\Controllers\AirtimeController;
use App\Models\Campus;
use App\Models\TiuMember;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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
 * application's real connection, so nothing here inserts a request.
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
}
