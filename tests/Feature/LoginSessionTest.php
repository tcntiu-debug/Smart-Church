<?php

namespace Tests\Feature;

use App\Models\TiuMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The login form is the door the sidebar's session contract was written for.
 * The session setup and the "may this account sign in" rules now live in
 * `App\Services\MemberSessionService`, which the password reset shares, so these
 * tests keep the form itself pinned: same keys, same two refusals.
 *
 * Read-only apart from a transaction that is always rolled back: no
 * RefreshDatabase (the suite runs against the application's real connection).
 */
class LoginSessionTest extends TestCase
{
    public function test_a_successful_login_fills_the_session_the_left_menu_is_rendered_from()
    {
        $admin = TiuMember::query()
            ->whereNotNull('email')->where('email', '<>', '')
            ->whereIn('member_role', ['Super User', 'Admin'])
            ->first();

        if (! $admin) {
            $this->markTestSkipped('No admin with an e-mail address to sign in with.');
        }

        $password = 'Probe-password-123!';

        DB::beginTransaction();

        try {
            DB::table('tiu_member')->where('tiu_member_id', $admin->getKey())->update([
                'password' => Hash::make($password),
                'status' => 1,
                'email_verified' => 1,
            ]);

            $response = $this->post(route('login.post'), [
                'email' => $admin->email,
                'password' => $password,
            ]);

            $response->assertSessionHasNoErrors();
            $response->assertRedirect('/home');

            $this->assertTrue(auth()->check());
            $this->assertTrue(session('login'));
            $this->assertEquals($admin->getKey(), session('tiu_member_id'));

            // The keys partials/nav.blade.php and layouts/app.blade.php read.
            $this->assertSame($admin->member_role, session('member_role'));
            $this->assertSame($admin->first_name, session('first_name'));
            $this->assertTrue((bool) session('userIsHODOrLead'));

            // The menu those keys produce, rendered as the dashboard does.
            $nav = view('partials.nav')->render();
            $this->assertStringContainsString('Admin Console', $nav);
        } finally {
            DB::rollBack();
        }
    }

    public function test_the_login_form_refuses_a_disabled_member()
    {
        $member = TiuMember::query()->whereNotNull('email')->where('email', '<>', '')->first();

        if (! $member) {
            $this->markTestSkipped('No member with an e-mail address to sign in with.');
        }

        $password = 'Probe-password-123!';

        DB::beginTransaction();

        try {
            DB::table('tiu_member')->where('tiu_member_id', $member->getKey())->update([
                'password' => Hash::make($password),
                'status' => 2,
                'email_verified' => 1,
            ]);

            $response = $this->post(route('login.post'), [
                'email' => $member->email,
                'password' => $password,
            ]);

            $response->assertSessionHas('error', 'Account is currently disabled. Please contact Admin');

            $this->assertGuest();
            $this->assertFalse((bool) session('login', false));
        } finally {
            DB::rollBack();
        }
    }

    public function test_the_login_form_refuses_an_unverified_member()
    {
        $member = TiuMember::query()->whereNotNull('email')->where('email', '<>', '')->first();

        if (! $member) {
            $this->markTestSkipped('No member with an e-mail address to sign in with.');
        }

        $password = 'Probe-password-123!';

        DB::beginTransaction();

        try {
            DB::table('tiu_member')->where('tiu_member_id', $member->getKey())->update([
                'password' => Hash::make($password),
                'status' => 1,
                'email_verified' => 0,
            ]);

            $response = $this->post(route('login.post'), [
                'email' => $member->email,
                'password' => $password,
            ]);

            $response->assertSessionHas('error', 'Email not yet verified. Contact Admin');

            $this->assertGuest();
            $this->assertFalse((bool) session('login', false));
        } finally {
            DB::rollBack();
        }
    }
}
