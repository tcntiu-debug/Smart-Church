<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\TiuMember;
use App\Services\SubgroupAssignmentService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `/group` panels.
 *
 * The page rendered no subgroup panels on production because the query
 * filtered `sub_group.department_id` while the imported rows only named their
 * department in the legacy `department_name` column. The controller reads
 * through SubgroupAssignmentService now, so this test pins the wiring: a member
 * assigned to a touch point must appear inside that touch point's panel.
 *
 * The suite uses the application's real connection, so the write is wrapped in
 * a transaction and rolled back.
 */
class GroupPageTest extends TestCase
{
    /**
     * Members the profile-completeness middleware lets through (same query as
     * `ProfileTouchpointEditTest`).
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
            $this->markTestSkipped('No admin with a complete profile available to render /group.');
        }

        return $admin;
    }

    private function touchpointGroup()
    {
        $group = DB::table('sub_group')
            ->where('department_id', SubgroupAssignmentService::TOUCHPOINT_DEPARTMENT_ID)
            ->orderBy('subid')
            ->first();

        if ($group) {
            return $group;
        }

        $subid = DB::table('sub_group')->insertGetId([
            'department_id' => SubgroupAssignmentService::TOUCHPOINT_DEPARTMENT_ID,
            'sub_group_name' => 'Touch Point Test',
        ]);

        return DB::table('sub_group')->where('subid', $subid)->first();
    }

    public function test_the_group_page_lists_a_member_inside_their_touch_point_panel()
    {
        $admin = $this->admin();

        DB::beginTransaction();

        try {
            $group = $this->touchpointGroup();

            // A bare legacy value, exactly what the production rows hold.
            $member = TiuMember::query()
                ->where('tiu_member_id', '!=', $admin->tiu_member_id)
                ->where('status', '!=', '2')
                ->first();

            if (! $member) {
                $this->markTestSkipped('No member available to assign to a touch point.');
            }

            DB::table('tiu_member')
                ->where('tiu_member_id', $member->tiu_member_id)
                ->update(['subgroup' => $group->sub_group_name]);

            $response = $this->actingAs($admin)->get('/group/fetch?department=' . SubgroupAssignmentService::TOUCHPOINT_DEPARTMENT_ID);

            $response->assertStatus(200);
            $response->assertSee('Assign Members for:');
            $response->assertSee($group->sub_group_name);
            $response->assertSee($member->first_name);
        } finally {
            DB::rollBack();
        }
    }
}
