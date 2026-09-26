<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePolicyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Church policy acknowledgment/signature records.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('policy', function (Blueprint $table) {
            $table->id('pid')->comment('Primary key matching legacy pid');
            $table->unsignedBigInteger('tiu_member_id');
            $table->string('name', 100);
            $table->string('signature', 10)->comment('Signed/Unsigned');
            $table->date('date_signed')->nullable();
            $table->timestamps();

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
        Schema::dropIfExists('policy');
    }
}
