<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Weekly Call List (`/weeklist` -> "View Call List" modal).
 *
 * Every first timer inside the selected window is handed to a caller from the
 * "Touch Point 7" team, round-robin and gender-matched: male first timers
 * rotate through the male callers, female first timers through the female
 * callers (see WeekListController::buildCallerData()).
 *
 * Read-only on purpose: the suite runs against the application's real
 * connection, so these tests never POST (that would rewrite
 * `caller_assignments`).
 */
class WeekListCallerAssignmentTest extends TestCase
{
    /**
     * Sub-group whose members staff the weekly call list.
     */
    private const CALLER_SUBGROUP = 'Touch Point 7';

    /**
     * A window wide enough to always contain the seeded first timers, so the
     * assertions do not depend on "today" being a Sunday.
     */
    private const WINDOW = ['start_date' => '2000-01-01', 'end_date' => '2100-12-31'];

    /**
     * Members with the profile fields CheckProfileComplete demands, otherwise
     * the request is redirected to the profile page instead of the week list.
     */
    private function memberQuery()
    {
        return \App\Models\TiuMember::query()
            ->whereNotNull('marital_status')
            ->whereNotNull('age')
            ->whereNotNull('occupation')
            ->whereNotNull('next_of_kin_name')
            ->whereNotNull('next_of_kin_phone')
            ->whereNotNull('campus_id')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('birthday')
                    ->whereColumn('birthday.tiu_member_id', 'tiu_member.tiu_member_id')
                    ->whereNotNull('birthday.birthday');
            });
    }

    /**
     * A member allowed to open the week list. Super Users first, so the campus
     * filter cannot empty the selected window.
     */
    private function viewer()
    {
        $roles = ['Worker', 'Admin', 'Admins', 'Workers', 'Super User'];

        $viewer = $this->memberQuery()->where('member_role', 'Super User')->first()
            ?: $this->memberQuery()->whereIn('member_role', $roles)->first();

        if (! $viewer) {
            $this->markTestSkipped('No Worker/Admin/Super User with a complete profile available.');
        }

        return $viewer;
    }

    /**
     * The caller pool the modal is supposed to offer, in round-robin order.
     */
    private function callers()
    {
        return DB::table('tiu_member')
            ->where('subgroup', 'like', '%' . self::CALLER_SUBGROUP . '%')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['tiu_member_id', 'first_name', 'last_name', 'gender']);
    }

    private function fullName($member): string
    {
        return trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? ''));
    }

    /**
     * The `groupedPrintData` payload the modal's "Print List" button renders
     * from: caller name => list of people to call.
     */
    private function groupedPrintData(string $html): array
    {
        preg_match('/var groupedPrintData = (.+?);\r?\n\s*var printTitle/s', $html, $matches);

        $this->assertNotEmpty($matches, 'Could not find the groupedPrintData payload in the week list.');

        return json_decode($matches[1], true) ?: [];
    }

    /**
     * Option ids offered by the modal's caller dropdown.
     */
    private function callerOptionIds(string $html): array
    {
        $this->assertMatchesRegularExpression(
            '/class="form-control caller-select"/',
            $html,
            'The caller dropdown was not rendered - is the selected window empty for that campus?'
        );

        preg_match('/class="form-control caller-select"[\s\S]*?<\/select>/', $html, $select);
        preg_match_all('/<option value="(\d+)"/', $select[0], $ids);

        $ids = array_map('intval', $ids[1]);
        sort($ids);

        return $ids;
    }

    private function weekListResponse()
    {
        $response = $this->actingAs($this->viewer())
            ->get('/weeklist?' . http_build_query(self::WINDOW));

        $response->assertStatus(200);

        return $response;
    }

    /**
     * The dropdown - i.e. what a worker can pick manually - is the Touch Point
     * 7 team and nothing else.
     */
    public function test_the_modal_only_offers_the_touch_point_7_team()
    {
        $callers = $this->callers();

        if ($callers->isEmpty()) {
            $this->markTestSkipped('No members in the "' . self::CALLER_SUBGROUP . '" subgroup available.');
        }

        $response = $this->weekListResponse();
        $expected = $callers->pluck('tiu_member_id')->map('intval')->sort()->values()->all();

        $this->assertSame($expected, $this->callerOptionIds($response->getContent()));

        // The team the feature used to rely on must no longer be offered.
        $response->assertDontSee('Touch Point 6');
    }

    /**
     * Automatic assignment: male first timers rotate through the male callers,
     * female first timers through the female callers, and the load is spread
     * evenly across the window instead of piling up on one caller.
     */
    public function test_automatic_assignment_keeps_genders_together_round_robin()
    {
        $callers = $this->callers();

        if ($callers->isEmpty()) {
            $this->markTestSkipped('No members in the "' . self::CALLER_SUBGROUP . '" subgroup available.');
        }

        $callerGenders = [];
        foreach ($callers as $caller) {
            $callerGenders[$this->fullName($caller)] = strtolower(trim((string) $caller->gender));
        }

        $data = $this->groupedPrintData($this->weekListResponse()->getContent());

        $this->assertNotEmpty($data, 'The call list is empty for the whole window.');
        $this->assertArrayNotHasKey(
            'Unassigned',
            $data,
            'Callers are available, so every first timer should get one.'
        );

        // Per-lane head count, used for the rotation check (unknown genders
        // have no lane of their own and are ignored).
        $laneCounts = ['male' => [], 'female' => []];

        foreach ($data as $callerName => $persons) {
            $this->assertArrayHasKey(
                $callerName,
                $callerGenders,
                '"' . $callerName . '" is not a "' . self::CALLER_SUBGROUP . '" member, so the round-robin pool has drifted.'
            );

            $callerLane = $callerGenders[$callerName];

            foreach ($persons as $person) {
                $lane = strtolower(trim((string) $person['gender']));

                if (! in_array($lane, ['male', 'female'], true)) {
                    continue;
                }

                $this->assertSame(
                    $lane,
                    $callerLane,
                    $person['name'] . ' (' . $lane . ') was handed to a ' . $callerLane . ' caller (' . $callerName . ').'
                );

                $laneCounts[$lane][$callerName] = ($laneCounts[$lane][$callerName] ?? 0) + 1;
            }
        }

        // Manual overrides legitimately skew the split, so only the untouched
        // case can be checked for a perfect rotation.
        if (DB::table('caller_assignments')->exists()) {
            return;
        }

        foreach ($laneCounts as $lane => $counts) {
            if (count($counts) < 2) {
                continue;
            }

            $this->assertLessThanOrEqual(
                1,
                max($counts) - min($counts),
                'The ' . $lane . ' lane is not a round-robin: ' . json_encode($counts)
            );
        }
    }
}
