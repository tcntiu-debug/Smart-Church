<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * `app:sync-schema` - the two things the deploy must do to the database that the
 * SFTP-only pipeline cannot do itself (it has no shell to run artisan):
 *
 *   1. create the tables the shipped code needs (the notification bell and the
 *      birthday reminder log);
 *   2. retire the Transport (bus route) and standalone FOF program - dropping
 *      their tables, the tiu_member transport foreign keys and the files/routes
 *      that belonged to them.
 *
 * Deliberately no RefreshDatabase: the suite runs against the application's real
 * connection and the command is a no-op once everything is in step.
 */
class SyncSchemaTest extends TestCase
{
    /**
     * Tables the code needs after the sync.
     *
     * @var array
     */
    protected $requiredTables = [
        'birthday_reminder_logs',
        'app_notifications',
    ];

    /**
     * Tables the retirement must have removed.
     *
     * @var array
     */
    protected $legacyTables = [
        'transport_routes',
        'transport_stops',
        'bus_attendance',
        'fof_cohort_setting',
        'fof_register_table',
        'fof_mark_attendance_table',
    ];

    /**
     * Every migration the command is responsible for, so a later `migrate` run
     * cannot try to apply them again.
     *
     * @var array
     */
    protected $syncedMigrations = [
        '2026_10_09_000001_create_birthday_reminder_logs_table',
        '2026_10_09_000002_create_app_notifications_table',
        '2026_05_25_000002_drop_transport_foreign_keys_from_tiu_member_table',
        '2026_05_25_000003_drop_transport_tables',
        '2026_05_25_000004_drop_fof_tables',
    ];

    /**
     * The command is registered and can be re-run safely (`app:post-deploy` and
     * the daily scheduler both call it).
     */
    public function test_command_runs_and_is_idempotent()
    {
        $this->artisan('app:sync-schema')->assertExitCode(0);
        $this->artisan('app:sync-schema')->assertExitCode(0);
    }

    /**
     * The dry run reports without creating or dropping anything.
     */
    public function test_dry_run_changes_nothing()
    {
        $this->artisan('app:sync-schema', ['--dry-run' => true])->assertExitCode(0);

        foreach ($this->legacyTables as $table) {
            $this->assertFalse(
                Schema::hasTable($table),
                "The dry run recreated the retired table {$table}."
            );
        }
    }

    /**
     * Tables the shipped code needs exist after the sync.
     */
    public function test_required_tables_exist()
    {
        $this->artisan('app:sync-schema')->assertExitCode(0);

        foreach ($this->requiredTables as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "The table the code needs ({$table}) is missing."
            );
        }
    }

    /**
     * None of the retired tables exist any more.
     */
    public function test_legacy_tables_are_gone()
    {
        $this->artisan('app:sync-schema')->assertExitCode(0);

        foreach ($this->legacyTables as $table) {
            $this->assertFalse(
                Schema::hasTable($table),
                "The retired table {$table} is still present."
            );
        }
    }

    /**
     * Every migration the command owns is recorded, so the schema cannot drift
     * from the `migrations` table.
     */
    public function test_synced_migrations_are_recorded()
    {
        $this->artisan('app:sync-schema')->assertExitCode(0);

        $ran = DB::table('migrations')->pluck('migration');

        foreach ($this->syncedMigrations as $migration) {
            $this->assertTrue(
                $ran->contains($migration),
                "Migration {$migration} is not recorded in the migrations table."
            );
        }
    }

    /**
     * The models, controllers and views of the retired modules are gone from the
     * tree - and must stay gone, otherwise the deploy prune step has nothing to
     * remove on the server.
     */
    public function test_retired_files_are_no_longer_in_the_tree()
    {
        $paths = [
            app_path('Http/Controllers/TransportController.php'),
            app_path('Http/Controllers/FofController.php'),
            app_path('Http/Controllers/Api/TransportController.php'),
            app_path('Http/Controllers/Api/FofController.php'),
            app_path('Models/TransportRoute.php'),
            app_path('Models/TransportStop.php'),
            app_path('Models/BusAttendance.php'),
            app_path('Models/FofCohortSetting.php'),
            app_path('Models/FofRegister.php'),
            app_path('Models/FofMarkAttendance.php'),
            resource_path('views/transport/register.blade.php'),
            resource_path('views/fof/register.blade.php'),
        ];

        foreach ($paths as $path) {
            $this->assertFileDoesNotExist($path, "Retired file {$path} is back in the tree.");
        }
    }

    /**
     * The retired URLs are no longer registered, so a stale link cannot reach a
     * removed controller.
     */
    public function test_retired_routes_are_not_registered()
    {
        $names = ['transport.register', 'transport.admin', 'transport.view-attendance', 'fof.register', 'fof.members'];
        $uris = collect(Route::getRoutes()->getRoutes())->map(function ($route) {
            return $route->uri();
        });

        foreach ($names as $name) {
            $this->assertFalse(Route::has($name), "The retired route name {$name} is still registered.");
        }

        foreach (['bus-route', 'bus-route-admin', 'fof-register', 'fof-members', 'bus-attendance'] as $uri) {
            $this->assertFalse($uris->contains($uri), "The retired URI /{$uri} is still registered.");
        }
    }
}
