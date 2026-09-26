<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingFieldsToCommunitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds fields from the legacy communities table that were missing
     * in the original migration: latitude, longitude, campus_id
     *
     * @return void
     */
    public function up()
    {
        Schema::table('communities', function (Blueprint $table) {
            if (!Schema::hasColumn('communities', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable()->after('community_name');
            }
            if (!Schema::hasColumn('communities', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            }
            if (!Schema::hasColumn('communities', 'campus_id')) {
                $table->unsignedBigInteger('campus_id')->nullable()->after('longitude');
                $table->foreign('campus_id')->references('cid')->on('campus')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('communities', function (Blueprint $table) {
            $table->dropForeign(['campus_id']);
            $table->dropColumn(['latitude', 'longitude', 'campus_id']);
        });
    }
}
