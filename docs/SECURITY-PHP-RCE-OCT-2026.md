# PHP2Shell (CVE-2026-109618 / CVE-2026-109619) - what it means for Smart-Church

Two critical, unauthenticated remote-code-execution bugs in PHP itself were published on
**21 October 2026** by the PHP project. Nothing in this repository causes or can fix them:
the flaw is in the interpreter that runs `public/index.php`. This page records where the
project stands, what to do, and why no amount of Laravel code closes the hole.

Sources: [php.net news, 21 Oct 2026](https://www.php.net/archive/2026.php),
[GHSA-w8hw-w9fx-j79w](https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w) (CVE-2026-109618),
[GHSA-6wmf-4rv5-p3rp](https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp) (CVE-2026-109619),
NVD records `CVE-2026-109618` / `CVE-2026-109619` (status *Analyzed*).

## 1. Where this project stands

| Where | PHP in use | Verdict | Action |
| --- | --- | --- | --- |
| Production - `smartchurch.tcnikorodu.org` (cPanel) | 8.3.x - the account's interpreter is `/opt/cpanel/ea-php83/root/usr/bin/php`, recorded in [`CPANEL-DEPLOYMENT.md`](CPANEL-DEPLOYMENT.md) and verified on 2026-10-17 | **Affected.** Every 8.3 build below **8.3.36** is vulnerable | Host must ship ea-php83 >= 8.3.36, then re-verify |
| Dev site - `dev.lit-grp.com` | Same cPanel account / same alt-PHP builds | **Affected** for the same reason | Same fix |
| Local XAMPP on the dev workstation | **8.0.30** (`C:\xampp\php\php.exe -v`) | **Affected and permanently unpatched** - the 8.0 branch reached end of life in Nov 2023 and will never receive a fix | Keep it local-only, or run a 8.3.36+ build for dev |
| Laravel 8 framework (`laravel/framework` ^8.75) | `composer.json` declares `php: ^7.3\|^8.0`; upstream support stops at 8.1 | Not vulnerable itself, but it constrains which patch you can take | Target **8.3.36**; do not jump to 8.5.12 without upgrading Laravel |

Patch levels, as published by the PHP project (all four are equivalent fixes):

| Branch | Vulnerable | Patched |
| --- | --- | --- |
| 8.2 | < 8.2.35 | 8.2.35 (branch reaches end of life **31 Dec 2026**) |
| 8.3 | < 8.3.36 | **8.3.36** (security support to 31 Dec 2027) |
| 8.4 | < 8.4.27 | 8.4.27 |
| 8.5 | < 8.5.12 | 8.5.12 |

> 8.2.35 is patched but is only supported until 31 Dec 2026 - two months away. 8.3.36 is
> the only release that is both patched and supported for another year, and the account
> is already on the 8.3 series, so 8.3.36 is the target.

## 2. What the two bugs are

- **CVE-2026-109618 - RCE via OOM corruption.** PHP uses `longjmp()` to abort on
  out-of-memory. In some contexts request memory is reachable before it is initialised, so
  an allocation failure leaves the request in an invalid state that the shutdown routine
  then reads or branches on. Multipart parsing gives several ways to reach OOM inside the
  declared `post_max_size` / `memory_limit` (no usable content length, unlimited part
  headers, empty file controls, nested field names).
- **CVE-2026-109619 - RCE via stale global state.** Error/session/object/extension state
  that points into one request's memory survives into the next request handled by the same
  worker. A "producer" request publishes the pointer (a fatal error, OOM, or even a warning
  emitted during shutdown), a "consumer" request - including an INI diagnostic raised
  *before* normal request initialisation - acts on it. Confirmed impact: heap use-after-free,
  memory disclosure and RCE as the web-worker account.

Both are rated **Critical** (`CVSS:4.0/AV:N/AC:H/AT:P/PR:N/UI:N/VC:H/VI:H/VA:H`): network
reachable, no privileges, no user interaction, full confidentiality/integrity/availability
impact - but `AC:H`, so exploitation needs a crafted sequence and repeated attempts that
land on the same worker, not a single drive-by request.

## 3. Why no application change fixes this

- On **mod_php**, the confirmed exploit fires *during request startup, before any
  application code runs*. Laravel's authentication, CSRF tokens, `verify.token` middleware,
  `APP_DEBUG=false` and the `Sanctum`/JWT API guard are not in the path yet. A WAF that
  inspects application parameters does not see a malformed HTTP request here - it sees PHP
  corrupting its own heap.
- On **PHP-FPM** the startup path is safer (the child is discarded when startup fails), but
  the session-lifecycle variant of CVE-2026-109619 was **validated against FPM** as well.
  FrankenPHP is expected to behave like mod_php; LiteSpeed (LSAPI) is modelled on FPM.
- The only complete mitigation is the patched interpreter. Everything below is risk
  reduction, and the advisory says so explicitly.

## 4. What an attacker gets if the worker is taken over

The worker runs as the cPanel account user, so a shell here is a shell over the whole
application directory:

- `.env` - `DB_*` credentials, `APP_KEY`, `API_TOKEN_KEY`, `MAIL_PASSWORD`,
  `TELEGRAM_BOT_TOKEN`. With `API_TOKEN_KEY` an attacker can mint mobile-app bearer tokens
  that pass `verify.token` (see [`API-TOKENS.md`](API-TOKENS.md)).
- The member database - `tiu_member`, `first_timer`, attendance, birthday and follow-up
  data: names, phone numbers, e-mail addresses, residential addresses, dates of birth.
- Write access to the document root, including `public/uploads/`, `public/display_photo/`,
  `public/gallery_uploads/`, `public/member_photos/` - the classic place to drop a
  persistent `.php` web shell, because the pipeline never prunes or overwrites those
  directories.
- The scheduled jobs (`schedule:run` every minute, `birthdays:remind`,
  `app:sync-schema`, `firsttimers:auto-unassign`) run as the same account.

## 5. Do now - patching

1. **Read the exact version** rather than assuming:

   ```bash
   /opt/cpanel/ea-php83/root/usr/bin/php -v
   ```

   That is the interpreter the cron and `app:post-deploy` use. Confirm the *web handler*
   version too - cPanel > **MultiPHP Manager** for the domain, and `php -i | grep -i 'Server API'`
   if you have shell. Anything below 8.3.36 is vulnerable.

2. **Ask the hosting provider for ea-php83 >= 8.3.36.** Alt-PHP builds are installed by
   EasyApache at root level; a shared-hosting account cannot upgrade them, and this
   repository's GitHub Actions pipeline only uploads files - it never touches the PHP build.
   Quote the CVE IDs and the target version in the ticket.

3. **Do not "just switch to 8.5.12"** in MultiPHP Manager to get a patched version. Laravel 8
   is not supported on 8.4/8.5 and this app has never been run there; 8.3.36 keeps the
   runtime you already validated. If a 8.4 branch ever becomes necessary, run the suite
   (`php artisan test`) on a staging copy first.

4. **Restart the PHP workers after the upgrade** (the host does this when it applies a new
   ea-php build). A new binary only takes effect for newly spawned workers.

5. **Re-verify** and record the result:

   ```bash
   /opt/cpanel/ea-php83/root/usr/bin/php -r 'echo PHP_VERSION, PHP_EOL;'
   # 8.3.36 or newer  ->  patched
   ```

### Local XAMPP

`C:\xampp\php\php.exe -v` reports **8.0.30**, which will never be patched. It only matters
if Apache is reachable from somewhere other than this machine: XAMPP listens on all
interfaces (`Listen 80` in `C:\xampp\apache\conf\httpd.conf`), so a developer machine on a
shared/hotel network is an exposed target. Either keep the Windows Firewall rule closed to
everything but localhost, or install a 8.3.36 build for development.

## 6. Interim mitigations that fit this application

`session.upload_progress.enabled=0` ships in the repository root as
[`.user.ini`](../.user.ini) - read on by PHP-FPM/CGI, ignored by mod_php, so on a mod_php
host set it in **cPanel > MultiPHP INI Editor** as well. It is safe here because nothing in
the app uses the feature (no `session_start()`, no `session.upload_progress` reference, and
`config/session.php` uses Laravel's own `file` driver).

After changing any INI value, **restart every PHP worker**, otherwise the old setting stays
live in running processes.

What else is worth doing, in priority order:

| Control | How it applies here |
| --- | --- |
| Keep request bodies and part counts bounded at the edge | Uploads are genuinely multipart in this app (gallery 20 MB, resources 10 MB, profile photos), so blocking multipart outright is not an option. Cap total body size, number of parts, number of headers per part and `max_file_uploads` (currently 20 locally) at the proxy/WAF instead. |
| Never raise `max_input_nesting_level` above the default 64 | The OOM strategies rely on nesting and header count multiplying memory. |
| Rate-limit the anonymous POST routes | The `api` group is covered (`throttle:api`, 60/min per IP, in `App\Http\Kernel`), but the public **web** POSTs are not: `POST /` (login), `/register`, `/mregister`, `/attendance/search`, `/attendance/submit` and `/home/*` run in the `web` group, which adds no throttle, and `CustomAuthController::login()` uses neither `ThrottlesLogins` nor a limiter. Both CVEs need many attempts against the same worker, so a `throttle:` limiter on those routes is real risk reduction - and it closes a plain password-guessing gap at the same time. |
| Keep per-request INI overrides out of the code path | The repo is clean today: no `php_value`/`php_flag` in `.htaccess`, no `ini_set('memory_limit', ...)`, and the `PHP_MEMORY_LIMIT` line in `.env` / `.env.cpanel.example` is read by nothing. That matters because the advisory's fallback (`memory_limit=-1`) is defeated by any per-request override. |
| `memory_limit=-1`, `pm.max_requests=1`, `fatal_error_backtraces=0` | Documented fallbacks for hosts that cannot patch. None of them is a complete mitigation, all have costs (unbounded memory/DoS, throughput), and this host *can* be patched - so do not apply them here. Never use `RLIMIT_AS`/`RLIMIT_DATA`: they re-arm the vulnerable `longjmp()` path. |

## 7. Detection

Turn up logging and watch for the producer/consumer fingerprints. In
`storage/logs/laravel.log`, the Apache error log and the PHP-FPM log:

- repeated `Allowed memory size of ... bytes exhausted` on **large or multipart** requests,
- `Maximum execution time of ... exceeded`,
- warnings/deprecations that appear **before** any script name in the log line,
- new `.php` files (or files with doubled extensions such as `x.jpg.php`) under
  `public/uploads/`, `public/display_photo/`, `public/gallery_uploads/`,
  `public/member_photos/`,
- unexpected entries in cPanel > Cron Jobs, or `git status`-style drift in the deployed tree.

Absence of log lines is not proof of safety - the advisory is explicit that a failure can
occur before PHP writes anything. Logs supplement the version check, not replace it.

## 8. If you suspect a compromise

1. Rotate `API_TOKEN_KEY` (signs every mobile token out), `APP_KEY` (signs every browser
   session out), the database password, `MAIL_PASSWORD` and `TELEGRAM_BOT_TOKEN`; update
   `.env` on the server and re-run `php artisan app:post-deploy`.
2. Delete unknown `.php` files under the upload directories and compare the deployed tree
   with `main`; the SFTP pipeline only prunes files it recognises as removed from git.
3. Review admin/super-user rows in the member tables and recent `member_tracking_followup_archive`
   activity for changes nobody made.
4. Preserve the logs before cleaning up, then patch PHP before putting the site back.

## 9. Notes on the viral summary

The widely-shared write-up is broadly right (two CVEs, 21 Oct 2026, pre-auth RCE plus
memory disclosure, patch on 8.2.35/8.3.36/8.4.27/8.5.12, no fixes for EOL branches), with
three corrections that matter when you plan work:

- **"All versions 5.4 through 8.5.11"** overstates the recorded range. NVD lists affected
  versions as 8.2.* < 8.2.35, 8.3.* < 8.3.36, 8.4.* < 8.4.27, 8.5.* < 8.5.12. Older branches
  are simply end-of-life with no fix coming - which for this project is the whole problem
  with the 8.0.30 dev box.
- **"A WAF can offer mitigation"** is only partly true. The advisory's frontend controls are
  *body size, part/header counts and blocking multipart where unused* - not signature
  matching. On an app that legitimately uploads files, a generic WAF rule set will not stop
  a heap-corruption sequence.
- **"9.2 Critical"** - the publication is Critical (`CVSS:4.0` vector above), but note
  `AC:H`: exploitation is a repeated, positioned attack, not an instant one-shot. That buys
  time to patch; it does not lower the priority.

