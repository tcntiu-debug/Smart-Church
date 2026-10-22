<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\TiuMember;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Super User = every view.
 *
 * The sidebar decides what a role is offered with a handful of @if blocks; the
 * controllers decide again with `in_array($role, ['Super User', 'Admin'])`.
 * This file checks both agree for the one role above them all: nothing in the
 * menu may be hidden from a Super User, and every console page they are offered
 * has to open.
 *
 * Deliberately no RefreshDatabase: the suite runs against the application's real
 * connection and these pages read it. Every test opens a transaction in setUp()
 * and rolls it back in tearDown().
 */
class SuperUserAccessTest extends TestCase
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

        return $members;
    }

    private function superUser()
    {
        $member = $this->memberQuery()->first();

        if (! $member) {
            $this->markTestSkipped('No member with a complete profile available.');
        }

        $superUser = clone $member;
        $superUser->member_role = 'Super User';

        return $superUser;
    }

    /**
     * The sidebar for one role, read the way layouts/app.blade.php reads it:
     * partials/nav.blade.php takes every key from the session.
     */
    private function navFor(string $role, array $departments = [], bool $isHodOrLead = false): string
    {
        session([
            'member_role' => $role,
            'department_name' => json_encode($departments),
            'userIsHODOrLead' => $isHodOrLead,
        ]);

        return view('partials.nav')->render();
    }

    /**
     * The targets a menu offers: hrefs that actually go somewhere.
     *
     * @return string[]
     */
    private function linksIn(string $nav): array
    {
        preg_match_all('/href="([^"]+)"/', $nav, $matches);

        $links = array_filter($matches[1], function ($href) {
            return $href !== '#' && strpos($href, 'javascript:') !== 0;
        });

        return array_values(array_unique($links));
    }

    /**
     * Every toolbar the sidebar has, in the Super User's menu. A missing one
     * means an @if block left the top role out.
     */
    public function test_the_super_user_is_offered_every_menu_the_app_has()
    {
        $menus = [
            'Dashboard', 'My Box', 'Week List', 'HOD-Lead View', 'Welcome Center',
            'Marketplace', 'Shared Resource', 'Tracking-Integration',
            'Children Admin', 'Admin Console', 'Analytics & Maps',
            'Settings & Config', 'Policy', 'Log Out',
        ];

        $nav = $this->navFor('Super User');

        foreach ($menus as $menu) {
            $this->assertStringContainsString($menu, $nav, 'The Super User is not offered the '.$menu.' menu.');
        }

        // The rows inside those menus. /weeklist is called out because its @if
        // listed the four staff roles and not the Super User.
        $rows = [
            '/weeklist', '/birthdays', '/birthdays/status', '/mview', '/mregister',
            '/fviewupdate', '/prayer-suggestion-list', '/airtime_admin', '/aoverview',
            '/setting', '/etask', '/group', '/config-departments', '/config-community',
            '/admin-marketplace', '/manage-resources', '/report-barcharts',
            '/report-piecharts', '/department-view', '/attendance-analysis', '/map-view',
            '/children-church', '/children-church/manage', '/lead/department',
            '/lead/house-fellowship', '/lead/cluster', '/ftlead', '/pending-tasks',
            '/fview', '/fthangout', '/my-tasks', '/ft-register', '/my-business',
            '/purchase', '/public', '/private', '/privacy-policy', '/data-policy',
            '/profile', '/mtouchpoint', '/gallery',
        ];

        $paths = array_map(function ($href) {
            return parse_url($href, PHP_URL_PATH);
        }, $this->linksIn($nav));

        foreach ($rows as $row) {
            $this->assertContains($row, $paths, 'The Super User menu has no link to '.$row.'.');
        }
    }

    /**
     * The invariant behind "all views": whatever another role is offered, the
     * Super User is offered too - for every role and for the department-gated
     * menus (11 Children Church, 23 Tracking, 28 Welcome Center) that a role can
     * pick up from its departments rather than its name.
     */
    public function test_the_super_user_menu_contains_every_other_roles_menu()
    {
        $superUserLinks = $this->linksIn($this->navFor('Super User'));

        $others = [
            'Admin' => [[], false],
            'Admins' => [[], false],
            'Workers' => [[], false],
            'Worker' => [[], false],
            'Worker (Welcome Center)' => [['28'], false],
            'Worker (Tracking)' => [['23'], false],
            'Worker (Children Church)' => [['11'], false],
            'Worker (all three)' => [['11', '23', '28'], false],
            'Member' => [[], false],
            'Member (Children Church)' => [['11'], false],
            'Lead' => [[], false],
            'Lead (HOD)' => [[], true],
            'TIU Guest' => [[], false],
        ];

        foreach ($others as $role => $config) {
            list($departments, $isHodOrLead) = $config;

            $theirLinks = $this->linksIn($this->navFor($role, $departments, $isHodOrLead));

            // /celebrations is the one link deliberately absent from the Super
            // User's menu: they read the staff list instead. Everything else
            // another role is offered must carry over.
            $theirLinks = array_values(array_filter($theirLinks, function ($href) {
                return parse_url($href, PHP_URL_PATH) !== '/celebrations';
            }));

            $missing = array_values(array_diff($theirLinks, $superUserLinks));

            $this->assertSame(
                [],
                $missing,
                'The Super User menu is missing what the '.$role.' menu offers: '.implode(', ', $missing)
            );
        }
    }

    /**
     * The pages behind those menus, opened as the Super User.
     *
     * The gates are `in_array($role, ['Super User', 'Admin'])` in a dozen
     * controllers; this is what proves it from the outside - none of them may
     * answer a Super User with "Access Denied".
     */
    public function test_the_super_user_can_open_every_console_page()
    {
        $pages = [
            '/birthdays',
            '/weeklist',
            '/mview',
            '/fviewupdate',
            '/prayer-suggestion-list',
            '/airtime_admin',
            '/aoverview',
            '/setting',
            '/etask',
            '/group',
            '/config-departments',
            '/config-community',
            '/admin-marketplace',
            '/manage-resources',
            '/report-barcharts',
            '/report-piecharts',
            '/department-view',
            '/attendance-analysis',
            '/map-view',
            '/children-church',
            '/children-church/manage',
            '/my-tasks',
            '/pending-tasks',
            '/fview',
            '/fthangout',
            '/lead/department',
            '/lead/house-fellowship',
            '/lead/cluster',
            '/ftlead',
        ];

        $superUser = $this->superUser();

        foreach ($pages as $page) {
            $response = $this->actingAs($superUser)->get($page);

            $this->assertNotSame(
                302,
                $response->getStatusCode(),
                $page.' turned the Super User away: '.$response->headers->get('Location')
            );

            $response->assertOk();
        }
    }
}
