# cPanel Auto-Deploy Guide

Every push to `main` (or `master`) builds the application on GitHub's runners and
publishes it to your cPanel account. No manual uploading, ever.

```
 git push
    │
    ▼
 GitHub Actions  (.github/workflows/deploy-cpanel.yml)
    │  1. checkout the commit
    │  2. composer install --no-dev --optimize-autoloader   ← vendor/ is git-ignored,
    │                                                        so it is built here
    │  3. php artisan package:discover
    │  4. smoke test the build
    │  5. SFTP/FTPS upload of changed files only
    ▼
 cPanel  →  public_html/   (app, config, vendor, storage, public, .htaccess)
```

What is deliberately **not** uploaded: `.env`, `vendor/` sources are replaced by the
built copy, `tests/`, `docs/`, `node_modules/`, git metadata, debug scripts and every
user upload (`public/uploads`, `public/display_photo`, `public/gallery_uploads`).
`dangerous-clean-slate` is `false`, so files that exist on the server but not in the
repo are never deleted — that protects live member photos and PDFs.

---

## 1. Create the GitHub repository

Already done: <https://github.com/tcntiu-debug/Smart-Church> (keep it **private** —
it describes a real member database).

## 2. Push the code (first time)

```powershell
cd c:\xampp\htdocs\Smart-Church
git remote add origin https://github.com/tcntiu-debug/Smart-Church.git
git add -A
git commit -m "Initial commit"
git push -u origin master
```

> Your Git default branch is `master` (not `main`), so the examples use `master`.
> The workflow listens for **both** `main` and `master`, so either name works — rename
> it in GitHub → Settings → Branches if you prefer `main`.

The first push opens a browser window (Windows Credential Manager). Sign in to GitHub
once and every future push is silent. Nothing is stored in the project.

## 3. Add the deployment secrets

GitHub → your repository → **Settings** → **Secrets and variables** → **Actions**.

### Secrets tab

| Name | Value |
| --- | --- |
| `CPANEL_HOST` | FTP hostname. Use the server hostname from cPanel → **FTP Accounts** (e.g. `server123.webhost.com`). If unsure, try your domain, e.g. `ftp.yourdomain.com`. |
| `CPANEL_USER` | Your cPanel username (e.g. `tcnikoro`), or a dedicated FTP account created in cPanel → **FTP Accounts**. |
| `CPANEL_PASSWORD` | The password for that account. |

### Variables tab (optional — sensible defaults are built in)

| Name | Default | Notes |
| --- | --- | --- |
| `CPANEL_PROTOCOL` | `ftps` | `ftps` (port 21, encrypted), `sftp` (port 22, needs SSH enabled), or `ftp` (port 21, **unencrypted — avoid**). |
| `CPANEL_PORT` | `21` | Use `22` when `CPANEL_PROTOCOL` is `sftp`. |
| `CPANEL_REMOTE_DIR` | `/` | The directory to publish into. **Must end with a `/`**. e.g. `public_html/` or `public_html/smartchurch/`. |

> A dedicated FTP account scoped to the target directory is safer than the main cPanel
> account. If you create one in cPanel → FTP Accounts, its username looks like
> `smartchurch@yourdomain.com` and its remote directory is the subfolder you chose —
> in that case set `CPANEL_REMOTE_DIR` to `/` because the FTP account is already jailed
> to the right folder.

## 4. Create the production `.env` on cPanel

This is the only file the pipeline will never touch, so it survives every deploy.

1. cPanel → **MySQL Databases**: create the database and a user, and add the user to the
   database with **ALL PRIVILEGES**. cPanel prefixes names with your account, e.g.
   `tcnikoro_smart_church`.
2. cPanel → **File Manager** → open the deploy directory (`public_html`).
3. Using the template at `.env.cpanel.example` in this repo, create a new file named
   exactly `.env` and fill in `APP_KEY`, `APP_URL`, `DB_*`, `MAIL_*` and the Telegram
   values.
4. Generate the key. cPanel → **Advanced** → **Terminal** (if your host enables it):

   ```bash
   cd ~/public_html
   php artisan key:generate
   ```

   If Terminal is unavailable, run it once from your local machine against the server
   copy, or paste a known-good `base64:...` value from your local `.env`.

> `APP_DEBUG=false` and `APP_ENV=production` are essential on the live site.

## 5. Trigger the first deploy

Commit and push. The pipeline starts automatically:

```powershell
git add -A
git commit -m "Add cPanel deployment pipeline"
git push
```

Watch it at GitHub → **Actions** → *Deploy to cPanel*. The first run takes several
minutes because `vendor/` is uploaded for the first time; later runs only transfer
changed files.

You can also start a run on demand: **Actions** → *Deploy to cPanel* → **Run workflow**.

## 6. Run the post-deploy command on the server

The pipeline cannot run PHP on your server, so run these once from
cPanel → **Advanced** → **Terminal**:

```bash
cd ~/public_html

php artisan app:post-deploy             # directories, caches, storage link
php artisan app:post-deploy --migrate   # ...plus pending database migrations
```

`app:post-deploy` is idempotent and safe to re-run after any deploy. It:

1. creates any missing runtime directory (`storage/framework/*`, `storage/logs`,
   `public/uploads`, `public/display_photo`, `public/gallery_uploads`) — FTP does not
   create empty directories;
2. clears and rebuilds the config cache;
3. clears and pre-compiles the Blade views;
4. creates the `public/storage` symlink when missing;
5. attempts `route:cache` and **warns instead of failing** if your routes cannot be
   serialised (see *Known issue* below);
6. with `--migrate`, runs pending migrations using `--force`.

### If cPanel Terminal is not available

Add a cron job (cPanel → **Cron Jobs**) that runs once a day:

```
php /home/<cpanel-user>/public_html/artisan app:post-deploy
```

Adjust the PHP binary if your host pins a version, e.g.
`/usr/local/bin/ea-php80 /home/<user>/public_html/artisan app:post-deploy`.

### Known issue: `route:cache` fails

```
Unable to prepare route [bus-route/register] for serialization.
Another route has already been assigned name [transport.save-registration].
```

`routes/web.php` assigns the name `transport.save-registration` twice (lines 247 and
254). Both point at the same controller method, so the site works, but route caching is
impossible and the generated URL for that name is whichever route registered last.
Fixing it means renaming one of the two routes — do that separately and test the
transport forms, since Blade views reference that name.

The post-deploy command treats this as a warning, so deployments are unaffected.

## 7. Verify the deployment

- `https://your-domain.com/login` loads (the login page is the `/` route).
- `https://your-domain.com/.env` returns **403**, `https://your-domain.com/vendor/`
  returns **404** — proof the `.htaccess` is protecting application code.
- Upload a member photo; it appears under `public/display_photo` on the server.
- **Actions** → the run shows green with a summary table.

## Everyday workflow

```powershell
# make changes locally, test them
git add -A
git commit -m "Describe the change"
git push
```

Then, if the change included migrations or you want the caches rebuilt, run
`php artisan app:post-deploy --migrate` in cPanel Terminal.

Deploys are serialised per branch (`concurrency`), so two quick pushes queue instead of
colliding. Only changed files are transferred.

## Troubleshooting

| Symptom | Likely cause / fix |
| --- | --- |
| `Missing repository secret: CPANEL_HOST` | Secrets not added, or added as *variables* instead of *secrets*. |
| `530 Login authentication failed` | Wrong password, or the FTP account was created for a different directory. Change the password and re-add the secret. |
| `getaddrinfo ENOTFOUND` / timeout | Wrong `CPANEL_HOST`, or the host blocks FTP. Try `sftp` (port 22) via the variables. |
| TLS/certificate errors | Keep `security: loose` (already set) or switch to `sftp`. |
| Deploy succeeded but the site shows an old page | Run `php artisan app:post-deploy` — `config:cache`/`view:cache` are stale. |
| `Please provide a valid cache path` | Missing `storage/framework/*` directories. `php artisan app:post-deploy` creates them. |
| 500 error, blank page | Set `APP_DEBUG=true` temporarily in the server `.env`, load the page, read the message, then set it back to `false`. |
| Assets missing (CSS/JS) | The `public/assets` and `public/vendors` folders were not uploaded. Check the *Publish to cPanel* step log for skipped files. |
| Site redirects in a loop | A second copy of the root `.htaccess` or a conflicting rewrite rule exists in `public_html`. |

## Security notes

- `.env`, `storage/`, `vendor/` and `app/` are unreachable over HTTP thanks to the root
  `.htaccess`, which funnels every request into `public/` and denies `.env`/`composer.*`
  even if the rewrite is bypassed.
- `dangerous-clean-slate: false` — someone deleting a file from the repo will **not**
  delete it from the server. Remove stale files manually when needed.
- Rotate the cPanel password if it is ever pasted anywhere other than the GitHub secrets
  form. The Telegram bot token currently in your local `.env` should be rotated too, as
  it has been shared in plain text.
- Consider a dedicated FTP account limited to the deploy directory instead of the main
  cPanel account.

## Alternative: SSH / git-based deploys

If your host enables SSH and cPanel → **Git Version Control**, you can clone this repo on
the server and pull instead of uploading over FTP. cPanel runs the tasks listed in a
`.cpanel.yml` file placed at the repository root. The FTP pipeline above needs no such
configuration, so it is the default here — but ask if you would like the SSH variant set
up as well.

