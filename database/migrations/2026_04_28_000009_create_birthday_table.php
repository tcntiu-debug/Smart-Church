<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBirthdayTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('birthday', function (Blueprint $table) {
            $table->id('bid')->comment('Primary key matching legacy bid');
            $table->unsignedBigInteger('tiu_member_id');
            $table->string('Name', 50);
            $table->string('birthday', 50)->comment('e.g., 10 Dec');
            $table->string('extra', 50)->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('tiu_member_id')->references('tiu_member_id')->on('tiu_member')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('birthday');
    }
}
