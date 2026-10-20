<?php

namespace Tests\Unit;

use App\Services\SubgroupAssignmentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * tiu_member.subgroup used to be written as a bare subgroup name by the
 * profile page while /group wrote "deptID::SubgroupName" JSON entries. The
 * service is the single reader/writer for both shapes.
 */
class SubgroupAssignmentServiceTest extends TestCase
{
    public function test_it_reads_every_stored_shape()
    {
        $this->assertSame(['Touch Point 2'], SubgroupAssignmentService::decode('Touch Point 2'));
        $this->assertSame(['23::Touch Point 2'], SubgroupAssignmentService::decode('["23::Touch Point 2"]'));
        $this->assertSame(['Touch Point 2'], SubgroupAssignmentService::decode('"Touch Point 2"'));
    }

    public function test_it_treats_blank_values_as_no_assignment()
    {
        $this->assertSame([], SubgroupAssignmentService::decode(null));
        $this->assertSame([], SubgroupAssignmentService::decode(''));
        $this->assertSame([], SubgroupAssignmentService::decode('[]'));
        $this->assertSame([], SubgroupAssignmentService::decode('unassigned'));
    }

    public function test_it_reports_names_for_one_department()
    {
        $raw = '["15::Foundation A","23::Touch Point 2"]';

        $this->assertSame(['Touch Point 2'], SubgroupAssignmentService::namesForDepartment($raw, 23));
        $this->assertSame(['Foundation A'], SubgroupAssignmentService::namesForDepartment($raw, 15));
    }

    public function test_a_bare_name_counts_for_the_department_that_owns_it()
    {
        $groups = ['Touch Point 1', 'Touch Point 2'];

        $this->assertTrue(SubgroupAssignmentService::isAssignedToAny('Touch Point 2', 23, $groups));
        $this->assertFalse(SubgroupAssignmentService::isAssignedToAny('Touch Point 2', 15, ['Foundation A']));
    }

    public function test_it_writes_the_canonical_shape_over_a_bare_name()
    {
        $this->assertSame(
            '["23::Touch Point 5"]',
            SubgroupAssignmentService::apply('Touch Point 2', 23, 'Touch Point 5', ['Touch Point 2', 'Touch Point 5'])
        );
    }

    public function test_it_keeps_assignments_from_other_departments()
    {
        $this->assertSame(
            '["15::Foundation A","23::Touch Point 5"]',
            SubgroupAssignmentService::apply('["15::Foundation A"]', 23, 'Touch Point 5', ['Touch Point 5'])
        );
    }

    public function test_clearing_removes_both_stored_shapes()
    {
        $this->assertSame(
            '["15::Foundation A"]',
            SubgroupAssignmentService::apply(
                '["23::Touch Point 5","Touch Point 2","15::Foundation A"]',
                23,
                '',
                ['Touch Point 2', 'Touch Point 5']
            )
        );
    }

    public function test_it_accepts_a_full_key_from_an_old_client()
    {
        $this->assertSame(
            '["23::Touch Point 5"]',
            SubgroupAssignmentService::apply('', 23, '23::Touch Point 5', ['Touch Point 5'])
        );
    }

    public function test_it_writes_a_whole_selection_in_one_pass()
    {
        $this->assertSame(
            '["23::Touch Point 1","23::Touch Point 3"]',
            SubgroupAssignmentService::applyMany(
                'Touch Point 2',
                23,
                ['Touch Point 1', 'Touch Point 3'],
                ['Touch Point 1', 'Touch Point 2', 'Touch Point 3']
            )
        );
    }

    public function test_a_selection_keeps_other_departments_and_drops_blanks_and_duplicates()
    {
        $this->assertSame(
            '["15::Foundation A","23::Touch Point 1","23::Touch Point 2"]',
            SubgroupAssignmentService::applyMany(
                '["15::Foundation A","23::Touch Point 5","23::Touch Point 2"]',
                23,
                ['', 'Touch Point 1', 'Touch Point 2', 'Touch Point 1', null],
                ['Touch Point 1', 'Touch Point 2', 'Touch Point 5']
            )
        );
    }

    public function test_an_empty_selection_clears_the_department()
    {
        $this->assertSame(
            '["15::Foundation A"]',
            SubgroupAssignmentService::applyMany(
                '["15::Foundation A","23::Touch Point 2"]',
                23,
                [],
                ['Touch Point 2']
            )
        );
    }

    public function test_a_selection_accepts_the_canonical_key_from_an_old_client()
    {
        $this->assertSame(
            '["23::Touch Point 2"]',
            SubgroupAssignmentService::applyMany('', 23, ['23::Touch Point 2'], ['Touch Point 2'])
        );
    }

    public function test_like_patterns_cover_every_shape()
    {
        $this->assertSame([
            '%"23::Touch Point 2"%',
            '"Touch Point 2"',
            'Touch Point 2',
        ], SubgroupAssignmentService::likePatterns(23, 'Touch Point 2'));
    }

    public function test_the_mobile_primary_name_prefers_the_touch_point()
    {
        $this->assertSame('Touch Point 2', SubgroupAssignmentService::primaryName('["15::Foundation A","23::Touch Point 2"]'));
        $this->assertSame('Foundation A', SubgroupAssignmentService::primaryName('["15::Foundation A"]'));
        $this->assertSame('', SubgroupAssignmentService::primaryName(null));
    }

    public function test_it_resolves_a_members_names_against_the_real_group_rows()
    {
        $group = DB::table('sub_group')
            ->where('department_id', SubgroupAssignmentService::TOUCHPOINT_DEPARTMENT_ID)
            ->orderBy('subid')
            ->first(['sub_group_name']);

        if (! $group) {
            $this->markTestSkipped('No touch point groups available.');
        }

        $canonical = SubgroupAssignmentService::key(
            SubgroupAssignmentService::TOUCHPOINT_DEPARTMENT_ID,
            $group->sub_group_name
        );

        $this->assertSame(
            [$group->sub_group_name],
            SubgroupAssignmentService::namesForMemberDepartment('["' . $canonical . '"]', 23)
        );

        // The same name is not a touch point when the entry names another department.
        $this->assertSame([], SubgroupAssignmentService::namesForMemberDepartment('["15::' . $group->sub_group_name . '"]', 23));
    }

    public function test_a_bare_name_is_resolved_against_the_department_that_owns_it()
    {
        $group = DB::table('sub_group')
            ->where('department_id', SubgroupAssignmentService::TOUCHPOINT_DEPARTMENT_ID)
            ->orderBy('subid')
            ->first(['sub_group_name']);

        if (! $group) {
            $this->markTestSkipped('No touch point groups available.');
        }

        $this->assertSame(
            [$group->sub_group_name],
            SubgroupAssignmentService::namesForMemberDepartment($group->sub_group_name, 23)
        );

        $this->assertSame([], SubgroupAssignmentService::namesForMemberDepartment($group->sub_group_name, 15));
    }

    public function test_group_ownership_works_with_either_schema_shape()
    {
        $this->assertTrue(SubgroupAssignmentService::groupBelongsToDepartment(
            (object) ['department_id' => 23, 'department_name' => null],
            23
        ));
        $this->assertFalse(SubgroupAssignmentService::groupBelongsToDepartment(
            (object) ['department_id' => 15, 'department_name' => null],
            23
        ));

        // The original schema stores the department name and no id at all.
        $name = SubgroupAssignmentService::departmentName(23);
        $this->assertSame('Tracking and Integration', $name);

        $this->assertTrue(SubgroupAssignmentService::groupBelongsToDepartment(
            (object) ['department_id' => null, 'department_name' => $name],
            23
        ));
        $this->assertFalse(SubgroupAssignmentService::groupBelongsToDepartment(
            (object) ['department_id' => null, 'department_name' => 'Foundation'],
            23
        ));
    }

    public function test_it_reads_groups_from_the_legacy_department_name_schema()
    {
        $originalConnection = DB::getDefaultConnection();

        config(['database.connections.legacy_sub_group_test' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]]);

        DB::purge('legacy_sub_group_test');
        DB::setDefaultConnection('legacy_sub_group_test');

        try {
            Schema::create('department', function (Blueprint $table) {
                $table->integer('dept_id');
                $table->string('dept_name');
            });

            // The original schema: no department_id column at all.
            Schema::create('sub_group', function (Blueprint $table) {
                $table->increments('subid');
                $table->string('department_name');
                $table->string('sub_group_name');
                $table->integer('lead_id')->nullable();
            });

            DB::table('department')->insert([
                ['dept_id' => 23, 'dept_name' => 'Tracking and Integration'],
                ['dept_id' => 15, 'dept_name' => 'Foundation'],
            ]);

            DB::table('sub_group')->insert([
                ['department_name' => 'Tracking and Integration', 'sub_group_name' => 'Touch Point 1', 'lead_id' => 1],
                ['department_name' => 'Foundation', 'sub_group_name' => 'Foundation A', 'lead_id' => 2],
            ]);

            $this->assertSame(['Touch Point 1'], SubgroupAssignmentService::groupNamesForDepartment(23));
            $this->assertSame(['Foundation A'], SubgroupAssignmentService::groupNamesForDepartment(15));

            $this->assertSame(
                ['Touch Point 1'],
                SubgroupAssignmentService::namesForMemberDepartment('["23::Touch Point 1"]', 23)
            );
            $this->assertSame(
                ['Touch Point 1'],
                SubgroupAssignmentService::namesForMemberDepartment('Touch Point 1', 23)
            );
            $this->assertSame(
                [],
                SubgroupAssignmentService::namesForMemberDepartment('Touch Point 1', 15)
            );

            $groups = SubgroupAssignmentService::groupsForMember('Touch Point 1');
            $this->assertSame(['Touch Point 1'], $groups->pluck('sub_group_name')->all());
        } finally {
            DB::setDefaultConnection($originalConnection);
            DB::purge('legacy_sub_group_test');
        }
    }
}
