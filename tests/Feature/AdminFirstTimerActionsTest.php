<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\TiuMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The write side of /fview: the MORE ACTION dropdown and the search box.
 *
 * The regression these tests pin down: ConvertEmptyStringsToNull turns the empty
 * "reason for unassignment" textarea into null, and the old rule list ran the
 * `string` rule against that null even for Delete, Disable and Integrated. Every
 * one of those actions therefore failed validation, the page redirected back
 * without a flash message, and the record stayed exactly where it was.
 *
 * Deliberately no RefreshDatabase: the suite runs against the application's real
 * connection. Every test writes inside a transaction opened in setUp() and rolled
 * back in tearDown(), so no row survives the run.
 */
class AdminFirstTimerActionsTest extends TestCase
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

    public function test_delete_action_removes_the_first_timer_and_its_related_rows()
    {
        $admin = $this->admin();
        $firstTimerId = $this->createFirstTimer(['first_name' => 'ZZDeleteMe']);

        DB::table('first_timer_hangout')->insert([
            'first_timer_id' => $firstTimerId,
            'first_name' => 'ZZDeleteMe',
            'last_name' => 'ActionTest',
            'phone_number' => '00000000000',
            'occupation' => 'IT and Tech',
            'occupation2' => 'IT and Tech',
            'hangout_date' => now()->toDateTimeString(),
            'member_status' => 'No',
            'department' => '',
            'extra2' => '',
        ]);

        DB::table('first_timers_updates')->insert([
            'first_timer_id' => $firstTimerId,
            'department' => '',
            'cluster' => '',
            'house_fellowship' => '',
            'foundation_of_faith' => '',
            'water_baptism' => '',
            'holy_ghost_baptism' => '',
            'birthday' => '',
            'created_date' => now()->toDateTimeString(),
            'community' => '',
        ]);

        $hasCallerAssignments = Schema::hasTable('caller_assignments');

        if ($hasCallerAssignments) {
            DB::table('caller_assignments')->insert([
                'first_timer_id' => $firstTimerId,
                'caller_member_id' => null,
                'caller_name' => 'ZZ Caller',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // An empty reason is exactly what the untouched textarea posts; the web
        // middleware converts it to null before the controller sees it.
        $response = $this->actingAs($admin)->post(route('admin.ft.perform-action'), [
            'first_timer_id' => $firstTimerId,
            'more_action' => 'Delete',
            'unassign_reason' => '',
        ]);

        $response->assertRedirect(route('admin.first-timers'));
        $response->assertSessionHas('success', 'First timer deleted successfully.');

        $this->assertDatabaseMissing('first_timer', ['first_timer_id' => $firstTimerId]);
        $this->assertDatabaseMissing('first_timer_hangout', ['first_timer_id' => $firstTimerId]);
        $this->assertDatabaseMissing('first_timers_updates', ['first_timer_id' => $firstTimerId]);

        if ($hasCallerAssignments) {
            $this->assertDatabaseMissing('caller_assignments', ['first_timer_id' => $firstTimerId]);
        }
    }

    public function test_disable_action_is_not_blocked_by_the_empty_unassign_reason()
    {
        $admin = $this->admin();
        $firstTimerId = $this->createFirstTimer();

        $response = $this->actingAs($admin)->post(route('admin.ft.perform-action'), [
            'first_timer_id' => $firstTimerId,
            'more_action' => 'Disable',
            'unassign_reason' => '',
        ]);

        $response->assertRedirect(route('admin.first-timers'));
        $response->assertSessionHas('success', 'First timer disabled successfully.');
        $this->assertSame(
            'Disable',
            DB::table('first_timer')->where('first_timer_id', $firstTimerId)->value('status')
        );
    }

    public function test_unassign_action_still_requires_a_reason()
    {
        $admin = $this->admin();
        $firstTimerId = $this->createFirstTimer();

        $response = $this->actingAs($admin)
            ->from(route('admin.first-timers'))
            ->post(route('admin.ft.perform-action'), [
                'first_timer_id' => $firstTimerId,
                'more_action' => 'Unassigned',
                'unassign_reason' => '',
            ]);

        $response->assertRedirect(route('admin.first-timers'));
        $response->assertSessionHasErrors('unassign_reason');
        $this->assertNotSame(
            'Unassigned',
            DB::table('first_timer')->where('first_timer_id', $firstTimerId)->value('status')
        );
    }

    public function test_search_filters_the_table_and_keeps_the_term_in_the_box()
    {
        $admin = $this->admin();
        $needle = 'ZzSearch' . substr(uniqid(), -6);
        $firstTimerId = $this->createFirstTimer([
            'first_name' => $needle,
            'phone_number' => '08012345678',
            'email' => strtolower($needle) . '@example.invalid',
        ]);

        $found = $this->actingAs($admin)->get(route('admin.first-timers', ['search' => $needle]));

        $found->assertOk();
        $found->assertSee($needle);
        $found->assertSee('value="' . $needle . '"', false);
        // The row really rendered, not just the search term echoed in the input.
        $found->assertSee('data-ft-id="' . $firstTimerId . '"', false);

        $missing = $this->actingAs($admin)->get(route('admin.first-timers', ['search' => 'ZzNoSuch' . uniqid()]));

        $missing->assertOk();
        $missing->assertDontSee('data-ft-id="' . $firstTimerId . '"', false);
        $this->assertDatabaseHas('first_timer', ['first_timer_id' => $firstTimerId]);
    }

    public function test_search_reaches_records_older_than_the_default_browsing_window()
    {
        $admin = $this->admin();
        $needle = 'ZzArchive' . substr(uniqid(), -6);

        $firstTimerId = $this->createFirstTimer([
            'first_name' => $needle,
            'register_date' => now()->subYears(3)->toDateTimeString(),
        ]);

        // Browsing keeps its "recent records" window...
        $browse = $this->actingAs($admin)->get(route('admin.first-timers'));
        $browse->assertOk();
        $browse->assertDontSee('data-ft-id="' . $firstTimerId . '"', false);

        // ...but a search must still find the older record.
        $found = $this->actingAs($admin)->get(route('admin.first-timers', ['search' => $needle]));

        $found->assertOk();
        $found->assertSee($needle);
        $found->assertSee('data-ft-id="' . $firstTimerId . '"', false);
    }

    public function test_the_tracking_overview_panel_is_gone()
    {
        $response = $this->actingAs($this->admin())->get(route('admin.first-timers'));

        $response->assertOk();
        $response->assertDontSee('TRACKING OVERVIEW');
    }

    /**
     * A complete Super User built inside the test's transaction: the tests must
     * not depend on a real administrator having filled every profile field, and
     * Super User bypasses the campus filter, so no real row can hide the test's.
     */
    private function admin(): TiuMember
    {
        $suffix = substr(uniqid(), -8);

        $memberId = DB::table('tiu_member')->insertGetId([
            'first_name' => 'ZZ',
            'last_name' => 'ActionAdmin',
            'phone_number' => '0000000' . $suffix,
            'email' => 'zz-admin-' . $suffix . '@example.invalid',
            'gender' => 'Male',
            'marital_status' => 'Single',
            'member_role' => 'Super User',
            'email_verified' => '1',
            'date_registered' => now()->toDateTimeString(),
            'occupation' => 'IT and Tech',
            'password' => bcrypt('not-used-by-acting-as'),
            'status' => '1',
            'subgroup' => '',
            'oversight_extra1' => '',
            'cluster' => '[]',
            'residential_address' => 'Test address',
            'next_of_kin_name' => 'Test Kin',
            'next_of_kin_phone' => '00000000002',
            'house_fellowship' => '[]',
            'accountability_extra3' => '',
            'age' => '25-34',
            'parent_gaudian' => '',
            'Phone' => '00000000001',
            'Relationship' => 'Sibling',
            'community_other' => '',
            'campus_id' => Campus::isSingle() ? null : DB::table('campus')->value('cid'),
        ]);

        DB::table('birthday')->insert([
            'tiu_member_id' => $memberId,
            'birthday' => '1990-01-01',
        ]);

        return TiuMember::findOrFail($memberId);
    }

    private function createFirstTimer(array $overrides = []): int
    {
        return (int) DB::table('first_timer')->insertGetId(array_merge([
            'first_name' => 'ZZ',
            'last_name' => 'ActionTest',
            'phone_number' => '00000000000',
            'email' => 'zz-action-' . uniqid() . '@example.invalid',
            'gender' => 'Male',
            'age' => '25-34',
            'occupation' => 'IT and Tech',
            'attendant_type' => 'Visiting',
            'attendance_count' => 0,
            'status' => 'Not Assigned',
            'register_date' => now()->toDateTimeString(),
            'address' => 'Test address',
        ], $overrides));
    }
}
