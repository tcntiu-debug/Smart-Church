<?php

use App\Models\Campus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single campus mode (`config/campus.php`): the campus_id data itself is retired.
 *
 * TCN Ikorodu runs one campus, so a campus on a row carries no information any
 * more - and keeping it is not neutral: row identity that reads back a row it
 * wrote (task / WeekTaskController, the reminder logs) matches on the campus the
 * app writes now, which is NULL. Old rows still holding 1 or 2 would silently
 * stop matching, so the column is emptied here once - after the columns were
 * made nullable (2026_10_14_000002) and after the code that writes NULL was
 * deployed.
 *
 * Guarded by the switch on purpose: with SINGLE_CAMPUS=false the campus is still
 * what routes every row, so an accidental `migrate` on a multi-campus install
 * must not empty it. `down()` has no such guard - it is the "bring the ids back"
 * step you run when a second campus is opened.
 */
class RetireTheCampusIdData extends Migration
{
    /**
     * Every table that carries a campus_id column (the campus table itself
     * obviously keeps its cid).
     *
     * @var array<int, string>
     */
    protected $tables = [
        'announcements',
        'app_notifications',
        'birthday_reminder_logs',
        'child_ceremonies',
        'children_church',
        'communities',
        'department',
        'first_timer',
        'first_time_view_limit',
        'prayer_suggestions',
        'sub_group',
        'task',
        'tiu_member',
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Campus::isSingle()) {
            return;
        }

        $this->eachTable(function ($table) {
            DB::table($table)->update(['campus_id' => null]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * The individual campus of a row cannot be reconstructed, so every retired
     * row goes back onto the campus the church ran - which is where all but a
     * handful of them came from anyway.
     *
     * @return void
     */
    public function down()
    {
        $campusId = Campus::defaultId();

        $this->eachTable(function ($table) use ($campusId) {
            DB::table($table)->whereNull('campus_id')->update(['campus_id' => $campusId]);
        });
    }

    /**
     * Call the callback with every table that actually has a campus_id column.
     *
     * @param  callable  $callback  function (string $table)
     * @return void
     */
    protected function eachTable(callable $callback)
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'campus_id')) {
                $callback($table);
            }
        }
    }
}
