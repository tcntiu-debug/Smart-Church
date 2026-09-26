<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddTimestampsToChurchAttendanceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('church_attendance', function (Blueprint $table) {
            if (!Schema::hasColumn('church_attendance', 'created_at')) {
                $table->timestamps();
            }
        });

        // Migrate existing data from date_created to created_at if date_created column exists
        if (Schema::hasColumn('church_attendance', 'date_created')) {
            DB::table('church_attendance')
                ->whereNull('created_at')
                ->whereNotNull('date_created')
                ->update([
                    'created_at' => DB::raw('date_created'),
                    'updated_at' => DB::raw('date_created'),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('church_attendance', function (Blueprint $table) {
            $table->dropColumn(['created_at', 'updated_at']);
        });
    }
}
