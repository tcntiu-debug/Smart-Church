<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Web Push: the devices a member allowed, and the alerts waiting to go to them.
 *
 * `push_subscriptions` is one row per browser/PWA install that called
 * pushManager.subscribe(). The endpoint is what the push service (FCM for Chrome,
 * Mozilla, Apple, ...) handed that browser; `endpoint_hash` is its sha256 because
 * a unique index cannot sit on a URL that long under utf8mb4.
 *
 * `push_messages` is the outbox. NotificationService writes a row instead of
 * calling a push service inside the assignment request, and `php artisan push:send`
 * (every minute) drains it - the server has no queue worker, so a table plus the
 * existing schedule:run cron is the queue. See docs/PUSH.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('push_subscriptions')) {
            Schema::create('push_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tiu_member_id')->index()->comment('Owner (tiu_member.tiu_member_id)');

                // The push service endpoint is a long URL; hash it for the index.
                $table->text('endpoint');
                $table->char('endpoint_hash', 64)->unique('push_subscriptions_endpoint_unique');

                // What PushSubscription.getKey() returned, base64url.
                $table->string('p256dh', 191);
                $table->string('auth', 191);
                $table->string('content_encoding', 20)->default('aes128gcm');
                $table->string('user_agent', 255)->nullable()->comment('Which browser/PWA this device is');

                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('last_success_at')->nullable();
                $table->unsignedTinyInteger('fail_count')->default(0);
                // Set when the push service says the subscription is gone (404/410).
                $table->timestamp('disabled_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('push_messages')) {
            Schema::create('push_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tiu_member_id')->index();
                $table->string('type', 60)->default('announcement');
                $table->string('title', 191);
                $table->text('body')->nullable();
                $table->text('url')->nullable()->comment('Where a tap lands');
                $table->string('tag', 100)->nullable()->comment('Collapses a repeat of the same alert');

                $table->string('status', 20)->default('queued')->comment('queued | sending | sent | failed | skipped');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->string('error', 255)->nullable();
                $table->timestamp('send_after')->nullable()->comment('Retry backoff');
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'send_after'], 'push_messages_pending_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('push_messages');
        Schema::dropIfExists('push_subscriptions');
    }
};
