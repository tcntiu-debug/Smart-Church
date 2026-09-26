<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMarketplaceBusinessesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Church member businesses listed in the marketplace.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('marketplace_businesses', function (Blueprint $table) {
            $table->id('business_id')->comment('Primary key matching legacy');
            $table->unsignedBigInteger('tiu_member_id')->comment('Business owner');
            $table->string('business_name', 255);
            $table->unsignedBigInteger('category_id');
            $table->text('business_details');
            $table->string('contact_email', 100)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->text('office_address')->nullable();
            $table->enum('status', ['pending_approval', 'active', 'pending_deactivation', 'inactive'])->default('pending_approval');
            $table->boolean('agreed_to_terms')->default(false);
            $table->timestamps();

            $table->foreign('tiu_member_id')->references('tiu_member_id')->on('tiu_member')->onDelete('cascade');
            $table->foreign('category_id')->references('category_id')->on('marketplace_categories')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('marketplace_businesses');
    }
}
