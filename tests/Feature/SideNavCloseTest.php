<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Closing the side navigation.
 *
 * The panel is open by default on desktop (`<body class="ms-body
 * ms-aside-left-open ...">`), and every control drives the one open state owned
 * by public/assets/js/framework.js: a `.ms-toggler` carrying
 * `data-toggle="slideLeft"` flips `ms-aside-left-open` on <body>, `ms-aside-open`
 * on the panel and `d-block` on the left backdrop. On phones the 255px panel is
 * fixed over the navbar, so the navbar's own toggler ends up behind it - without
 * the close control and the backdrop below, the menu there could only be left by
 * following a link.
 *
 * As with the display-mode tests, no RefreshDatabase: the suite runs against the
 * application's real connection and these requests only render markup.
 */
class SideNavCloseTest extends TestCase
{
    /**
     * The signed-in shell carries a close control inside the panel and a backdrop
     * next to it, and keeps the navbar toggler - all on the slideLeft contract.
     */
    public function test_signed_in_layout_ships_a_close_control_and_a_backdrop()
    {
        $member = \App\Models\TiuMember::query()->first();

        if (! $member) {
            $this->markTestSkipped('No member row available to authenticate the request.');
        }

        $response = $this->actingAs($member)->get('/admin-assign-dragdrop');

        $response->assertStatus(200);

        // The panel's own close button, wired to the same slideLeft toggle.
        $response->assertSee('ms-aside-close ms-toggler', false);
        $response->assertSee('aria-label="Close menu"', false);

        // The backdrop that closes the menu when tapped on a phone.
        $response->assertSee('ms-aside-overlay ms-overlay-left ms-toggler', false);

        // The navbar toggler the desktop layout has always used.
        $response->assertSee('ms-aside-toggler ms-toggler pl-0', false);

        // All three point at the same panel through the same toggle.
        $response->assertSee('data-target="#ms-side-nav"', false);
        $response->assertSee('data-toggle="slideLeft"', false);
    }

    /**
     * The stylesheet styles the close button (with a dark twin) and keeps the
     * backdrop off from 1025px up, where the side nav is a permanent column and
     * dimming the page while it collapses would be wrong.
     */
    public function test_stylesheet_hides_the_backdrop_on_desktop_and_ships_dark_twin()
    {
        // Normalise the line endings first so the block below can be asserted
        // verbatim whatever the checkout used.
        $css = str_replace("\r\n", "\n", file_get_contents(public_path('assets/css/style.css')));

        $this->assertStringContainsString(".ms-aside-close{\n", $css);
        $this->assertStringContainsString(".ms-dark-theme .ms-aside-close{\n", $css);
        $this->assertStringContainsString(
            "@media (min-width: 1025px){\n"
            . "  .ms-aside-overlay.ms-overlay-left{\n"
            . "    display: none !important;\n"
            . "  }\n"
            . "}",
            $css
        );
    }
}
