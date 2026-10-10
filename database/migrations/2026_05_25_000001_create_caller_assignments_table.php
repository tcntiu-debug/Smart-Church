<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCallerAssignmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Stores the manual "Caller" overrides chosen in the Weekly Call List modal.
     * A missing row means "use the automatic round-robin assignment".
     *
     * @return void
     */
    public function up()
    {
        Schema::create('caller_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('first_timer_id')->unique();
            $table->unsignedBigInteger('caller_member_id')->nullable()->comment('tiu_member_id of the assigned caller (Touch Point 7 team)');
            $table->string('caller_name', 150)->nullable();
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
        Schema::dropIfExists('caller_assignments');
    }
}
