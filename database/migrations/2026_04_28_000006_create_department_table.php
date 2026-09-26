<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDepartmentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('department', function (Blueprint $table) {
            $table->id('dept_id')->comment('Primary key matching legacy dept_id');
            $table->string('dept_type', 100)->comment('e.g., Department, Cluster');
            $table->string('dept_name', 100);
            $table->text('dept_address')->nullable();
            $table->unsignedBigInteger('community_id')->nullable();
            $table->string('dept_lead', 100)->nullable()->comment('Department lead name');
            $table->unsignedBigInteger('dept_lead_id')->nullable()->comment('Department lead member ID');
            $table->unsignedBigInteger('campus_id');
            $table->timestamps();

            // Foreign keys
            $table->foreign('campus_id')->references('cid')->on('campus')->onDelete('cascade');
            $table->foreign('community_id')->references('id')->on('communities')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('department');
    }
}
