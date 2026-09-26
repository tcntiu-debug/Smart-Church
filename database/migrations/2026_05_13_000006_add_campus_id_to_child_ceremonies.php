<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCampusIdToChildCeremonies extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('child_ceremonies', function (Blueprint $table) {
            $table->unsignedBigInteger('campus_id')->nullable()->after('member_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('child_ceremonies', function (Blueprint $table) {
            $table->dropColumn('campus_id');
        });
    }
}
