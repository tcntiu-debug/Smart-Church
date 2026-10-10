<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Retirement of the Transport (bus route) and standalone FOF programme modules.
 *
 * The drop-migrations ship with the code, but the SFTP-only deploy pipeline
 * cannot run `php artisan migrate` on the server, so `app:retire-legacy` applies
 * them from the scheduler and from `app:post-deploy` instead. These tests pin
 * down both halves of that promise: the command succeeds (and is idempotent) and
 * the retired tables, files and routes really are gone.
 *
 * Deliberately no RefreshDatabase: the suite runs against the application's real
 * connection and the command is a no-op once the migrations are recorded.
 */
class RetireLegacyModulesTest extends TestCase
{
    /**
     * Tables the retirement must have removed from the database.
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
     * The migrations that must end up recorded in the `migrations` table, so a
     * later `migrate` run cannot try to apply them again.
     *
     * @var array
     */
    protected $retirementMigrations = [
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
        $this->artisan('app:retire-legacy')->assertExitCode(0);
        $this->artisan('app:retire-legacy')->assertExitCode(0);
    }

    /**
     * The dry run reports without touching the database.
     */
    public function test_dry_run_changes_nothing()
    {
        $this->artisan('app:retire-legacy', ['--dry-run' => true])->assertExitCode(0);

        foreach ($this->legacyTables as $table) {
            $this->assertFalse(
                Schema::hasTable($table),
                "The dry run created or restored the retired table {$table}."
            );
        }
    }

    /**
     * After the command has run, none of the retired tables exist any more.
     */
    public function test_legacy_tables_are_gone()
    {
        $this->artisan('app:retire-legacy')->assertExitCode(0);

        foreach ($this->legacyTables as $table) {
            $this->assertFalse(
                Schema::hasTable($table),
                "The retired table {$table} is still present."
            );
        }
    }

    /**
     * The retirement migrations are recorded, so the deploy cannot strand the
     * database with a pending drop.
     */
    public function test_retirement_migrations_are_recorded()
    {
        $ran = DB::table('migrations')->pluck('migration');

        foreach ($this->retirementMigrations as $migration) {
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
