<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAirtimeHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Airtime recharge request history for members.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('airtime_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('tiu_member_id of requester');
            $table->string('user_name', 255)->nullable();
            $table->string('user_phone', 100)->nullable();
            $table->year('year');
            $table->integer('week');
            $table->datetime('requested_at')->nullable();
            $table->boolean('credited')->default(false);
            $table->datetime('credited_at')->nullable();
            $table->string('status', 50)->default('pending');
            $table->timestamps();

            $table->foreign('user_id')->references('tiu_member_id')->on('tiu_member')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('airtime_history');
    }
}
