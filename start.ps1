#Requires -Version 5.1
<#
.SYNOPSIS
    Starts the Smart-Church development server.

.DESCRIPTION
    A thin launcher for `php artisan serve`, with the Windows details handled:
    it finds a PHP binary, picks a free port and prints the URL to open.

    Why this script exists: on Windows, `php artisan serve` used to die with

        Failed to listen on 127.0.0.1:8000 (reason: ?)
        Failed to listen on 127.0.0.1:8001 (reason: ?)
        ...  one port per try ...

    The framework scrubs the parent environment before spawning `php -S`, keeping
    only a whitelist that spells the Windows variable as SYSTEMROOT. Windows hands
    it out as SystemRoot, the check is case-sensitive, so it was deleted and PHP's
    built-in server could not bind its socket. app/Console/Commands/ServeCommand.php
    compares case-insensitively, so `php artisan serve` works normally again.

    -Direct skips artisan and runs `php -S` itself. Use it when the app-level
    override has been lost (for example a stale bootstrap/cache) or when you just
    want the raw built-in server.

.EXAMPLE
    ./start.ps1
    ./start.ps1 -Port 8080
    ./start.ps1 -Direct -Address 0.0.0.0      # reachable from a phone on the LAN
#>
[CmdletBinding()]
param(
    [string]$Address = '127.0.0.1',

    [int]$Port = 8000,

    [switch]$Direct
)

$ErrorActionPreference = 'Stop'

# The repository root is wherever this script lives, so it can be run from any cwd.
$root = if ($PSScriptRoot) { $PSScriptRoot } else { Split-Path -Parent $MyInvocation.MyCommand.Path }
Set-Location $root

function Find-Php {
    $candidates = @(
        'C:\tools\php83\php.exe',
        'C:\xampp\php\php.exe'
    )

    foreach ($candidate in $candidates) {
        if (Test-Path -LiteralPath $candidate) { return $candidate }
    }

    $onPath = Get-Command php -ErrorAction SilentlyContinue
    if ($onPath) { return $onPath.Source }

    throw 'php.exe not found. Add PHP to PATH or install XAMPP (C:\xampp\php\php.exe).'
}

# `php artisan serve` bumps the port by itself, but a fixed --port does not, so
# look for a free one here and keep the two ways of starting the server identical.
function Find-FreePort {
    param([int]$Start, [int]$Tries = 10)

    for ($offset = 0; $offset -lt $Tries; $offset++) {
        $candidate = $Start + $offset
        $listener = $null
        try {
            $listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, $candidate)
            $listener.Start()
            return $candidate
        } catch {
            continue
        } finally {
            if ($listener) { $listener.Stop() }
        }
    }

    throw "No free port between $Start and $($Start + $Tries - 1). Pass -Port."
}

$php = Find-Php
$port = Find-FreePort -Start $Port

if ($port -ne $Port) {
    Write-Host "Port $Port is busy, using $port instead." -ForegroundColor Yellow
}

$url = "http://${Address}:$port"

Write-Host ''
Write-Host "Smart-Church development server" -ForegroundColor Cyan
Write-Host "  php     : $php"
Write-Host "  command : $(if ($Direct) { 'php -S (built-in server, direct)' } else { 'php artisan serve' })"
Write-Host "  url     : $url" -ForegroundColor Green
Write-Host '  stop    : Ctrl+C'
Write-Host ''

Push-Location $root
try {
    if ($Direct) {
        # The same thing artisan does, minus the environment scrubbing: server.php
        # serves public/ as the document root and boots Laravel for every other URL.
        & $php -S "${Address}:$port" -t public server.php
    } else {
        & $php artisan serve --host=$Address --port=$port
    }
} finally {
    Pop-Location
}
