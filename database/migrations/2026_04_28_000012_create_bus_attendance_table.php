<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBusAttendanceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bus_attendance', function (Blueprint $table) {
            $table->id('attendance_id')->comment('Primary key matching legacy');
            $table->unsignedBigInteger('tiu_member_id');
            $table->unsignedBigInteger('route_id');
            $table->unsignedBigInteger('bus_stop_id');
            $table->date('attendance_date');
            $table->datetime('check_in_time');
            $table->unsignedBigInteger('marked_by')->comment('tiu_member_id of the person who marked attendance');
            $table->timestamps();

            // Foreign keys
            $table->foreign('tiu_member_id')->references('tiu_member_id')->on('tiu_member')->onDelete('cascade');
            $table->foreign('route_id')->references('route_id')->on('transport_routes')->onDelete('cascade');
            $table->foreign('bus_stop_id')->references('stop_id')->on('transport_stops')->onDelete('cascade');
            $table->foreign('marked_by')->references('tiu_member_id')->on('tiu_member')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bus_attendance');
    }
}
