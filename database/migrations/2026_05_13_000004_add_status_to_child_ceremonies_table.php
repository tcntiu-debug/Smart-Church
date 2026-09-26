<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusToChildCeremoniesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('child_ceremonies', function (Blueprint $table) {
            $table->enum('status', ['pending', 'completed'])->default('pending')->after('ceremony_type');
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
            $table->dropColumn('status');
        });
    }
}
