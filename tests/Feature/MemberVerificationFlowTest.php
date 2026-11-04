<?php

namespace Tests\Feature;

use App\Mail\MemberVerificationMail;
use App\Models\TiuMember;
use App\Services\MemberVerificationService;
use Illuminate\Support\Facades\DB;
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
 * sender, the signed link, the flag the login form reads, and the resend page.
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

            $response->assertRedirect(route('login'));
            $response->assertSessionHas('success');

            $this->assertSame(
                '1',
                (string) DB::table('tiu_member')->where('tiu_member_id', $member->getKey())->value('email_verified'),
                'Opening the link must set the flag the login form reads (MemberSessionService::canSignIn()).'
            );
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
