# Retiring the legacy TIU host

`https://tiu.tcnikorodu.org` (the old plain-PHP "TIU Dashboard": `index.php`,
`process_script.php`, `dashboard.php`) is replaced by
`https://smartchurch.tcnikorodu.org`. These files make the old address send
everyone to the new one with a **301**, instead of leaving old links on a
"Page not found".

## Renaming `index.php` is not a redirect

The old pages redirect to `index.php` themselves, so deleting/renaming it turns
every one of them into a 404. Measured on `tiu.tcnikorodu.org` after
`index.php` → `indexdeprecated.php`:

| URL | With only `index.html` | After `.htaccess` |
|-----|------------------------|-------------------|
| `/` | `200` — the notice page | `301` → new site |
| `/index.php`, `/indexdeprecated.php` | `404` | `301` → new site |
| `/dashboard.php`, `/member-view.php` | `302` → `/index.php` → `404` | `301` → new site |
| `/process_script.php` | `200` — **the old endpoint still runs** | `301` → new site |

That last row is why the `.htaccess` matters more than the notice page: while
the old PHP is reachable, its AJAX/CRON endpoints keep talking to the old
database (and can still send mail/Telegram). The 301 answers before any of it
executes.

## What is in here

| File | Purpose |
|------|---------|
| `index.html` | Self-contained redirect page: instant `<meta refresh>` + `location.replace()`, a visible button for anyone whose browser blocks it, `noindex` so search engines drop the old address. |
| `.htaccess` | The authoritative fix: `DirectoryIndex index.html` (so a leftover `index.php` stops answering `/`), a **301** of every other URL to the new home page, the AutoSSL `/.well-known/` path exempted, and `ErrorDocument 404 /index.html` as the no-rewrite fallback. |
| `index.php` | Fallback for hosts where dotfiles are awkward: a 5-line 301 shim. Use it **instead of** `.htaccess` (the `.htaccess` covers deep links and the old endpoints; this only repairs the `302 → /index.php` chain). |

## One-click route (GitHub Actions, uses the deploy credentials)

If you would rather not touch File Manager, `.github/workflows/retire-legacy-host.yml`
does the upload with the same cPanel account the production deploy already uses
(it keeps one folder per domain inside `public_html/`, so it can reach the
legacy folder):

1. GitHub → **Actions** → **Retire legacy host (301 to Smart Church)** →
   **Run workflow**.
2. Run it once with `mode: check` (the default). It lists the legacy folder and
   **uploads nothing** — confirm it shows `dashboard.php`, `process_script.php`,
   `index.html`. If the path is wrong it prints what *is* inside `public_html/`
   so you can set the `LEGACY_REMOTE_DIR` repository variable (default
   `public_html/tiu.tcnikorodu.org/`).
3. Run it again with `mode: apply`. It renames the existing `.htaccess` to
   `.htaccess.bak`, uploads this folder's `.htaccess` + `index.html`, then
   verifies `/`, `/index.php`, `/indexdeprecated.php`, `/dashboard.php`,
   `/member-view.php` and `/process_script.php` all answer `301` to the new
   host, that `/.well-known/` is **not** redirected, and that the new site still
   answers `200`. Any failure is reported as a red ✗ with the exact URL and code.

Undo is the same either way: `mv .htaccess.bak .htaccess` in the legacy folder.
The workflow never runs on push — only from the **Run workflow** button.

## Upload (2 minutes, by hand)

The document root is shown in cPanel → **Domains** → the `tiu.tcnikorodu.org`
row → *Manage*. It is usually a sibling of `public_html/smartchurch.tcnikorodu.org/`.

1. cPanel → **File Manager** → open that document root.
2. cPanel → **Settings** (top right) → tick **Show Hidden Files**, otherwise
   `.htaccess` is invisible and cannot be created or backed up.
3. **Back up what is there**: select `.htaccess` (if present) → *Rename* →
   `.htaccess.bak`. Do the same for `index.php` → `index.php.bak`.
4. Upload `index.html` and `.htaccess` from this folder.
5. Make sure both are readable: `644`, and the folder `755`.

> **Cannot create a dotfile?** (Some hosts strip `.htaccess` on upload.) Use
> **File Manager → + File**, name it `.htaccess`, paste the contents of this
> folder's `.htaccess`, save. If that still fails, upload `index.php` (the 301
> shim) and `index.html` instead — the old login page must then be
> `index.php.bak`, or it keeps owning `/`.

> **Why `DirectoryIndex` is set:** the old config lists `index.php` before
> `index.html`, so a surviving `index.php` means `/` keeps showing the old
> login form and the notice is never reached.

## Verify

```bash
# 301 + the new location (with .htaccess):
curl -sI https://tiu.tcnikorodu.org/ | head -n 5
# HTTP/1.1 301 Moved Permanently
# Location: https://smartchurch.tcnikorodu.org/

# Deep links from old bookmarks, shortcuts and installed PWAs must move too:
curl -sI https://tiu.tcnikorodu.org/dashboard.php | head -n 3   # expect 301
curl -sI https://tiu.tcnikorodu.org/index.php     | head -n 3   # expect 301, not 404
curl -sI https://tiu.tcnikorodu.org/process_script.php | head -n 3  # expect 301, not 200

# AutoSSL must stay reachable (not redirected):
curl -sI https://tiu.tcnikorodu.org/.well-known/acme-challenge/ping | head -n 1

# And the new site must still be untouched:
curl -s -o /dev/null -w "%{http_code}\n" https://smartchurch.tcnikorodu.org/
```

Then open `https://tiu.tcnikorodu.org` in a private window and confirm you land
on the new login page.

## Undo

Rename `.htaccess.bak` back to `.htaccess` (and `index.php.bak` back to
`index.php`), delete `index.html` and, if you used it, `index.php` (restore the
backup). Nothing else was touched — the old application's files, uploads and
database are all still in place, so keep them for reference until you are sure
nothing else reads that database.

## Notes

* The redirect is intentionally **path-agnostic** (everything goes to the new
  home page). The two applications do not share route names, so mapping
  `/dashboard.php` onto a new path would produce 404s. This also means the old
  `reset-password.php?reset-token=...` links land on the new login page — use
  the new app's own "Forgot password" flow (see
  `docs/MIGRATION-smartchurch.md`, section 3).
* Do this **after** the new site is verified on its own address — this file
  hides the old application from every user the moment it lands.
* The `/.well-known/` exemption is not optional: without it AutoSSL cannot renew
  the certificate for the old host.
* The redirect is a **301 on purpose**: browsers and installed PWAs treat it as
  permanent and update their start URL. Test with a private window first.

