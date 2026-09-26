<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChurchAttendanceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('church_attendance', function (Blueprint $table) {
            $table->id('attendance_id')->comment('Primary key matching legacy');
            $table->unsignedBigInteger('member_id')->nullable()->comment('tiu_member_id or first_timer_id');
            $table->string('member_type', 20)->nullable()->comment('Member, FirstTimer, etc.');
            $table->string('full_name', 100)->nullable();
            $table->unsignedBigInteger('church_type_id')->nullable()->comment('Service type e.g., Main, Youth');
            $table->date('attendance_date')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('church_type_id')->references('id')->on('church_type')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('church_attendance');
    }
}
