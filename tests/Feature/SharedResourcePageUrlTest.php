<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Shared Resource -> Public is a page, but it used to be linked as url('/public'),
 * and that URL cannot work on the live hosts.
 *
 * The cPanel document root sits one level above the folder that holds index.php,
 * so https://host/public *is* that folder: DirectorySlash answers 301 -> /public/,
 * the front controller is then reached with the path "/" and renders the login
 * page. Every URL that begins with /public belongs to the folder, never to a
 * route. `php artisan serve` uses the base path "/", answers /public from the
 * router exactly as the sidebar expects, and that is why the page passed every
 * local check while dev and production showed the login form.
 *
 * The page answers on /resources/public now, and the root .htaccess forwards the
 * old address because a folder can be reached by a URL but never by Laravel.
 */
class SharedResourcePageUrlTest extends TestCase
{
    public function test_the_page_answers_on_a_url_no_folder_shadows()
    {
        $route = Route::getRoutes()->getByName('resources.public');

        $this->assertNotNull(
            $route,
            "route('resources.public') must keep its name - the sidebar builds its link from it."
        );

        $this->assertSame('resources/public', $route->uri());

        $this->assertStringStartsNotWith(
            'public/',
            $route->uri(),
            'A route that starts with /public is shadowed by the folder that holds index.php: '
            . 'the host answers it before Laravel runs, and the page is replaced by the login form.'
        );
    }

    public function test_the_page_stays_behind_the_login()
    {
        $this->get('/resources/public')->assertRedirect('/');
    }

    /**
     * The bare /public route is kept for `php artisan serve`, where it works.
     */
    public function test_the_local_public_url_is_still_registered()
    {
        $this->assertSame('public', Route::getRoutes()->getByName('resources.public.dev')->uri());
    }

    /**
     * On the hosts the old address has to be forwarded by the front controller:
     * Apache answers /public with the folder and runs public/index.php as its
     * directory index, so the request never reaches the router at all.
     */
    public function test_the_hosts_forward_the_old_url_to_the_page()
    {
        $index = (string) file_get_contents(base_path('public/index.php'));

        $this->assertStringContainsString(
            '$requestPath === $publicFolder',
            $index,
            'Without the guard, https://host/public runs the application with the path "/" '
            . 'and shows the login page instead of the resources page.'
        );

        $this->assertStringContainsString(
            "'/resources/public'",
            $index,
            'The folder has to forward to the page it no longer serves.'
        );
    }
}
