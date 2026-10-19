<?php

namespace Tests\Feature;

use App\Models\TiuMember;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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
 * Read-only apart from one transaction that is always rolled back: no
 * RefreshDatabase (the suite runs against the application's real connection).
 * The one POST uses an address that cannot exist, so nothing is mailed.
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

    /**
     * The link opens for somebody who is not signed in, on a phone, out of a
     * mail client. It used to extend layouts.app, so the form arrived inside the
     * dashboard - left menu, welcome name, bell - and every link in that menu
     * only bounces to the login page. Guest pages use layouts.auth (the shell
     * /mregister uses): `ms-logged-out` on <body>, and no `<aside id="ms-side-nav">`.
     */
    public function test_the_reset_form_the_mail_links_to_has_no_dashboard_chrome()
    {
        $response = $this->get(route('password.reset', ['token' => 'probe-token', 'email' => 'member@example.com']));

        $response->assertStatus(200);
        $response->assertSee('ms-logged-out', false);
        $response->assertDontSee('ms-side-nav', false);
        $response->assertDontSee('ms-aside-left-open', false);
        $response->assertDontSee('ms-navbar', false);
    }

    public function test_the_link_request_page_has_no_dashboard_chrome_either()
    {
        $response = $this->get(route('password.request'));

        $response->assertStatus(200);
        $response->assertSee('ms-logged-out', false);
        $response->assertDontSee('ms-side-nav', false);
        $response->assertDontSee('ms-aside-left-open', false);
    }

    public function test_the_reset_form_posts_the_token_it_was_opened_with()
    {
        $this->get(route('password.reset', ['token' => 'probe-token', 'email' => 'member@example.com']))
            ->assertStatus(200)
            ->assertSee('name="token" value="probe-token"', false)
            ->assertSee('name="password_confirmation"', false)
            ->assertSee(route('password.update'), false);
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

    /**
     * The reset mail is sent by `CanResetPassword::sendPasswordResetNotification()`,
     * which calls `$this->notify(new ResetPassword($token))`. `TiuMember` is the
     * model behind `config('auth.providers.users')`, and without the `Notifiable`
     * trait that call is a BadMethodCallException: the token is stored, the mail
     * never leaves, and the modal can only report a 500.
     */
    public function test_the_member_model_can_send_notifications()
    {
        $this->assertTrue(
            in_array(Notifiable::class, class_uses_recursive(TiuMember::class), true),
            'App\Models\TiuMember must use the Notifiable trait or the password reset notification cannot be sent.'
        );

        $this->assertTrue(
            method_exists(TiuMember::class, 'notify'),
            'App\Models\TiuMember::notify() is what the reset notification calls.'
        );
    }

    public function test_the_reset_notification_is_handed_to_the_member()
    {
        $member = TiuMember::query()->whereNotNull('email')->where('email', '<>', '')->first();

        if (! $member) {
            $this->markTestSkipped('No member with an e-mail address to send the notification to.');
        }

        Notification::fake();

        $member->sendPasswordResetNotification('probe-token');

        Notification::assertSentTo($member, ResetPassword::class, function (ResetPassword $notification) {
            return $notification->token === 'probe-token';
        });
    }

    /**
     * The modal reads `errors.email[0]`, and it asks for JSON explicitly so a
     * 419/500 carries a body too. The old handler read `responseJSON.email`,
     * which Laravel never sends - hence the literal "undefined" in the modal.
     */
    public function test_an_unknown_address_is_reported_in_the_shape_the_modal_reads()
    {
        $response = $this->postJson(route('password.email'), [
            'email' => 'nobody-' . uniqid() . '@example.invalid',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['email']]);
        $response->assertJsonPath('errors.email.0', "We can't find a user with that email address.");
    }

    public function test_the_login_page_reads_the_error_shape_laravel_sends()
    {
        $this->get('/')
            ->assertStatus(200)
            ->assertSee('json.errors.email', false)
            ->assertSee('Accept', false);
    }

    /**
     * A `log` (or `array`) mailer writes the message to a file instead of
     * delivering it, so on production the request is refused. That refusal has
     * to be logged at error level: production runs with LOG_LEVEL=error, and a
     * warning about the one thing that stops the mail would never be written.
     */
    public function test_a_log_mailer_is_refused_with_an_error_log_on_production()
    {
        $this->withoutMiddleware();
        $this->app['env'] = 'production';
        config(['mail.default' => 'log']);

        Log::spy();

        $response = $this->postJson(route('password.email'), [
            'email' => 'nobody@example.invalid',
        ]);

        $response->assertStatus(500);
        $this->assertStringContainsString(
            'could not send the reset e-mail',
            (string) $response->json('message'),
            'A log mailer must not be reported to the member as a sent link.'
        );

        Log::shouldHaveReceived('error')->once();
    }

    /**
     * Production shipped without the `password_resets` table, so the broker threw
     * before a mail could be built. `app:sync-schema` is what creates missing
     * tables on the server (the deploy has no shell), so it has to know this one.
     */
    public function test_the_schema_sync_creates_the_password_reset_table()
    {
        $command = new \App\Console\Commands\SyncSchema();

        $property = new \ReflectionProperty($command, 'requiredTables');
        $property->setAccessible(true);

        $tables = $property->getValue($command);

        $this->assertArrayHasKey('password_resets', $tables);
        $this->assertFileExists(base_path($tables['password_resets']));
    }

    /**
     * The modal never shows a reason, so the log line is the only evidence of
     * what a failed send actually tried. It has to name the mailbox, the host,
     * the port and the encryption: dev's template shipped
     * `MAIL_USERNAME=noreply@lit-grp.com` while the mailbox that exists is
     * `no-reply@lit-grp.com`, and that one hyphen is invisible anywhere else.
     */
    public function test_a_failed_send_logs_the_mailbox_it_used()
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'mail.lit-grp.com',
            'mail.mailers.smtp.port' => 587,
            'mail.mailers.smtp.username' => 'no-reply@lit-grp.com',
            'mail.mailers.smtp.encryption' => 'tls',
            'mail.from.address' => 'no-reply@lit-grp.com',
        ]);

        Log::spy();

        $controller = new \App\Http\Controllers\Auth\ForgotPasswordController();
        $method = new \ReflectionMethod($controller, 'logFailure');
        $method->setAccessible(true);

        $method->invoke(
            $controller,
            \Illuminate\Http\Request::create('/password/email', 'POST', ['email' => 'member@example.com']),
            new \RuntimeException('Failed to authenticate on SMTP server with username "noreply@lit-grp.com"')
        );

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function ($message, $context = []) {
                return strpos($message, 'member@example.com') !== false
                    && ($context['mail_username'] ?? null) === 'no-reply@lit-grp.com'
                    && ($context['mail_host'] ?? null) === 'mail.lit-grp.com'
                    && (int) ($context['mail_port'] ?? 0) === 587
                    && ($context['mail_encryption'] ?? null) === 'tls'
                    && ($context['mailer'] ?? null) === 'smtp';
            });
    }

    /**
     * `tiu_member` is the auth table, but it was created from the legacy schema
     * and never had Laravel's `remember_token` column. Nothing reads it, so the
     * gap only shows up on a write: `ResetsPasswords::reset()` calls
     * `setRememberToken()` before `save()`, MySQL then rejects the whole UPDATE
     * with "Unknown column 'remember_token' in 'field list'", the reset form
     * answers 500 - and the single-use token is already gone, so the member
     * cannot even retry with the same link.
     */
    public function test_the_member_table_has_the_remember_token_column_auth_writes()
    {
        if (! Schema::hasTable('tiu_member')) {
            $this->markTestSkipped('The tiu_member table does not exist on this connection.');
        }

        $this->assertTrue(
            Schema::hasColumn('tiu_member', 'remember_token'),
            'tiu_member has no remember_token column: every password reset (and every "remember me" '
            . "login) dies on \"Unknown column ... in 'field list'\". Run `php artisan migrate`, or "
            . '`php artisan app:sync-schema` on a host that has no shell.'
        );
    }

    /**
     * The statement that failed, reproduced: the model update a reset makes. It
     * runs in a transaction that is always rolled back, so no member keeps the
     * probe token and no password changes.
     */
    public function test_the_update_a_password_reset_runs_is_accepted()
    {
        $member = TiuMember::query()->whereNotNull('email')->where('email', '<>', '')->first();

        if (! $member) {
            $this->markTestSkipped('No member with an e-mail address to try the reset write on.');
        }

        DB::beginTransaction();

        try {
            $member->setRememberToken(Str::random(60));

            $this->assertTrue(
                $member->save(),
                'The UPDATE a password reset runs was rejected by the database.'
            );

            $this->assertNotNull(
                DB::table('tiu_member')->where('tiu_member_id', $member->getKey())->value('remember_token'),
                'The remember_token a reset writes was not stored.'
            );
        } finally {
            DB::rollBack();
        }
    }

    /**
     * The whole submission the member makes, through the controller the form
     * posts to - this is the request that answered "Server Error". A real token
     * row is created, the form is posted and the stored hash is checked; the
     * transaction is always rolled back, so the member's password and tokens are
     * left exactly as they were.
     */
    public function test_submitting_the_reset_form_stores_the_new_password()
    {
        $member = TiuMember::query()->whereNotNull('email')->where('email', '<>', '')->first();

        if (! $member) {
            $this->markTestSkipped('No member with an e-mail address to reset.');
        }

        $token = Str::random(64);
        $password = 'Probe-password-123!';

        DB::beginTransaction();

        try {
            DB::table('password_resets')->where('email', $member->email)->delete();
            DB::table('password_resets')->insert([
                'email' => $member->email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]);

            $response = $this->post(route('password.update'), [
                'token' => $token,
                'email' => $member->email,
                'password' => $password,
                'password_confirmation' => $password,
            ]);

            $response->assertSessionHasNoErrors();
            $response->assertRedirect(RouteServiceProvider::HOME);

            $this->assertTrue(
                Hash::check($password, (string) DB::table('tiu_member')
                    ->where('tiu_member_id', $member->getKey())
                    ->value('password')),
                'The reset form answered, but the new password was not stored.'
            );
        } finally {
            DB::rollBack();
        }
    }

    /**
     * The reset signs the member in, but `ResetsPasswords` only calls
     * `Auth::login()`. The left menu and the greeting are rendered from session
     * keys that only the login form used to write (MemberSessionService), so a
     * reset arrived on /home as "Welcome Guest" with the plain member menu - the
     * admin blocks stayed hidden until the member signed out and in again.
     */
    public function test_the_reset_fills_the_session_the_left_menu_is_rendered_from()
    {
        $member = TiuMember::query()
            ->whereNotNull('email')->where('email', '<>', '')
            ->whereIn('member_role', ['Super User', 'Admin'])
            ->first();

        if (! $member) {
            $this->markTestSkipped('No admin with an e-mail address to reset.');
        }

        $token = Str::random(64);
        $password = 'Probe-password-123!';

        DB::beginTransaction();

        try {
            DB::table('password_resets')->where('email', $member->email)->delete();
            DB::table('password_resets')->insert([
                'email' => $member->email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]);

            $response = $this->post(route('password.update'), [
                'token' => $token,
                'email' => $member->email,
                'password' => $password,
                'password_confirmation' => $password,
            ]);

            $response->assertSessionHasNoErrors();

            $this->assertTrue(auth()->check(), 'The reset did not sign the member in.');
            $this->assertEquals($member->getKey(), session('tiu_member_id'));
            $this->assertTrue(session('login'));

            // The keys partials/nav.blade.php and layouts/app.blade.php read.
            $this->assertSame(
                $member->member_role,
                session('member_role'),
                'partials/nav.blade.php decides every admin block from session(member_role).'
            );
            $this->assertSame($member->first_name, session('first_name'));
            $this->assertTrue(
                (bool) session('userIsHODOrLead'),
                'An Admin must open the HOD-Lead View on the page the reset redirects to.'
            );

            // And the menu those keys produce, rendered as the dashboard does.
            $nav = view('partials.nav')->render();
            $this->assertStringContainsString('Admin Console', $nav);
            $this->assertStringContainsString('Welcome Center', $nav);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * A reset proves the mailbox and the new password, not that the account may
     * sign in. The login form turns away a disabled member (`status` 2) and an
     * unverified address, so the reset must not hand them a session either.
     */
    public function test_the_reset_does_not_sign_in_a_disabled_member()
    {
        $member = TiuMember::query()->whereNotNull('email')->where('email', '<>', '')->first();

        if (! $member) {
            $this->markTestSkipped('No member with an e-mail address to reset.');
        }

        $token = Str::random(64);
        $password = 'Probe-password-123!';

        DB::beginTransaction();

        try {
            DB::table('tiu_member')
                ->where('tiu_member_id', $member->getKey())
                ->update(['status' => 2]);

            DB::table('password_resets')->where('email', $member->email)->delete();
            DB::table('password_resets')->insert([
                'email' => $member->email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]);

            $response = $this->post(route('password.update'), [
                'token' => $token,
                'email' => $member->email,
                'password' => $password,
                'password_confirmation' => $password,
            ]);

            $response->assertRedirect(route('login'));
            $response->assertSessionHas('error', 'Account is currently disabled. Please contact Admin');

            $this->assertGuest();
            $this->assertFalse(
                (bool) session('login', false),
                'A refused account must not be left with session(login) set.'
            );
        } finally {
            DB::rollBack();
        }
    }

    /**
     * The column has to be reachable from `app:sync-schema`, not only from
     * `migrate`: the dev and production hosts are deployed over SFTP and their
     * schema is brought in step by that command (docs/PASSWORD-RESET.md).
     */
    public function test_the_schema_sync_can_add_the_remember_token_column()
    {
        $property = new \ReflectionProperty(\App\Console\Commands\SyncSchema::class, 'requiredColumns');
        $property->setAccessible(true);

        $columns = $property->getValue(new \App\Console\Commands\SyncSchema());

        $this->assertArrayHasKey(
            'remember_token',
            $columns['tiu_member'] ?? [],
            'app:sync-schema does not know about tiu_member.remember_token, so the SFTP-only hosts cannot be fixed.'
        );
        $this->assertFileExists(base_path($columns['tiu_member']['remember_token']));
    }
}
