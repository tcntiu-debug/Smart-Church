<#
.SYNOPSIS
    Rebuild the installable-app icons (and favicon) from the church logo.

.DESCRIPTION
    The logo in public/assets/img/tiupix/tiupix-logo.png is a wide lock-up:
    the crest on the left, the "TCN IKORODU SMART CHURCH" wordmark on the
    right. A launcher icon is square and is drawn at 48-192 px on a phone, so
    the wordmark would be a smudge - the icons use the crest on its own.

    The crest is not a separate file, so the script crops the crest band off the
    left of the logo (see -CrestWidth) and draws that band, centred, on the
    brand orange tile. Point -Source at a new logo and re-run.

    Icons are written with a version suffix (-v2, -v3, ...) on purpose:
    browsers and already-installed apps cache an icon by its URL, so a file
    rewritten in place keeps showing the old artwork until the cache expires.
    A new file name is the only reliable way to make an installed app pick up
    new artwork. Bump -Suffix when the logo changes and update the two places
    that name the icons:

        public/manifest.webmanifest
        resources/views/layouts/app.blade.php

    This script only writes into public/icons and public/favicon.ico.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts\make-pwa-icons.ps1

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts\make-pwa-icons.ps1 -Suffix '-v3'
#>
param(
    [string]$Source = 'public\assets\img\tiupix\tiupix-logo.png',
    [string]$OutDir = 'public\icons',
    [string]$Suffix = '-v2',
    # Width of the crest band as a fraction of the logo width. 0.41 is the
    # measured crest of the current lock-up (333 of 812 px): the crest ends at
    # x=330 and the "S" of SMART starts at x=337, so the band stops between them.
    [double]$CrestWidth = 0.41
)

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing

$root = Split-Path -Parent $PSScriptRoot
$sourcePath = if ([System.IO.Path]::IsPathRooted($Source)) { $Source } else { Join-Path $root $Source }
$outDir = if ([System.IO.Path]::IsPathRooted($OutDir)) { $OutDir } else { Join-Path $root $OutDir }

if (-not (Test-Path $sourcePath)) { throw "Logo not found: $sourcePath" }
if (-not (Test-Path $outDir)) { New-Item -ItemType Directory -Path $outDir | Out-Null }

$logo = [System.Drawing.Image]::FromFile($sourcePath)

# --- crop the crest -------------------------------------------------------
# The lock-up is crest + wordmark, and the crest is the left ~42% of it
# (341 px of 812). This band is deliberately explicit instead of detected:
# the gap before the "S" of SMART is about one column wide, so any "stop at
# the first empty column" heuristic either slices the S into the tile or
# swallows the wordmark. If a future logo puts the crest elsewhere, pass
# -CrestWidth (fraction of the logo width) - do not edit the numbers below.
$cropX = 0
$cropY = 0
$cropW = [int]($logo.Width * $CrestWidth)
$cropH = $logo.Height
$crop = New-Object System.Drawing.Rectangle($cropX, $cropY, $cropW, $cropH)

Write-Host ("crest band: {0}x{1} at ({2},{3}) of {4}x{5}" -f $cropW, $cropH, $cropX, $cropY, $logo.Width, $logo.Height)


# --- render -----------------------------------------------------------------
$brand = [System.Drawing.Color]::FromArgb(249, 115, 22)   # #F97316, see the manifest

function New-Tile([int]$size, [string]$file, [double]$scale) {
    $canvas = New-Object System.Drawing.Bitmap($size, $size)
    $g = [System.Drawing.Graphics]::FromImage($canvas)
    $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $g.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
    $g.Clear($brand)

    # Fit the crest inside $scale of the tile, keeping its aspect ratio.
    $ratio = [Math]::Min(($size * $scale) / $crop.Width, ($size * $scale) / $crop.Height)
    $w = [int]($crop.Width * $ratio)
    $h = [int]($crop.Height * $ratio)
    $dest = New-Object System.Drawing.Rectangle([int](($size - $w) / 2), [int](($size - $h) / 2), $w, $h)

    $g.DrawImage($logo, $dest, $crop, [System.Drawing.GraphicsUnit]::Pixel)
    $g.Dispose()
    $canvas.Save($file, [System.Drawing.Imaging.ImageFormat]::Png)
    $canvas.Dispose()
    Write-Host ("wrote {0} ({1}x{1}, artwork {2}x{3})" -f $file, $size, $w, $h)
}

New-Tile 192 (Join-Path $outDir "icon-192$Suffix.png") 0.84
New-Tile 512 (Join-Path $outDir "icon-512$Suffix.png") 0.84
# Maskable: Android may crop to a circle, so the artwork stays inside 62%.
New-Tile 512 (Join-Path $outDir "maskable-512$Suffix.png") 0.62
# Tab icon (the Blade layouts ask for 32x32) and the .ico fallback below.
New-Tile 32  (Join-Path $outDir 'icon-32.png') 0.92

$png = @{}
foreach ($s in 16, 32) {
    $tmp = Join-Path $env:TEMP "tiu-favicon-$s.png"
    New-Tile $s $tmp 0.92
    $png[$s] = [System.IO.File]::ReadAllBytes($tmp)
    Remove-Item $tmp -Force
}

# A .ico that carries PNG images (Vista and newer, every browser we serve).
# ICONDIR (6 bytes) + one 16-byte entry per size + the PNG payloads.
$sizes = 16, 32
$favicon = Join-Path $root 'public\favicon.ico'
$fs = [System.IO.File]::Open($favicon, [System.IO.FileMode]::Create)
$bw = New-Object System.IO.BinaryWriter($fs)
try {
    $bw.Write([UInt16]0)                 # reserved
    $bw.Write([UInt16]1)                 # type: icon
    $bw.Write([UInt16]$sizes.Count)

    $offset = 6 + 16 * $sizes.Count
    foreach ($s in $sizes) {
        $bw.Write([byte]$s)              # width
        $bw.Write([byte]$s)              # height
        $bw.Write([byte]0)               # palette size
        $bw.Write([byte]0)               # reserved
        $bw.Write([UInt16]1)             # colour planes
        $bw.Write([UInt16]32)            # bits per pixel
        $bw.Write([UInt32]$png[$s].Length)
        $bw.Write([UInt32]$offset)
        $offset += $png[$s].Length
    }
    foreach ($s in $sizes) { $bw.Write($png[$s]) }
} finally {
    $bw.Dispose()
    $fs.Dispose()
}
Write-Host "wrote $favicon (16, 32)"

$logo.Dispose()
Write-Host "Done. If the suffix changed, update the manifest and layouts: $Suffix"
