<?php
// database/migrations/2026_01_01_000001_create_first_timers_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFirstTimersTable extends Migration
{
    public function up()
    {
        Schema::create('first_timers', function (Blueprint $table) {
            $table->id('first_timer_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone_number');
            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable();
            $table->string('occupation')->nullable();
            $table->string('attendant_type')->nullable();
            $table->text('address')->nullable();
            $table->unsignedBigInteger('community_id')->nullable();
            $table->timestamp('timeStamp_registered')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('first_timers');
    }
}