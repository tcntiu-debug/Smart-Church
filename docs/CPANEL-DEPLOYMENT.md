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

**Deletions are propagated as well.** The upload itself only adds and overwrites, so a
file removed from the repository would linger on the server forever. The *Prune files
deleted from the repository* step closes that gap: it diffs the commit of the last
successful deploy against the commit being pushed and deletes exactly the paths that
`git rm` took out of the tree. Paths that only ever exist on the server are protected by
a hard-coded list — `.env`, `storage/**`, `bootstrap/cache/**`, `public/uploads/**`,
`public/display_photo/**`, `public/gallery_uploads/**`, `public/storage`,
`.well-known/**`, `vendor/**`, `node_modules/**` — so live member photos, PDFs and the
production environment file can never be removed by a commit.

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

## 3. Connect GitHub to cPanel

**There is no "link your hosting" button.** GitHub Actions is a headless Linux machine in
Microsoft's cloud, and it reaches cPanel exactly the way FileZilla does: hostname +
username + password over FTP/SFTP. "Connecting" the two means storing those three values
as encrypted **GitHub Secrets**, which the workflow reads at deploy time. GitHub cannot
discover them from your domain — you must supply them.

### 3a. Find the three values in cPanel

| What you need | Where to find it |
| --- | --- |
| **Hostname** | cPanel right-hand sidebar → **Server Information** / *Shared IP Address*. Usually `serverNNN.webhost.com`. `ftp.yourdomain.com` also works on most hosts. |
| **Username** | cPanel right-hand sidebar → **Current User**, e.g. `tcnikoro`. The full list is in **FTP Accounts** → *Special FTP Accounts*. |
| **Password** | Your main cPanel password. **FTP Accounts** → *Configure FTP Client* also shows the hostname, port and encryption mode the host recommends — the fastest way to get all three. |

### 3b. Verify them before touching GitHub

Wrong credentials cost you a red build and an unhelpful FTP log. Test them from your PC
first — this logs in, lists the directory, writes a test file and deletes it again:

```powershell
cd c:\xampp\htdocs\Smart-Church
.\scripts\test-cpanel-connection.ps1 -Server server123.webhost.com -Username tcnikoro
```

It prompts for the password securely, then prints the exact secret values to paste. It
also tells you whether the app is already deployed in the target directory.

### 3c. Add the secrets

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
| `CPANEL_REMOTE_DIR` | `public_html/` | The directory to publish into. **Must end with a `/`**. See the warning below. |

> ⚠️ **`CPANEL_REMOTE_DIR` is the setting that breaks most first deploys.** The main cPanel
> account is jailed to your **home directory**, so the FTP path `/` means `/home/tcnikoro/`
> — the app would land *beside* `public_html/`, where Apache never serves it, and the live
> site would look completely unchanged even though the deploy went green. Keep the default
> `public_html/`.
> A dedicated FTP account jailed to `public_html/` is different: its `/` **is**
> `public_html/`, so in that case set the value to `/`. Run
> `.\scripts\test-cpanel-connection.ps1` to have it list the directory and show you exactly
> where you are landing.

> A dedicated FTP account scoped to the target directory is safer than the main cPanel
> account. If you create one in cPanel → FTP Accounts, its username looks like
> `smartchurch@yourdomain.com` and its remote directory is the subfolder you chose —
> in that case set `CPANEL_REMOTE_DIR` to `/` because the FTP account is already jailed
> to the right folder.

### 3d. Your setup: deploying to a subdomain

Create the subdomain **first** and point its document root at the app's `public` folder.
That one decision puts `.env`, `app/` and `vendor/` *above* the web root, so they are
physically unreachable over HTTP — no rewrite rules have to protect them.

1. cPanel → **Domains** → **Create A New Domain** (older themes: **Subdomains**).
2. Subdomain: e.g. `church` → creates `church.yourdomain.com`.
3. **Document Root**: set it to `/home/tcnikoro/smart-church/public`
   (cPanel creates the whole path, including `public/`).
4. cPanel → **SSL/TLS Status** → **Run AutoSSL** so HTTPS works on the subdomain.

Then set `CPANEL_REMOTE_DIR` to `smart-church/` — the app folder, **not** `public/`. The
repo root is what gets uploaded, and the `public/` inside it becomes the document root.

Resulting layout on the server:

```
/home/tcnikoro/smart-church/          <- CPANEL_REMOTE_DIR
├── app/ bootstrap/ config/ routes/   <- NOT web-accessible
├── .env                              <- NOT web-accessible
├── vendor/                           <- NOT web-accessible
└── public/                           <- document root of church.yourdomain.com
    ├── .htaccess        (Laravel's own, already in the repo)
    ├── index.php
    ├── assets/  vendors/
    ├── storage/         (symlink created by app:post-deploy)
    └── uploads/  display_photo/  gallery_uploads/
```

Set `APP_URL=https://church.yourdomain.com` in the server `.env` so uploads, links and
redirects all resolve to the subdomain.

> Alternative: leave the document root at cPanel's default
> (`/home/tcnikoro/church.yourdomain.com`) and set `CPANEL_REMOTE_DIR` to
> `church.yourdomain.com/`. That also works — the repo's root `.htaccess` funnels traffic
> into `public/` — but then `.env` is protected only by rewrite rules rather than by being
> outside the web root.



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

php artisan app:post-deploy             # directories, caches, storage link, legacy clean-up
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
7. always runs `app:retire-legacy`, which drops the tables of the retired Transport
   (bus route) and standalone FOF modules — idempotent, see *Retired modules* below.

### If cPanel Terminal is not available

Add a cron job (cPanel → **Cron Jobs**) that runs once a day:

```
php /home/<cpanel-user>/public_html/artisan app:post-deploy
```

Adjust the PHP binary if your host pins a version, e.g.
`/usr/local/bin/ea-php80 /home/<user>/public_html/artisan app:post-deploy`.

### Scheduled jobs: Welcome Center birthday reminders

The app ships a **daily** job that e-mails + pushes the upcoming birthdays of the
**Welcome Center** (`dept_id 28`) admins at the **3 days**, **2 days** and **1 day** to go
milestones. It runs through Laravel's scheduler, so add **one** cron entry
(cPanel → **Cron Jobs**, every minute):

```
* * * * * /usr/local/bin/ea-php80 /home/<cpanel-user>/public_html/artisan schedule:run >> /dev/null 2>&1
```

That single entry drives every entry in `app/Console/Kernel.php::schedule()` — currently
just `birthdays:remind`, which fires at `07:00` `Africa/Lagos`. Use the same PHP binary as
the post-deploy cron above. This is **separate** from the daily `app:post-deploy` cron:
keep both.

> **Do not leave this entry on *Once Per Day* (`0 0 * * *`).** The cron line is only the
> heartbeat that wakes the scheduler — Laravel compares its own expression (`0 7 * * *`
> in `Africa/Lagos`) on every tick and fires `birthdays:remind` only when they match. A
> daily tick at `00:00` server time (which is `01:00` in Lagos) never coincides with the
> `07:00` due time, so the reminder would silently never send.

> **Alternative — if your host refuses a per-minute cron:** point cron straight at the
> command instead of the scheduler. `07:00` `Africa/Lagos` is `06:00` UTC, so on a UTC
> server:
>
> ```
> 0 6 * * * /usr/local/bin/ea-php80 /home/<cpanel-user>/public_html/artisan birthdays:remind >> /dev/null 2>&1
> ```
>
> This works, but it is a dead end: it drives *only* this one command (nothing else you
> ever add to `Kernel::schedule()` will run), it breaks silently if the server clock is not
> UTC, and it must be re-timed whenever `BIRTHDAY_SEND_AT` changes. Use the per-minute
> `schedule:run` entry above unless you truly cannot.

**Verifying the cron is actually alive.** The tick is silent by design: its own output goes to
`/dev/null`, and the event is what writes `storage/logs/birthday-reminders.log` - a tick where
nothing is due writes nothing. An empty (or missing) log therefore means *"no run yet"*, not
*"broken"*:

```bash
cd ~/public_html

php artisan schedule:list      # expect 0 7 * * * with Next Due 06:00:00 +00:00 (07:00 Lagos)
php artisan schedule:run       # "No scheduled commands are ready to run." = the tick works
```

To prove the whole chain end to end without waiting for 07:00, nudge the send time to the next
minute or two in Lagos time:

```bash
cd ~/public_html

# .env: BIRTHDAY_SEND_AT="21:47"   (a couple of minutes ahead of Lagos "now")
php artisan config:clear && php artisan config:cache   # required - post-deploy caches the config
sleep 120
tail -n 40 storage/logs/birthday-reminders.log          # the run is appended here
```

Then restore `BIRTHDAY_SEND_AT="07:00"` and run `php artisan app:post-deploy` again. `--force`
re-sends a milestone that `birthday_reminder_logs` already recorded, so use it only while
testing.

> **One lock to know about:** `withoutOverlapping()` holds a cache mutex for up to 24 hours. If
> a run dies half way through, later ticks are skipped until it expires - `cache:clear` (which
> `app:post-deploy` already runs) releases it immediately.

Test it before leaving it to the scheduler:

```bash
cd ~/public_html

php artisan birthdays:remind --dry-run                  # show what the next run would do
php artisan birthdays:remind --days=3 --campus=1 --channel=mail --force
php artisan birthdays:remind --days=3,2,1,0             # include the birthday itself
```

Each digest goes out on three channels: `--channel=mail` (e-mail to every Welcome Center
admin with an address), `--channel=database` (the in-app **🔔 bell** + `/notifications`
page) and `--channel=telegram` (push to the bot chat already configured for airtime
alerts). Every attempt is written to `birthday_reminder_logs`, so the same milestone is
never sent twice — use `--force` to override that while testing.

#### Settings (optional)

Everything below has a working default and only needs adding to the server `.env` to
change it:

| Variable | Default | Purpose |
| --- | --- | --- |
| `BIRTHDAY_DAYS` | `3,2,1` | Milestones (days before the birthday) that trigger a reminder |
| `BIRTHDAY_SEND_AT` | `07:00` | Local time of the daily run |
| `BIRTHDAY_TIMEZONE` | `Africa/Lagos` | Timezone used to work out "today" |
| `BIRTHDAY_DEPARTMENT_IDS` | `28` | Departments whose **Admins** receive the e-mail (28 = Welcome Center) |
| `BIRTHDAY_CHANNELS` | `mail,database,telegram` | Channels used by the scheduled run |
| `BIRTHDAY_EXTRA_EMAILS` | *(empty)* | Extra recipients, comma separated |
| `BIRTHDAY_TELEGRAM_CHAT_IDS` | falls back to `TELEGRAM_CHAT_ID` | Telegram chats to push to |
| `BIRTHDAY_WISH_COUNTRY_CODE` | `234` | Country code prepended to phone numbers in the wish links |

Recipients are resolved from the database on every run, so a **newly appointed Welcome
Center admin is e-mailed automatically** — there is nothing to configure.

#### Database tables

The feature needs two tables and **creates them itself** the first time
`birthdays:remind` runs — it applies only its own two migration files. To create them by
hand (or if you prefer to see the output), run:

```bash
php artisan migrate --force \
  --path=database/migrations/2026_10_09_000001_create_birthday_reminder_logs_table.php \
  --path=database/migrations/2026_10_09_000002_create_app_notifications_table.php
```

> A plain `php artisan migrate` (and therefore `app:post-deploy --migrate`) can abort
> with `Base table or view already exists` on this database, because it was seeded
> before the `migrations` table was kept in step. Use the `--path` form above for this
> feature and let the command self-provision.

`birthday_reminder_logs` is the send history / de-duplication record (one row per campus
+ milestone + birthday date); `app_notifications` feeds the navbar bell and the
`/notifications` page.

### Route cache

`php artisan route:cache` runs cleanly. (The duplicate `transport.save-registration`
route name that previously blocked caching was removed on 2026-05-25 when the
transport feature was retired.)

## Retired modules (Transport / bus route, standalone FOF)

Both modules were removed from the codebase on 2026-05-25 (models, controllers, routes,
views, navigation entries). Removing them from the **server** is a two-part job, and both
parts are automatic — no phpMyAdmin and no manual SFTP deletes:

| Part | What removes it | When |
|------|-----------------|------|
| Files (`app/Http/Controllers/TransportController.php`, `resources/views/transport/*`, …) | the *Prune files deleted from the repository* step in `.github/workflows/deploy-cpanel.yml` | on the deploy that carries the deletion |
| Tables (`transport_routes`, `transport_stops`, `bus_attendance`, `fof_cohort_setting`, `fof_register_table`, `fof_mark_attendance_table`) and `tiu_member` transport foreign keys | `php artisan app:retire-legacy` | the daily `schedule:run` cron (03:20 Africa/Lagos) and every `app:post-deploy` run |

How the table clean-up works: the three `2026_05_25_00000{2,3,4}_drop_*` migrations ship
with the code, but the pipeline cannot run PHP on the server, so `app:retire-legacy`
applies exactly those three files with `migrate --path` — a plain `migrate` could abort on
an unrelated legacy migration (see *Troubleshooting*). Every drop is `dropIfExists`/guarded,
so the command is safe to re-run and reports `Nothing to migrate` afterwards.

Verify it, or run it immediately without waiting for the schedule:

```bash
cd ~/public_html

php artisan app:retire-legacy --dry-run   # what would be applied
php artisan app:retire-legacy             # apply + print the state of every legacy table
php artisan schedule:list                 # app:retire-legacy should be listed next to birthdays:remind
```

The command's own output ends in a table that reads `gone` for every retired table, which
is the proof the retirement reached the database. (The `app:retire-legacy` schedule needs
the per-minute `schedule:run` cron described above; without it the daily
`app:post-deploy` cron still applies it — and `php artisan app:retire-legacy` in cPanel
Terminal does it right now.)

---

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
| Deploy is green but the website is unchanged | `CPANEL_REMOTE_DIR` is wrong — the app was uploaded *beside* `public_html` instead of into it. Re-run `.\scripts\test-cpanel-connection.ps1` to see where you land. |
| Everything uploads on every single run | The state file `.ftp-deploy-sync-state.json` is not surviving between runs. Harmless — just slower. |
| `530 Login authentication failed` | Wrong password, or the FTP account was created for a different directory. Change the password and re-add the secret. |
| `getaddrinfo ENOTFOUND` / timeout | Wrong `CPANEL_HOST`, or the host blocks FTP. Try `sftp` (port 22) via the variables. |
| TLS/certificate errors | Keep `security: loose` (already set) or switch to `sftp`. |
| Deploy succeeded but the site shows an old page | Run `php artisan app:post-deploy` — `config:cache`/`view:cache` are stale. |
| `Base table or view already exists` while migrating | The legacy database was seeded before the `migrations` table was kept in step, so an old migration aborts the run. Migrate the file you actually need with `--path` (see *Scheduled jobs*) — `birthdays:remind` also self-provisions its own tables. |
| No birthday reminder e-mail arrives | The status was already recorded in `birthday_reminder_logs` (columns `campus_id`, `days_before`, `reminder_date`). Re-test with `php artisan birthdays:remind --days=3 --force --channel=mail`, and check the recipients: only `member_role` `Admin`/`Super User` members whose `department_name` JSON contains the configured department id receive it. |
| Bell shows no numbers / `/notifications` is empty | Nothing has been written to `app_notifications` yet, or the logged-in member is not a Welcome Center admin. Run `php artisan birthdays:remind --days=3 --channel=database --force`. |
| Birthday reminders still not running | The `schedule:run` cron entry is missing, uses the wrong PHP binary, or is set to *Once Per Day* (`0 0 * * *`) instead of *Once Per Minute* (`* * * * *`) — a daily tick never coincides with the `07:00` due time, so nothing ever fires. See *Scheduled jobs*. Verify with `php artisan birthdays:remind --dry-run` over SSH/Terminal. |
| `Please provide a valid cache path` | Missing `storage/framework/*` directories. `php artisan app:post-deploy` creates them. |
| 500 error, blank page | Set `APP_DEBUG=true` temporarily in the server `.env`, load the page, read the message, then set it back to `false`. |
| Assets missing (CSS/JS) | The `public/assets` and `public/vendors` folders were not uploaded. Check the *Publish to cPanel* step log for skipped files. |
| Site redirects in a loop | A second copy of the root `.htaccess` or a conflicting rewrite rule exists in `public_html`. |

## Security notes

- `.env`, `storage/`, `vendor/` and `app/` are unreachable over HTTP thanks to the root
  `.htaccess`, which funnels every request into `public/` and denies `.env`/`composer.*`
  even if the rewrite is bypassed.
- Deletions are propagated: the *Prune files deleted from the repository* step removes
  exactly the paths a commit deleted, and its protected-prefix list keeps everything
  that only exists on the server (`.env`, `storage/**`, `bootstrap/cache/**`,
  `public/uploads/**`, `public/display_photo/**`, `public/gallery_uploads/**`,
  `public/storage`, `.well-known/**`) out of reach. A dry run of the same diff is
  `git diff --no-renames --diff-filter=D --name-only <last-deployed-sha> HEAD`.
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

