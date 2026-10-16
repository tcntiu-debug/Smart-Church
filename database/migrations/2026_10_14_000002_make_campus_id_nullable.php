<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single campus mode (`config/campus.php`): TCN Ikorodu runs one campus again, so
 * campus_id stops being an attribute of anything. New rows are left empty
 * (`Campus::writeId()` returns null) instead of being stamped with a campus
 * nobody picks any more - but the columns are NOT NULL on this database, and a
 * column can only be emptied once it allows it.
 *
 * This migration is the structural half; 2026_10_14_000003 retires the data.
 * The order matters in both directions: NULL first, then the column change back
 * (that is why `down()` of the data migration puts the ids back first).
 *
 * MySQL only, and readable on purpose: the column type is read back from
 * information_schema because Laravel 8 cannot change a column without
 * doctrine/dbal, which this project deliberately does not ship. Every table is
 * guarded, so the run is a no-op where the work is already done (the SFTP-only
 * hosts apply one guarded migration file at a time - see app:sync-schema).
 */
class MakeCampusIdNullable extends Migration
{
    /**
     * The campus_id columns that are NOT NULL on the live database. The already
     * nullable ones (app_notifications, child_ceremonies, prayer_suggestions,
     * task) need nothing.
     *
     * @var array<int, string>
     */
    protected $tables = [
        'announcements',
        'birthday_reminder_logs',
        'children_church',
        'communities',
        'department',
        'first_timer',
        'first_time_view_limit',
        'sub_group',
        'tiu_member',
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->eachColumn(function ($table, $column) {
            if ($column->nullable === 'YES') {
                return;
            }

            DB::statement("ALTER TABLE `{$table}` MODIFY `campus_id` {$column->type} NULL DEFAULT NULL");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $this->eachColumn(function ($table, $column) {
            if ($column->nullable === 'NO') {
                return;
            }

            DB::statement("ALTER TABLE `{$table}` MODIFY `campus_id` {$column->type} NOT NULL");
        });
    }

    /**
     * Hand every table that has a campus_id column to the callback.
     *
     * @param  callable  $callback  function (string $table, object $column)
     * @return void
     */
    protected function eachColumn(callable $callback)
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $column = DB::selectOne(
                'SELECT COLUMN_TYPE as type, IS_NULLABLE as nullable, COLUMN_DEFAULT as `default`
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, 'campus_id']
            );

            if ($column) {
                $callback($table, $column);
            }
        }
    }
}
