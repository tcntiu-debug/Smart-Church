<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app notification centre ("bell") shown in resources/views/layouts/app.blade.php.
 *
 * Deliberately a plain table (not Laravel's polymorphic `notifications` table)
 * because recipients are tiu_member rows and the app queries it directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tiu_member_id')->index()->comment('Recipient (tiu_member.tiu_member_id)');
            $table->unsignedInteger('campus_id')->nullable()->index();
            $table->string('type', 60)->default('birthday_reminder');
            $table->string('title', 191);
            $table->text('body')->nullable();
            $table->json('data')->nullable()->comment('Structured payload (member ids, dates, ...)');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['tiu_member_id', 'read_at'], 'app_notifications_unread_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
