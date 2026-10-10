<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit/idempotency trail for the Welcome Center birthday reminders.
 *
 * One row is written every time a digest for a given campus + milestone
 * (days_before) + target birthday date is handed to the channels, so the
 * daily scheduler never spams the same reminder twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('birthday_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('campus_id')->index()->comment('Campus the digest belongs to');
            $table->unsignedTinyInteger('days_before')->comment('3 = three days to go, 0 = today');
            $table->date('reminder_date')->comment('The birthday date being reminded about');
            $table->date('sent_on')->comment('Local date the digest went out');
            $table->unsignedInteger('birthday_count')->default(0);
            $table->unsignedInteger('recipient_count')->default(0);
            $table->string('channels', 191)->comment('Channels actually delivered to');
            $table->string('status', 20)->default('sent')->comment('sent | failed');
            $table->text('message')->nullable()->comment('Failure reason / delivery notes');
            $table->timestamps();

            $table->unique(['campus_id', 'days_before', 'reminder_date'], 'birthday_reminder_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('birthday_reminder_logs');
    }
};
