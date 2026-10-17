<#
.SYNOPSIS
    Downloads every uploaded file the database still points at from the legacy site.

.DESCRIPTION
    The member photos, gallery images and shared documents live in public/, outside
    the git repository, so neither the SFTP deploy nor the SQL dump carries them.
    Importing the dump therefore restores the row but not the file: profile pages
    render a broken image, because tiu_member.picture_part holds a relative path
    such as display_photo/user_561_6abebf00ee1ea5.05102561.jpeg and nothing on the
    new host answers that URL.

    This script reads the three columns that hold such a path -

      tiu_member.picture_part      -> public/display_photo/, public/uploads/
      photo_gallery.image_path     -> public/gallery_uploads/
      shared_resources.file_path   -> public/uploads/resources/

    - downloads each referenced file from the legacy site into -OutDir, keeping the
    relative path. -OutDir can then be uploaded to the new host's public/ as-is, or
    zipped and extracted there, or copied server side when both sites live in one
    cPanel account (docs/MIGRATION-smartchurch.md section 7).

    Only referenced files are fetched. The legacy display_photo folder also holds
    images that nothing in the database points at any more; they are skipped on
    purpose, which keeps the transfer to ~190 MB instead of the whole folder.

    The run is resumable: a file that already exists in -OutDir with a non-zero
    length is skipped, so interrupting and re-running costs only what is left.

.PARAMETER MysqlExe
    mysql.exe used to read the reference list.

.PARAMETER Database
    Database holding the imported dump (sc_prod_export locally).

.PARAMETER MysqlUser
    MySQL user, root for XAMPP.

.PARAMETER MysqlPassword
    MySQL password; empty for the default XAMPP root account.

.PARAMETER BaseUrl
    The legacy site that still serves the files. No trailing slash.

.PARAMETER OutDir
    Destination folder, laid out like public/ (display_photo\..., uploads\...).

.PARAMETER Only
    Download only this top-level folder, e.g. display_photo. Used to split a long
    run into chunks.

.PARAMETER Skip
    Number of references to skip before downloading; use with -First to fetch a
    slice. A slice is taken from the ordered reference list, so parallel runs with
    different -Skip values never ask for the same file.

.PARAMETER First
    Download at most this many references.

.PARAMETER LogFile
    Append one line per file to this file, so a background run can be watched.

.EXAMPLE
    .\scripts\fetch-legacy-media.ps1
        Fetch all 193 referenced files into C:\xampp\migration\media-originals.

.EXAMPLE
    .\scripts\fetch-legacy-media.ps1 -Only gallery_uploads
        Fetch just the eight gallery images.

.NOTES
    Needs curl.exe (Windows 10 or later) and a running MySQL/MariaDB with the
    imported dump present. Never writes to the database.
#>
[CmdletBinding()]
param(
    [string]$MysqlExe      = 'C:\xampp\mysql\bin\mysql.exe',
    [string]$Database      = 'sc_prod_export',
    [string]$MysqlUser     = 'root',
    [string]$MysqlPassword = '',
    [string]$BaseUrl       = 'https://tiu.tcnikorodu.org',
    [string]$OutDir        = 'C:\xampp\migration\media-originals',
    [string]$Only          = '',
    [int]$Skip             = 0,
    [int]$First            = 0,
    [string]$LogFile       = ''
)

$ErrorActionPreference = 'Stop'

function Write-Log {
    param([string]$Message)
    Write-Host $Message
    if ($LogFile) {
        Add-Content -LiteralPath $LogFile -Value $Message -Encoding utf8
    }
}

# The three columns that hold a path to a file under public/. UNION (not UNION ALL)
# because a few members share the same photo after a duplicate-record merge.
$sql = @'
select picture_part as path from tiu_member where picture_part is not null and picture_part <> ''
union
select image_path from photo_gallery where image_path is not null and image_path <> ''
union
select file_path from shared_resources where file_path is not null and file_path <> ''
'@

$mysqlArgs = @('-u', $MysqlUser, '-N', '-B', $Database, '-e', $sql)
if ($MysqlPassword) {
    $mysqlArgs = @('-u', $MysqlUser, "-p$MysqlPassword", '-N', '-B', $Database, '-e', $sql)
}

$refs = & $MysqlExe @mysqlArgs
if ($LASTEXITCODE -ne 0) {
    throw "mysql failed (exit $LASTEXITCODE); check -Database, -MysqlUser and -MysqlPassword"
}

# A value with no directory part (a bare filename left over from the old
# first_timer_gallery table) has no known base folder and nothing in the deployed
# code reads it, so it is reported rather than guessed at.
$skipped = @()
$wanted  = @()
foreach ($ref in $refs) {
    $ref = "$ref".Trim()
    if (-not $ref) { continue }
    if ($ref -notmatch '/') { $skipped += $ref; continue }
    if ($ref -match '^[a-z]+://') { $skipped += $ref; continue }
    $top = ($ref -split '/')[0]
    if ($Only -and $top -ne $Only) { continue }
    $wanted += ($ref -replace '\\', '/')
}

Write-Log "database      : $Database"
Write-Log "legacy base   : $BaseUrl"
Write-Log "destination   : $OutDir"

if ($Skip -gt 0) { $wanted = @($wanted | Select-Object -Skip $Skip) }
if ($First -gt 0) { $wanted = @($wanted | Select-Object -First $First) }

Write-Log "referenced    : $($wanted.Count) file(s)$(if ($Only) { " under $Only/" })$(if ($Skip -or $First) { " [slice skip=$Skip first=$First]" })"

$ok = 0; $okBytes = [long]0; $kept = 0; $failed = @()

foreach ($ref in $wanted) {
    $dest = Join-Path $OutDir ($ref -replace '/', [IO.Path]::DirectorySeparatorChar)
    $dir  = Split-Path -Parent $dest
    if (-not (Test-Path -LiteralPath $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }

    if ((Test-Path -LiteralPath $dest) -and (Get-Item -LiteralPath $dest).Length -gt 0) {
        $kept++
        $okBytes += (Get-Item -LiteralPath $dest).Length
        continue
    }

    # Escape each segment: the shared resources have spaces in their names.
    $encoded = (($ref -split '/') | ForEach-Object { [uri]::EscapeDataString($_) }) -join '/'
    $url = "$BaseUrl/$encoded"
    $tmp = "$dest.part"

    # curl exits 22 on 404 and writes to stderr; with $ErrorActionPreference = Stop
    # PowerShell 5.1 turns that stderr into a terminating error before the exit code
    # below is ever inspected, so the call is made under Continue and the outcome is
    # judged only by $LASTEXITCODE.
    $prevEap = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    & curl.exe -fsSL --retry 3 --retry-delay 2 --max-time 300 -o $tmp $url 2>$null
    $curlExit = $LASTEXITCODE
    $ErrorActionPreference = $prevEap

    if ($curlExit -ne 0) {
        if (Test-Path -LiteralPath $tmp) { Remove-Item -LiteralPath $tmp -Force }
        $failed += $ref
        Write-Log "FAILED        : $ref (curl exit $curlExit)"
        continue
    }

    $len = (Get-Item -LiteralPath $tmp).Length
    if ($len -le 0) {
        Remove-Item -LiteralPath $tmp -Force
        $failed += $ref
        Write-Log "FAILED        : $ref (empty body)"
        continue
    }

    Move-Item -LiteralPath $tmp -Destination $dest -Force
    $ok++
    $okBytes += $len
    Write-Log ("downloaded    : {0} ({1:N0} kB)" -f $ref, ($len / 1kb))
}

$mine = @(Get-ChildItem -LiteralPath $OutDir -File -Recurse -ErrorAction SilentlyContinue)
Write-Log "---"
Write-Log ("downloaded    : {0} file(s), {1:N1} MB" -f $ok, ($okBytes / 1mb))
Write-Log ("already there : {0} file(s)" -f $kept)
Write-Log ("on disk now   : {0} file(s), {1:N1} MB" -f $mine.Count, (($mine | Measure-Object Length -Sum).Sum / 1mb))
Write-Log ("missing       : {0}" -f $failed.Count)
foreach ($f in $failed) { Write-Log "  missing     : $f" }
if ($skipped.Count) {
    Write-Log ("skipped       : {0} value(s) with no folder part" -f $skipped.Count)
    foreach ($s in $skipped) { Write-Log "  skipped     : $s" }
}
