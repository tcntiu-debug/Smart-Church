# Installable App (PWA)

The web app can be installed on a phone or desktop like a native app: an icon on
the home screen, a full-screen window without browser chrome, and (from the
assignment/birthday work) system notifications. Installation is offered by the
browser — nothing has to be published to an app store and no extra build step is
involved.

## What ships

| Piece | Responsibility |
|-------|----------------|
| `public/manifest.webmanifest` | Name, icons, `start_url` (`/home`), `display: standalone`, brand colours and three shortcuts (Home Dashboard, Week List, Notifications). |
| `public/icons/icon-192.png`, `public/icons/icon-512.png` | The launcher icons (orange `#F97316` tile with the TIU logo). |
| `public/icons/maskable-512.png` | Android adaptive icon: the logo sits inside the safe zone so a round/squircle mask does not clip it. |
| `public/service-worker.js` | Registered by the layout. A network-only `fetch` handler (unintercepted, nothing cached), plus `notificationclick` handling. Chrome/Edge only offer "Install app" — and only let a page raise a system notification — on an HTTPS origin that has a worker with a `fetch` handler. |
| `resources/views/layouts/app.blade.php` | Links the manifest, sets `<meta name="theme-color">` and the Apple touch icon, registers the worker and captures `beforeinstallprompt` into `window.tiuInstallPrompt` (raising `tiu:install-available`). |
| `resources/views/partials/pwa-install-button.blade.php` | The button. Include it with `@include('partials.pwa-install-button')`. |

## How the button behaves

- **Chrome / Edge / Android**: the browser fires `beforeinstallprompt`, the layout
  stashes it, and the button appears (on the Home Dashboard today). One click calls
  `prompt()`, so the install happens in-place — no "open the ⋮ menu" instructions.
- **iOS Safari / Firefox**: the event does not exist, so on iOS the partial swaps
  the button for the *Share → Add to Home Screen* instructions instead of showing
  a dead button. Everywhere else it stays hidden.
- **Already installed** (`display-mode: standalone`, or `navigator.standalone` on
  iOS): the button hides itself, and it hides for the rest of the session after a
  successful install (`appinstalled`).

## Requirements

1. **HTTPS.** Service workers and install prompts do not exist on plain HTTP.
   `http://localhost:8000` counts as secure for local testing, the production site
   (`https://tiu.tcnikorodu.org`) is fine, a LAN IP such as
   `http://192.168.x.x:8000` is **not**.
2. The document has to be served from the same origin as the manifest and the
   worker (`/manifest.webmanifest`, `/service-worker.js`), which is why both live
   in `public/`.

## Regenerating the icons

The icons are rendered from `public/assets/img/tiupix/tiupix-logo.png` (812×305).
If the logo changes, re-run this on Windows PowerShell:

```powershell
Add-Type -AssemblyName System.Drawing
$src = 'public\assets\img\tiupix\tiupix-logo.png'
$out = 'public\icons'
$logo = [System.Drawing.Image]::FromFile((Resolve-Path $src))
function New-Icon([int]$size, [string]$file, [double]$scale) {
    $bmp = New-Object System.Drawing.Bitmap($size, $size)
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $g.Clear([System.Drawing.Color]::FromArgb(249, 115, 22))
    $ratio = [Math]::Min(($size * $scale) / $logo.Width, ($size * $scale * 0.55) / $logo.Height)
    $w = [int]($logo.Width * $ratio); $h = [int]($logo.Height * $ratio)
    $g.DrawImage($logo, [int](($size - $w) / 2), [int](($size - $h) / 2), $w, $h)
    $g.Dispose(); $bmp.Save($file, [System.Drawing.Imaging.ImageFormat]::Png); $bmp.Dispose()
}
New-Icon 192 "$out\icon-192.png" 0.86
New-Icon 512 "$out\icon-512.png" 0.86
New-Icon 512 "$out\maskable-512.png" 0.62
```

Any square PNGs will do as well — keep one at 192×192 and one at 512×512, plus a
`maskable` twin whose artwork stays inside the middle 80% of the canvas.

## Verifying an install

1. Serve over localhost/HTTPS and hard-reload `/home`.
2. DevTools → **Application** → *Manifest*: no errors, icons listed, "Installability"
   green. *Service Workers* shows `service-worker.js` activated.
3. Click the install button (or DevTools → the install icon in the address bar) and
   check the app opens without an address bar and the icon appears on the home screen.

## Tests

`tests/Feature/ProgressiveWebAppTest.php` asserts the manifest is valid JSON, every
icon it references exists on disk, a maskable icon is present, the Home Dashboard
links the manifest, registers the worker and renders the install button, and the
worker still handles `install`/`activate`/`fetch`/`notificationclick`.

```bash
vendor/bin/phpunit --filter ProgressiveWebAppTest
```

## Notes for the server

`public/` is the document root on cPanel, so the manifest, the icons and the worker
are served straight from it. The worker must be served from the site root to claim
the whole scope — do not move it into `public/assets/`. Nothing in this feature
touches the database, so no migration or cron entry is involved.
