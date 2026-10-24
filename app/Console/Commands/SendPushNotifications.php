<?php

namespace App\Console\Commands;

use App\Services\WebPushService;
use Illuminate\Console\Command;

/**
 * Drains the `push_messages` outbox into real Web Push messages.
 *
 * Scheduled every minute (app/Console/Kernel.php) and driven by the same cPanel
 * `schedule:run` cron as the rest of the app, because the server has no queue
 * worker. An alert therefore reaches a phone within about a minute of the
 * assignment that raised it; the in-app bell is still instant.
 *
 * Safe to run by hand:
 *
 *   php artisan push:send            # deliver what is queued (up to config('webpush.batch'))
 *   php artisan push:send --limit=5  # a small batch while testing
 *   php artisan push:send --prune    # also drop delivered rows / dead devices
 *
 * See docs/PUSH.md.
 */
class SendPushNotifications extends Command
{
    /**
     * @var string
     */
    protected $signature = 'push:send
                            {--limit= : How many queued alerts this run may deliver}
                            {--prune : Also delete delivered rows and dead devices past the keep window}';

    /**
     * @var string
     */
    protected $description = 'Deliver queued Web Push notifications to members\' phones';

    /**
     * Always exits 0: a push that could not be delivered is recorded on the row and
     * in the log, and must not turn the per-minute cron into a failing job.
     */
    public function handle(WebPushService $push)
    {
        if (! $push->isConfigured()) {
            $this->warn('Web Push is not configured: set WEBPUSH_PUBLIC_KEY and WEBPUSH_PRIVATE_KEY (php artisan push:keys). Nothing to send.');

            return 0;
        }

        if (! $push->tablesReady()) {
            $this->warn('push_subscriptions / push_messages are missing: run `php artisan app:sync-schema` first.');

            return 0;
        }

        $limit = $this->option('limit');
        $summary = $push->sendPending($limit === null || $limit === '' ? null : (int) $limit);

        $this->info(sprintf(
            'push: sent %d, retried %d, failed %d, skipped %d, dead devices %d (%d delivery attempt(s))',
            $summary['sent'],
            $summary['retried'],
            $summary['failed'],
            $summary['skipped'],
            $summary['gone'],
            $summary['devices']
        ));

        if ($this->option('prune')) {
            $pruned = $push->prune();

            $this->info(sprintf(
                'pruned: %d message(s), %d dead device(s)',
                $pruned['messages'],
                $pruned['subscriptions']
            ));
        }

        return 0;
    }
}
