<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Route names have to be unique. `php artisan route:cache` refuses a collection
 * with a duplicate name ("Unable to prepare route [...] for serialization.
 * Another route has already been assigned name [...]"), and the deploy runs
 * that step through `app:post-deploy` - so one duplicated name silently costs
 * the whole application its route cache and shows up as a FAILED step on every
 * release.
 *
 * The duplicate that motivated this test: `/my-business/deactivate/{id}` and
 * `/my-business/deactivate-request` were both named
 * `marketplace.deactivate-request`.
 */
class RouteNameCollisionTest extends TestCase
{
    public function test_every_named_route_has_a_unique_name()
    {
        $seen = [];
        $duplicates = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || $name === '') {
                continue;
            }

            if (isset($seen[$name])) {
                $duplicates[] = $name . ' is used by both ' . $seen[$name] . ' and ' . $route->uri();

                continue;
            }

            $seen[$name] = $route->uri();
        }

        $this->assertSame(
            [],
            $duplicates,
            'Two routes share a name, so `php artisan route:cache` cannot build a cache and '
            . "`app:post-deploy` reports that step as FAILED:\n - " . implode("\n - ", $duplicates)
        );
    }

    /**
     * The rename above has to keep both URLs answering, because a member may
     * have one of them bookmarked: only the name moved.
     */
    public function test_the_marketplace_deactivation_urls_keep_their_own_names()
    {
        $this->assertSame(
            'my-business/deactivate-request',
            Route::getRoutes()->getByName('marketplace.deactivate-request')->uri(),
            "route('marketplace.deactivate-request') must build the form URL."
        );

        $this->assertSame(
            'my-business/deactivate/{id}',
            Route::getRoutes()->getByName('marketplace.deactivate-request-by-id')->uri()
        );
    }
}
