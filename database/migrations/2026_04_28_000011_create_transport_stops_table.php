<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransportStopsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transport_stops', function (Blueprint $table) {
            $table->id('stop_id')->comment('Primary key matching legacy');
            $table->unsignedBigInteger('route_id')->comment('Links to transport_routes');
            $table->string('stop_name', 255)->comment('e.g., Main Library Entrance');
            $table->time('take_off_time')->nullable()->comment('Scheduled pick-up time');
            $table->decimal('latitude', 10, 8)->nullable()->comment('GPS latitude');
            $table->decimal('longitude', 11, 8)->nullable()->comment('GPS longitude');
            $table->integer('stop_order')->nullable()->comment('Stop sequence on route (1, 2, 3...)');
            $table->timestamps();

            // Foreign key
            $table->foreign('route_id')->references('route_id')->on('transport_routes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transport_stops');
    }
}
