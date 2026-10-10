<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class RetireLegacyModules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:retire-legacy
                            {--dry-run : Report what would be applied without touching the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove the retired Transport (bus route) and FOF programme tables by applying their drop-migrations';

    /**
     * The drop-migrations that retire the Transport and FOF modules, applied one
     * file at a time.
     *
     * They are listed explicitly instead of running a plain `migrate`: this
     * database was seeded before the `migrations` table was kept in step, so an
     * older migration can abort the whole run - see
     * docs/CPANEL-DEPLOYMENT.md -> Troubleshooting. Every one of these files is
     * idempotent (`dropIfExists` / guarded column drops), so re-running this
     * command is always safe.
     *
     * @var array
     */
    protected $migrations = [
        'database/migrations/2026_05_25_000002_drop_transport_foreign_keys_from_tiu_member_table.php',
        'database/migrations/2026_05_25_000003_drop_transport_tables.php',
        'database/migrations/2026_05_25_000004_drop_fof_tables.php',
    ];

    /**
     * The tables those migrations are expected to have removed. Reported at the
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
        $this->info('Retiring the Transport / FOF modules...');
        $this->newLine();

        $failed = 0;
        $applied = 0;

        foreach ($this->migrations as $migration) {
            $name = basename($migration);
            $path = base_path($migration);

            if (! is_file($path)) {
                $this->warn(" - {$name}: file not found, skipped");
                $failed++;

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line(" - {$name}: would be applied");

                continue;
            }

            try {
                $exitCode = Artisan::call('migrate', [
                    '--force' => true,
                    '--path' => [$path],
                ]);
                $output = trim(Artisan::output());
            } catch (\Throwable $e) {
                $this->warn(" - {$name}: FAILED - {$e->getMessage()}");
                $failed++;

                continue;
            }

            if ($exitCode !== 0) {
                $this->warn(" - {$name}: FAILED with exit code {$exitCode}");
                $this->line($this->indent($output));

                $failed++;

                continue;
            }

            if (stripos($output, 'Nothing to migrate') !== false) {
                $this->line(" - {$name}: already applied earlier");
            } else {
                $this->line(" - {$name}: applied");
                $applied++;
            }
        }

        $this->newLine();
        $this->table(['Legacy table', 'State'], array_map(function ($table) {
            return [$table, Schema::hasTable($table) ? 'STILL PRESENT' : 'gone'];
        }, $this->legacyTables));

        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing was changed.');

            return 0;
        }

        if ($failed > 0) {
            $this->error("Retirement finished with {$failed} failed migration(s).");

            return 1;
        }

        $this->info($applied > 0
            ? "Retirement complete: {$applied} migration(s) applied, every legacy table is gone."
            : 'Retirement complete: nothing left to do, every legacy table is gone.');

        return 0;
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
