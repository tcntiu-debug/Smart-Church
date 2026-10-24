<?php

namespace App\Console\Commands;

use App\Services\WebPushCrypto;
use App\Services\WebPushService;
use Illuminate\Console\Command;

/**
 * Makes the VAPID key pair Web Push needs (`php artisan push:keys`).
 *
 * The pair is this site's identity to every push service: the private half signs
 * the messages, the public half is handed to the browser when it subscribes. It
 * lives in .env only - never in the repository - because anybody with the private
 * key can push to this site's subscribers.
 *
 * Replacing the pair is not free: every existing subscription is bound to the old
 * public key and the browsers stop accepting the messages, so each member has to
 * press "Enable phone alerts" again. That is why an existing pair is left alone
 * unless --force is given.
 */
class GenerateWebPushKeys extends Command
{
    /**
     * @var string
     */
    protected $signature = 'push:keys {--force : Replace a key pair that is already configured (existing devices must re-subscribe)}';

    /**
     * @var string
     */
    protected $description = 'Generate the VAPID key pair used to sign Web Push messages';

    public function handle(WebPushService $push)
    {
        if ($push->isConfigured() && ! $this->option('force')) {
            $this->warn('Web Push keys are already configured on this server, and every device that subscribed is bound to them.');
            $this->line('Current public key: '.config('webpush.public_key'));
            $this->newLine();
            $this->line('Nothing was changed. Re-run with --force only if those keys were lost or leaked:');
            $this->line('  php artisan push:keys --force');

            return 1;
        }

        $keys = WebPushCrypto::generateKeys();

        $this->newLine();
        $this->info('Add these two lines to the server\'s .env (never commit them):');
        $this->newLine();
        $this->line('WEBPUSH_PUBLIC_KEY='.$keys['public']);
        $this->line('WEBPUSH_PRIVATE_KEY='.$keys['private']);
        $this->newLine();
        $this->comment('Optional but recommended, so a push service can reach you about a bad request:');
        $this->line('WEBPUSH_SUBJECT=mailto:'.(config('mail.from.address') ?: 'you@example.com'));
        $this->newLine();
        $this->comment('Then clear the cached config (`php artisan config:clear`, or `app:post-deploy`) and visit /notifications on the phone.');
        $this->comment('Every member presses "Enable phone alerts" once per device - see docs/PUSH.md.');

        if ((string) config('webpush.private_key', '') !== '') {
            $this->newLine();
            $this->warn('This replaced the previous pair: every device must subscribe again before it gets alerts.');
        }

        return 0;
    }
}
