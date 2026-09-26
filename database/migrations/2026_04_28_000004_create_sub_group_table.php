<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubGroupTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sub_group', function (Blueprint $table) {
            $table->id('subid')->comment('Primary key matching legacy subid');
            $table->string('department_name', 100);
            $table->string('sub_group_name', 50);
            $table->string('lead_id', 11)->comment('References tiu_member.tiu_member_id');
            $table->unsignedBigInteger('campus_id');
            $table->timestamps(); // Added for Laravel best practice

            // Add foreign key if campus table already exists (created in same batch)
            // $table->foreign('campus_id')->references('cid')->on('campus')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sub_group');
    }
}
