<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFirstTimerHangoutTable extends Migration
{
    /**
     * Run the migrations.
     *
     * First timer hangout event registration records.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('first_timer_hangout', function (Blueprint $table) {
            $table->id('h_id')->comment('Primary key matching legacy h_id');
            $table->unsignedBigInteger('first_timer_id')->comment('References first_timer.first_timer_id');
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('phone_number', 200);
            $table->string('occupation', 50)->nullable();
            $table->string('occupation2', 200)->nullable();
            $table->date('hangout_date')->nullable();
            $table->string('member_status', 20)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('extra2', 100)->nullable();
            $table->timestamps();

            $table->foreign('first_timer_id')->references('first_timer_id')->on('first_timer')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('first_timer_hangout');
    }
}
