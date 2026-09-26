<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMarketplaceCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Marketplace business categories (e.g., Food, Tech, Fashion).
     *
     * @return void
     */
    public function up()
    {
        Schema::create('marketplace_categories', function (Blueprint $table) {
            $table->id('category_id')->comment('Primary key matching legacy');
            $table->string('category_name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('marketplace_categories');
    }
}
