<?php

namespace Tests\Unit;

use App\Services\SubgroupAssignmentService;
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
}
