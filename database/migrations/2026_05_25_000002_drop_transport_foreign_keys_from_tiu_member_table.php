<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DropTransportForeignKeysFromTiuMemberTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Transport (routes / stops / attendance) is being retired, so tiu_member
     * must no longer depend on transport_stops. The legacy SQL dump created two
     * foreign keys on tiu_member.bus_stop_id (fk_member_bus_stop and
     * fk_member_to_stop); this drops them so tiu_member becomes self-contained.
     * The bus_stop_id column itself is kept as a plain column for now.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('tiu_member')) {
            return;
        }

        foreach ($this->foreignKeysReferencing('tiu_member', 'transport_stops') as $name) {
            Schema::table('tiu_member', function ($table) use ($name) {
                $table->dropForeign($name);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * Intentionally a no-op: transport_stops is being removed, so the
     * constraint must not (and cannot) be re-created.
     *
     * @return void
     */
    public function down()
    {
        //
    }

    /**
     * Names of the foreign keys on $table that reference $referencedTable.
     *
     * @param  string  $table
     * @param  string  $referencedTable
     * @return array<int, string>
     */
    private function foreignKeysReferencing($table, $referencedTable)
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('REFERENCED_TABLE_NAME', $referencedTable)
            ->pluck('CONSTRAINT_NAME')
            ->all();
    }
}
