<?php

namespace App\Console\Commands;

use App\Services\WebPushService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Does the phone actually buzz?" - sends one alert to a member's devices right
 * now, without waiting for an assignment and without touching the outbox.
 *
 *   php artisan push:test                # the only member who has a device
 *   php artisan push:test 123            # a specific tiu_member id
 *   php artisan push:test 123 --title="Test" --body="Hello"
 *
 * Every outcome is printed per device, so a failure names the reason (no key pair,
 * no device, HTTP status from the push service) instead of leaving the operator
 * guessing. See docs/PUSH.md.
 */
class SendTestPush extends Command
{
    /**
     * @var string
     */
    protected $signature = 'push:test
                            {member? : tiu_member id (defaults to the only member with a device)}
                            {--title=Smart Church test alert : Notification title}
                            {--body=If you can read this on your phone, phone alerts work. : Notification body}
                            {--url=/notifications : Where a tap on the notification lands}';

    /**
     * @var string
     */
    protected $description = 'Send one Web Push notification to a member\'s devices, now';

    public function handle(WebPushService $push)
    {
        if (! $push->isConfigured()) {
            $this->error('Web Push is not configured: set WEBPUSH_PUBLIC_KEY and WEBPUSH_PRIVATE_KEY (php artisan push:keys).');

            return 1;
        }

        if (! $push->tablesReady()) {
            $this->error('push_subscriptions / push_messages are missing: run `php artisan app:sync-schema` first.');

            return 1;
        }

        $memberId = $this->memberId($push);

        if ($memberId === null) {
            return 1;
        }

        $result = $push->sendNow($memberId, (string) $this->option('title'), (string) $this->option('body'), (string) $this->option('url'));

        if ($result['devices'] === 0) {
            $this->error('Member '.$memberId.' has no device on file: open /notifications on the phone and press "Enable phone alerts".');

            return 1;
        }

        $this->info(sprintf('Member %d: %d device(s), %d accepted the alert.', $memberId, $result['devices'], $result['sent']));

        foreach ($result['errors'] as $error) {
            $this->warn('  '.$error);
        }

        return $result['sent'] > 0 ? 0 : 1;
    }

    /**
     * The member to send to: the argument, the only subscriber, or null when the
     * command could not tell (and has already said why).
     */
    protected function memberId(WebPushService $push): ?int
    {
        $argument = $this->argument('member');

        if ($argument !== null && $argument !== '') {
            return (int) $argument;
        }

        if (! Schema::hasTable('push_subscriptions')) {
            return null;
        }

        $owners = DB::table('push_subscriptions')
            ->whereNull('disabled_at')
            ->distinct()
            ->pluck('tiu_member_id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->all();

        if ($owners === []) {
            $this->error('No device has subscribed yet: open /notifications on the phone and press "Enable phone alerts".');

            return null;
        }

        if (count($owners) > 1) {
            $this->error('More than one member has a device on file - name one: php artisan push:test <tiu_member_id>');
            $this->line('  '.implode(', ', $owners));

            return null;
        }

        return $owners[0];
    }
}
