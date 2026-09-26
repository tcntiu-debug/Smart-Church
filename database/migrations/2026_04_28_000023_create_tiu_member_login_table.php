<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTiuMemberLoginTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Tracks member login activity and theme settings.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tiu_member_login', function (Blueprint $table) {
            $table->id('login_id')->comment('Primary key matching legacy');
            $table->unsignedBigInteger('tiu_member_id');
            $table->datetime('date_logged_in')->nullable();
            $table->string('source_address', 100)->nullable()->comment('IP address or device info');
            $table->string('theme_settings', 100)->nullable();
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
        Schema::dropIfExists('tiu_member_login');
    }
}
