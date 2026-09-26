<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFofCohortSettingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Foundation of Faith cohort settings.
     * Cohorts are groups (e.g., A, B, C) that FOF students are organized into.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fof_cohort_setting', function (Blueprint $table) {
            $table->id('cohort_id')->comment('Primary key matching legacy');
            $table->string('cohort_name', 10)->comment('e.g., A, B, C');
            $table->string('cohort_status', 10)->comment('active/inactive');
            $table->unsignedBigInteger('campus_id');
            $table->timestamps();

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
        Schema::dropIfExists('fof_cohort_setting');
    }
}
