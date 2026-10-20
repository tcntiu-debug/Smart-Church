<?php

namespace Tests\Feature;

use App\Mail\BirthdayReminderMail;
use App\Models\Campus;
use App\Services\BirthdayReminderService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Which transport the Welcome Center digest leaves on.
 *
 * Password reset and the birthday digest share MAIL_MAILER by default; the
 * digest may opt into its own account with BIRTHDAY_MAIL_MAILER=birthday
 * (config/birthday.php + the `birthday` mailer in config/mail.php). These tests
 * pin both halves down, because a wrong mailer name fails silently: the
 * service catches the exception per recipient so the bell and Telegram can
 * still deliver.
 *
 * The delivery test wraps everything in a transaction: deliver() writes the
 * idempotency row of birthday_reminder_logs.
 */
class BirthdayMailerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('birthday_reminder_logs')) {
            $this->markTestSkipped('birthday_reminder_logs is missing.');
        }

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();

        parent::tearDown();
    }

    /**
     * The service with its protected deliver() exposed for one call.
     */
    private function deliveryService()
    {
        return new class extends BirthdayReminderService {
            public function deliverNow($campus, int $daysBefore, Carbon $target, array $birthdays, array $recipients, array $channels): array
            {
                return $this->deliver($campus, $daysBefore, $target, $birthdays, $recipients, $channels, false);
            }
        };
    }

    /**
     * A minimal digest: one birthday, one emailable recipient.
     *
     * @return array{0: Carbon, 1: array<int, array<string, mixed>>, 2: array<int, array<string, mixed>>}
     */
    private function digest(): array
    {
        // Far enough ahead that no real reminder log row can be in the way.
        $target = Carbon::today()->addYears(5);

        return [
            $target,
            [[
                'member_id' => 0,
                'name' => 'Test Member',
                'birthday_label' => $target->format('d M'),
                'birthday_date' => $target->toDateString(),
                'phone' => null,
                'whatsapp_url' => null,
                'email' => null,
                'departments' => [],
                'role' => 'Member',
            ]],
            [[
                'member_id' => 0,
                'name' => 'Welcome Admin',
                'email' => 'welcome@example.test',
                'role' => 'Admin',
            ]],
        ];
    }

    public function test_the_digest_falls_back_to_the_default_mailer()
    {
        config(['birthday.mailer' => '']);

        $this->assertSame(
            (string) config('mail.default'),
            app(BirthdayReminderService::class)->mailerName(),
            'Without BIRTHDAY_MAIL_MAILER the digest must keep using MAIL_MAILER.'
        );
    }

    public function test_the_dedicated_mailer_is_used_when_configured()
    {
        config(['birthday.mailer' => 'birthday']);

        $this->assertSame('birthday', app(BirthdayReminderService::class)->mailerName());
        $this->assertSame('smtp', config('mail.mailers.birthday.transport'));
    }

    public function test_the_digest_is_sent_through_the_dedicated_mailer()
    {
        Mail::fake();
        config(['birthday.mailer' => 'birthday']);

        [$target, $birthdays, $recipients] = $this->digest();

        $result = $this->deliveryService()->deliverNow(
            (object) ['cid' => Campus::defaultId(), 'cname' => Campus::defaultName()],
            3,
            $target,
            $birthdays,
            $recipients,
            ['mail']
        );

        $this->assertSame('sent', $result['status'], 'The digest was not delivered: '.$result['message']);
        $this->assertSame(['mail (1)'], $result['channels']);

        Mail::assertSent(BirthdayReminderMail::class, function (BirthdayReminderMail $mail) {
            return $mail->mailer === 'birthday' && $mail->hasTo('welcome@example.test');
        });
    }

    public function test_the_from_header_falls_back_to_the_global_address()
    {
        config([
            'birthday.from.address' => '',
            'birthday.from.name' => '',
        ]);

        $mail = new BirthdayReminderMail('Ikorodu', 3, Carbon::today()->toDateString(), []);
        $mail->build();

        $this->assertSame([], $mail->from, 'An empty BIRTHDAY_MAIL_FROM_* must not override the global From.');
    }

    public function test_a_birthday_from_address_overrides_the_global_one()
    {
        config([
            'birthday.from.address' => 'welcome@example.test',
            'birthday.from.name' => 'Welcome Center',
        ]);

        $mail = new BirthdayReminderMail('Ikorodu', 3, Carbon::today()->toDateString(), []);
        $mail->build();

        $this->assertSame('welcome@example.test', $mail->from[0]['address'] ?? null);
        $this->assertSame('Welcome Center', $mail->from[0]['name'] ?? null);
    }
}
