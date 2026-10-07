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
- After deploying, run once on the server:

  ```bash
  php artisan app:post-deploy              # directories, caches, storage link
  php artisan app:post-deploy --migrate    # ...and run pending migrations
  ```

## Scheduled tasks

| Command | Purpose | Suggested schedule |
| --- | --- | --- |
| `php artisan firsttimers:auto-unassign` | Releases first-timers who have been assigned for 8+ weeks | weekly |

Add the cron entry in cPanel → **Cron Jobs**.

## Environment notes

- `.env` is git-ignored and is **never** uploaded by the pipeline. Create it once on the
  server from `.env.cpanel.example`; it survives every deploy.
- `public/uploads`, `public/display_photo`, `public/gallery_uploads` and everything under
  `storage/` are git-ignored because they hold live member data on the server. Deploys
  never overwrite or delete them.
- Never commit credentials. GitHub login details belong in Windows Credential Manager,
  cPanel deploy credentials belong in GitHub repository secrets, and application secrets
  belong in the server-side `.env`.
