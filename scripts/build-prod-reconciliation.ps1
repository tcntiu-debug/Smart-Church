<#
.SYNOPSIS
    Builds the additive reconciliation SQL that turns the production dump into a
    database the deployed code can run against, and optionally applies it.

.DESCRIPTION
    docs/PROD-DB-DIFF.md documents the drift by hand: production is missing whole
    tables and about twenty columns the code reads, and carries tables it no
    longer uses. This script makes that difference reproducible instead of typed.

      -StagingDb  the database that models production (an import of the dump,
                  sc_prod_check locally - see docs/PROD-DB-DIFF.md section 1)
      -SourceDb   the local development database, whose schema is what the
                  migrations produce and therefore what the code expects

    It compares the two and writes scripts/sql/prod-reconciliation.sql:

      1. CREATE TABLE, copied verbatim from the source, for every table the code
         has and the dump does not (indexes and constraints come along)
      2. ADD COLUMN for every column the code has and the dump does not, with the
         source definition. campus_id columns are forced NULLABLE, because the
         deployed code writes NULL (docs/SINGLE-CAMPUS.md)
      3. task.extra -> task.department_id as a RENAME when the dump still uses the
         old name, so answers that were already recorded survive
      4. a report of the columns and tables the dump has and the code does not
         (reported, never dropped)
      5. the retired tables with their exact row counts and the mysqldump command
         that archives them before anything is dropped

    Every emitted statement is guarded with the information_schema + PREPARE
    idiom, so applying the file twice is a no-op and applying it to a database
    that is already ahead does nothing. Nothing in the generated file drops a
    table or a column.

.PARAMETER DumpPath
    The production dump, used only with -RebuildStaging.

.PARAMETER RebuildStaging
    DROP and recreate -StagingDb and import -DumpPath into it. Refused unless the
    database name starts with sc_, staging_ or rehearsal_, because it drops data.

.PARAMETER ApplyTo
    Run the generated file against this database once it has been written. Use it
    on the staging database for the rehearsal, or on the new production database
    during the cut-over window (docs/MIGRATION-smartchurch.md).

.EXAMPLE
    .\scripts\build-prod-reconciliation.ps1 -RebuildStaging
        Import the dump into sc_prod_check and write the SQL.

.EXAMPLE
    .\scripts\build-prod-reconciliation.ps1 -ApplyTo sc_prod_check
        Rehearse: write the SQL and apply it to the staging database.

.NOTES
    Needs a running MySQL/MariaDB with both databases present (XAMPP's is enough:
    C:\xampp\mysql\bin\mysql.exe, user root, no password).
#>
[CmdletBinding()]
param(
    [string]$DumpPath = 'C:\xampp\htdocs\legacy-smart-church-files\tcnikoro_smart_church.sql',
    [string]$StagingDb = 'sc_prod_check',
    [string]$SourceDb = 'tcnikoro_smart_church',
    [string]$MySqlExe = 'C:\xampp\mysql\bin\mysql.exe',
    [string]$MySqlDumpExe = '',
    [string]$User = 'root',
    [string]$Password = '',
    [string]$OutFile = '',
    [string]$ApplyTo = '',
    [switch]$RebuildStaging,
    # The tables `php artisan app:sync-schema` retires (drops) on the new
    # database: the Transport (bus route) and standalone FOF modules. They have
    # to be archived before that command runs, which is what section 4 of the
    # generated file is for.
    [string[]]$RetiredTables = @(
        'transport_routes', 'transport_stops', 'bus_attendance',
        'fof_cohort_setting', 'fof_register_table', 'fof_mark_attendance_table'
    ),
    # The code's own table that no migration creates (AdminFirstTimerController
    # writes to it, and it only exists because production has it).
    [string[]]$KeepTables = @('messages')
)

$ErrorActionPreference = 'Stop'
$nl = "`n"

if (-not $OutFile) { $OutFile = Join-Path $PSScriptRoot 'sql\prod-reconciliation.sql' }
if (-not $MySqlDumpExe) { $MySqlDumpExe = Join-Path (Split-Path -Parent $MySqlExe) 'mysqldump.exe' }

foreach ($exe in @($MySqlExe, $MySqlDumpExe)) {
    if (-not (Test-Path $exe)) { throw "Not found: $exe" }
}

$AuthArgs = @("--user=$User")
if ($Password) { $AuthArgs += "--password=$Password" }

function Write-Step { param([string]$Text) Write-Host "`n== $Text" -ForegroundColor Cyan }
function Write-Info { param([string]$Text) Write-Host "   $Text" -ForegroundColor Gray }
function Invoke-Sql {
    param([string]$Database, [string]$Query)

    $arguments = @($AuthArgs) + @('--batch', '--skip-column-names', '--default-character-set=utf8mb4')
    if ($Database) { $arguments += $Database }
    $arguments += @('-e', $Query)

    $output = & $script:MySqlExe @arguments 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "mysql failed (exit $LASTEXITCODE) on '$Database': $($output -join ' | ')"
    }

    return @($output | Where-Object { $_ -ne '' })
}

function Get-Tables {
    param([string]$Database)

    return Invoke-Sql -Database $Database -Query @"
SELECT TABLE_NAME FROM information_schema.TABLES
WHERE TABLE_SCHEMA = '$Database' AND TABLE_TYPE = 'BASE TABLE'
ORDER BY TABLE_NAME
"@
}

function Get-SchemaColumns {
    param([string]$Database)

    $rows = Invoke-Sql -Database $Database -Query @"
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE,
       IF(COLUMN_DEFAULT IS NULL, '@NULL@', COLUMN_DEFAULT), EXTRA, COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = '$Database'
ORDER BY TABLE_NAME, ORDINAL_POSITION
"@

    $columns = @()

    foreach ($row in $rows) {
        $parts = $row -split "`t"
        if ($parts.Count -lt 6) { continue }

        $columns += [pscustomobject]@{
            Table    = $parts[0]
            Column   = $parts[1]
            Type     = $parts[2]
            Nullable = $parts[3]
            Default  = if ($parts[4] -eq '@NULL@') { $null } else { $parts[4] }
            Extra    = $parts[5]
            Comment  = if ($parts.Count -gt 6) { ($parts[6..($parts.Count - 1)] -join ' ') } else { '' }
        }
    }

    return $columns
}

function Get-CreateTable {
    param([string]$Database, [string]$Table)

    $arguments = @($AuthArgs) + @('--no-data', '--compact', '--skip-comments', '--single-transaction', $Database, $Table)
    $output = & $script:MySqlDumpExe @arguments 2>&1

    if ($LASTEXITCODE -ne 0) {
        throw "mysqldump failed (exit $LASTEXITCODE) for '$Database.$Table': $($output -join ' | ')"
    }

    $ddl = ($output | Where-Object { $_ -notmatch '^/\*' -and $_ -notmatch '^SET ' -and $_ -notmatch '^--' }) -join $nl

    if (-not $ddl.Trim()) { throw "mysqldump returned no CREATE TABLE for '$Database.$Table'." }

    return $ddl.Trim()
}

function Get-RowCount {
    param([string]$Database, [string]$Table)

    $count = Invoke-Sql -Database $Database -Query "SELECT COUNT(*) FROM ``$Table``"

    return [int]($count[0])
}

# Runs a .sql file. The mysql client keeps going after a failed statement and
# still exits 0, which would hide exactly the errors this rehearsal is looking
# for, so the output is scanned as well as the exit code.
function Invoke-SqlFile {
    param([string]$Database, [string]$Path)

    $output = & $script:MySqlExe @AuthArgs '--default-character-set=utf8mb4' $Database '-e' "SOURCE $($Path -replace '\\', '/')" 2>&1
    $errors = @($output | Where-Object { $_ -match '^ERROR \d+' })

    if ($LASTEXITCODE -ne 0 -or $errors.Count -gt 0) {
        $detail = $errors -join [Environment]::NewLine
        throw "Applying '$Path' to '$Database' failed ($($errors.Count) error(s), exit $LASTEXITCODE): $detail"
    }

    return $output
}

# A SQL string literal, escaped so it can be embedded in the PREPARE guard's
# string. Both backslash and quote are handled: the first so a default such as
# 'C:\\path' survives, the second because that is what ends the literal.
function ConvertTo-SqlLiteral {
    param([string]$Text)

    return $Text.Replace('\', '\\').Replace("'", "''")
}

function New-Guard {
    param([string]$Title, [string]$ExistsSql, [string]$Ddl)

    return @(
        "-- $Title"
        "SET @sql := IF(EXISTS($ExistsSql),"
        "  'DO 0',"
        "  '$(ConvertTo-SqlLiteral $Ddl)');"
        "PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;"
        ""
    ) -join $nl
}

function Test-Table {
    param([string]$Table)

    return "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$Table'"
}

function Test-Column {
    param([string]$Table, [string]$Column)

    return "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$Table' AND COLUMN_NAME = '$Column'"
}

# The column definition straight out of the source database, minus the parts that
# must never be copied: a NOT NULL campus_id (the deployed code writes NULL) and
# an AUTO_INCREMENT (which belongs to a primary key the difference report covers).
function New-ColumnDefinition {
    param($Column, [switch]$WithoutName)

    # CHANGE COLUMN needs the new name spelled out separately, so the caller asks
    # for the definition without it in that one case.
    $definition = if ($WithoutName) { '' } else { "``$($Column.Column)`` " }
    $definition += $Column.Type

    $nullable = $Column.Nullable -eq 'YES' -or $Column.Column -eq 'campus_id'

    if ($nullable) {
        $definition += ' NULL'
    } else {
        $definition += ' NOT NULL'
    }

    if ($nullable -and $null -eq $Column.Default) {
        $definition += ' DEFAULT NULL'
    } elseif ($null -ne $Column.Default) {
        $default = $Column.Default

        # MariaDB returns a string default already wrapped in quotes ('pending'),
        # MySQL returns it bare. Strip one layer first: the DEFAULT clause below
        # quotes the value again, so without this the column ends up defaulting
        # to the literal text 'pending' - quotes included. An empty default ('')
        # survives the strip unchanged, which is why only one column was hit.
        $wasQuoted = $default.Length -ge 2 -and $default.StartsWith("'") -and $default.EndsWith("'")

        if ($wasQuoted) {
            $default = $default.Substring(1, $default.Length - 2).Replace("''", "'")
        }

        $isNumber = $default -match '^-?\d+(\.\d+)?$'
        $isFunction = $default -match '\)$' -or $default -match '^(CURRENT_TIMESTAMP|CURRENT_DATE|CURRENT_TIME|NULL)$'

        if (!$wasQuoted -and ($isNumber -or $isFunction)) {
            $definition += " DEFAULT $default"
        } else {
            $definition += " DEFAULT '$(ConvertTo-SqlLiteral $default)'"
        }
    }

    if ($Column.Comment) {
        $definition += " COMMENT '$(ConvertTo-SqlLiteral $Column.Comment)'"
    }

    return $definition
}
# ---------------------------------------------------------------------------
# Optional: rebuild the staging database from the production dump
# ---------------------------------------------------------------------------
if ($RebuildStaging) {
    # This is the only destructive thing in the script, so it is fenced off: a
    # database that does not look like a scratch one is never dropped.
    if ($StagingDb -notmatch '^(sc_|staging_|rehearsal_)') {
        throw "-RebuildStaging drops '$StagingDb' - refusing: the name must start with sc_, staging_ or rehearsal_."
    }
    if (-not (Test-Path $DumpPath)) { throw "Dump not found: $DumpPath" }

    Write-Step "Rebuilding $StagingDb from $(Split-Path -Leaf $DumpPath)"

    Invoke-Sql -Database '' -Query "DROP DATABASE IF EXISTS ``$StagingDb``;
CREATE DATABASE ``$StagingDb`` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" | Out-Null

    Invoke-SqlFile -Database $StagingDb -Path $DumpPath | Out-Null

    Write-Info 'imported'
}

# ---------------------------------------------------------------------------
# Read both schemas
# ---------------------------------------------------------------------------
Write-Step "Reading $SourceDb (the code's schema) and $StagingDb (the dump)"

$sourceTables = @(Get-Tables $SourceDb)
$stagingTables = @(Get-Tables $StagingDb)

if ($sourceTables.Count -eq 0) { throw "No tables in '$SourceDb' - point -SourceDb at the database the migrations built." }
if ($stagingTables.Count -eq 0) { throw "No tables in '$StagingDb' - import the dump first (-RebuildStaging)." }

$sourceColumns = @(Get-SchemaColumns $SourceDb)
$stagingColumns = @(Get-SchemaColumns $StagingDb)

$sourceColumnIndex = @{}
foreach ($column in $sourceColumns) { $sourceColumnIndex["$($column.Table).$($column.Column)"] = $column }

$stagingColumnIndex = @{}
foreach ($column in $stagingColumns) { $stagingColumnIndex["$($column.Table).$($column.Column)"] = $column }

Write-Info "$($sourceTables.Count) tables / $($sourceColumns.Count) columns in $SourceDb"
Write-Info "$($stagingTables.Count) tables / $($stagingColumns.Count) columns in $StagingDb"

# ---------------------------------------------------------------------------
# Build the file
# ---------------------------------------------------------------------------
$sql = New-Object System.Collections.Generic.List[string]
$missingTables = @()
$missingColumns = @()
$dumpOnlyColumns = @()
$dumpOnlyTables = @()
$renamedColumns = @()

$sql.Add('-- prod-reconciliation.sql')
$sql.Add('--')
$sql.Add("-- Generated $(Get-Date -Format 'yyyy-MM-dd HH:mm') by scripts/build-prod-reconciliation.ps1")
$sql.Add("--   code schema (source)   : $SourceDb")
$sql.Add("--   production model       : $StagingDb (an import of the production dump)")
$sql.Add('--')
$sql.Add('-- Additive only: this file never drops a table or a column. Every statement is')
$sql.Add('-- wrapped in an information_schema guard, so running it again changes nothing.')
$sql.Add('-- Apply it to the new production database with phpMyAdmin (SQL tab) or:')
$sql.Add('--   mysql -u <user> -p <database> -e "SOURCE C:/path/to/prod-reconciliation.sql"')
$sql.Add('-- then run `php artisan app:sync-schema` and scripts/sql/verify-new-db.sql.')
$sql.Add('--')
$sql.Add('-- See docs/MIGRATION-smartchurch.md section 6 before you apply it.')
$sql.Add('')

# --- 1. Tables the code has and the dump does not -------------------------
$sql.Add('-- ===========================================================================')
$sql.Add('-- 1. Tables the code reads that the dump does not have.')
$sql.Add('--    CREATE TABLE comes from the code schema, so indexes and constraints match.')
$sql.Add('--    Any foreign key in these blocks needs its referenced table to exist')
$sql.Add('--    first; if one fails, create the referenced table and run this file again.')
$sql.Add('-- ===========================================================================')
$sql.Add('')

foreach ($table in $sourceTables) {
    if ($stagingTables -contains $table) { continue }

    $missingTables += $table
    $ddl = Get-CreateTable -Database $SourceDb -Table $table

    $sql.Add((New-Guard -Title "CREATE TABLE ``$table`` (missing from the dump)" `
        -ExistsSql (Test-Table $table) -Ddl ($ddl + ';')))
}

if ($missingTables.Count -eq 0) { $sql.Add('-- none: the dump already has every table the code reads.') ; $sql.Add('') }
# --- 2. Columns the code has and the dump does not -------------------------
$sql.Add('-- ===========================================================================')
$sql.Add('-- 2. Columns the code reads that the dump does not have.')
$sql.Add('--    Definitions come from the code schema. campus_id is always added NULLABLE:')
$sql.Add('--    the deployed code writes a row without a campus (docs/SINGLE-CAMPUS.md).')
$sql.Add('-- ===========================================================================')
$sql.Add('')

foreach ($table in $sourceTables) {
    # A table that is missing entirely arrives whole in section 1.
    if ($stagingTables -notcontains $table) { continue }

    foreach ($column in ($sourceColumns | Where-Object { $_.Table -eq $table })) {
        $key = "$table.$($column.Column)"

        if ($stagingColumnIndex.ContainsKey($key)) { continue }

        if ($column.Extra -eq 'auto_increment') {
            $sql.Add("-- REVIEW: $table.$($column.Column) is AUTO_INCREMENT in the code schema.")
            $sql.Add('--         Add it by hand if the primary key of the table changed.')
            $sql.Add('')
            continue
        }

        # task.extra -> task.department_id. The dump still carries answers under
        # the old name, so this is a rename - an ADD COLUMN would leave them
        # behind and the deployed code would read an empty column.
        if ($table -eq 'task' -and $column.Column -eq 'department_id' -and $stagingColumnIndex.ContainsKey('task.extra')) {
            # Two forms: CHANGE keeps the existing answers, ADD is the fallback for
            # a database where `extra` never existed.
            $added = ConvertTo-SqlLiteral (New-ColumnDefinition $column)
            $changed = "``department_id`` " + (ConvertTo-SqlLiteral (New-ColumnDefinition $column -WithoutName))

            $sql.Add('-- task.extra -> task.department_id (the dump still uses the old name)')
            $sql.Add("SET @sql := IF(EXISTS($(Test-Column 'task' 'department_id')),")
            $sql.Add("  'DO 0',")
            $sql.Add("  IF(EXISTS($(Test-Column 'task' 'extra')),")
            $sql.Add("    'ALTER TABLE ``task`` CHANGE COLUMN ``extra`` $changed',")
            $sql.Add("    'ALTER TABLE ``task`` ADD COLUMN $added'));")
            $sql.Add('PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;')
            $sql.Add('')

            $missingColumns += $key
            $renamedColumns += 'task.extra -> task.department_id'
            continue
        }

        $missingColumns += $key
        $sql.Add((New-Guard -Title "ADD $table.$($column.Column)" -ExistsSql (Test-Column $table $column.Column) `
            -Ddl "ALTER TABLE ``$table`` ADD COLUMN $(New-ColumnDefinition $column);"))
    }
}

if ($missingColumns.Count -eq 0) { $sql.Add('-- none: the dump already has every column the code reads.'); $sql.Add('') }

# --- 3. What the dump has and the code does not ---------------------------
$sql.Add('-- ===========================================================================')
$sql.Add('-- 3. In the dump, not in the code schema. REPORTED, never dropped - a column')
$sql.Add('--    here is usually a feature that was retired, not a mistake to fix.')
$sql.Add('-- ===========================================================================')
$sql.Add('--')

foreach ($table in $stagingTables) {
    if ($sourceTables -contains $table) { continue }

    $rows = Get-RowCount -Database $StagingDb -Table $table
    $dumpOnlyTables += $table

    $note = if ($RetiredTables -contains $table) {
        'retired by app:sync-schema - dropped when it runs, archive it first'
    } elseif ($KeepTables -contains $table) {
        'LIVE - the code writes to it'
    } else {
        'orphan - nothing in the code reads it, archive before any drop'
    }

    $sql.Add("--   $table ($rows row(s)) - $note")
}

$sql.Add('--')
$sql.Add('-- Columns present in the dump and absent from the code schema:')

foreach ($column in $stagingColumns) {
    $key = "$($column.Table).$($column.Column)"

    if ($sourceColumnIndex.ContainsKey($key)) { continue }

    $dumpOnlyColumns += $key
    $sql.Add("--   $key $($column.Type)")
}

if ($dumpOnlyColumns.Count -eq 0) { $sql.Add('--   none.') }

$sql.Add('')
# --- 4. The tables only production has ------------------------------------
# Every table in the dump that the code's schema does not have is the only copy
# of its own data, so all of them are listed with their row counts and archived
# by the command below. `php artisan app:sync-schema` drops the six Transport /
# FOF ones on its first run, which is exactly why the archive comes first
# (docs/MIGRATION-smartchurch.md section 9).
$archive = @($stagingTables | Where-Object { $sourceTables -notcontains $_ })
$droppedBySync = @($archive | Where-Object { $RetiredTables -contains $_ })

$sql.Add('-- ===========================================================================')
$sql.Add('-- 4. The tables only production has. The reconciliation drops none of them:')
$sql.Add('--    they are listed here - with the rows each one holds - so that the archive')
$sql.Add('--    below can be taken before `php artisan app:sync-schema` removes the')
$sql.Add('--    retired Transport / FOF ones.')
$sql.Add('-- ===========================================================================')
$sql.Add('--')

foreach ($table in $archive) {
    $rows = Get-RowCount -Database $StagingDb -Table $table
    $note = if ($RetiredTables -contains $table) {
        'retired by app:sync-schema'
    } elseif ($KeepTables -contains $table) {
        'LIVE - the code writes to it'
    } else {
        'orphan - nothing in the code reads it'
    }

    $sql.Add("--   $table - $rows row(s) - $note")
}

if ($archive.Count -eq 0) { $sql.Add('--   none: production has no table the code does not.') }

$sql.Add('--')
$sql.Add('-- Archive command (run against the OLD production database; keep the output off')
$sql.Add('-- the web root - it is a copy of member data):')
$sql.Add('--')

if ($archive.Count -gt 0) {
    $sql.Add('--   mysqldump -u <user> -p --no-create-info --default-character-set=utf8mb4 \')
    $sql.Add("--     <old_database> $($archive -join ' ') \")
    $sql.Add('--     > legacy-tables-<date>.sql')
    $sql.Add('--')
    $sql.Add("-- $($droppedBySync.Count) of these are dropped by app:sync-schema on its first run, so")
    $sql.Add('-- take this archive before that command - it is the only copy of their data.')
} else {
    $sql.Add('--   (nothing to archive on this database)')
}

$sql.Add('')

# ---------------------------------------------------------------------------
# Write it
# ---------------------------------------------------------------------------
$directory = Split-Path -Parent $OutFile
if (-not (Test-Path $directory)) { New-Item -ItemType Directory -Path $directory | Out-Null }

[System.IO.File]::WriteAllText($OutFile, (($sql -join $nl) + $nl), (New-Object System.Text.UTF8Encoding($false)))

Write-Step 'Wrote the reconciliation'
Write-Info $OutFile

# ---------------------------------------------------------------------------
# Optionally apply it
# ---------------------------------------------------------------------------
if ($ApplyTo) {
    Write-Step "Applying to $ApplyTo"

    Invoke-SqlFile -Database $ApplyTo -Path $OutFile | Out-Null

    Write-Info 'applied - run `php artisan app:sync-schema` and scripts/sql/verify-new-db.sql next'
}

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------
Write-Step 'Summary'
Write-Info "missing tables    : $($missingTables.Count)  $($missingTables -join ', ')"
Write-Info "missing columns   : $($missingColumns.Count)"
Write-Info "renamed columns   : $($renamedColumns.Count)  $($renamedColumns -join ', ')"
Write-Info "dump-only tables  : $($dumpOnlyTables.Count)  $($dumpOnlyTables -join ', ')"
Write-Info "dump-only columns : $($dumpOnlyColumns.Count)"

if ($missingColumns.Count -gt 0) {
    foreach ($key in $missingColumns) { Write-Info "                    $key" }
}

Write-Host ''
Write-Host 'Rehearsal (see docs/MIGRATION-smartchurch.md section 1):' -ForegroundColor Cyan
Write-Host '  .\scripts\build-prod-reconciliation.ps1 -ApplyTo sc_prod_check' -ForegroundColor Gray
Write-Host '  php artisan app:sync-schema           # against sc_prod_check (.env)' -ForegroundColor Gray
Write-Host '  mysql -u root sc_prod_check -e "SOURCE scripts/sql/verify-new-db.sql"' -ForegroundColor Gray
