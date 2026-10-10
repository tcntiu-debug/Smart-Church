<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fully retire the Admin FOF (Foundation of Faith) program by dropping its tables.
 *
 * Drop order respects foreign keys: children first, then the cohort parent.
 * fof_mark_attendance_table -> fof_register_table
 * fof_register_table       -> fof_cohort_setting
 *
 * The member_tracking_followup.fof_register_id column was only ever used by the
 * FOF assignment flow (department_id = 15); it is dropped here as well. It was
 * added as a plain integer column (no DB-level foreign key) by migration
 * 2026_05_24_205010, so no constraint needs to be removed for it.
 *
 * FOF remains a valid department on tiu_member (department_name JSON); this
 * migration only removes the standalone program tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Defensively drop any FK that references a FOF table before dropping.
        $this->dropForeignKeysReferencing('fof_mark_attendance_table');
        $this->dropForeignKeysReferencing('fof_register_table');
        $this->dropForeignKeysReferencing('fof_cohort_setting');

        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('fof_mark_attendance_table');
        Schema::dropIfExists('fof_register_table');
        Schema::dropIfExists('fof_cohort_setting');

        // Remove the now-unused FOF assignment column.
        if (Schema::hasColumn('member_tracking_followup', 'fof_register_id')) {
            Schema::table('member_tracking_followup', function ($table) {
                $table->dropColumn('fof_register_id');
            });
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Admin FOF feature retired; tables/column are intentionally not recreated.
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
