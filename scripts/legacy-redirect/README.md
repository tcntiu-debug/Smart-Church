# Retiring the legacy TIU host

`https://tiu.tcnikorodu.org` (the old plain-PHP "TIU Dashboard": `index.php`,
`process_script.php`, `dashboard.php`) is replaced by
`https://smartchurch.tcnikorodu.org`. These two files make the old address send
everyone to the new one.

## What is in here

| File | Purpose |
|------|---------|
| `index.html` | Self-contained redirect page: instant `<meta refresh>` + `location.replace()`, a visible button for anyone whose browser blocks it, `noindex` so search engines drop the old address. |
| `.htaccess` | The authoritative fix: `DirectoryIndex index.html` (so the old `index.php` stops answering `/`) and a **301** of every other URL to the new home page, with the AutoSSL `/.well-known/` path exempted. |

## Upload (2 minutes)

The document root is shown in cPanel → **Domains** → the `tiu.tcnikorodu.org`
row → *Manage*. It is usually a sibling of `public_html/smartchurch.tcnikorodu.org/`.

1. cPanel → **File Manager** → open that document root.
2. **Back up what is there**: select `.htaccess` (if present) → *Rename* →
   `.htaccess.bak`. Do the same for `index.php` → `index.php.bak` if you are
   **not** uploading the `.htaccess` (see the note below).
3. Upload `index.html` and `.htaccess` from this folder.
4. Make sure both are readable: `644`, and the folder `755`.

> **If you only want to upload `index.html`** (no `.htaccess`), rename
> `index.php` to `index.php.bak` first. Apache/LiteSpeed check
> `DirectoryIndex` in order and the old config lists `index.php` before
> `index.html`, so leaving `index.php` in place means `/` keeps showing the old
> login form and your new file is never reached.

## Verify

```bash
# 301 + the new location (with .htaccess):
curl -sI https://tiu.tcnikorodu.org/ | head -n 5
# HTTP/1.1 301 Moved Permanently
# Location: https://smartchurch.tcnikorodu.org/

# A deep link from an old bookmark must also move:
curl -sI https://tiu.tcnikorodu.org/dashboard.php | head -n 3

# And the new site must still be untouched:
curl -s -o /dev/null -w "%{http_code}\n" https://smartchurch.tcnikorodu.org/
```

Then open `https://tiu.tcnikorodu.org` in a private window and confirm you land
on the new login page.

## Undo

Rename `.htaccess.bak` back to `.htaccess` (and `index.php.bak` back to
`index.php`), delete `index.html`. Nothing else was touched — the old
application's files, uploads and database are all still in place, so keep them
for reference until you are sure nothing else reads that database.

## Notes

* The redirect is intentionally **path-agnostic** (everything goes to the new
  home page). The two applications do not share route names, so mapping
  `/dashboard.php` onto a new path would produce 404s.
* Do this **after** the new site is verified on its own address — this file
  hides the old application from every user the moment it lands.
* The `/.well-known/` exemption is not optional: without it AutoSSL cannot renew
  the certificate for the old host.
