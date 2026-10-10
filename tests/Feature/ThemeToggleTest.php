<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Display mode (dark / light) switch.
 *
 * These tests deliberately avoid RefreshDatabase: the suite runs against the
 * application's real connection and the guest login page does not touch it.
 */
class ThemeToggleTest extends TestCase
{
    /**
     * The guest login screen exposes the switch, its assets and the early
     * anti-flash resolution script.
     */
    public function test_login_page_offers_the_display_mode_switch()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('data-theme-toggle', false);
        $response->assertSee('aria-label="Switch between dark and light mode"', false);
        $response->assertSee('theme-icon-light fas fa-moon', false);
        $response->assertSee('theme-icon-dark fas fa-sun', false);
        $response->assertSee('assets/css/theme-toggle.css', false);
        $response->assertSee('assets/js/theme.js', false);
        $response->assertSee('prefers-color-scheme: dark', false);
    }

    /**
     * Without a stored choice the page is rendered in light mode.
     */
    public function test_guest_page_defaults_to_light_mode()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('class="ms-body ms-primary-theme ms-logged-out"', false);
    }

    /**
     * A `tiu_theme=dark` cookie is rendered server side, so the dark palette is
     * applied with the HTML itself (no flash of light while loading).
     */
    public function test_dark_cookie_is_rendered_into_the_body_class()
    {
        $response = $this->withUnencryptedCookie('tiu_theme', 'dark')->get('/');

        $response->assertStatus(200);
        $response->assertSee('class="ms-body ms-primary-theme ms-logged-out ms-dark-theme"', false);
    }

    /**
     * A `tiu_theme=light` cookie keeps the light palette.
     */
    public function test_light_cookie_keeps_the_light_palette()
    {
        $response = $this->withUnencryptedCookie('tiu_theme', 'light')->get('/');

        $response->assertStatus(200);
        $response->assertSee('class="ms-body ms-primary-theme ms-logged-out"', false);
        $response->assertDontSee('ms-logged-out ms-dark-theme', false);
    }

    /**
     * The dashboard paints its own light palette (white tiles, white list rows).
     * style.css turns every link and span into #fff inside the dark theme, so the
     * page must ship dark counterparts or its labels end up white on white.
     */
    public function test_dashboard_ships_dark_counterparts_for_its_light_surfaces()
    {
        $member = \App\Models\TiuMember::query()->first();

        if (! $member) {
            $this->markTestSkipped('No member row available to authenticate the dashboard request.');
        }

        $dark = $this->actingAs($member)
            ->withUnencryptedCookie('tiu_theme', 'dark')
            ->get('/home');

        $dark->assertStatus(200);
        $dark->assertSee('ms-aside-left-open ms-dark-theme', false);
        $dark->assertSee('.ms-dark-theme .action-card', false);
        $dark->assertSee('.ms-dark-theme .menu-row', false);

        $light = $this->actingAs($member)
            ->withUnencryptedCookie('tiu_theme', 'light')
            ->get('/home');

        $light->assertStatus(200);
        $light->assertSee('class="ms-body ms-aside-left-open"', false);
        $light->assertSee('.action-card', false);
    }

    /**
     * The My Tasks page (`/my-tasks`) paints its own light palette too. The
     * outer assignee card is repainted by `.ms-dark-theme .card` while its
     * headings use the `!important` `.text-dark` utility, so the page must ship
     * dark counterparts or those names render dark-on-dark (invisible).
     */
    public function test_my_tasks_ships_dark_counterparts_for_its_light_surfaces()
    {
        $member = \App\Models\TiuMember::query()->first();

        if (! $member) {
            $this->markTestSkipped('No member row available to authenticate the My Tasks request.');
        }

        $dark = $this->actingAs($member)
            ->withUnencryptedCookie('tiu_theme', 'dark')
            ->get('/my-tasks');

        $dark->assertStatus(200);
        $dark->assertSee('.ms-dark-theme .assignee-card', false);
        $dark->assertSee('.ms-dark-theme .tracking-panel-header', false);

        $light = $this->actingAs($member)
            ->withUnencryptedCookie('tiu_theme', 'light')
            ->get('/my-tasks');

        $light->assertStatus(200);
        $light->assertSee('.tracking-panel-header', false);
        $light->assertSee('.assignee-card', false);
    }

    /**
     * The Drag & Drop assignment page (`/admin-assign-dragdrop`) paints its own
     * light surfaces (`#f8f9fc` filter boxes, white content/first-timer cards).
     * style.css turns every heading/paragraph/span #fff inside the dark theme but
     * does not know those classes, so without dark twins the text ends up white
     * on white - invisible.
     */
    public function test_drag_drop_page_ships_dark_counterparts_for_its_light_surfaces()
    {
        $member = \App\Models\TiuMember::query()->first();

        if (! $member) {
            $this->markTestSkipped('No member row available to authenticate the Drag & Drop request.');
        }

        $dark = $this->actingAs($member)
            ->withUnencryptedCookie('tiu_theme', 'dark')
            ->get('/admin-assign-dragdrop');

        $dark->assertStatus(200);
        $dark->assertSee('.ms-dark-theme .filters-box', false);
        $dark->assertSee('.ms-dark-theme .content-box', false);
        $dark->assertSee('.ms-dark-theme .first-timer-card', false);
        $dark->assertSee('.ms-dark-theme .text-muted', false);
        $dark->assertSee('.ms-dark-theme .guide-card .card-header .btn-link', false);
        $dark->assertSee('.ms-dark-theme .btn-link', false);

        $light = $this->actingAs($member)
            ->withUnencryptedCookie('tiu_theme', 'light')
            ->get('/admin-assign-dragdrop');

        $light->assertStatus(200);
        $light->assertSee('.filters-box', false);
        $light->assertSee('.content-box', false);
        $light->assertSee('.first-timer-card', false);
    }

    /**
     * The remaining pages that paint their own light surfaces: white cards and
     * tiles style.css never repaints, plus text it forces to #fff. Every local
     * light rule needs a `.ms-dark-theme` twin, otherwise that text ends up
     * white on white (same convention as the dashboard, My Tasks and the Drag &
     * Drop page above).
     *
     * @dataProvider lightSurfacePages
     */
    public function test_local_light_surfaces_ship_dark_counterparts(string $uri, array $darkSelectors, array $lightSelectors)
    {
        $member = \App\Models\TiuMember::query()->first();

        if (! $member) {
            $this->markTestSkipped('No member row available to authenticate the request.');
        }

        $dark = $this->actingAs($member)
            ->withUnencryptedCookie('tiu_theme', 'dark')
            ->get($uri);

        $dark->assertStatus(200, "Dark request to {$uri} did not succeed.");
        $dark->assertSee('ms-dark-theme', false);

        foreach ($darkSelectors as $selector) {
            $dark->assertSee($selector, false);
        }

        $light = $this->actingAs($member)
            ->withUnencryptedCookie('tiu_theme', 'light')
            ->get($uri);

        $light->assertStatus(200, "Light request to {$uri} did not succeed.");

        foreach ($lightSelectors as $selector) {
            $light->assertSee($selector, false);
        }
    }

    /**
     * `uri => [dark twins, light selectors]`, so a page that joins the sweep
     * only has to be listed here.
     */
    public static function lightSurfacePages(): array
    {
        return [
            'children church check-in' => [
                '/children-church',
                [
                    'body.ms-dark-theme',
                    '.ms-dark-theme .search-card',
                    '.ms-dark-theme .child-card',
                    '.ms-dark-theme span.badge-marked',
                    '.ms-dark-theme .text-muted',
                ],
                ['.search-card', '.badge-marked', '.attendance-counter'],
            ],
            'attendance analysis' => [
                '/attendance-analysis',
                [
                    '.ms-dark-theme .filter-section',
                    '.ms-dark-theme .summary-card-neutral',
                    '.ms-dark-theme .summary-card-success',
                    '.ms-dark-theme .panel-heading-info',
                ],
                ['.summary-card-neutral', '.panel-heading-info'],
            ],
            'marketplace purchase' => [
                '/purchase',
                [
                    '.ms-dark-theme .filter-section',
                    '.ms-dark-theme h5.business-name',
                    '.ms-dark-theme p.business-description',
                    '.ms-dark-theme span.business-category',
                ],
                ['.business-name', '.business-category', '.disclaimer-box'],
            ],
            'community map' => [
                '/map-view',
                [
                    '.ms-dark-theme #community-panel',
                    '.ms-dark-theme #community-toggle',
                    '.ms-dark-theme thead.bg-primary',
                ],
                ['#community-panel', '#community-toggle', '.count-badge'],
            ],
            'admin gallery' => [
                '/gallery',
                ['.ms-dark-theme .g-card'],
                ['.g-card', '.btn-download'],
            ],
            'first timer register' => [
                '/ft-register',
                ['.ms-dark-theme .admin-section-divider span'],
                ['.admin-section-divider span'],
            ],
        ];
    }

    /**
     * `/my-tasks/{id}/tasks` is the per-first-timer task timeline. Its profile
     * header, log rows and soft badges are page-local light surfaces, so it has
     * to ship dark twins as well.
     */
    public function test_task_timeline_page_ships_dark_counterparts_for_its_light_surfaces()
    {
        $member = \App\Models\TiuMember::query()->first();

        if (! $member) {
            $this->markTestSkipped('No member row available to authenticate the task timeline request.');
        }

        $firstTimerId = \Illuminate\Support\Facades\DB::table('first_timer')->value('first_timer_id');

        if (! $firstTimerId) {
            $this->markTestSkipped('No first timer row available to render the task timeline.');
        }

        $uri = '/my-tasks/' . $firstTimerId . '/tasks';

        $dark = $this->actingAs($member)
            ->withUnencryptedCookie('tiu_theme', 'dark')
            ->get($uri);

        $dark->assertStatus(200);
        $dark->assertSee('.ms-dark-theme .profile-header', false);
        $dark->assertSee('.ms-dark-theme .log-entry', false);
        $dark->assertSee('.ms-dark-theme span.badge-soft-success', false);

        $light = $this->actingAs($member)
            ->withUnencryptedCookie('tiu_theme', 'light')
            ->get($uri);

        $light->assertStatus(200);
        $light->assertSee('.profile-header', false);
        $light->assertSee('.log-entry', false);
    }
    /**
     * `layouts/app.blade.php` paints every `.btn-link` `#0062cc !important` in
     * its own <head> <style> (the template's `.btn-link` used to be unreadable).
     * A page-local twin *without* `!important` loses to that - which is how the
     * Drag & Drop guide names stayed blue after the page had been patched - so
     * the layout carries its own dark twin next to the light rule, like the
     * notification bell in the same block.
     */
    public function test_layout_button_link_rules_ship_dark_counterparts()
    {
        $member = \App\Models\TiuMember::query()->first();

        if (! $member) {
            $this->markTestSkipped('No member row available to authenticate the request.');
        }

        $dark = $this->actingAs($member)
            ->withUnencryptedCookie('tiu_theme', 'dark')
            ->get('/admin-assign-dragdrop');

        $dark->assertStatus(200);
        $dark->assertSee('#0062cc !important', false);
        $dark->assertSee('.ms-dark-theme .btn-link', false);
        $dark->assertSee('.ms-dark-theme .accordion .card-header .btn-link', false);

        $light = $this->actingAs($member)
            ->withUnencryptedCookie('tiu_theme', 'light')
            ->get('/admin-assign-dragdrop');

        $light->assertStatus(200);
        $light->assertSee('.accordion .card-header .btn-link', false);
    }

}
