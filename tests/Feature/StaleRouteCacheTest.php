<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\TiuMember;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A route cache that has not learned the newest route names.
 *
 * bootstrap/cache/routes-*.php is written once and then read by every request, so
 * after a deploy it can still describe the previous release. route('name') throws
 * RouteNotFoundException for a name that file does not carry, while
 * Route::has('name') answers false - and both production outages were one view
 * naming such a name: /push/* first, then /celebrations, which lives in the shared
 * sidebar and so took every page down for every non-staff role while a Super User
 * (whom the sidebar sends to /birthdays) saw nothing wrong.
 *
 * This file fakes that cache: birthdays.celebrations is unknown, everything else
 * works. The pages must lose the link, not the page. Rebuild the real cache with
 * `php artisan route:clear` - see docs/CPANEL-DEPLOYMENT.md section 6.
 *
 * Deliberately no RefreshDatabase: the suite runs against the application's real
 * connection and these pages read it. Every test opens a transaction in setUp()
 * and rolls it back in tearDown().
 */
class StaleRouteCacheTest extends TestCase
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

    /**
     * A signed-in member holding one role, the way the views read them: the
     * dashboard takes $user->member_role, the sidebar takes the session.
     */
    private function viewer(string $role)
    {
        $member = $this->memberQuery()->first();

        if (! $member) {
            $this->markTestSkipped('No member with a complete profile available.');
        }

        $viewer = clone $member;
        $viewer->member_role = $role;

        session(['member_role' => $role, 'department_name' => '[]']);

        return $this->actingAs($viewer);
    }

    /**
     * The route cache from before the birthdays split: /celebrations missing.
     *
     * Not a mock: the router and the URL generator are handed a route collection
     * that simply does not contain the name, which is what a routes-*.php built
     * before the deploy does. Route::has() then answers false and route() throws,
     * exactly as on the server.
     */
    private function cacheWithoutCelebrations(): void
    {
        $this->assertTrue(
            Route::has('birthdays.celebrations'),
            'This test fakes a name the application does not have: nothing to hide.'
        );

        $trimmed = new RouteCollection();

        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            if ($route->getName() !== 'birthdays.celebrations') {
                $trimmed->add($route);
            }
        }

        $trimmed->refreshNameLookups();

        app('router')->setRoutes($trimmed);
        app('url')->setRoutes($trimmed);

        $this->assertFalse(Route::has('birthdays.celebrations'), 'The stale cache still knows the name.');
        $this->assertTrue(Route::has('home'), 'The trimmed cache lost routes it should keep.');
    }

    /**
     * The sidebar sits on every signed-in page, so an unknown name in it may not
     * throw for the roles that read it.
     */
    public function test_the_sidebar_costs_a_non_staff_role_a_link_not_every_page()
    {
        $this->cacheWithoutCelebrations();
        $this->viewer('Member');

        $nav = view('partials.nav')->render();

        $this->assertStringContainsString('Dashboard', $nav, 'The sidebar stopped rendering.');
        $this->assertStringNotContainsString('/celebrations', $nav, 'A dead link was offered.');

        foreach (['/home', '/profile', '/notifications'] as $page) {
            $this->get($page)->assertOk();
        }
    }

    /**
     * The dashboard card for the members' list is the other place that names it.
     */
    public function test_the_dashboard_card_is_dropped_rather_than_thrown()
    {
        $this->cacheWithoutCelebrations();
        $this->viewer('Member');

        $response = $this->get('/home');

        $response->assertOk();
        $response->assertDontSee('/celebrations', false);
        // The rest of the dashboard is still there.
        $response->assertSee('Quick Actions', false);
        $response->assertSee('Quick Links', false);
    }

    /**
     * Roles that read the staff list were never affected: their name is old
     * enough to be in any cache, and it still has to be offered.
     */
    public function test_a_staff_role_keeps_the_list_it_reads()
    {
        $this->cacheWithoutCelebrations();
        $this->viewer('Super User');

        $response = $this->get('/home');

        $response->assertOk();
        $response->assertSee(route('birthdays.index'), false);

        $nav = view('partials.nav')->render();

        $this->assertStringContainsString(route('birthdays.index'), $nav);
    }
}
