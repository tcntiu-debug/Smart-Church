<?php
// database/migrations/2026_01_01_000003_create_first_timers_updates_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFirstTimersUpdatesTable extends Migration
{
    public function up()
    {
        Schema::create('first_timers_updates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('first_timer_id');
            $table->string('department')->nullable();
            $table->string('cluster')->nullable();
            $table->enum('foundation_of_faith', ['Yes', 'No'])->default('No');
            $table->timestamps();
            
            $table->foreign('first_timer_id')->references('first_timer_id')->on('first_timers')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('first_timers_updates');
    }
}