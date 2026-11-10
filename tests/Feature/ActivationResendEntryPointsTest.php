<?php

namespace Tests\Feature;

use App\Mail\MemberVerificationMail;
use App\Models\Campus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Where a member meets the activation mail a second time.
 *
 * The activation link and a password reset are two different e-mails
 * (member.verification.resend vs password.email), so the resend link is offered
 * only where it is true: in the popup right after registration, and on the login
 * page when a sign-in was refused for an unverified address or an activation link
 * was dead. Anywhere else it is noise - it used to sit under the password field,
 * and then inside the Forgot Password modal.
 */
class ActivationResendEntryPointsTest extends TestCase
{
    public function test_the_login_page_does_not_mention_activation_by_default()
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('Resend the activation email', false)
            ->assertDontSee('different e-mail from a password reset', false);
    }

    /**
     * The state CustomAuthController and MemberController::verifyEmail set when the
     * member cannot get in: an unverified address, or a link that expired.
     */
    public function test_the_login_page_offers_it_when_the_member_is_stuck()
    {
        $response = $this->withSession(['activation_blocked' => 'member@example.test'])->get('/');

        $response->assertOk()
            ->assertSee('Resend the activation email', false)
            ->assertSee('different e-mail from a password reset', false)
            ->assertSee('email=member%40example.test', false);
    }

    public function test_the_activation_page_prefills_the_address_it_is_given()
    {
        $this->get('/member/verification/notice?email=member%40example.test')
            ->assertOk()
            ->assertSee('value="member@example.test"', false);
    }

    /**
     * The page that shows the popup: registration itself posts over AJAX, so the
     * wording lives in this page's script.
     */
    public function test_the_registration_page_carries_the_check_your_mail_popup()
    {
        $this->get('/mregister')
            ->assertOk()
            ->assertSee('Registration successful', false)
            ->assertSee('within 10 minutes', false)
            ->assertSee('Resend the activation email', false)
            ->assertSee('Go to sign-in', false);
    }

    /**
     * The registration popup names the mailbox and carries the resend button, so it
     * needs both in the answer. No RefreshDatabase: the suite runs against the real
     * connection, so the row is created inside a transaction and rolled back.
     */
    public function test_registration_answers_with_the_mailbox_and_the_resend_url()
    {
        Mail::fake();

        $churchType = DB::table('church_type')->orderBy('id')->first();
        $community = DB::table('communities')->orderBy('id')->first();

        if (! $churchType || ! $community) {
            $this->markTestSkipped('The church_type / communities lookup tables are empty.');
        }

        $email = 'probe-resend-'.uniqid().'@example.org';

        DB::beginTransaction();

        try {
            $response = $this->post(route('member.public-store'), [
                'first_name' => 'Probe',
                'last_name' => 'Resend',
                'phone_number' => '08012345678',
                'email' => $email,
                'gender' => 'Female',
                'residential_address' => '1 Probe Street',
                'campus_id' => Campus::defaultId(),
                'church_type_id' => $churchType->id,
                'community_id' => $community->id,
            ]);

            $response->assertOk()->assertJson(['success' => true]);

            Mail::assertSent(MemberVerificationMail::class, function (MemberVerificationMail $mail) use ($email) {
                return $mail->hasTo($email);
            });

            $payload = $response->json();

            $this->assertSame($email, $payload['email'], 'The popup has to name the mailbox the link went to.');
            $this->assertNotNull($payload['activate_url'], 'The popup has to offer a way to get another activation mail.');
            $this->assertStringContainsString('member/verification/notice', $payload['activate_url']);
            $this->assertStringContainsString('email='.urlencode($email), $payload['activate_url']);
        } finally {
            DB::rollBack();
        }
    }
}
