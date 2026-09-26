<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateResourceSharesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Tracks which resources were shared with which members.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('resource_shares', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('resource_id')->comment('References shared_resources.id');
            $table->unsignedBigInteger('member_id')->nullable()->comment('tiu_member_id of recipient');
            $table->timestamps();

            $table->foreign('resource_id')->references('id')->on('shared_resources')->onDelete('cascade');
            $table->foreign('member_id')->references('tiu_member_id')->on('tiu_member')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('resource_shares');
    }
}
