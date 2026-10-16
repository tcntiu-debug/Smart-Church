# Smart-Church

Church management platform for **TCN Ikorodu** — members, first-timers, departments,
house fellowships, attendance, first-timers' follow-up (FOF), transport, birthdays,
galleries, marketplace and reporting.

Built with **Laravel 8**, **MySQL/MariaDB** and a custom Blade + Bootstrap 5 front end.
Member data lives in the central `tiu_member` table; see
[`docs/Database-Architecture-Summary.md`](docs/Database-Architecture-Summary.md)
for the full data model.

## Requirements

- PHP **7.3 – 8.0** with `bcmath, ctype, curl, dom, fileinfo, filter, gd, hash, json,
  mbstring, openssl, pdo, pdo_mysql, session, simplexml, tokenizer, xml, zip`
- Composer 2
- MySQL 5.7+ / MariaDB 10.2+
- Apache with `mod_rewrite` (the bundled XAMPP setup works out of the box)

Node.js is **not** required: the front-end assets in `public/assets` and `public/vendors`
are committed as-is and are not compiled at deploy time.

## Local setup

```powershell
git clone https://github.com/tcntiu-debug/Smart-Church.git
cd Smart-Church
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Create the database, point `DB_*` at it inside `.env`, then:

```bash
php artisan migrate
php artisan serve
```

> On XAMPP you can also browse to `http://localhost/Smart-Church/`. The root
> `.htaccess` forwards traffic into `public/`, so both entry points work.

## Database

Run `php artisan migrate` to build the schema from scratch, or import a dump via
phpMyAdmin. The full table-by-table reference is in
[`docs/Database-Architecture-Summary.md`](docs/Database-Architecture-Summary.md).

## Deployment (GitHub Actions → cPanel)

Every push to `main` or `master` builds the application and publishes it to cPanel
automatically — no manual uploading.

```
git push  ->  GitHub Actions  ->  composer install --no-dev  ->  SFTP/FTPS upload  ->  cPanel
```

- Workflow: [`.github/workflows/deploy-cpanel.yml`](.github/workflows/deploy-cpanel.yml)
- Quick, filled-in guide for **dev.lit-grp.com**:
  [`docs/DEPLOY-dev.lit-grp.com.md`](docs/DEPLOY-dev.lit-grp.com.md)
- Full setup guide, secrets table and troubleshooting:
  [`docs/CPANEL-DEPLOYMENT.md`](docs/CPANEL-DEPLOYMENT.md)
- How the mobile app authenticates, and how to revoke every token at once:
  [`docs/API-TOKENS.md`](docs/API-TOKENS.md)
- After deploying, run once on the server:

  ```bash
  php artisan app:post-deploy              # directories, caches, storage link
  php artisan app:post-deploy --migrate    # ...and run pending migrations
  ```

## Scheduled tasks

Everything the app runs on a timer is registered in `app/Console/Kernel.php`, and a single
per-minute `schedule:run` cron entry drives all of it — see
[`docs/CPANEL-DEPLOYMENT.md`](docs/CPANEL-DEPLOYMENT.md).

| Command | Purpose | Schedule (`Africa/Lagos`) |
| --- | --- | --- |
| `php artisan birthdays:remind` | E-mail + push the Welcome Center (dept 28) birthday reminders | daily `07:00` |
| `php artisan app:sync-schema` | Brings the database in step with the code: creates the tables the code needs, drops the retired Transport / FOF tables and applies the campus retirement migrations (also part of `app:post-deploy`) | daily `03:20` |
| `php artisan firsttimers:auto-unassign` | Releases first-timers who have had a guide for 8+ weeks. The follow-up is copied into `member_tracking_followup_archive` first, and stays readable under **/weeklist/guide-history** (Admins / Super Users) | weekly, Monday `04:10` |

`public/auto_unassign.php` runs the same release over HTTP for a host that can only trigger a
URL (`?dry-run=1` reports who would be released without writing). Running it as well as the
scheduler is harmless: a released first timer has no follow-up row left, so the second run
finds nothing to do.

Add the per-minute `schedule:run` entry in cPanel → **Cron Jobs**.

## Environment notes

- `.env` is git-ignored and is **never** uploaded by the pipeline. Create it once on the
  server from `.env.cpanel.example`; it survives every deploy.
- `public/uploads`, `public/display_photo`, `public/gallery_uploads` and everything under
  `storage/` are git-ignored because they hold live member data on the server. Deploys
  never overwrite or delete them.
- `API_TOKEN_KEY` - the secret that signs the mobile app's Bearer tokens - belongs in the
  server-side `.env` only, never in a front end, a screenshot or a ticket. Rotating it signs
  every device out: [`docs/API-TOKENS.md`](docs/API-TOKENS.md).
- Never commit credentials. GitHub login details belong in Windows Credential Manager,
  cPanel deploy credentials belong in GitHub repository secrets, and application secrets
  belong in the server-side `.env`.
