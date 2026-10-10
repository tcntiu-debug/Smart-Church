<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fully retire the transport (bus route) feature by dropping its tables.
 *
 * Drop order respects foreign keys: children first, then the route parent.
 * bus_attendance  -> transport_routes (route_id), transport_stops (bus_stop_id)
 * transport_stops -> transport_routes (route_id)
 *
 * The reverse FK from tiu_member.bus_stop_id to transport_stops is dropped by
 * the earlier migration 2026_05_25_000002; it is also dropped defensively here
 * so this migration is safe to run on its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Defensively drop any FK that references a transport table before dropping.
        $this->dropForeignKeysReferencing('transport_routes');
        $this->dropForeignKeysReferencing('transport_stops');

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('bus_attendance');
        Schema::dropIfExists('transport_stops');
        Schema::dropIfExists('transport_routes');
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Transport feature retired; tables are intentionally not recreated.
    }

    /**
     * Drop every foreign key on any table that references the given table.
     */
    private function dropForeignKeysReferencing(string $referencedTable): void
    {
        $database = DB::getDatabaseName();

        $rows = DB::select(
            'SELECT TABLE_NAME, CONSTRAINT_NAME
               FROM information_schema.KEY_COLUMN_USAGE
              WHERE TABLE_SCHEMA = ?
                AND REFERENCED_TABLE_NAME = ?',
            [$database, $referencedTable]
        );

        foreach ($rows as $row) {
            DB::statement("ALTER TABLE `{$row->TABLE_NAME}` DROP FOREIGN KEY `{$row->CONSTRAINT_NAME}`");
        }
    }
};
