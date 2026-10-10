<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncSchema extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-schema
                            {--dry-run : Report what would change without touching the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bring the server database in step with the deployed code: create the notification tables and retire the Transport / FOF tables';

    /**
     * Tables the code needs but the SFTP-only deploy pipeline cannot create (it
     * has no shell to run `php artisan migrate`), mapped to the migration that
     * creates them. `SendBirthdayReminders` applies the same two files on its
     * first run, so both paths agree on the schema.
     *
     * @var array
     */
    protected $requiredTables = [
        'birthday_reminder_logs' => 'database/migrations/2026_10_09_000001_create_birthday_reminder_logs_table.php',
        'app_notifications' => 'database/migrations/2026_10_09_000002_create_app_notifications_table.php',
    ];

    /**
     * Drop-migrations that retire the Transport (bus route) and standalone FOF
     * modules, applied one file at a time.
     *
     * A list instead of a plain `migrate` on purpose: this database was seeded
     * before the `migrations` table was kept in step, so an older migration can
     * abort a full run - see docs/CPANEL-DEPLOYMENT.md -> Troubleshooting. Every
     * file here is idempotent (`dropIfExists` / guarded column drops).
     *
     * @var array
     */
    protected $dropMigrations = [
        'database/migrations/2026_05_25_000002_drop_transport_foreign_keys_from_tiu_member_table.php',
        'database/migrations/2026_05_25_000003_drop_transport_tables.php',
        'database/migrations/2026_05_25_000004_drop_fof_tables.php',
    ];

    /**
     * Tables the drop-migrations are expected to have removed. Reported at the
     * end so a deployment log proves the retirement actually happened.
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
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Syncing the database schema with the deployed code...');
        $this->newLine();

        $failed = 0;

        foreach ($this->requiredTables as $table => $migration) {
            if (! $this->ensureTable($table, $migration)) {
                $failed++;
            }
        }

        foreach ($this->dropMigrations as $migration) {
            if (! $this->dropLegacyObjects($migration)) {
                $failed++;
            }
        }

        $this->newLine();
        $this->table(['Table the code needs', 'State'], array_map(function ($table) {
            return [$table, Schema::hasTable($table) ? 'present' : 'MISSING'];
        }, array_keys($this->requiredTables)));

        $this->table(['Retired table', 'State'], array_map(function ($table) {
            return [$table, Schema::hasTable($table) ? 'STILL PRESENT' : 'gone'];
        }, $this->legacyTables));

        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing was changed.');

            return 0;
        }

        if ($failed > 0) {
            $this->error("Schema sync finished with {$failed} failed migration(s).");

            return 1;
        }

        $this->info('Schema sync complete: required tables present, retired tables gone.');

        return 0;
    }

    /**
     * Make sure $table exists, applying $migration for it when it does not.
     *
     * A table that is already there is never touched: when its migration is not
     * recorded yet it is recorded as applied, so a later `migrate` cannot fail
     * with "table already exists".
     *
     * @param  string  $table
     * @param  string  $migration
     * @return bool
     */
    protected function ensureTable($table, $migration)
    {
        $name = basename($migration);
        $path = base_path($migration);

        if (! is_file($path)) {
            $this->warn(" - {$name}: file not found, skipped");

            return false;
        }

        if (Schema::hasTable($table)) {
            if ($this->option('dry-run')) {
                $this->line(" - {$name}: table {$table} already present");

                return true;
            }

            if ($this->isRecorded($name)) {
                $this->line(" - {$name}: already applied earlier");
            } else {
                $this->recordAsApplied($name);
                $this->line(" - {$name}: table {$table} already present, migration recorded as applied");
            }

            return true;
        }

        if ($this->option('dry-run')) {
            $this->line(" - {$name}: would be applied (creates {$table})");

            return true;
        }

        return $this->applyMigration($name, $path);
    }

    /**
     * Apply a drop-migration. Every drop in those files is guarded, so this is
     * safe on a database where the tables were already removed by hand.
     *
     * @param  string  $migration
     * @return bool
     */
    protected function dropLegacyObjects($migration)
    {
        $name = basename($migration);
        $path = base_path($migration);

        if (! is_file($path)) {
            $this->warn(" - {$name}: file not found, skipped");

            return false;
        }

        if ($this->option('dry-run')) {
            $this->line(" - {$name}: would be applied");

            return true;
        }

        return $this->applyMigration($name, $path);
    }

    /**
     * Run one migration file with `migrate --path`, which also keeps the
     * `migrations` table in step.
     *
     * @param  string  $name
     * @param  string  $path
     * @return bool
     */
    protected function applyMigration($name, $path)
    {
        try {
            $exitCode = Artisan::call('migrate', [
                '--force' => true,
                '--path' => [$path],
            ]);
            $output = trim(Artisan::output());
        } catch (\Throwable $e) {
            $this->warn(" - {$name}: FAILED - {$e->getMessage()}");

            return false;
        }

        if ($exitCode !== 0) {
            $this->warn(" - {$name}: FAILED with exit code {$exitCode}");
            $this->line($this->indent($output));

            return false;
        }

        $this->line(stripos($output, 'Nothing to migrate') !== false
            ? " - {$name}: already applied earlier"
            : " - {$name}: applied");

        return true;
    }

    /**
     * Whether the migration is already recorded in the `migrations` table.
     *
     * @param  string  $name
     * @return bool
     */
    protected function isRecorded($name)
    {
        if (! Schema::hasTable('migrations')) {
            return false;
        }

        return DB::table('migrations')->where('migration', $name)->exists();
    }

    /**
     * Record a migration as applied without running it (the object it creates is
     * already there), mirroring what the migrator would have written.
     *
     * @param  string  $name
     * @return void
     */
    protected function recordAsApplied($name)
    {
        if (! Schema::hasTable('migrations') || $this->isRecorded($name)) {
            return;
        }

        $batch = ((int) DB::table('migrations')->max('batch')) + 1;

        DB::table('migrations')->insert([
            'migration' => $name,
            'batch' => $batch,
        ]);
    }

    /**
     * Indent multi-line artisan output so it reads as part of the step above.
     *
     * @param  string  $output
     * @return string
     */
    protected function indent($output)
    {
        return '   ' . str_replace("\n", "\n   ", $output);
    }
}
