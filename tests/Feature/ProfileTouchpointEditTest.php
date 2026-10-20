<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\TiuMember;
use App\Services\SubgroupAssignmentService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Touch Point on `/profile`.
 *
 * Admins used to see a read-only copy of `tiu_member.subgroup` pointing at
 * `/group`. They can edit the assignment in place now: the page renders the
 * department's real groups as a multi-select and `profile.update` writes the
 * selection through SubgroupAssignmentService, keeping assignments that belong
 * to other departments untouched.
 *
 * Read-only unless the save test runs; that one wraps its POST in a transaction
 * and rolls it back, because the suite uses the application's real connection.
 */
class ProfileTouchpointEditTest extends TestCase
{
    /**
     * Members the profile-completeness middleware lets through (same query as
     * `MyTaskProfileModalTest`).
     */
    private function completeMembers()
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
        $admin = $this->completeMembers()
            ->whereIn('member_role', ['Super User', 'Admin'])
            ->first();

        if (! $admin) {
            $this->markTestSkipped('No admin with a complete profile available to render /profile.');
        }

        return $admin;
    }

    private function touchpointGroups()
    {
        $groups = SubgroupAssignmentService::groupNamesForDepartment(
            SubgroupAssignmentService::TOUCHPOINT_DEPARTMENT_ID
        );

        if (empty($groups)) {
            $this->markTestSkipped('No touch point groups available.');
        }

        return $groups;
    }

    private function targetFor(TiuMember $admin)
    {
        return TiuMember::query()
            ->where('tiu_member_id', '!=', $admin->tiu_member_id)
            ->first() ?: $admin;
    }

    public function test_an_admin_gets_an_editable_touch_point_on_the_profile_page()
    {
        $admin = $this->admin();
        $groups = $this->touchpointGroups();
        $target = $this->targetFor($admin);

        $response = $this->actingAs($admin)->get('/profile?tiu_member_id=' . $target->tiu_member_id);

        $response->assertStatus(200);

        $response->assertSee('name="touch_points[]"', false);
        $response->assertSee('id="touch-points"', false);
        $response->assertSee('data-placeholder="Select touch point(s)"', false);
        $response->assertSee('<option value="' . e($groups[0]) . '"', false);

        // The old read-only copy is gone.
        $response->assertDontSee('cannot be changed here');
    }

    public function test_a_non_admin_does_not_get_the_touch_point_editor()
    {
        $member = $this->completeMembers()
            ->where(function ($query) {
                $query->whereNull('member_role')
                    ->orWhereNotIn('member_role', ['Super User', 'Admin']);
            })
            ->first();

        if (! $member) {
            $this->markTestSkipped('No non-admin with a complete profile available to render /profile.');
        }

        $response = $this->actingAs($member)->get('/profile');

        $response->assertStatus(200);
        $response->assertSee('Touch Point');
        $response->assertSee('Assigned by an administrator');
        $response->assertDontSee('name="touch_points[]"', false);
    }

    public function test_a_legacy_admin_role_with_whitespace_still_gets_the_editor()
    {
        $admin = $this->admin();

        DB::beginTransaction();

        try {
            DB::table('tiu_member')
                ->where('tiu_member_id', $admin->tiu_member_id)
                ->update(['member_role' => ' admin ']);

            $admin->refresh();

            $response = $this->actingAs($admin)->get('/profile');

            $response->assertStatus(200);
            $response->assertSee('name="touch_points[]"', false);
            $response->assertSee('id="touch-points"', false);
        } finally {
            DB::rollBack();
        }
    }

    public function test_an_admin_save_stores_the_selection_and_rejects_unknown_groups()
    {
        $admin = $this->admin();
        $groups = $this->touchpointGroups();
        $target = $this->targetFor($admin);

        // Two groups prove the whole selection is written, not just the last one.
        $selection = array_slice($groups, 0, 2);

        DB::beginTransaction();

        try {
            $response = $this->actingAs($admin)->post('/profile/update', [
                'tiu_member_id' => $target->tiu_member_id,
                'first_name' => $target->first_name,
                'last_name' => $target->last_name,
                'phone_number' => $target->phone_number,
                'email' => $target->email,
                'gender' => $target->gender,
                'marital_status' => $target->marital_status,
                'age' => $target->age,
                'community_id' => $target->community_id,
                'occupation' => $target->occupation,
                'church_type_id' => $target->church_type_id,
                'residential_address' => $target->residential_address,
                'next_of_kin_name' => $target->next_of_kin_name,
                'next_of_kin_phone' => $target->next_of_kin_phone,
                'departments_json' => [],
                'clusters_json' => [],
                'house_fellowships_json' => [],
                'touch_points' => array_merge(['', 'Not A Real Group'], $selection),
            ]);

            $response->assertStatus(200);
            $response->assertJson(['success' => true]);

            $stored = DB::table('tiu_member')
                ->where('tiu_member_id', $target->tiu_member_id)
                ->value('subgroup');

            $entries = SubgroupAssignmentService::decode($stored);

            foreach ($selection as $name) {
                $this->assertContains(
                    SubgroupAssignmentService::key(SubgroupAssignmentService::TOUCHPOINT_DEPARTMENT_ID, $name),
                    $entries
                );
            }

            $this->assertNotContains('Not A Real Group', SubgroupAssignmentService::allNames($stored));
        } finally {
            DB::rollBack();
        }
    }
}
