<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\TiuMember;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * /admin-assign-dragdrop: the guides on the left, the unassigned first timers
 * on the right, and drag & drop in both directions.
 *
 * The page used to be three pages in one (Suggestions, Standard View and a
 * department picker). It now shows exactly two things - who can guide and who
 * is still waiting - and every filter in its two boxes has to reach the query.
 *
 * Deliberately no RefreshDatabase: the suite runs against the application's
 * real connection. Everything a test writes happens inside a transaction
 * opened in setUp() and rolled back in tearDown(), so the department's real
 * first timers and follow-ups survive the run untouched.
 */
class AdminDragDropAssignmentTest extends TestCase
{
    /**
     * Every row a test creates carries this in its last name, so the page can
     * be narrowed to the test's own data with one search term.
     */
    private const MARKER = 'Zzdragtest';

    /**
     * A day in 2000: older than anything the church recorded, so the "registered
     * from/up to" assertions cannot pick up a real row by accident.
     */
    private const REGISTERED_AT = '2000-06-04 10:00:00';

    private const REGISTERED_DATE = '2000-06-04';

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

    /**
     * A member CheckProfileComplete lets through, otherwise the request is sent
     * to the profile page before it reaches the controller (same rule the other
     * assignment tests follow).
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

        return $members->whereExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('birthday')
                ->whereColumn('birthday.tiu_member_id', 'tiu_member.tiu_member_id')
                ->whereNotNull('birthday.birthday');
        });
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

    private function page(array $query = [])
    {
        return $this->actingAs($this->admin())
            ->get(route('admin.assignments.drag-drop', $query));
    }

    /**
     * A member of the Tracking & Integration department, in the form the
     * export stores the membership: department_name holds `"23"`.
     */
    private function makeGuide(string $firstName, string $gender = 'Male', string $occupation = 'Engineer'): int
    {
        return (int) DB::table('tiu_member')->insertGetId([
            'community_other'       => '',
            'first_name'            => $firstName,
            'last_name'             => self::MARKER,
            'phone_number'          => '0800000001',
            'email'                 => strtolower($firstName) . '.guide@example.test',
            'gender'                => $gender,
            'marital_status'        => 'Single',
            'member_role'           => 'Member',
            'email_verified'        => '1',
            'date_registered'       => self::REGISTERED_DATE,
            'occupation'            => $occupation,
            'password'              => bcrypt('secret'),
            'status'                => '1',
            'subgroup'              => 'Tracking and Integration',
            'oversight_extra1'      => '',
            'cluster'               => '',
            'residential_address'   => 'Test address',
            'next_of_kin_name'      => 'Test Kin',
            'next_of_kin_phone'     => '0800000002',
            'house_fellowship'      => '',
            'accountability_extra3' => '',
            'age'                   => '30',
            'parent_gaudian'        => '',
            'Phone'                 => '0800000001',
            'Relationship'          => 'Friend',
            'department_name'       => '"23"',
            'campus_id'             => $this->admin()->campus_id ?? null,
        ]);
    }

    private function makeFirstTimer(string $firstName, string $gender = 'Male', string $status = 'Unassigned'): int
    {
        return (int) DB::table('first_timer')->insertGetId([
            'first_name'       => $firstName,
            'last_name'        => self::MARKER,
            'phone_number'     => '0800000003',
            'email'            => strtolower($firstName) . '.ft@example.test',
            'gender'           => $gender,
            'age'              => '30',
            'occupation'       => 'Engineer',
            'attendant_type'   => 'Visiting',
            'attendance_count' => 1,
            'status'           => $status,
            'register_date'    => self::REGISTERED_AT,
            'address'          => 'Test address',
            'campus_id'        => $this->admin()->campus_id ?? null,
        ]);
    }

    private function followUp(int $guideId, int $firstTimerId): int
    {
        return (int) DB::table('member_tracking_followup')->insertGetId([
            'first_timer_id'        => $firstTimerId,
            'department_id'         => 23,
            'tiu_member_id'         => $guideId,
            'followup_mode'         => 'call',
            'followup_date_time'    => '10:00:00',
            'followup_response'     => '',
            'followup_rank'         => 1,
            'tiu_member_comment'    => '',
            'timeStamp_registered'  => self::REGISTERED_AT,
            'followup_response_new' => '{"week1":[]}',
        ]);
    }

    public function test_guests_are_sent_to_the_login_page()
    {
        $this->get(route('admin.assignments.drag-drop'))->assertRedirect(route('login'));
    }

    public function test_a_member_who_is_not_an_admin_is_turned_away()
    {
        $member = TiuMember::query()
            ->whereNotIn('member_role', ['Super User', 'Admin', 'Admins'])
            ->first();

        if (! $member) {
            $this->markTestSkipped('No ordinary member available.');
        }

        $this->actingAs($member)
            ->get(route('admin.assignments.drag-drop'))
            ->assertRedirect('/home');
    }

    public function test_the_page_shows_only_the_guides_and_the_unassigned_pool()
    {
        $this->makeGuide('Guide');
        $this->makeFirstTimer('Unassigned');

        $this->page(['guide_search' => self::MARKER, 'ft_search' => self::MARKER])
            ->assertOk()
            ->assertSee('Assign First Timers')
            ->assertSee('Guide ' . self::MARKER)
            ->assertSee('Unassigned ' . self::MARKER)
            ->assertSee('first-timer-card draggable-unassigned', false)
            ->assertSee('Drop a first timer here')
            // The three sections the page no longer has.
            ->assertDontSee('Standard View')
            ->assertDontSee('Take Away')
            ->assertDontSee('suggestion-box', false)
            ->assertDontSee('name="dept_id"', false);
    }

    public function test_a_first_timer_a_guide_holds_sits_under_that_guide_and_leaves_the_pool()
    {
        $guide = $this->makeGuide('Guide');
        $held = $this->makeFirstTimer('Held', 'Female', 'Assigned');
        $this->followUp($guide, $held);

        $this->page(['guide_search' => self::MARKER, 'ft_search' => self::MARKER])
            ->assertOk()
            ->assertSee('Held ' . self::MARKER)
            ->assertSee('1 first timer')
            ->assertSee('first-timer-card draggable-assigned', false)
            ->assertDontSee('first-timer-card draggable-unassigned', false);
    }

    public function test_the_guide_filters_narrow_the_guides()
    {
        $this->makeGuide('Aaa');
        $this->makeGuide('Bbb', 'Female');

        $this->page(['guide_search' => self::MARKER, 'guide_gender' => 'Female'])
            ->assertOk()
            ->assertSee('Bbb ' . self::MARKER)
            ->assertDontSee('Aaa ' . self::MARKER);
    }

    public function test_the_first_timer_filters_narrow_the_pool()
    {
        $this->makeFirstTimer('Aaa');
        $this->makeFirstTimer('Bbb', 'Female');

        $this->page(['ft_search' => self::MARKER, 'ft_gender' => 'Female'])
            ->assertOk()
            ->assertSee('Bbb ' . self::MARKER)
            ->assertDontSee('Aaa ' . self::MARKER);
    }

    public function test_one_registration_date_is_enough()
    {
        $this->makeFirstTimer('Aaa');

        // The box used to demand both dates, so picking one showed nothing.
        $this->page(['ft_start_date' => self::REGISTERED_DATE, 'pool_limit' => 5000])
            ->assertOk()
            ->assertSee('Aaa ' . self::MARKER)
            ->assertSee('first-timer-card draggable-unassigned', false);
    }

    public function test_dropping_a_first_timer_on_a_guide_assigns_it()
    {
        $guide = $this->makeGuide('Guide');
        $timer = $this->makeFirstTimer('Dropped');

        $this->actingAs($this->admin())
            ->postJson(route('admin.assignments.ajax-assign'), [
                'Guild_name'     => $guide,
                'first_timer_id' => $timer,
                'full_name'      => 'Dropped ' . self::MARKER,
                'status'         => 'Unassigned',
            ])
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $followUp = DB::table('member_tracking_followup')->where('first_timer_id', $timer)->first();

        $this->assertNotNull($followUp, 'The drop did not create a follow-up row.');
        $this->assertSame($guide, (int) $followUp->tiu_member_id);
        $this->assertSame(23, (int) $followUp->department_id);
        $this->assertSame('Assigned', DB::table('first_timer')->where('first_timer_id', $timer)->value('status'));

        // Reloaded, the card is under the guide and no longer in the pool.
        $this->page(['guide_search' => self::MARKER, 'ft_search' => self::MARKER])
            ->assertOk()
            ->assertSee('Dropped ' . self::MARKER)
            ->assertSee('first-timer-card draggable-assigned', false)
            ->assertDontSee('first-timer-card draggable-unassigned', false);
    }

    public function test_dragging_an_assigned_first_timer_back_unassigns_it()
    {
        $guide = $this->makeGuide('Guide');
        $timer = $this->makeFirstTimer('Released', 'Male', 'Assigned');
        $trackingId = $this->followUp($guide, $timer);

        $this->actingAs($this->admin())
            ->postJson(route('admin.assignments.ajax-unassign'), ['first_timer_id' => $timer])
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertSame(
            0,
            (int) DB::table('member_tracking_followup')->where('tracking_id', $trackingId)->value('tiu_member_id')
        );
        $this->assertSame('Unassigned', DB::table('first_timer')->where('first_timer_id', $timer)->value('status'));

        // The row survives with no guide on it, so the card has to come back to
        // the pool instead of disappearing from the page.
        $this->page(['guide_search' => self::MARKER, 'ft_search' => self::MARKER])
            ->assertOk()
            ->assertSee('Released ' . self::MARKER)
            ->assertSee('first-timer-card draggable-unassigned', false)
            ->assertDontSee('first-timer-card draggable-assigned', false);
    }

    public function test_a_first_timer_whose_status_still_says_unassigned_is_not_in_both_columns()
    {
        $guide = $this->makeGuide('Guide');
        $timer = $this->makeFirstTimer('Stale', 'Female', 'Unassigned');
        $this->followUp($guide, $timer);

        $this->page(['guide_search' => self::MARKER, 'ft_search' => self::MARKER])
            ->assertOk()
            ->assertSee('Stale ' . self::MARKER)
            ->assertSee('first-timer-card draggable-assigned', false)
            ->assertDontSee('first-timer-card draggable-unassigned', false);
    }
}
