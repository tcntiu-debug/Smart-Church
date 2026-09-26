<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChildrenChurchAttendanceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('children_church_attendance', function (Blueprint $table) {
            $table->id('attendance_id');
            $table->unsignedBigInteger('child_id')->comment('FK to children_church.child_id');
            $table->date('attendance_date')->comment('Date of attendance');
            $table->integer('marked_by')->nullable()->comment('Admin who marked attendance (tiu_member_id)');
            $table->timestamps();

            // Foreign keys
            $table->foreign('child_id')->references('child_id')->on('children_church')->onDelete('cascade');
            $table->foreign('marked_by')->references('tiu_member_id')->on('tiu_member')->onDelete('set null');

            // Unique constraint: one attendance per child per day
            $table->unique(['child_id', 'attendance_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('children_church_attendance');
    }
}
