<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFirstTimerTrashTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Soft-deleted/trashed first timer records for audit trail.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('first_timer_trash', function (Blueprint $table) {
            $table->id('trash_id')->comment('Primary key matching legacy');
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('phone_number', 200);
            $table->string('email', 50)->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('age', 50)->nullable();
            $table->string('occupation', 50)->nullable();
            $table->string('attendant_type', 50)->nullable();
            $table->integer('attendance_count')->default(0);
            $table->string('status', 50)->nullable();
            $table->date('register_date')->nullable();
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
        Schema::dropIfExists('first_timer_trash');
    }
}
