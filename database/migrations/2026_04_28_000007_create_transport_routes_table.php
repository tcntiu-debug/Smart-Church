<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransportRoutesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transport_routes', function (Blueprint $table) {
            $table->id('route_id')->comment('Primary key matching legacy');
            $table->string('route_name', 255)->comment('e.g., North Route, Downtown Express');
            $table->string('driver_name', 255)->nullable();
            $table->string('driver_contact', 50)->nullable();
            $table->string('team_lead_name', 255)->nullable();
            $table->string('team_lead_contact', 50)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedBigInteger('campus_id');
            $table->timestamps();

            // Foreign keys
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
        Schema::dropIfExists('transport_routes');
    }
}
