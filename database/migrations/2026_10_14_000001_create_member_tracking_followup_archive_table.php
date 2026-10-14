<?php
// database/migrations/2026_10_14_000001_create_member_tracking_followup_archive_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a guide's work is kept after a first timer is unassigned.
 *
 * `member_tracking_followup` holds the guide (tiu_member_id) and everything they
 * recorded (`followup_response_new`, `followup_response`, `tiu_member_comment`).
 * The 8-week auto-unassign used to delete that row, so the last guide and the
 * last comment were lost. The row is now copied here first.
 *
 * The columns mirror the live `member_tracking_followup` table, which is older
 * than the migrations (it carries followup_mode / followup_response /
 * tiu_member_comment / followup_date_time, and production is missing
 * department_id), so every column here is nullable and the copy step only
 * carries the columns the source row actually has.
 */
class CreateMemberTrackingFollowupArchiveTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('member_tracking_followup_archive', function (Blueprint $table) {
            $table->bigIncrements('archive_id');

            // Where the row came from.
            $table->unsignedBigInteger('tracking_id')->nullable()->index();
            $table->unsignedBigInteger('first_timer_id')->nullable()->index();
            $table->unsignedBigInteger('tiu_member_id')->nullable()->index();

            // The follow-up itself.
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('followup_mode', 50)->nullable();
            $table->string('followup_date_time', 50)->nullable();
            $table->longText('followup_response')->nullable();
            $table->string('followup_rank', 50)->nullable();
            $table->string('tiu_member_comment')->nullable();
            $table->string('timeStamp_registered', 50)->nullable();
            $table->longText('followup_response_new')->nullable();

            // Why and when the row was archived.
            $table->string('archive_reason', 100)->nullable();
            $table->timestamp('archived_at')->nullable()->index();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('member_tracking_followup_archive');
    }
}
