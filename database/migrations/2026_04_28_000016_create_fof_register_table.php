<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFofRegisterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Foundation of Faith student registration records.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('fof_register_table', function (Blueprint $table) {
            $table->id('reg_id')->comment('Primary key matching legacy');
            $table->string('reg_number', 100)->comment('Unique registration number');
            $table->string('student_name', 100);
            $table->string('student_phone', 50)->nullable();
            $table->string('student_email', 100)->nullable();
            $table->string('student_department', 100)->nullable();
            $table->string('cohort', 15)->nullable()->comment('e.g., A, B, C');
            $table->date('register_date')->nullable();
            $table->integer('total_attendance')->default(0);
            $table->enum('status', ['active', 'completed', 'dropped'])->default('active');
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
        Schema::dropIfExists('fof_register_table');
    }
}
