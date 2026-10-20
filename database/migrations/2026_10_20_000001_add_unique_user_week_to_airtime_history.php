<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One airtime request per member per ISO week.
 *
 * Production already carries this `unique_user_week` index - it is what
 * rejected the second half of a double-submitted request ("Duplicate entry
 * '1-2026-43'") - but the create migration in this repository never declared
 * it, so a database built from migrations accepted duplicates. Add it where it
 * is missing; the guard keeps the deploy a no-op on production.
 *
 * @return void
 */
class AddUniqueUserWeekToAirtimeHistory extends Migration
{
    /**
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('airtime_history') || $this->indexExists()) {
            return;
        }

        // A database that never had the index may already hold duplicates -
        // exactly the rows it exists to prevent. Keep the earliest request of
        // each week (the one the member would have been credited for) so the
        // index can be created. Written with the query builder, which quotes
        // `year` for MySQL and works the same on SQLite.
        $duplicates = DB::table('airtime_history')
            ->select('user_id', 'year', 'week', DB::raw('MIN(id) as keep_id'))
            ->groupBy('user_id', 'year', 'week')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('airtime_history')
                ->where('user_id', $duplicate->user_id)
                ->where('year', $duplicate->year)
                ->where('week', $duplicate->week)
                ->where('id', '<>', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('airtime_history', function (Blueprint $table) {
            $table->unique(['user_id', 'year', 'week'], 'unique_user_week');
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        if (!Schema::hasTable('airtime_history') || !$this->indexExists()) {
            return;
        }

        Schema::table('airtime_history', function (Blueprint $table) {
            $table->dropUnique('unique_user_week');
        });
    }

    /**
     * Read the index list instead of trying to create it: MySQL fails the whole
     * ALTER TABLE on a duplicate key name, and the deploy must stay a no-op on
     * the database that already has it.
     *
     * @return bool
     */
    private function indexExists()
    {
        $connection = Schema::getConnection();
        $table = $connection->getTablePrefix() . 'airtime_history';

        if ($connection->getDriverName() === 'sqlite') {
            foreach ($connection->select('PRAGMA index_list(\'' . $table . '\')') as $index) {
                if (($index->name ?? null) === 'unique_user_week') {
                    return true;
                }
            }

            return false;
        }

        return (bool) $connection->selectOne(
            'SELECT 1 AS found FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ?
             LIMIT 1',
            [$connection->getDatabaseName(), $table, 'unique_user_week']
        );
    }
}
