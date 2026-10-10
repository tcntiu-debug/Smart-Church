<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class PostDeployCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:post-deploy
                            {--migrate : Also run the pending database migrations (--force) as part of this deployment}
                            {--keep-cache : Keep the existing application cache instead of flushing it first}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prepare a freshly deployed Smart-Church instance (directories, caches, storage link, optional migrations)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Smart-Church post-deploy starting...');
        $this->newLine();

        $this->ensureDirectories();

        $results = [];

        if (! $this->option('keep-cache')) {
            $results[] = ['Application cache flushed', $this->runSteps([
                ['Flushing the application cache', 'cache:clear'],
            ])];
        }

        $results[] = ['Configuration cached', $this->runSteps([
            ['Clearing the configuration cache', 'config:clear'],
            ['Rebuilding the configuration cache', 'config:cache'],
        ])];

        $results[] = ['Compiled Blade views rebuilt', $this->runSteps([
            ['Clearing compiled Blade views', 'view:clear'],
            ['Pre-compiling all Blade views', 'view:cache'],
        ])];

        $results[] = ['Public storage linked', $this->linkStorage()];

        // Retired modules (Transport / bus route, standalone FOF programme): the
        // drop-migrations ship with the code, and this is what actually applies
        // them - the SFTP deploy pipeline cannot run artisan on the server. The
        // command is idempotent, so a re-run is a no-op.
        $results[] = ['Retired modules cleaned up', $this->runSteps([
            ['Dropping the retired Transport / FOF tables', 'app:retire-legacy'],
        ])];

        // route:cache is deliberately non-fatal. routes/web.php defines Closure
        // routes (/auto-unassign and /my-inbox) which cannot be serialised, so
        // this step is reported as a warning rather than an error.
        $this->runSteps([
            ['Caching the application routes (optional)', 'route:cache'],
        ]);

        if ($this->option('migrate')) {
            $results[] = ['Database migrations', $this->runSteps([
                ['Running pending migrations', 'migrate', ['--force' => true]],
            ])];
        }

        $this->newLine();
        $this->table(['Step', 'Result'], array_map(function ($result) {
            return [$result[0], $result[1] ? 'OK' : 'FAILED'];
        }, $results));

        $failed = 0;

        foreach ($results as $result) {
            if (! $result[1]) {
                $failed++;
            }
        }

        if ($failed > 0) {
            $this->error("Post-deploy finished with {$failed} failed step(s). Review the output above.");

            return 1;
        }

        $this->info('Post-deploy completed successfully.');

        return 0;
    }

    /**
     * Make sure every writable runtime directory exists. FTP deployments do not
     * create empty directories, and Laravel throws "Please provide a valid cache
     * path" when they are missing.
     *
     * @return void
     */
    protected function ensureDirectories()
    {
        $directories = [
            'storage/app/public',
            'storage/framework/cache/data',
            'storage/framework/sessions',
            'storage/framework/testing',
            'storage/framework/views',
            'storage/logs',
            'public/uploads',
            'public/display_photo',
            'public/gallery_uploads',
        ];

        $created = 0;

        foreach ($directories as $directory) {
            $path = base_path($directory);

            if (File::isDirectory($path)) {
                continue;
            }

            File::makeDirectory($path, 0755, true);
            $created++;

            $this->line("   created {$directory}");
        }

        $this->info($created > 0
            ? "Application directories: {$created} created."
            : 'Application directories: all present.');
    }

    /**
     * Create the public/storage symlink when it is missing.
     *
     * @return bool
     */
    protected function linkStorage()
    {
        $link = public_path('storage');

        if (is_link($link) || file_exists($link)) {
            $this->line(' - Public storage link...');
            $this->line('   Already present, nothing to do.');

            return true;
        }

        return $this->runStep('Create the public storage link', 'storage:link');
    }

    /**
     * Run a list of artisan commands, reporting each one.
     *
     * @param  array  $steps
     * @return bool
     */
    protected function runSteps(array $steps)
    {
        $succeeded = true;

        foreach ($steps as $step) {
            $stepSucceeded = $this->runStep($step[0], $step[1], $step[2] ?? []);

            $succeeded = $succeeded && $stepSucceeded;
        }

        return $succeeded;
    }

    /**
     * Run a single artisan command without aborting on failure.
     *
     * @param  string  $description
     * @param  string  $command
     * @param  array  $parameters
     * @return bool
     */
    protected function runStep($description, $command, array $parameters = [])
    {
        $this->line(" - {$description}...");

        try {
            $exitCode = Artisan::call($command, $parameters);
            $output = trim(Artisan::output());
        } catch (\Throwable $e) {
            $this->warn("   FAILED: {$command} - " . $e->getMessage());

            return false;
        }

        if ($exitCode !== 0) {
            $this->warn("   FAILED: {$command} exited with code {$exitCode}");

            if ($output !== '') {
                $this->line($this->indentOutput($output));
            }

            return false;
        }

        if ($output !== '') {
            $this->line($this->indentOutput($output));
        }

        return true;
    }

    /**
     * Indent multi-line command output so it reads as part of the step above.
     *
     * @param  string  $output
     * @return string
     */
    protected function indentOutput($output)
    {
        return '   ' . str_replace("\n", "\n   ", $output);
    }
}
