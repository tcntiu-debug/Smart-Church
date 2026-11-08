<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand as BaseServeCommand;
use Symfony\Component\Process\Process;

/**
 * `php artisan serve`, with the Windows environment handled properly.
 *
 * The framework's ServeCommand starts `php -S host:port server.php` and scrubs the
 * parent environment so the child reads the current .env instead of the values the
 * parent already loaded into $_ENV. Everything except a small whitelist is deleted
 * from the child, and that whitelist contains "SYSTEMROOT" - all capitals. Windows
 * hands the variable out as "SystemRoot", the lookup is case-sensitive, so on this
 * machine it is deleted too and PHP's built-in server cannot bind its socket:
 *
 *   Failed to listen on 127.0.0.1:8000 (reason: ?)
 *   Failed to listen on 127.0.0.1:8001 (reason: ?)
 *   ... one port per try, until the command gives up.
 *
 * Comparing case-insensitively keeps SystemRoot (and the other whitelisted names)
 * in the child environment, so plain `php artisan serve` works again. Everything
 * else - .env reload, --host, --port, --tries, --no-reload - is the parent's.
 */
class ServeCommand extends BaseServeCommand
{
    /**
     * Environment variables the `php -S` child is allowed to inherit, compared
     * without regard to case (see the class docblock).
     *
     * @var array<int, string>
     */
    protected $passthrough = [
        'APP_ENV',
        'LARAVEL_SAIL',
        'PHP_CLI_SERVER_WORKERS',
        'PHP_IDE_CONFIG',
        'SYSTEMROOT',
        'XDEBUG_CONFIG',
        'XDEBUG_MODE',
        'XDEBUG_SESSION',
    ];

    /**
     * Start a new server process.
     *
     * @param  bool  $hasEnvironment
     * @return \Symfony\Component\Process\Process
     */
    protected function startProcess($hasEnvironment)
    {
        $process = new Process($this->serverCommand(), null, collect($_ENV)->mapWithKeys(function ($value, $key) use ($hasEnvironment) {
            if ($this->option('no-reload') || ! $hasEnvironment) {
                return [$key => $value];
            }

            return in_array(strtoupper($key), $this->passthrough, true)
                ? [$key => $value]
                : [$key => false];
        })->all());

        $process->start(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        return $process;
    }
}
