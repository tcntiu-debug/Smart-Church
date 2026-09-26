<?php
// database/migrations/2026_01_01_000002_create_member_tracking_followup_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMemberTrackingFollowupTable extends Migration
{
    public function up()
    {
        Schema::create('member_tracking_followup', function (Blueprint $table) {
            $table->id('tracking_id');
            $table->unsignedBigInteger('tiu_member_id');
            $table->unsignedBigInteger('first_timer_id');
            $table->json('followup_response_new')->nullable();
            $table->timestamps();
            
            $table->foreign('tiu_member_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('first_timer_id')->references('first_timer_id')->on('first_timers')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('member_tracking_followup');
    }
}