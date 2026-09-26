<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFirstTimeViewLimitTable extends Migration
{
    /**
     * Run the migrations.
     *
     * View/data limits configurable per campus for the first-timer module.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('first_time_view_limit', function (Blueprint $table) {
            $table->id();
            $table->string('data_limit', 20)->comment('e.g., 30, 60, 90 days limit');
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
        Schema::dropIfExists('first_time_view_limit');
    }
}
