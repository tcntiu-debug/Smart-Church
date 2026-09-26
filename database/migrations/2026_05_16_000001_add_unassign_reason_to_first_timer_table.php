<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUnassignReasonToFirstTimerTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('first_timer', function (Blueprint $table) {
            $table->text('unassign_reason')->nullable()->after('status_change_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('first_timer', function (Blueprint $table) {
            $table->dropColumn('unassign_reason');
        });
    }
}
