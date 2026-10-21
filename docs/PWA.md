# Installable App (PWA)

The web app can be installed on a phone or desktop like a native app: an icon on
the home screen, a full-screen window without browser chrome, and (from the
assignment/birthday work) system notifications. Installation is offered by the
browser — nothing has to be published to an app store and no extra build step is
involved.

## What ships

| Piece | Responsibility |
|-------|----------------|
| `public/manifest.webmanifest` | Name, icons, `start_url` (`/home`), `display: standalone`, brand colours and three shortcuts (Dashboard, Week List, Notifications). |
| `public/icons/icon-192-v2.png`, `public/icons/icon-512-v2.png` | The launcher icons: the TIU crest alone (no wordmark) on the orange `#F97316` tile. |
| `public/icons/maskable-512-v2.png` | Android adaptive icon: the crest sits inside the safe zone so a round/squircle mask does not clip it. |
| `public/icons/icon-32.png`, `public/favicon.ico` | Tab icon (`<link rel="icon">`) and the 16+32 `.ico` a browser requests from the site root. |
| `public/service-worker.js` | Registered by the layout. A network-only `fetch` handler (unintercepted, nothing cached), plus `notificationclick` handling. Chrome/Edge only offer "Install app" — and only let a page raise a system notification — on an HTTPS origin that has a worker with a `fetch` handler. |
| `resources/views/layouts/app.blade.php` | Links the manifest, sets `<meta name="theme-color">` and the Apple touch icon, registers the worker and captures `beforeinstallprompt` into `window.tiuInstallPrompt` (raising `tiu:install-available`). |
| `resources/views/partials/pwa-install-button.blade.php` | The button. Include it with `@include('partials.pwa-install-button')`. |

## How the button behaves

- **Chrome / Edge / Android**: the browser fires `beforeinstallprompt`, the layout
  stashes it, and the button appears (on the Dashboard today). One click calls
  `prompt()`, so the install happens in-place — no "open the ⋮ menu" instructions.
- **iOS Safari / Firefox**: the event does not exist, so on iOS the partial swaps
  the button for the *Share → Add to Home Screen* instructions instead of showing
  a dead button. Everywhere else it stays hidden.
- **Already installed** (`display-mode: standalone`, or `navigator.standalone` on
  iOS): the button hides itself, and it hides for the rest of the session after a
  successful install (`appinstalled`).

## Requirements

1. **HTTPS.** Service workers and install prompts do not exist on plain HTTP.
   `http://localhost:8000` counts as secure for local testing, the live site
   (`https://smartchurch.tcnikorodu.org`, and `https://tiu.tcnikorodu.org` while
   its 301 redirect is still in place - see `docs/MIGRATION-smartchurch.md`) is
   fine, a LAN IP such as `http://192.168.x.x:8000` is **not**.
2. The document has to be served from the same origin as the manifest and the
   worker (`/manifest.webmanifest`, `/service-worker.js`), which is why both live
   in `public/`.

## Regenerating the icons

The icons come from `public/assets/img/tiupix/tiupix-logo.png` (812×305), which is
a lock-up: the crest on the left, the "TCN IKORODU SMART CHURCH" wordmark on the
right. A launcher icon is drawn at 48–192 px on a phone, so the wordmark would be
a smudge — the icons show the crest alone, cropped off the left of the logo
(`-CrestWidth`, default `0.41` = the 333 px before the "S" of SMART starts) and
centred on the `#F97316` brand tile.

Do not hand-draw the icons. From the repository root:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\make-pwa-icons.ps1
```

It writes `icon-192-v2.png`, `icon-512-v2.png`, `maskable-512-v2.png` (artwork kept
inside the middle 80 %, so Android's round/squircle mask cannot clip the crest),
`icon-32.png` for `<link rel="icon">` and a 16+32 `public/favicon.ico`.

**Bump the version suffix whenever the artwork changes.** Browsers — and already
installed apps — cache an icon by its URL, so rewriting a file in place keeps
showing the old picture. Generate a new name and update every place that names the
icons:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\make-pwa-icons.ps1 -Suffix '-v3'
```

- `public/manifest.webmanifest` — the `icons` array and the three shortcuts
- `resources/views/layouts/app.blade.php` — `apple-touch-icon` and the notification icon
- `tests/Feature/ProgressiveWebAppTest.php` — asserts the dashboard links the icon

Delete the previous suffix once the new one is live. A phone that already has the
app installed may need it removed and re-installed before the launcher shows the
new artwork, because Android snapshots the icon at install time.

## Verifying an install

1. Serve over localhost/HTTPS and hard-reload `/home`.
2. DevTools → **Application** → *Manifest*: no errors, icons listed, "Installability"
   green. *Service Workers* shows `service-worker.js` activated.
3. Click the install button (or DevTools → the install icon in the address bar) and
   check the app opens without an address bar and the icon appears on the home screen.

## Tests

`tests/Feature/ProgressiveWebAppTest.php` asserts the manifest is valid JSON, every
icon it references exists on disk, a maskable icon is present, the Dashboard
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
