# Migrating production to smartchurch.tcnikorodu.org

Moving the live app off `https://tiu.tcnikorodu.org` onto the new subdomain
`https://smartchurch.tcnikorodu.org`, on a **new database**, with the old site,
the old folder and the old database kept intact as the rollback path.

Assumptions (change these if they are wrong):

| | Value |
|---|---|
| cPanel account / user | `tcnikoro` |
| Account layout | one folder per domain, inside `public_html/` (File Manager shows `tiu.tcnikorodu.org/`, `content.tcnikorodu.org/`, `smartchurch.tcnikorodu.org/`, ...), so every deploy target is `public_html/<domain>/` |
| New app folder on the server | `/home/tcnikoro/public_html/smartchurch.tcnikorodu.org` (uploaded by the pipeline) |
| New subdomain document root | **`public_html/smartchurch.tcnikorodu.org` - the domain folder itself** (confirmed on cPanel -> Domains). The repo's root `.htaccess` forwards every request into `public/` |
| New database (cPanel-prefixed) | `tcnikoro_smartchurch` |
| New database user | `tcnikoro_smartchurch` (ALL PRIVILEGES on that database only) |
| Old database (archived, never dropped) | `tcnikoro_smart_church` |
| Old app folder (kept until retirement) | `/home/tcnikoro/public_html/tiu.tcnikorodu.org` (confirmed on cPanel -> Domains) |

Both subdomains must be on **the same cPanel account / server**: that is what
makes `DB_HOST=localhost`, the File Manager copies, one cron host and a `301`
between the two hosts work. If `smartchurch.` lives on another server, stop and
re-plan the copy steps (SFTP plus a whitelisted remote MySQL user instead of File
Manager plus `localhost`).

## 0. Why a new database, and why the dump alone is not enough

`docs/PROD-DB-DIFF.md` already established that the production schema is **not**
what the deployed code expects. Measuring the dump against the local development
database (which is what the migrations produce) puts exact numbers on it:

| Drift | Count | What it is |
|---|---|---|
| Tables the code reads, production lacks | **12** | `users`, `campus`, `migrations`, `password_resets`, `personal_access_tokens`, `failed_jobs`, `app_notifications`, `birthday_reminder_logs`, `member_tracking_followup_archive`, `children_church`, `children_church_attendance`, `child_ceremonies` |
| Columns the code reads, production lacks | **22** | `airtime_history.status`, `caller_assignments.caller_member_id`, `church_attendance.created_at`, `sub_group.department_id`, the `campus_id` columns, and so on |
| Tables production has, the code's schema does not | **14** | 6 the app retires itself (Transport, FOF), `messages` (which the code writes), 7 orphans nothing reads |
| `migrations` table | **absent** | which is why a plain `php artisan migrate` aborts part way through |

One of the 22 is not a new column at all: production keeps the Action Board
answers in `task.extra`, and the code reads `task.department_id`.

Importing the dump into a new database therefore reproduces exactly that
breakage. The new database is built as **dump -> reconcile (additive only) ->
`app:sync-schema`** (which creates what is still missing, records the migrations,
and runs the app's own module retirement). `sc_prod_check` - a scratch import of
the same dump - is the rehearsal, and it is where every number above was measured.

## 1. Before the window (dry runs, no impact on the live site)

1. Create the new database and user in cPanel -> **MySQL Databases**, and add the
   user with **ALL PRIVILEGES**. Keep the old database's user separate, so a
   mistyped `.env` fails loudly instead of writing to live.
2. The subdomain already exists and its **Document Root is the domain folder
   itself**: `public_html/smartchurch.tcnikorodu.org` (cPanel -> **Domains**).
   This account gives every domain its own folder inside `public_html/`, so the
   app deploys into `/home/tcnikoro/public_html/smartchurch.tcnikorodu.org/` -
   and **that folder is the deploy target, never `public/`** (the pipeline uploads
   the repository root into it).

   Because the document root is the folder rather than `<folder>/public`, the
   repository's root `.htaccess` is what makes the site work: it rewrites every
   request into `public/`, and its `FilesMatch` block denies direct HTTP access to
   `.env`, `composer.json`, `artisan` and `*.log|sql|bak|yml|sh`. Nothing to
   configure - the file is in the repo and `public_html/dev.lit-grp.com/` has run
   this way for a while - but it does mean **the root `.htaccess` has to survive
   the deploy**, which it does: the pipeline mirrors it along with the rest of the
   repository root (it is not in any prune/exclude list).

   Check that HTTPS works on the subdomain (cPanel -> **SSL/TLS Status** ->
   **Run AutoSSL** if the certificate does not cover it yet).
3. Build the **rehearsal** database locally (`sc_prod_check`, see
   `docs/PROD-DB-DIFF.md` section 1) and rehearse the whole build against it:

   ```powershell
   # imports the dump into sc_prod_check and writes scripts/sql/prod-reconciliation.sql
   .\scripts\build-prod-reconciliation.ps1 -RebuildStaging

   # applies it to the rehearsal database
   .\scripts\build-prod-reconciliation.ps1 -ApplyTo sc_prod_check

   # the second half of the build; Laravel's immutable .env loader lets a shell
   # variable win over .env, so this needs no edit to the file:
   $env:DB_DATABASE='sc_prod_check'; php artisan app:sync-schema; Remove-Item Env:DB_DATABASE
   ```

   Then run `scripts/sql/verify-new-db.sql` against `sc_prod_check` and check the
   expectations in section 10: parts 2 and 3 all `present`, part 4 all `YES`,
   part 5's six retired tables `gone`, part 6 all `recorded`.

   The order matters. The reconciliation can only add what the code's schema has,
   so it cannot fix a column that both databases have and disagree about: that is
   `app:sync-schema`'s job, and `tiu_member.campus_id` (NOT NULL in production,
   fixed by `2026_10_14_000002_make_campus_id_nullable`) is the one that proves it.
   A rehearsal that stops after the reconciliation still shows `NO` for it.
4. Add the second deploy target (section 7), then **run it once** (GitHub ->
   **Actions** -> *Deploy to cPanel (production)* -> *Run workflow*): it creates
   `public_html/smartchurch.tcnikorodu.org/` and fills it with a build, without
   repointing the live site. Do this before the window, so the folder exists and
   the target has been proven once.
5. Apply the two code fixes in section 8 and push them **before** the window.

## 2. The window (30-60 min, live site is read-only)

Throughout this section `$OLD_DIR` is the folder the live site is served from and
`$NEW_DIR` the new one (section 1 step 2). Set them once in a cPanel **Terminal**
so no path has to be retyped:

```bash
export OLD_DIR=/home/tcnikoro/public_html/tiu.tcnikorodu.org   # confirmed: cPanel -> Domains
export NEW_DIR=/home/tcnikoro/public_html/smartchurch.tcnikorodu.org
```

> **Check for a Terminal before the window opens.** Everything below assumes cPanel's
> **Advanced -> Terminal** exists, but `deploy-cpanel-prod.yml` quotes this host as
> answering *"Shell access is not enabled on your account"* (that is why the pipeline
> never runs a remote command). Open **Advanced** and look for **Terminal** now:
>
> | If Terminal is... | Then |
> |---|---|
> | **there** | follow this section exactly as written |
> | **missing** | `mysqldump` / `mysql` become **phpMyAdmin** *Export* / *Import*, and every `php artisan ...` becomes a temporary **Cron Jobs** entry - see `docs/CPANEL-DEPLOYMENT.md` -> *If cPanel Terminal is not available* and *Scheduled jobs*. The per-minute `schedule:run` heartbeat already fires `app:sync-schema` at `03:20` `Africa/Lagos`, which is the no-shell equivalent of step 8's first command. |

> **The new folder answers with a directory listing until the first deploy lands.**
> `https://smartchurch.tcnikorodu.org/` currently prints *Index of /* - a `cgi-bin/`
> folder and the host's `php.ini`, names only. Nothing secret is reachable: the host
> itself answers `403` to `/.env` and `/php.ini` *even before* the repository's
> `.htaccess` exists there to deny them, and the `.env` you paste in step 7 never
> appears in that listing. A listing is just what a document root with no index file
> looks like. It disappears with step 8's deploy, because the repo's root `.htaccess`
> sets `Options -Indexes`. So do **not** point traffic at the new host (section 3's
> **301**) before that deploy has landed, or visitors get the listing instead of the
> login page.


1. **Freeze.** Put the old install into maintenance mode and stop its cron, so
   nothing writes to the old database while it is being copied and so the console
   commands cannot run twice:

   ```bash
   cd "$OLD_DIR"
   php artisan down
   crontab -l > ~/cron-backup.txt      # keep a copy before editing
   crontab -e                          # delete the line running `php artisan schedule:run`
   ```

   The scheduled work that must not double-run:
   `birthdays:remind` (daily 07:00) and `firsttimers:auto-unassign` (daily 01:00).
2. **Dump the old database** (schema + data, single transaction, routines too):

   ```bash
   mkdir -p ~/migration && cd ~/migration
   mysqldump -u tcnikoro -p --single-transaction --routines --triggers \
     --default-character-set=utf8mb4 tcnikoro_smart_church > smartchurch-$(date +%F).sql
   ```

   Save the `.sql` in `~/migration/` **and** download a copy to your PC. This file
   is the archive as well as the import: it is never deleted.
3. **Import it into the new database** (cPanel -> phpMyAdmin or the shell):

   ```bash
   mysql -u tcnikoro_smartchurch -p tcnikoro_smartchurch < smartchurch-$(date +%F).sql
   ```

   `--routines --triggers` matters: the birthday job reads its data through
   queries, but any view/procedure in the old database is part of the archive.

   > **The pipeline never touches MySQL.** `deploy-cpanel-prod.yml` is SFTP-only: it
   > uploads files and nothing else - no SQL import, no `php artisan migrate`, no
   > `artisan` at all (the runner has no shell on the host). Every database step in this
   > window is manual, and this import is the starting point: whatever
   > `~/migration/smartchurch-<date>.sql` holds is what the new site opens with.
4. **Apply the reconciliation** with the file produced in step 3 of section 1:
   phpMyAdmin on the new database -> SQL tab -> paste `prod-reconciliation.sql`
   -> Go. It is additive and idempotent: every statement is wrapped in an
   `information_schema` guard, so re-running it changes nothing.
5. **Archive what step 8 will drop, before it drops it.** `php artisan
   app:sync-schema` retires the Transport / FOF modules by dropping their six
   tables, so this export is what keeps that step reversible. It also takes
   `messages` (which the code writes to) and the seven orphans nothing in the
   code reads, because each of them is the only copy of its own data:

   ```bash
   mysqldump -u tcnikoro_smartchurch -p --no-create-info \
     tcnikoro_smartchurch \
     transport_routes transport_stops bus_attendance \
     fof_cohort_setting fof_register_table fof_mark_attendance_table \
     messages call_center call_tree_assignments call_tree_feedback \
     first_timer_gallery gift_logs member_telegram whatapp \
     > ~/migration/legacy-tables-$(date +%F).sql
   ```

   Download it next to the dump and keep the two together. Section 9 is where the
   future of those tables is decided; nothing else is dropped.
6. **Copy the files the pipeline never uploads** by hand (cPanel **File
   Manager**: `$OLD_DIR` -> `$NEW_DIR`; if the target subfolder does not exist
   yet, create it with **+ Folder** - the pipeline's `mkdir -p` does the same),
   preserving permissions:
   `public/display_photo/`, `public/gallery_uploads/`, `public/uploads/`,
   `public/member_photos/`, `storage/app/public/` and the `public/storage` symlink.
   None of these are in the git repository, so a deploy - old or new - will never
   bring them along. Re-create the symlink in the new folder with
   `ln -s ../storage/app/public public/storage` (Terminal / SSH) - optional in this
   project, since nothing in the codebase calls `Storage::*`.

   The paths need no rewriting: `tiu_member.picture_part` holds
   `display_photo/user_300_....jpeg` - relative, no hostname - and the code renders it
   through `asset()`, so every URL follows `APP_URL` / `ASSET_URL`. Copying the folders
   *is* the whole photo migration. `public/member_photos/` is the fourth one: the *add
   member* form (`MemberController`) writes there and reads it back through the same
   column, and it is created on demand if it is missing.
7. **Create the new `.env`** (File Manager -> new folder -> `.env`):

   ```
   APP_NAME="Smart Church"
   APP_ENV=production
   APP_KEY=base64:<the exact old value>
   APP_DEBUG=false
   APP_URL=https://smartchurch.tcnikorodu.org
   ASSET_URL=https://smartchurch.tcnikorodu.org

   SINGLE_CAMPUS=true

   DB_CONNECTION=mysql
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=tcnikoro_smartchurch
   DB_USERNAME=tcnikoro_smartchurch
   DB_PASSWORD=<new password>

   SESSION_DRIVER=file
   CACHE_DRIVER=file
   QUEUE_CONNECTION=sync

   API_TOKEN_KEY=<the exact old value>
   API_TOKEN_TTL_DAYS=365
   ```

   There are three `.env` files in play during the move, and **only this one is
   yours to write**:

   | File | Who creates it | What happens to it |
   |---|---|---|
   | the **runner's** build copy | the workflow (`cp .env.example .env`, then `key:generate`) | dies with the runner; excluded from the payload (`--exclude='./.env'`) |
   | **`$NEW_DIR/.env`** on cPanel | **you, once, above** | never uploaded and never pruned, so it survives every deploy |
   | your local `c:\xampp\...\.env` | nobody | the rehearsal overrides the database per command instead (section 1 step 3) |

   Nothing generates `APP_KEY` for you: the account has no shell, so
   `php artisan key:generate` cannot run on it, and a wrong key is what produces
   `No application encryption key has been specified`. Paste the old value.

   The three driver lines are load-bearing, not decoration: the dump has no
   `sessions` and no `cache` table, and none of the 54 migrations create them, so
   putting `SESSION_DRIVER` or `CACHE_DRIVER` on `database` makes every page fail
   with `Base table or view not found: ... sessions`. Leave both on `file`.
   `QUEUE_CONNECTION=sync` matches the code: nothing implements `ShouldQueue` and
   the one `Mail::send` (the birthday digest) runs inline in the scheduled command,
   because no queue worker runs on this account.

   `APP_TIMEZONE` is deliberately absent - `config/app.php` hardcodes `UTC`, and the
   daily jobs take their clock from `BIRTHDAY_TIMEZONE` (default `Africa/Lagos`,
   `app/Console/Kernel.php`), so setting it here would do nothing.

   Everything else `env()` reads already has a working default (`BIRTHDAY_*`,
   `NOTIFICATIONS_*`, `DEFAULT_CAMPUS_*`), so the block above is the whole
   requirement to bring the site up. `MAIL_*` is only needed if the birthday digest
   should send e-mail (`BIRTHDAY_CHANNELS` includes `mail`), and `TELEGRAM_*` only
   for the Telegram channel - `.env.cpanel.example` documents both, plus the optional
   `API_TOKEN_ISSUER` / `API_TOKEN_CLOCK_SKEW` / `API_TOKEN_TTL_DAYS` knobs.

   If the old `.env` has **no** `API_TOKEN_KEY` line, leave it out here too: the
   config falls back to `APP_KEY` (`config/api.php`), which keeps the phones signed
   in. Inventing a new value logs every installed app out.

   **Where these values come from.** Both live in the *old* install's `.env`, and
   File Manager hides dotfiles by default, which is why the file looks missing:

   1. cPanel -> **File Manager** -> top-right **Settings** -> tick **Show Hidden Files
      (dotfiles)** -> **Save**.
   2. Open `$OLD_DIR/.env`
      (`/home/tcnikoro/public_html/tiu.tcnikorodu.org/.env`) -> right-click -> **Edit**.
   3. Copy `APP_KEY=` (the whole value, `base64:` prefix included) and, if present,
      `API_TOKEN_KEY=`, into the new `.env`.

   Do **not** reuse the `APP_KEY` from your local `c:\xampp\...\.env`: it belongs to a
   different install, so every session cookie in people's browsers and every encrypted
   value in the database becomes unreadable.

   That same file is where the `MAIL_*` block comes from if you want mail on day one:
   `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION` and `MAIL_USERNAME` copy across verbatim
   - and so does **`MAIL_PASSWORD`**. cPanel cannot show you an existing mailbox
   password (Email Accounts -> **Security -> New Password** only *sets* a new one), so
   the old `.env` is the last place it can still be read.

   If you do reset that mailbox password, put the new value in **both** `.env` files:
   the old site keeps running as the rollback path, and its birthday mail would start
   failing the moment the mailbox password changes.

   Mail is optional for the cut-over. `BIRTHDAY_CHANNELS` defaults to
   `mail,database,telegram`, but `Mail::to(...)->send()` sits inside a `try/catch`
   (`BirthdayReminderService`), so a missing or wrong password only writes a line to
   `storage/logs/laravel.log` - the in-app bell and Telegram still deliver. To leave
   SMTP alone for now, set `MAIL_MAILER=log` (mail goes to the log instead of out), or
   narrow the digest with `BIRTHDAY_CHANNELS=database,telegram`.

   Two values are not negotiable:

   * **`APP_KEY` must be byte-identical to the old one.** It encrypts the
     sessions/cookies that are already in people's browsers; a new key logs
     everybody out, and any encrypted column becomes unreadable.
   * **`API_TOKEN_KEY` must be byte-identical too.** `ApiTokenService` signs the
     mobile tokens with HMAC and never checks the issuer - it does not know or
     care that the host changed - so keeping the key means every installed app
     stays signed in across the domain switch. Change it and every phone is
     forced to log in again.
8. **Run the deploy** to the new folder (section 7). Then, in the new folder:

   ```bash
   cd "$NEW_DIR"
   php artisan app:sync-schema     # records/creates what is missing and runs the
                                   # retirement: it drops the six Transport / FOF
                                   # tables (exported in step 5) and makes
                                   # tiu_member.campus_id nullable. Idempotent.
   php artisan optimize:clear
   ```
9. **Smoke test** against the new subdomain before touching DNS for anyone else:
   login, the dashboard, `/pending-tasks` (the Action Board query is the one that
   touches most columns), the reports, the map, one first-timer registration, and
   `curl -H "Authorization: Bearer <token>" https://smartchurch.tcnikorodu.org/api/pending-tasks`
   with a token issued **before** the window (proves the key survives).

   The document root here *is* the app folder, so also spend one minute proving the
   repository's root `.htaccess` did its job. Every one of these must come back
   **403 or 404** - never a download, never a directory listing:

   ```bash
   for p in .env artisan composer.json storage/logs/laravel.log vendor/; do
     printf '%-34s %s\n' "$p" \
       "$(curl -s -o /dev/null -w '%{http_code}' "https://smartchurch.tcnikorodu.org/$p")"
   done
   ```

   Expected - and this is *measured* on `dev.lit-grp.com`, which runs this same
   code and this same `.htaccess`, not a guess: `.env` -> `403`, `artisan` ->
   `404`, `composer.json` -> `404`, `vendor/` -> `404`,
   `storage/logs/laravel.log` -> `403`, or `404` if no log exists yet.

   The two numbers differ for a reason. The root `.htaccess` rewrites *everything*
   into `public/` first (its line 25) and the `FilesMatch` deny is evaluated after
   that: a file that really exists (`.env`) still carries its name into `public/`
   and is denied with `403`, while `artisan` and `composer.json` become
   `public/artisan` and `public/composer.json`, which do not exist, so Laravel's
   front controller answers `404`. Both are the correct outcome.

   So the test is **never `200`, and never a directory listing** - not a particular
   number. If any of them returns `200` or prints an *Index of /* page, **stop the
   window**: the root `.htaccess` is missing or was replaced, and that same file is
   what serves the whole site. Restore it from the repo (File Manager -> upload the
   root `.htaccess`) and re-test before pointing anybody's phone at the new host.
10. **Switch the traffic.** `smartchurch.tcnikorodu.org` already resolves to this
    server with its document root already on the new folder, so the only live
    change is section 3's **301** on the old host. Then confirm the new site
    answers `200` on `/` - that *is* the login page (`routes/web.php` binds `GET /`
    to `showLoginForm` under the name `login`, so `/login` itself answers `404`;
    the only `/login` route in the app is `POST /api/login`, the phones' endpoint) -
    and on a logged-in page, and that
    `https://tiu.tcnikorodu.org/...` now redirects to the matching URL on the new
    host.

## 3. Re-pointing the domains

The app must be served from `https://smartchurch.tcnikorodu.org` while
`https://tiu.tcnikorodu.org` keeps working for one month as a redirect.

1. cPanel -> **Domains**: **nothing to change**. `smartchurch.tcnikorodu.org`
   already has document root `public_html/smartchurch.tcnikorodu.org` - precisely
   the folder the pipeline publishes into - and the repository's root `.htaccess`
   turns that into a working Laravel site by forwarding into `public/`. One
   subdomain, one document root, one `.env`; `ASSET_URL` follows `APP_URL`, so
   nothing else needs the host written down.
2. When the new site passes the smoke test, make the old host a **301** so every
   installed PWA, every shortcut and every WhatsApp link lands on the new site:
   cPanel -> **Domains** -> *Manage* `tiu.tcnikorodu.org` -> **Redirect** ->
   `https://smartchurch.tcnikorodu.org` with **301 Moved Permanently**, or an
   `.htaccess` in the old document root:

   ```apache
   RewriteEngine On
   RewriteRule ^(.*)$ https://smartchurch.tcnikorodu.org/$1 [R=301,L]
   ```

   A 301 (not 302) is what makes the installed PWAs update their start URL. The
   Android/iOS apps rebuilt against the new host need nothing.
3. Add `https://smartchurch.tcnikorodu.org` to the CORS / Sanctum allowed
   origins (`config/cors.php`, `config/sanctum.php`) if they list a host.
4. Leave `tiu.tcnikorodu.org`'s DNS and certificate in place - the redirect needs
   them.

## 4. Rollback (do this if the new site misbehaves)

Nothing this runbook does is destructive: the reconciliation only adds, the old
database is only ever read, and the old folder keeps running until you turn the
redirect on. The one step that removes anything is the app's own retirement -
`app:sync-schema` drops the six Transport / FOF tables and empties the retired
campus columns - and it runs in the **new** database, after the archive in section
2 step 5. That is what makes rollback cheap.

| Trigger | Rollback | Time |
|---|---|---|
| New site 500s / pages error | Remove the 301, put old folder back online (`php artisan up`), restore its cron | ~5 min |
| New database looks wrong | Re-import `~/migration/smartchurch-<date>.sql` into a scratch database and diff with `scripts/sql/verify-new-db.sql`; the old database was never written to | ~20 min |
| Users report missing photos | Re-copy `public/display_photo/`, `public/gallery_uploads/`, `public/uploads/`, `public/member_photos/`, `storage/app/public/` and the `public/storage` symlink | ~15 min |
| Mobile app cannot log in | Confirm `API_TOKEN_KEY` and `APP_KEY` match the old values byte for byte | ~5 min |
| Deploy broke the new folder | Re-run the prod workflow from the previous commit; the old folder is untouched by it | ~10 min |

The one thing that can make rollback expensive is **writing to the new database
and then wanting those writes back**: from the moment the old host redirects,
the new database is the source of truth. Before that, `php artisan down` on the
old folder is what keeps the copy honest.

## 5. Cut-over checklist

- [ ] New database `tcnikoro_smartchurch` + user exist; user has ALL PRIVILEGES on that DB only.
- [ ] Rehearsal on `sc_prod_check` passed: all of `verify-new-db.sql` clean (12 tables + 22 columns present, every `campus_id` `YES`, the six Transport / FOF tables `gone`, eight migrations `recorded`).
- [ ] Second deploy target added and a prod deploy has run into the new folder.
- [ ] `APP_KEY` and `API_TOKEN_KEY` copied byte for byte; `SINGLE_CAMPUS=true`.
- [ ] Old site in maintenance mode, old `schedule:run` cron removed, `crontab` backed up.
- [ ] Dump taken (`~/migration/smartchurch-<date>.sql`) and downloaded to your PC.
- [ ] Dump imported into the new database; reconciliation applied; `php artisan app:sync-schema` ran clean.
- [ ] The 14 dump-only tables exported (`legacy-tables-<date>.sql`) **before** `app:sync-schema` dropped six of them.
- [ ] Uploaded files copied: `display_photo/`, `gallery_uploads/`, `uploads/`, `member_photos/`, `storage/app/public/`, `public/storage` symlink.
- [ ] Smoke test passed on the new subdomain, including a token issued before the window.
- [ ] `tiu.tcnikorodu.org` 301s to the new host; old DNS/SSL kept.
- [ ] Cron on the new folder re-added (only there).
- [ ] Old database left read-only for at least a month; nothing dropped there (the retirement ran in the new database only).

## 6. Reading the generated reconciliation

`scripts/build-prod-reconciliation.ps1` writes `scripts/sql/prod-reconciliation.sql`.
It is generated from two live schemas - the dump (`sc_prod_check`) and your local
development database, which is what the migrations produce - so it can never drift
from either. What it emits:

| Section | Content |
|---|---|
| 1 | `CREATE TABLE` (copied verbatim from the dev database) for every table the code's schema has and the dump does not - 12 tables, indexes and constraints included |
| 2 | `ADD COLUMN` for every such column, with the dev definition - 22 columns, of which **`campus_id` is forced NULLABLE** because the deployed code writes NULL (`docs/SINGLE-CAMPUS.md`). The `task.extra` -> `task.department_id` rename is emitted here too, as a guarded `CHANGE COLUMN`, so the answers already recorded survive |
| 3 | A report of the columns and tables production has and the code's schema does not. Nothing here is dropped |
| 4 | The tables only production has, the count of rows in each, which of them the app retires by itself, and the `mysqldump` archive command (section 9) |

Every statement is guarded with the `information_schema` + `PREPARE` idiom:

```sql
SET @sql := IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'first_timer'
      AND COLUMN_NAME = 'campus_id'),
  'DO 0',                                  -- already there: no-op
  'ALTER TABLE `first_timer` ADD COLUMN `campus_id` int(11) NULL');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
```

Re-running it is therefore safe, and applying it to a database that is *ahead* of
the dump (the new production database after a deploy, say) does nothing.

Read the file once before you apply it. Two places are worth your eyes: section 1,
whose `CREATE TABLE` blocks are copied from the code's schema and therefore define
tables that start out **empty** (the app fills them), and section 3, the report of
what production has and the code does not - a column listed there is usually a
feature that was retired, so reading it is what stops you "fixing" it by accident.

One thing the file cannot do is change a column both databases have and disagree
about. `tiu_member.campus_id` is exactly that (NOT NULL in production, nullable in
the code's schema), and it is fixed by `php artisan app:sync-schema` applying
`2026_10_14_000002_make_campus_id_nullable` - not by this file.

## 7. The second deploy target

Both subdomains deploy from the same repository, in parallel, to two folders.
`deploy-cpanel.yml` keeps publishing the dev/staging folder; the new
`.github/workflows/deploy-cpanel-prod.yml` publishes the new one and reads its own
secrets, so a mistake in one cannot overwrite the other.

Add on GitHub -> **Settings -> Secrets and variables -> Actions**:

| Kind | Name | Value |
|---|---|---|
| Secret | `PROD_CPANEL_HOST` | the same SSH hostname the dev workflow uses |
| Secret | `PROD_CPANEL_USER` | `tcnikoro` |
| Secret | `PROD_CPANEL_PASSWORD` | the same password |
| Variable | `PROD_CPANEL_REMOTE_DIR` | `public_html/smartchurch.tcnikorodu.org/` |
| Variable | `PROD_CPANEL_SSH_PORT` | `22` (optional) |

There is deliberately **no `PROD_CPANEL_PROTOCOL`**, and no `CPANEL_PROTOCOL` in the
dev repository either: both workflows are hardcoded to SFTP on their `*_SSH_PORT`
variable, because FTPS passive data ports are filtered from GitHub's runners (that is
the whole reason for the SFTP switch). A protocol variable added to the `production`
environment is ignored - and the dev workflow could not see an environment-scoped
variable anyway, since its job declares no environment.

Exact click path: **Settings -> Secrets and variables -> Actions**, then the
**Secrets** tab -> **New repository secret** (three times), then the **Variables**
tab for the last two. Names are case-sensitive, and secret values are **write-only**
once saved - GitHub never shows them again (only *Update* / *Remove*). If you no
longer know the hostname the dev target uses, read it from cPanel's right sidebar or
confirm it with the pre-flight script below - do not guess it.

With the `gh` CLI instead (repository scope; add `--env production` to any line to
scope it to the environment instead):

```powershell
gh secret set PROD_CPANEL_HOST     --body "<the host the dev target already uses>"
gh secret set PROD_CPANEL_USER     --body "tcnikoro"
gh secret set PROD_CPANEL_PASSWORD --body "<cPanel account password>"
gh variable set PROD_CPANEL_REMOTE_DIR --body "public_html/smartchurch.tcnikorodu.org/"
gh variable set PROD_CPANEL_SSH_PORT   --body "22"
gh secret list; gh variable list       # verify
```

Repository values are enough for the job: it only *declares* `environment:
production`, which does not hide repository secrets. Put them on the
**`production` GitHub Environment** too (or instead) if you want values scoped to
production; environment values win over repository ones of the same name.

**The review gate.** Production must not deploy itself the way dev does. Two settings
under **Settings -> Environments -> `production`** make every live deploy wait for you
(the click path, with screenshots-in-words, is in `docs/CPANEL-DEPLOYMENT.md`
section 3e):

1. **Required reviewers** -> add your GitHub username. The run then stops in *Pending*
   until you click **Approve and deploy** — nothing is checked out, built or uploaded
   before that. Leave **Prevent self-review** unticked: you are the only admin, and
   ticking it would leave the deploy with nobody able to approve it.
2. **Deployment branches and tags** -> *Selected branches and tags* -> Branch ->
   `main`. The environment-level twin of the workflow's `branches:` filter, so `main`
   is the **only** branch that can reach the live folder, manual runs included.

`deploy-cpanel.yml` (dev) declares no environment at all, so it keeps deploying on
every push with no approval - that asymmetry is deliberate.

> **Set both rules above *before* the push that carries the workflow.** They are
> environment settings, not workflow YAML, so a push that lands while `production` still
> has no required reviewer goes straight to the live folder - the file itself cannot make
> a run wait for anybody.

> Adding the secrets is also what makes it safe to push
> `.github/workflows/deploy-cpanel-prod.yml`: the workflow runs on every push to
> `main` (**`main` only** — its `branches:` filter excludes `master`, so a stale
> `master` push cannot overwrite the live folder). The first push that carries it
> **is** the first production deploy — except that the run now stops in *Pending*
> until you approve it (item 1 above), which makes that first upload deliberate.
> Until section 2 step 7 creates the server `.env`, expect
> `https://smartchurch.tcnikorodu.org` to answer with
> `No application encryption key has been specified` - that is the correct state at
> this point, not a broken deploy.

Before the first real run, verify the credentials and the folder with the existing
pre-flight script:

```powershell
.\scripts\test-cpanel-connection.ps1 -Server <host> -Username tcnikoro -Protocol sftp -RemoteDir public_html/smartchurch.tcnikorodu.org/
```

`PROD_CPANEL_REMOTE_DIR` is what makes this safe: the workflow mirrors into
whatever directory it names, and `public_html/smartchurch.tcnikorodu.org/` is a
folder that the dev workflow (`public_html/dev.lit-grp.com/`) never touches.

It is **relative to the account home** (`~` = `/home/tcnikoro/`), which is why the
value starts with `public_html/` and not with `/home/tcnikoro/`. Get it wrong and
the app lands beside `public_html/`, where Apache never serves it, while the deploy
still reports success - the connection script above prints the exact value to
paste.

## 8. Code fixes that belong to this migration

These are the only places in the code that know the host by name. They must be
deployed **before** the switch, so the new build is already correct when it lands:

1. `resources/views/pending-tasks/index.blade.php` - the Action Board built its
   "guide" link as an absolute `https://tiu.tcnikorodu.org/dash-board?tiu_member_id=...`.
   After the switch that link sends a signed-in user back to the hostname that is
   being retired (and, once the old folder is offline, to a 301 that loses the
   query string's meaning). It now reads `{{ url('/dash-board') }}`, so it follows
   `APP_URL` on localhost, on dev.lit-grp.com and on the new subdomain alike.
   `tests/Feature/PendingTasksBoardTest.php` holds that in place: it fails if a
   view, controller, config or route file hardcodes the retired host again.
2. `docs/PWA.md` - the Requirements section named the old production host as the
   example of a real HTTPS origin. Updated to the new one.
3. `.env.cpanel.example` - the server-side template. It now names
   `smartchurch.tcnikorodu.org` and `tcnikoro_smartchurch` instead of placeholder
   hostnames, and it states the rule this whole migration depends on: `APP_KEY`
   and `API_TOKEN_KEY` are copied from the old server **byte for byte**, so every
   signed-in browser session and every installed phone keeps working.
4. `docs/PROD-DB-DIFF.md` and this runbook keep the old hostname on purpose: they
   are records of what production was, and rewriting them would make the diff
   unreadable.

Nothing else in the tree references the host. `config/` reads `APP_URL` and
`ASSET_URL` from the environment, and the service worker and manifest are served
from `/` on whatever host the app answers on - which is what makes the switch a
`.env` edit rather than a code change.

## 9. The tables only production has

Fourteen tables in the dump are not in the code's schema. The reconciliation
reports them (section 3 of the generated file) rather than dropping them, and
this is where they are accounted for.

| Tables | What they are | What happens |
|---|---|---|
| `transport_routes`, `transport_stops`, `bus_attendance`, `fof_cohort_setting`, `fof_register_table`, `fof_mark_attendance_table` | the retired Transport (bus route) and standalone FOF modules | `php artisan app:sync-schema` drops them - that is the app's own retirement, not this migration's. **Archive them first** (section 2 step 5) |
| `messages` | the code's own table: `AdminFirstTimerController` inserts into it, and no migration creates it | keep, and do not let anything recreate it with a different column set |
| `call_center`, `call_tree_assignments`, `call_tree_feedback`, `first_timer_gallery`, `gift_logs`, `member_telegram`, `whatapp` | nothing in `app/`, `resources/`, `routes/`, `config/` or `database/migrations` reads or writes them | keep the exported copy; they do not get in the new database's way |

`first_timers` is not on the list - the real table is `first_timer`, and it is in
both databases. Note also what the reconciliation does with the tables and
columns both sides have: it only ever adds or renames, never deletes.

Dropping one of those seven orphans is a separate, later, deliberate change: one
migration, one table, after the cut-over has been stable for a while and only
once the export from section 2 step 5 has been read back. This runbook does not
drop them.

## 10. After the switch

1. Run `scripts/sql/verify-new-db.sql` against the new database and read it the way
   the rehearsal's output read:

   * part 1 - paste the generated counting script into the old database and into
     the new one, and compare the two outputs table by table
   * parts 2 and 3 - every row `present` (the 12 tables and the 22 columns)
   * part 4 - every `campus_id` `YES`, `tiu_member` included
   * part 5 - the six Transport / FOF tables `gone`, `messages` still there
   * part 6 - all eight migrations `recorded`
   * part 7 - the outstanding token count, which should match the old database's
2. Re-add the cron on the new folder (and only there):

   ```
   0 7 * * * cd /home/tcnikoro/public_html/smartchurch.tcnikorodu.org && php artisan schedule:run >> /dev/null 2>&1
   ```

   Confirm with `php artisan schedule:list` that `birthdays:remind` and
   `firsttimers:auto-unassign` resolve, then leave the old folder's cron deleted.
3. Watch for a week: `storage/logs/laravel.log` on the new folder, cPanel ->
   **Errors**, and the "Index of /" symptom in the workflow notes (that means a
   half-written tree - re-run the deploy, do not patch by hand).
4. Retire the old install after ~1 month of clean running:
   `php artisan up` is no longer needed, the old database becomes read-only
   (revoke the old user's write grants rather than dropping anything), and the 301
   becomes permanent. The old database is deleted only after the dump and the
   legacy exports have been confirmed readable, and never in the same change as
   anything else.

## What this migration deliberately does not do

* No `php artisan migrate:fresh` against anything that holds data.
* No plain `php artisan migrate` either - the dump has no `migrations` table, so a
  full run aborts part way through. `app:sync-schema` applies the files it names,
  one at a time, and records each one.
* No drops of its own. The only tables the pipeline removes are the six the app's
  own retirement migrations remove, and only after section 2 step 5 has exported
  them.
* Nothing written to the old database: it is read once, dumped, and left alone.
* No `APP_KEY` / `API_TOKEN_KEY` rotation (that would log every browser session
  and every phone out - see `docs/API-TOKENS.md` for how to do it deliberately).
* No change to the login flow, the API contract or the PWA: the app moves, its
  behaviour does not.
