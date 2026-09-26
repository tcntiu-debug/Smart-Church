<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOccupationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('occupation', function (Blueprint $table) {
            $table->id('occ_id')->comment('Primary key matching legacy occ_id');
            $table->string('occ_name', 100)->comment('Occupation title');
            $table->timestamps(); // Added for Laravel best practice
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('occupation');
    }
}
