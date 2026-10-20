<?php

namespace Tests\Feature;

use App\Services\SubgroupAssignmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Weekly Call List (`/weeklist` -> "View Call List" modal).
 *
 * Every first timer inside the selected window is handed to a caller from the
 * touch points chosen in the page's multi-select filter (Touch Point 7 by
 * default), round-robin and gender-matched: male first timers rotate through
 * the male callers, female first timers through the female callers (see
 * WeekListController::buildCallerData()).
 *
 * Read-only on purpose: the suite runs against the application's real
 * connection, so these tests never POST (that would rewrite
 * `caller_assignments`).
 */
class WeekListCallerAssignmentTest extends TestCase
{
    /**
     * Sub-group offered when no touch point is chosen (first visit / Reset).
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
     *
     * The campus is only demanded while the church runs more than one (single
     * campus mode retired the data - docs/SINGLE-CAMPUS.md).
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
     * The caller pool the modal is supposed to offer, in round-robin order,
     * for one or more touch points.
     *
     * @param  array<int, string>  $touchpoints
     */
    private function callers(array $touchpoints = [self::CALLER_SUBGROUP])
    {
        $query = DB::table('tiu_member');

        $query->where(function ($nested) use ($touchpoints) {
            foreach ($touchpoints as $touchpoint) {
                foreach (SubgroupAssignmentService::likePatterns(
                    SubgroupAssignmentService::TOUCHPOINT_DEPARTMENT_ID,
                    $touchpoint
                ) as $pattern) {
                    $nested->orWhere('subgroup', 'like', $pattern);
                }
            }
        });

        return $query
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['tiu_member_id', 'first_name', 'last_name', 'gender', 'phone_number']);
    }

    /**
     * Touch points the filter is supposed to list, straight from sub_group.
     *
     * @return array<int, string>
     */
    private function touchpointNames(): array
    {
        return DB::table('sub_group')
            ->where('department_id', SubgroupAssignmentService::TOUCHPOINT_DEPARTMENT_ID)
            ->orderBy('subid')
            ->pluck('sub_group_name')
            ->all();
    }

    private function fullName($member): string
    {
        return trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? ''));
    }

    /**
     * The first timers whose caller is stored in `caller_assignments` - a pick
     * made by hand in the modal, which the lane rule does not apply to.
     *
     * Keyed by the same display name the print payload carries, so a rendered
     * person can be recognised as a manual pick.
     *
     * @return array<string, int>
     */
    private function manualPickNames(): array
    {
        if (! Schema::hasTable('caller_assignments')) {
            return [];
        }

        return DB::table('caller_assignments')
            ->join('first_timer', 'first_timer.first_timer_id', '=', 'caller_assignments.first_timer_id')
            ->get(['first_timer.first_name', 'first_timer.last_name'])
            ->map(function ($row) {
                return trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
            })
            ->flip()
            ->all();
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
     * The `callerWhatsApp` payload the modal's "Print List" button renders the
     * green WhatsApp buttons from: caller name => [phone, url].
     */
    private function callerWhatsAppData(string $html): array
    {
        preg_match('/var callerWhatsApp = (.+?);\r?\n/', $html, $matches);

        $this->assertNotEmpty($matches, 'Could not find the callerWhatsApp payload in the week list.');

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

    /**
     * The multi-select touch point filter: option value => is it selected?
     *
     * @return array<string, bool>
     */
    private function touchpointFilterOptions(string $html): array
    {
        preg_match('/<select[^>]*name="touchpoints\[\]"[^>]*>([\s\S]*?)<\/select>/', $html, $select);

        $this->assertNotEmpty($select, 'The touch point filter was not rendered.');

        preg_match_all('/<option value="([^"]*)"([^>]*)>/', $select[1], $matches, PREG_SET_ORDER);

        $options = [];
        foreach ($matches as $match) {
            $options[$match[1]] = strpos($match[2], 'selected') !== false;
        }

        return $options;
    }

    /**
     * The multi-select guest type filter: option value => is it selected?
     *
     * @return array<string, bool>
     */
    private function guestTypeFilterOptions(string $html): array
    {
        preg_match('/<select[^>]*name="guest_type\[\]"[^>]*>([\s\S]*?)<\/select>/', $html, $select);

        $this->assertNotEmpty($select, 'The guest type filter was not rendered.');

        preg_match_all('/<option value="([^"]*)"([^>]*)>/', $select[1], $matches, PREG_SET_ORDER);

        $options = [];
        foreach ($matches as $match) {
            $options[html_entity_decode($match[1])] = strpos($match[2], 'selected') !== false;
        }

        return $options;
    }

    /**
     * The guest types of the rows the first timer table is showing.
     *
     * String parsing on purpose: the week list can hold thousands of rows, and a
     * regex over that much HTML blows through pcre.backtrack_limit.
     *
     * @return array<int, string>
     */
    private function firstTableGuestTypes(string $html): array
    {
        $start = strpos($html, '<table id="first-timers-table"');

        $this->assertNotFalse($start, 'The first timer table was not rendered.');

        $end = strpos($html, '</table>', $start);

        $this->assertNotFalse($end, 'The first timer table was not closed.');

        $table = substr($html, $start, $end - $start);

        $types = [];
        foreach (explode('<tr', $table) as $row) {
            if (substr_count($row, '<td') < 3) {
                continue; // header row
            }

            // The third cell is the guest type column.
            $cells = explode('<td', $row);
            $cell = $cells[3] ?? '';

            $tagEnd = strpos($cell, '>');
            $cellEnd = strpos($cell, '</td>');

            if ($tagEnd === false || $cellEnd === false) {
                continue;
            }

            $value = trim(strip_tags(substr($cell, $tagEnd + 1, $cellEnd - $tagEnd - 1)));

            if ($value !== '') {
                $types[] = html_entity_decode($value);
            }
        }

        return $types;
    }

    /**
     * @param  array<int, string>  $touchpoints
     * @param  array<int, string>  $guestTypes
     */
    private function weekListResponse(array $touchpoints = [], array $guestTypes = [])
    {
        $query = self::WINDOW;

        if ($touchpoints !== []) {
            $query['touchpoints'] = $touchpoints;
        }

        if ($guestTypes !== []) {
            $query['guest_type'] = $guestTypes;
        }

        $response = $this->actingAs($this->viewer())
            ->get('/weeklist?' . http_build_query($query));

        $response->assertStatus(200);

        return $response;
    }

    /**
     * The page offers every touch point of the department, and Touch Point 7 is
     * what a first visit (or Reset) still gets.
     */
    public function test_the_filter_lists_every_touch_point_and_defaults_to_touch_point_7()
    {
        $expected = $this->touchpointNames();

        if ($expected === []) {
            $this->markTestSkipped('No touch points (department 23 sub groups) available.');
        }

        $options = $this->touchpointFilterOptions($this->weekListResponse()->getContent());

        $this->assertEqualsCanonicalizing($expected, array_keys($options));

        $selected = array_keys(array_filter($options));

        $this->assertSame(
            [self::CALLER_SUBGROUP],
            $selected,
            'Only the default team should be selected on a first visit.'
        );
    }

    /**
     * The touch point filter is a chip-style dropdown, not a native list box:
     * Select2 is loaded and wired to the picker, and the markup keeps the
     * removable-chip wrapper the page styles (dark twins included).
     */
    public function test_the_touch_point_filter_renders_as_chips()
    {
        $response = $this->weekListResponse();
        $html = $response->getContent();

        $response->assertSee('select2.min.css', false);
        $response->assertSee('select2.min.js', false);
        $response->assertSee("$('#touchpoints').select2(", false);
        $this->assertMatchesRegularExpression(
            '/<div class="chip-picker">\s*<select[^>]*name="touchpoints\[\]"[^>]*multiple/',
            $html
        );
        $this->assertStringContainsString('data-placeholder="Select touch point(s)"', $html);
    }

    /**
     * Guest Type is a chip-style multi-select too: the native select accepts
     * several values and Select2 turns them into the same removable chips.
     */
    public function test_the_guest_type_filter_renders_as_chips()
    {
        $response = $this->weekListResponse();
        $html = $response->getContent();

        $response->assertSee("$('#guest_type').select2(", false);
        $this->assertMatchesRegularExpression(
            '/<div class="chip-picker">\s*<select[^>]*name="guest_type\[\]"[^>]*multiple/',
            $html
        );
        $this->assertStringContainsString('data-placeholder="All Guest Types"', $html);
    }

    /**
     * One or more guest types can be filtered at once: only the chosen types
     * are listed, and both chips are selected in the dropdown.
     */
    public function test_multiple_guest_types_can_be_filtered_at_once()
    {
        $available = array_values(array_unique(array_filter(
            $this->firstTableGuestTypes($this->weekListResponse()->getContent())
        )));

        if (count($available) < 2) {
            $this->markTestSkipped('Fewer than two guest types are visible in the week list window.');
        }

        $chosen = array_slice($available, 0, 2);

        $html = $this->weekListResponse([], $chosen)->getContent();

        $shown = array_values(array_unique($this->firstTableGuestTypes($html)));
        sort($shown);
        sort($chosen);

        $this->assertSame($chosen, $shown, 'The list should show exactly the chosen guest types.');

        $options = $this->guestTypeFilterOptions($html);

        foreach ($chosen as $type) {
            $this->assertArrayHasKey($type, $options, '"' . $type . '" is missing from the guest type filter.');
            $this->assertTrue($options[$type], '"' . $type . '" is not shown as selected.');
        }

        $this->assertCount(
            2,
            array_keys(array_filter($options)),
            'Only the chosen guest types should be selected.'
        );
    }

    /**
     * Old links that carry a single `guest_type` value keep filtering.
     */
    public function test_a_single_guest_type_link_still_filters()
    {
        $available = array_values(array_unique(array_filter(
            $this->firstTableGuestTypes($this->weekListResponse()->getContent())
        )));

        if ($available === []) {
            $this->markTestSkipped('No guest types are visible in the week list window.');
        }

        $type = $available[0];

        $response = $this->actingAs($this->viewer())->get(
            '/weeklist?' . http_build_query(self::WINDOW + ['guest_type' => $type])
        );

        $response->assertOk();

        $this->assertSame(
            [$type],
            array_values(array_unique($this->firstTableGuestTypes($response->getContent()))),
            'A single guest type in the URL should still filter to that type.'
        );
    }

    /**
     * Picking another team really switches the caller pool: the modal offers
     * that team's members and none of the default team's.
     */
    public function test_choosing_another_touch_point_switches_the_caller_pool()
    {
        $touchpoints = $this->touchpointNames();

        if (! in_array('Touch Point 6', $touchpoints, true)) {
            $this->markTestSkipped('"Touch Point 6" is not available to test the filter with.');
        }

        $callers = $this->callers(['Touch Point 6']);

        if ($callers->isEmpty()) {
            $this->markTestSkipped('No members in the "Touch Point 6" touch point available.');
        }

        $response = $this->weekListResponse(['Touch Point 6']);
        $html = $response->getContent();

        $expected = $callers->pluck('tiu_member_id')->map('intval')->sort()->values()->all();
        $this->assertSame($expected, $this->callerOptionIds($html));

        $options = $this->touchpointFilterOptions($html);
        $this->assertTrue($options['Touch Point 6'], 'The chosen team is not shown as selected.');
        $this->assertFalse($options[self::CALLER_SUBGROUP], 'The default team is still selected.');

        $response->assertSee('Caller team:');
        $response->assertSee('Touch Point 6');
    }

    /**
     * More than one team can staff the call list at the same time; the pool is
     * the union of the selected touch points.
     */
    public function test_multiple_touch_points_are_combined_into_one_pool()
    {
        $touchpoints = $this->touchpointNames();

        if (! in_array('Touch Point 6', $touchpoints, true)) {
            $this->markTestSkipped('"Touch Point 6" is not available to test the filter with.');
        }

        $chosen = ['Touch Point 6', self::CALLER_SUBGROUP];
        $callers = $this->callers($chosen);

        if ($callers->isEmpty()) {
            $this->markTestSkipped('No members in the selected touch points available.');
        }

        $html = $this->weekListResponse($chosen)->getContent();

        $expected = $callers->pluck('tiu_member_id')->map('intval')->sort()->values()->all();
        $this->assertSame($expected, $this->callerOptionIds($html));

        $options = $this->touchpointFilterOptions($html);
        $this->assertTrue($options['Touch Point 6']);
        $this->assertTrue($options[self::CALLER_SUBGROUP]);

        $this->assertCount(
            count(array_filter($options)),
            array_unique(array_map('strval', $chosen)),
            'The page should not add a team that was not chosen.'
        );
    }

    /**
     * The dropdown - i.e. what a worker can pick manually - defaults to the
     * Touch Point 7 team and nothing else.
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

        // First timers whose caller was picked by hand in the modal. The modal
        // offers every caller, so a manual pick may cross the lanes on purpose
        // (the round-robin itself never does - WeekListController::buildCallerData
        // only falls back to the whole team when the gender is blank).
        $manualPicks = $this->manualPickNames();

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

                if (isset($manualPicks[$person['name']])) {
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

    /**
     * The printed call list carries one WhatsApp button per caller. Its message
     * greets the caller by the gender stored on their profile ("Good day Sir" /
     * "Good day Ma", or the plain name when it is still blank), names the first
     * timers that caller was handed, and reminds them about the TIU app, the
     * airtime top-up, feedback, and closes with a blessing.
     */
    public function test_every_caller_gets_a_prefilled_whatsapp_message_on_the_printed_list()
    {
        $callers = $this->callers()->keyBy(function ($caller) {
            return $this->fullName($caller);
        });

        if ($callers->isEmpty()) {
            $this->markTestSkipped('No members in the "' . self::CALLER_SUBGROUP . '" subgroup available.');
        }

        $html = $this->weekListResponse()->getContent();
        $groups = $this->groupedPrintData($html);
        $links = $this->callerWhatsAppData($html);

        if ($groups === []) {
            $this->markTestSkipped('The call list is empty for the whole window.');
        }

        $this->assertNotEmpty($links, 'No caller got a WhatsApp button on the printed list.');

        foreach ($groups as $callerName => $persons) {
            if ($callerName === 'Unassigned') {
                $this->assertArrayNotHasKey($callerName, $links, '"Unassigned" must not get a WhatsApp button.');
                continue;
            }

            $caller = $callers->get($callerName);
            $this->assertNotNull($caller, '"' . $callerName . '" is not in the caller pool.');

            $digits = preg_replace('/[^0-9]/', '', (string) $caller->phone_number);

            if ($digits === '') {
                $this->assertArrayNotHasKey(
                    $callerName,
                    $links,
                    'A caller without a phone number should not get a WhatsApp button.'
                );
                continue;
            }

            $expectedPhone = substr($digits, 0, 1) === '0' ? '234' . substr($digits, 1) : $digits;

            $this->assertArrayHasKey($callerName, $links, 'No WhatsApp button for ' . $callerName . '.');
            $this->assertSame($expectedPhone, $links[$callerName]['phone']);
            $this->assertStringStartsWith(
                'https://wa.me/' . $expectedPhone . '?text=',
                $links[$callerName]['url']
            );

            parse_str((string) parse_url($links[$callerName]['url'], PHP_URL_QUERY), $query);
            $message = (string) ($query['text'] ?? '');

            $gender = strtolower(trim((string) $caller->gender));
            $greeting = $gender === 'male'
                ? 'Good day Sir'
                : ($gender === 'female' ? 'Good day Ma' : 'Good day ' . $callerName);

            $this->assertStringStartsWith(
                $greeting . ",\nI hope this meets you well\n",
                $message,
                $callerName . ' is not greeted by the gender on their profile.'
            );

            foreach ($persons as $person) {
                $this->assertStringContainsString(
                    $person['name'],
                    $message,
                    $person['name'] . ' is missing from ' . $callerName . "'s WhatsApp message."
                );
            }

            $this->assertStringContainsString('Please log in to the TIU app for details and follow up.', $message);
            $this->assertStringContainsString(
                'Please remember airtime topup on the app is available.',
                $message
            );
            $this->assertStringContainsString(
                'Kindly give feedback on the app after each call.',
                $message
            );
            $this->assertStringEndsWith('God bless you.', $message);
        }
    }
}
