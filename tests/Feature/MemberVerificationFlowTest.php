<?php

namespace Tests\Feature;

use App\Mail\MemberVerificationMail;
use App\Models\TiuMember;
use App\Services\MemberVerificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The activation mail behind a newly created member account.
 *
 * Registration used to write the row with email_verified = 0, answer "check
 * your email to activate your account" and send nothing: there was no
 * notification, no mailable and no verification route in the Laravel tree -
 * only the "Forgot Password" mail had been ported, which is why that one
 * arrived and this one never did (docs/MEMBER-VERIFICATION.md). These tests
 * pin the flow down the way PasswordResetFlowTest pins the reset flow: the
 * sender, the signed link, the flag the login form reads, the resend page and
 * the "choose your password" page the link lands on (registration stores a
 * random password nobody is told, so activation alone would not let the member
 * sign in).
 *
 * Read-only apart from transactions that are always rolled back: no
 * RefreshDatabase (the suite runs against the application's real connection).
 */
class MemberVerificationFlowTest extends TestCase
{
    public function test_the_activation_routes_exist_and_the_notice_page_is_public()
    {
        $this->assertTrue(Route::has('member.verify'), 'The link in the mail needs route("member.verify").');
        $this->assertTrue(Route::has('member.verification.notice'));
        $this->assertTrue(Route::has('member.verification.resend'));

        $response = $this->get(route('member.verification.notice'));

        $response->assertStatus(200);
        $response->assertSee('ms-logged-out', false);
        $response->assertSee(route('member.verification.resend'), false);
    }

    public function test_the_signed_link_in_the_mail_verifies_the_member()
    {
        $member = $this->memberWithEmail();
        $service = app(MemberVerificationService::class);

        DB::beginTransaction();

        try {
            DB::table('tiu_member')->where('tiu_member_id', $member->getKey())->update(['email_verified' => 0]);

            $url = $service->verificationUrl($member);

            $this->assertStringContainsString('/member/verify/'.$member->getKey().'/', $url);
            $this->assertStringContainsString('signature=', $url);

            $body = (new MemberVerificationMail(
                (string) $member->first_name,
                $url,
                MemberVerificationService::LINK_DAYS
            ))->render();

            $this->assertStringContainsString(
                '/member/verify/'.$member->getKey().'/',
                $body,
                'The mail must carry the activation link.'
            );
            $this->assertStringContainsString('signature=', $body);

            $response = $this->get($url);

            $response->assertRedirect(route('member.password.create'));
            $response->assertSessionHas('success');

            // The page it lands on is the password form, already open for this
            // browser - the id came from the link, never from the query string.
            $this->get(route('member.password.create'))
                ->assertOk()
                ->assertSee($member->email, false)
                ->assertSee('name="password_confirmation"', false);

            $this->assertSame(
                '1',
                (string) DB::table('tiu_member')->where('tiu_member_id', $member->getKey())->value('email_verified'),
                'Opening the link must set the flag the login form reads (MemberSessionService::canSignIn()).'
            );
        } finally {
            DB::rollBack();
        }
    }

    /**
     * The point of the page: the member chooses the password the account never
     * had, and that pair then works on the login form.
     */
    public function test_choosing_a_password_after_activation_lets_the_member_sign_in()
    {
        $member = $this->memberWithEmail();
        $service = app(MemberVerificationService::class);
        $password = 'Probe-password-123!';

        DB::beginTransaction();

        try {
            DB::table('tiu_member')->where('tiu_member_id', $member->getKey())->update([
                'email_verified' => 0,
                'status' => 1,
                // What public registration stores: a random string nobody saw.
                'password' => Hash::make(bin2hex(random_bytes(8))),
            ]);

            $this->get($service->verificationUrl($member))
                ->assertRedirect(route('member.password.create'));

            $this->post(route('member.password.store'), [
                'password' => $password,
                'password_confirmation' => $password,
            ])->assertRedirect(route('login'))->assertSessionHas('success');

            $stored = (string) DB::table('tiu_member')->where('tiu_member_id', $member->getKey())->value('password');

            $this->assertTrue(Hash::check($password, $stored), 'The chosen password was not stored hashed.');

            // And the pair works on the login form.
            $this->post(route('login.post'), [
                'email' => $member->email,
                'password' => $password,
            ])->assertRedirect('/home');

            $this->assertAuthenticated();
        } finally {
            DB::rollBack();
        }
    }

    /**
     * A mismatch is the usual failure - same rule as the reset form - and it
     * must not write anything.
     */
    public function test_a_mismatched_password_is_refused_and_nothing_is_saved()
    {
        $member = $this->memberWithEmail();
        $service = app(MemberVerificationService::class);

        DB::beginTransaction();

        try {
            DB::table('tiu_member')->where('tiu_member_id', $member->getKey())->update(['email_verified' => 0]);

            $before = (string) DB::table('tiu_member')->where('tiu_member_id', $member->getKey())->value('password');

            $this->get($service->verificationUrl($member))->assertRedirect(route('member.password.create'));

            $this->post(route('member.password.store'), [
                'password' => 'Probe-password-123!',
                'password_confirmation' => 'different-password',
            ])->assertSessionHasErrors('password');

            $this->assertSame(
                $before,
                (string) DB::table('tiu_member')->where('tiu_member_id', $member->getKey())->value('password'),
                'A refused form must not write a password.'
            );
        } finally {
            DB::rollBack();
        }
    }

    /**
     * The form is not a public password-reset page: without the link opened in
     * this browser there is nothing to open it for.
     */
    public function test_the_password_page_needs_the_activation_link_first()
    {
        $this->get(route('member.password.create'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->post(route('member.password.store'), [
            'password' => 'Probe-password-123!',
            'password_confirmation' => 'Probe-password-123!',
        ])->assertRedirect(route('login'))->assertSessionHas('error');
    }

    /**
     * The mailed link stays an activation link: once the flag is 1 it leads to
     * the login page, not to another chance to set a password.
     */
    public function test_a_second_click_on_the_link_goes_to_login()
    {
        $member = $this->memberWithEmail();
        $service = app(MemberVerificationService::class);

        DB::beginTransaction();

        try {
            DB::table('tiu_member')->where('tiu_member_id', $member->getKey())->update(['email_verified' => 1]);

            $this->get($service->verificationUrl($member))
                ->assertRedirect(route('login'))
                ->assertSessionHas('success');

            $this->get(route('member.password.create'))->assertRedirect(route('login'));
        } finally {
            DB::rollBack();
        }
    }

    public function test_a_tampered_link_does_not_verify_the_member()
    {
        $member = $this->memberWithEmail();
        $service = app(MemberVerificationService::class);

        DB::beginTransaction();

        try {
            DB::table('tiu_member')->where('tiu_member_id', $member->getKey())->update(['email_verified' => 0]);

            // Changed query string: the HMAC no longer matches.
            $tampered = $this->get($service->verificationUrl($member).'&tampered=1');
            $tampered->assertRedirect(route('login'));
            $tampered->assertSessionHas('error');

            // A valid signature over the wrong hash: the second guard.
            $wrongHash = URL::temporarySignedRoute('member.verify', now()->addDay(), [
                'id' => $member->getKey(),
                'hash' => 'deadbeef',
            ]);

            $this->get($wrongHash)->assertRedirect(route('login'))->assertSessionHas('error');

            $this->assertSame(
                '0',
                (string) DB::table('tiu_member')->where('tiu_member_id', $member->getKey())->value('email_verified'),
                'A link that fails either guard must leave the account unverified.'
            );
        } finally {
            DB::rollBack();
        }
    }

    public function test_public_registration_mails_the_activation_link()
    {
        Mail::fake();

        $churchType = DB::table('church_type')->orderBy('id')->first();
        $community = DB::table('communities')->orderBy('id')->first();

        if (! $churchType || ! $community) {
            $this->markTestSkipped('The church_type / communities lookup tables are empty.');
        }

        $email = 'probe-activation-'.uniqid().'@example.org';

        DB::beginTransaction();

        try {
            $response = $this->post(route('member.public-store'), [
                'first_name' => 'Probe',
                'last_name' => 'Activation',
                'phone_number' => '08012345678',
                'email' => $email,
                'gender' => 'Female',
                'residential_address' => '1 Probe Street',
                'campus_id' => \App\Models\Campus::defaultId(),
                'church_type_id' => $churchType->id,
                'community_id' => $community->id,
            ]);

            $response->assertOk();
            $response->assertJson(['success' => true]);

            Mail::assertSent(MemberVerificationMail::class, function (MemberVerificationMail $mail) use ($email) {
                return $mail->hasTo($email);
            });

            $row = DB::table('tiu_member')->where('email', $email)->first();

            $this->assertNotNull($row, 'The answer promised an inbox the registration never wrote a row for.');
            $this->assertSame(
                '0',
                (string) $row->email_verified,
                'A public registration must stay unverified until the link is opened.'
            );
        } finally {
            DB::rollBack();
        }
    }

    public function test_the_resend_page_sends_a_fresh_link()
    {
        Mail::fake();

        $member = $this->memberWithEmail();

        DB::beginTransaction();

        try {
            DB::table('tiu_member')->where('tiu_member_id', $member->getKey())->update(['email_verified' => 0]);

            $response = $this->post(route('member.verification.resend'), ['email' => $member->email]);

            $response->assertRedirect();
            $response->assertSessionHas('status');

            Mail::assertSent(MemberVerificationMail::class, function (MemberVerificationMail $mail) use ($member) {
                return $mail->hasTo($member->email);
            });
        } finally {
            DB::rollBack();
        }
    }

    public function test_the_resend_page_does_not_mail_an_unknown_address()
    {
        Mail::fake();

        $response = $this->post(route('member.verification.resend'), ['email' => 'nobody-'.uniqid().'@example.org']);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');

        Mail::assertNothingSent();
    }

    public function test_the_resend_page_does_not_mail_an_already_verified_member()
    {
        Mail::fake();

        $member = $this->memberWithEmail();

        DB::beginTransaction();

        try {
            DB::table('tiu_member')->where('tiu_member_id', $member->getKey())->update(['email_verified' => 1]);

            $response = $this->post(route('member.verification.resend'), ['email' => $member->email]);

            $response->assertRedirect();
            $response->assertSessionHas('status');

            Mail::assertNothingSent();
        } finally {
            DB::rollBack();
        }
    }

    public function test_the_service_never_mails_an_address_that_cannot_be_activated()
    {
        Mail::fake();

        $service = app(MemberVerificationService::class);

        $noAddress = new TiuMember([
            'first_name' => 'No',
            'last_name' => 'Address',
            'email' => '',
            'email_verified' => 0,
        ]);
        $this->assertFalse($service->send($noAddress), 'A member without an address cannot be mailed.');

        $verified = new TiuMember([
            'first_name' => 'Already',
            'last_name' => 'Verified',
            'email' => 'verified@example.org',
            'email_verified' => 1,
        ]);
        $this->assertFalse($service->send($verified), 'A verified member must not be mailed an activation link.');

        Mail::assertNothingSent();
    }

    protected function memberWithEmail(): TiuMember
    {
        $member = TiuMember::query()->whereNotNull('email')->where('email', '<>', '')->first();

        if (! $member) {
            $this->markTestSkipped('No member with an e-mail address to activate.');
        }

        return $member;
    }
}
