<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFofMarkAttendanceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Foundation of Faith weekly attendance records.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fof_mark_attendance_table', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id')->comment('References fof_register_table.reg_id');
            $table->string('cohort', 15);
            $table->integer('week')->comment('Week number of the program');
            $table->date('attendance_date');
            $table->enum('status', ['Present', 'Absent']);
            $table->boolean('finished')->default(false)->comment('Whether the student has completed FOF');
            $table->unsignedBigInteger('campus_id');
            $table->timestamps();

            $table->foreign('student_id')->references('reg_id')->on('fof_register_table')->onDelete('cascade');
            $table->foreign('campus_id')->references('cid')->on('campus')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fof_mark_attendance_table');
    }
}
