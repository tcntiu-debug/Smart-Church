<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDepartmentIdToMemberTrackingFollowupTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('member_tracking_followup', function (Blueprint $table) {
            // Add fof_register_id for FOF assignments
            $table->unsignedBigInteger('fof_register_id')->nullable()->after('first_timer_id');

            // Add department_id to distinguish between Tracking (23) and FOF (15)
            $table->unsignedBigInteger('department_id')->default(23)->after('fof_register_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('member_tracking_followup', function (Blueprint $table) {
            $table->dropColumn('department_id');
            $table->dropColumn('fof_register_id');
        });
    }
}
