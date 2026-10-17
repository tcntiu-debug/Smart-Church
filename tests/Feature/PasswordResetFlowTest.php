<?php

namespace Tests\Feature;

use App\Models\TiuMember;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The "Forgot Password" flow behind the login page.
 *
 * `POST /password/email` on its own is not the feature: the mail it sends builds
 * its link with `route('password.reset')`, so while that route was missing the
 * notification threw RouteNotFoundException and the modal on `/` failed instead
 * of mailing anything. These tests pin the whole flow down - the sender, the
 * link, the form the link opens - not just the endpoint that looks wired.
 *
 * Read-only: no RefreshDatabase, no writes. The one POST uses an address that
 * cannot exist, so nothing is mailed.
 */
class PasswordResetFlowTest extends TestCase
{
    public function test_the_link_the_reset_mail_builds_has_a_route()
    {
        $this->assertTrue(
            Route::has('password.reset'),
            'ResetPassword (the notification POST /password/email sends) builds its link from '
            . "route('password.reset'); without that route the sender throws RouteNotFoundException."
        );

        $member = TiuMember::query()->whereNotNull('email')->where('email', '<>', '')->first();

        if (! $member) {
            $this->markTestSkipped('No member with an e-mail address to build the notification for.');
        }

        $link = (new ResetPassword('probe-token'))->toMail($member)->actionUrl;

        $this->assertStringContainsString('/password/reset/probe-token', $link);
        $this->assertStringContainsString('email=', $link);
    }

    public function test_the_forgot_password_modal_posts_to_a_route_that_exists()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee(route('password.email'), false);
    }

    public function test_a_guest_can_open_the_reset_form_the_mail_links_to()
    {
        $this->get(route('password.request'))->assertStatus(200);

        $this->get(route('password.reset', ['token' => 'probe-token', 'email' => 'nobody@example.com']))
            ->assertStatus(200)
            ->assertSee('probe-token');
    }

    public function test_an_unknown_address_is_reported_not_crashed()
    {
        $response = $this->post(route('password.email'), [
            'email' => 'nobody-' . uniqid() . '@example.invalid',
        ]);

        $this->assertNotSame(
            500,
            $response->getStatusCode(),
            'POST /password/email must answer a validation error, not a server error.'
        );

        $response->assertSessionHasErrors('email');
    }
}
