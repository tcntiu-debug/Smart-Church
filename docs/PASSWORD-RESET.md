# Forgot password

The "Forgot Password?" link on the login page (`resources/views/auth/login.blade.php`)
opens a modal that POSTs the address to `password.email`
(`ForgotPasswordController::sendResetLinkEmail()`). The reply mail carries
`/password/reset/{token}?email=...` (`password.reset`), which opens
`auth.passwords.reset`; that form POSTs `password.update` and `ResetPasswordController`
writes the new hash to `tiu_member`.

The broker is the framework's: `config/auth.php` points `passwords.users` at the
`users` provider, which is `App\Models\TiuMember` (table `tiu_member`) and the
`password_resets` table. Nothing about the old app's
`reset-password.php?reset-token=...` scheme is used - that token was
`openssl_encrypt(email, 'AES-128-ECB', 'password')`, forgeable and non-expiring.

## What the link opens

`auth.passwords.reset` (and the `password.request` page at `GET /password/reset`)
extend `layouts.auth` - the bare, logged-out shell `/mregister` uses - not
`layouts.app`. The link is opened by a visitor who is *not* signed in, usually on
a phone straight out of a mail client, so the page must not arrive wrapped in the
dashboard: no left menu, no welcome name, no notification bell (every link in
that menu only bounces to the login page, and the chrome itself looks like the
link signed the visitor in). `PasswordResetFlowTest` fails if `ms-side-nav` or
`ms-aside-left-open` reappears on either page.

## Why the modal used to say "undefined"

The request failed, and the failure had two separate causes:

| Symptom | Cause |
|---|---|
| `Call to undefined method App\Models\TiuMember::notify()` | `TiuMember` extends `Illuminate\Foundation\Auth\User`, so it had `CanResetPassword` - whose `sendPasswordResetNotification()` calls `$this->notify(...)` - but it never used the `Notifiable` trait. The token row was written *before* that call, so the request also answered with a 500. |
| `Base table or view not found: ...password_resets` | The table was missing on production (`docs/MIGRATION-smartchurch.md` section 0). The broker throws before it can even build the mail. |

Both reached the browser as a 500, and the modal's handler read
`xhr.responseJSON.email` - a path Laravel never sends (validation errors live at
`errors.email[0]`) - and jQuery sent no `Accept: application/json`, so a 500 was
an HTML page with no `responseJSON` at all. `'...' + undefined || 'Email not
found'` then rendered the literal word **undefined** in the box.

## What the code does now

| Piece | Behaviour |
|---|---|
| `App\Models\TiuMember` | uses `Notifiable`, so `notify()` exists and the mail is actually handed to the mailer |
| `ForgotPasswordController::sendResetLinkEmail()` | sends through the broker with a callback, so a mail/transport exception is caught instead of becoming an HTML 500 |
| failure answer | `500` + `{"message": ..., "errors": {"email": [...]}}`, and the half-written token is deleted again so the 60 second throttle does not block the retry |
| `log` / `array` mailer | wherever `APP_ENV=production` (the live site, and dev - its `.env` says `production` too) the request is refused with the same JSON, because a reset link written to a log was never delivered; the refusal is logged at **error** level since those `.env`s use `LOG_LEVEL=error`. In `local` it only logs a warning, so the link can still be read in `storage/logs/laravel.log` |
| unknown address / throttle | `422` + `{"message": ..., "errors": {"email": [...]}}` |
| modal JavaScript | sends `Accept: application/json`, shows `data.message`, reads `errors.email[0]`, ignores a second click while the request is in flight |
| `app:sync-schema` | creates `password_resets` when it is missing (alongside the notification tables) |

## The mailer

This app is Laravel 8.83, so mail is sent by **SwiftMailer**
(`vendor/swiftmailer`, required by `laravel/framework`) - not PHPMailer, and not
PHP's `mail()`. `MAIL_MAILER` picks the transport:

| `MAIL_MAILER` | What actually happens |
|---|---|
| `smtp` (recommended) | a real SMTP session to `MAIL_HOST:MAIL_PORT`, `EHLO` + `STARTTLS` (when `MAIL_ENCRYPTION=tls`, port 587), then `AUTH LOGIN` with `MAIL_USERNAME`/`MAIL_PASSWORD` |
| `sendmail` | the message is piped to the server's own Exim (`MAIL_SENDMAIL_PATH`, `/usr/sbin/sendmail -t -i`) - no credentials, but the From must belong to the account |
| `log` / `array` | nothing is delivered (written to `storage/logs/laravel.log` / held in memory) - refused on any `APP_ENV=production` host |

There is no `mail` transport in Laravel 8; `MAIL_MAILER=mail` fails with
`Unsupported mail transport [mail]`. Use `sendmail` for local submission.

Every failure is logged with the mailer, host, port, mailbox, encryption and
From address - the password is never logged:

```
Password reset link could not be sent to member@example.com: Failed to authenticate on SMTP server with username "noreply@example.com"
{"mailer":"smtp","mail_host":"mail.example.com","mail_port":587,"mail_username":"noreply@example.com","mail_encryption":"tls","from":"no-reply@example.com"}
```

The `mail_username` in that line is what identifies the most common cause: a
mailbox name that differs from the one that exists (`noreply@` vs `no-reply@`).

**The reason is deliberately not shown to the member** - the modal only says the
mail could not be sent and to contact the church office. Read
`storage/logs/laravel.log` for the reason.

## Check it on the server

The deploy only uploads files; PHP does not run it remotely. After the first
deploy that contains this fix, run:

```bash
cd ~/public_html/smartchurch.tcnikorodu.org

php artisan config:clear
php artisan app:sync-schema            # creates password_resets when missing
php artisan tinker --execute="var_dump(Schema::hasTable('password_resets'));"
php artisan tinker --execute="var_dump(Schema::hasColumn('tiu_member', 'remember_token'));"
```

Then use the modal once with a real member address, and if no mail arrives:

```bash
tail -n 30 storage/logs/laravel.log
```

A `MAIL_MAILER=log` line means the mail is being written to the log instead of
sent - set a real transport in `.env`. For cPanel, `MAIL_HOST` is
`mail.<your-domain>` on port `587` with `MAIL_ENCRYPTION=tls`,
`MAIL_USERNAME` a mailbox that exists (**cPanel's Exim rejects a forged From,
so `MAIL_FROM_ADDRESS` must be that same mailbox**), and the mailbox password.
`APP_URL` should still be the public host (`https://smartchurch.tcnikorodu.org`).
The link itself uses `url(route('password.reset', ..., false))`, so it follows
the host the member is already on and works even before `APP_URL` is corrected;
`APP_URL` is the fallback when there is no request (a CLI or queued send) and is
what the rest of the app's absolute URLs use. Clear the config cache after
touching `.env`:

```bash
php artisan config:clear && php artisan config:cache
```

`php artisan app:post-deploy` does that for you and also runs `app:sync-schema`.

## Try it on `dev.lit-grp.com` first

`git push origin main` publishes the dev copy immediately; the production run
stops and waits for your approval, so dev is always the first place a change is
live. (`git push origin main:master` no longer works as a dev-only rehearsal:
`origin/master` has commits `main` does not, so the push is rejected - see
`docs/CPANEL-DEPLOYMENT.md`.)

The dev account (`litgrpco` on `lit-grp.com`) is a **different server and has no
shell**, so `php artisan app:sync-schema` cannot run there and the `password_resets`
table has to be created by hand once. cPanel > **phpMyAdmin** >
`litgrpco_smart_church_db` > **SQL** - this is exactly what the migration creates:

```sql
CREATE TABLE `password_resets` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Then check the mail settings in the dev `.env` (cPanel > **File Manager**,
`/home/litgrpco/public_html/dev.lit-grp.com/.env`). The template ships a real
transport - `MAIL_MAILER=smtp`, `MAIL_HOST=mail.lit-grp.com`, `MAIL_PORT=587`,
`MAIL_ENCRYPTION=tls`, `MAIL_USERNAME`/`MAIL_FROM_ADDRESS` = `noreply@lit-grp.com`
- so if `MAIL_PASSWORD` is still `CHANGE-ME` the send fails. That is not a
problem for the test: the modal has to say *"We could not send the reset e-mail
just now..."*, not "sent" and not "undefined". Put the real mailbox password in
to get the mail itself.

Dev runs with `APP_ENV=production` and `LOG_LEVEL=error`, and it caches no config
(no shell, so nothing runs `config:cache`), so a `.env` edit takes effect on the
next request. The reason a send failed is written to `storage/logs/laravel.log`,
which File Manager can download; `https://dev.lit-grp.com/storage/logs/laravel.log`
is refused by the root `.htaccess`, by design.

Because dev's `.env` sets `APP_ENV=production`, a `MAIL_MAILER=log` there is
refused like any other production log mailer - the modal answers "could not send"
and the reason goes to the log. Keep the `smtp` lines on dev; log-mailer testing
belongs on the local copy, where `APP_ENV=local`.

Running it locally instead: `MAIL_MAILER=log` plus any `MAIL_FROM_ADDRESS` in the
local `.env` writes the whole message (with the working reset link) to
`storage/logs/laravel.log` and the modal reports success, since nothing leaves
the machine. The local database already has `password_resets`.

### "We could not send the reset e-mail just now" on dev

The modal hides the reason on purpose. Read the one line the controller writes
to `storage/logs/laravel.log` - in
`/home/litgrpco/public_html/dev.lit-grp.com/storage/logs/laravel.log`, which
File Manager can download (`/storage/...` is refused over HTTP by the root
`.htaccess`). It starts with `Password reset link could not be sent to <address>:`
and ends with the transport's own message:

| The log line contains | Cause | Fix in the dev `.env` |
|---|---|---|
| `Base table or view not found ... password_resets` | the table was never created; dev has no shell, so `app:sync-schema` cannot be run there | run the `CREATE TABLE` above in phpMyAdmin |
| `Failed to authenticate on SMTP server with username "noreply@lit-grp.com"` | no such mailbox, or the password is still `CHANGE-ME`. The mailbox created in cPanel is `no-reply@lit-grp.com` - **one hyphen apart** | `MAIL_USERNAME=no-reply@lit-grp.com` + that mailbox's password |
| `Sender address rejected` / `550` | cPanel's Exim refuses a From that is not the authenticated mailbox | make `MAIL_FROM_ADDRESS` identical to `MAIL_USERNAME` |
| `Connection could not be established with host "mail.lit-grp.com:587"` | wrong host/port, or TLS on the wrong port | `587` + `tls` (STARTTLS); `465` needs `ssl` |
| `MAIL_MAILER is "log"` | a log mailer on a production-env host | use `smtp`; test `log` locally only |
| `Could not resolve host` | `MAIL_HOST` typo, or DNS not yet propagated | `mail.lit-grp.com` |

### Proving the token table exists without a shell

Dev has no shell, so `app:sync-schema` cannot be run there. To tell "missing
table" from "mail failed" without it, POST the reset form with a token that
cannot be valid:

- a **known** address answers `422 {"email":["This password reset token is invalid."]}`
  - only possible after `password_resets` was queried, so the table exists;
- an unknown address answers `We can't find a user...` **before** the table is
  touched, so it proves nothing;
- a `500` with `Base table or view not found ... password_resets` means the table
  is missing - create it with the DDL above.

No mail is sent by that probe and no password changes, because the token is
rejected.

No password at hand? Hand the message to the server's own Exim instead of an
authenticated mailbox - same machine, so no credentials are involved:

```dotenv
MAIL_MAILER=sendmail
MAIL_SENDMAIL_PATH="/usr/sbin/sendmail -t -i"
MAIL_FROM_ADDRESS="no-reply@lit-grp.com"
```

Exim accepts that From because `lit-grp.com` is local to the account. A real
authenticated mailbox (`smtp`) still has the better reputation for Gmail, so
come back to it once the password is in.

### "Server Error" after submitting the new password

The token page loads and the form posts, then the answer is a 500 and the password
is still the old one. `storage/logs/laravel.log` says:

```text
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'remember_token' in 'field list'
```

`tiu_member` came from the legacy schema and never had Laravel's `remember_token`
column (`2026_04_28_000005` creates the table without it). Nothing *reads* it, so
every page keeps rendering - the failure is on the write.
`ResetsPasswords::reset()` calls `setRememberToken(Str::random(60))` before
`save()`, so the UPDATE names a column MySQL cannot find and the whole statement,
new password included, is rejected. The single-use token has already been deleted
at that point, so the same link cannot be retried; the login form's **remember me**
checkbox died on the very same UPDATE.

The fix is the column, not the form:

```bash
php artisan migrate            # 2026_10_18_000001_add_remember_token_to_tiu_member_table
php artisan app:sync-schema    # hosts without a shell: applies the same one file
```

Dev has no shell, so run this in cPanel > **phpMyAdmin** >
`litgrpco_smart_church_db` > **SQL** instead - exactly what the migration creates.
Run it once: a second attempt answers *Duplicate column name 'remember_token'*,
which only means the column is already there.

```sql
ALTER TABLE `tiu_member`
  ADD COLUMN `remember_token` VARCHAR(100) NULL DEFAULT NULL AFTER `password`;
```

The reset page then redirects to `/home` signed in, and phpMyAdmin shows the
60-character `remember_token` on that member. `app:sync-schema` also records the
migration, so a later `php artisan migrate` does not try to add the column again.

## Signed in, but without the left menu

The reset signs the member in (`ResetsPasswords` calls `Auth::login()`), and that
alone is not what the login form does. The sidebar (`partials/nav.blade.php`)
renders from session keys the login form writes - `member_role` decides which
admin blocks exist, `department_name` (the JSON string the column holds) picks
the department menus, `lead_of_dept` / `lead_of_church_type` / `userIsHODOrLead`
open the HOD views - and `layouts/app.blade.php` greets the member with
`first_name`. A reset that only called `Auth::login()` therefore landed on
`/home` as "Welcome Guest" with the plain member menu; the admin menu came back
only after signing out and in again.

`App\Services\MemberSessionService` holds that session setup now, and both doors
use it: `CustomAuthController::login()` and
`ResetPasswordController::resetPassword()`. JSON columns are copied with
`getRawOriginal()`, so the session keeps the string the login form has always
stored rather than the array `TiuMember`'s accessors return.

The same service answers whether the account may sign in at all (`canSignIn()`:
`status` is not 2 and `email_verified` is 1). The reset asks it before filling
the session and, for an account the login form would turn away, signs the member
straight back out and redirects to the login page with the login form's own
message - a reset only proves the mailbox and the new password.

`ResetsPasswords`' own `resetPassword()` / `sendResetResponse()` are kept under
aliases (`performPasswordReset`, `sendSuccessfulResetResponse`): they are trait
methods, so `parent::...` would hit `Controller::__call` and throw
`BadMethodCallException`.

## Publishing this to production

Everything described above is already committed on `main`, and none of it has to
be re-done: `2902a1d` (the controller that really mails and the modal that cannot
print `undefined`), `920ab40` (the link opens the guest reset page), `191d64c` +
`4d3ed34` (`remember_token`) and `b479065` (the menu session). `2902a1d` is an
ancestor of `b479065` (`git merge-base --is-ancestor 2902a1d b479065` exits 0),
so approving the production run for `b479065` ships all five.

The live copy is the one step a push does **not** do by itself: the production
workflow stops at the `production` environment and waits for a reviewer
(`docs/CPANEL-DEPLOYMENT.md` §3e). Dev has no such gate, which is why the fix was
live on `dev.lit-grp.com` while prod kept serving the code from before `2902a1d`.
That old modal explains both production symptoms exactly:

- **"undefined"** - its error branch built the message as
  `'<div ...>' + xhr.responseJSON?.email?.[0] || 'Email not found'`, and `+`
  binds before `||`, so *any* reply without that field printed the literal word
  `undefined` (a 404/500 HTML page, or a 422 body);
- **"Password reset link sent" and no mail** - its success branch printed that
  text for every 2xx, and the old controller reported success as soon as the
  token row was written. A `MAIL_MAILER=log`, an unreachable `MAIL_HOST`, or a
  wrong mailbox password therefore looked like a delivery. The new controller
  asks the mailer first: it refuses `log` on a production host and rethrows the
  transport error, which the modal shows and the log records.

Publish it in this order:

1. **Approve the run.** GitHub -> *Actions* -> *Deploy to cPanel (production)* ->
   the run built from `b479065` -> *Review deployments* -> *Approve and deploy*.
   Cancel any older run still waiting (a `920ab40` or docs-only run): approving it
   would publish an older tree over the new one. If no run is waiting any more,
   use *Run workflow* on `main` - a `workflow_dispatch` run builds the current
   `HEAD`.
2. **Run the post-deploy step on the server.** The pipeline only copies files; it
   never executes PHP there.

   ```bash
   cd ~/public_html/smartchurch.tcnikorodu.org
   php artisan app:post-deploy
   ```

   It rebuilds the config and Blade caches (a stale compiled login view is
   another way the old `undefined` stays on screen) and runs `app:sync-schema`,
   which creates `password_resets` and adds `tiu_member.remember_token`. Without
   it the reset still 500s, because the UPDATE names `remember_token`.
3. **Point production at the server's own mailer.** Code cannot deliver mail on
   its own, and the deploy never touches the server's `.env` (every `.env*` is
   excluded from the upload), so whatever is there now is what prod has been
   using. On 2026-10-19 the log said:

   ```
   Connection could not be established with host mail.tcnikorodu.org :
   stream_socket_client(): Unable to connect to tcp://mail.tcnikorodu.org:587
   (Connection timed out)
   ```

   That is a DNS fact, not a password problem: `mail.tcnikorodu.org` is a
   **CNAME to the apex** `tcnikorodu.org`, the apex is `216.198.79.1` (the website
   host), and this application runs on `emerald3.doveserver.com`
   (`147.124.220.188`). Port 587 was never open there, so the timeout happens
   before TLS or `AUTH` - the mailbox password was never even tried.

   **Preferred: hand the mail to the local Exim** - no host, port or password to
   get wrong:

   ```dotenv
   MAIL_MAILER=sendmail
   MAIL_SENDMAIL_PATH="/usr/sbin/sendmail -t -i"
   MAIL_FROM_ADDRESS=no-reply@tcnikorodu.org
   MAIL_FROM_NAME="TCN Ikorodu"
   ```

   Deliverability is already set up for this: the domain's SPF is
   `v=spf1 ip4:147.124.220.188 +a +mx ... ~all`, so the server that will send is
   authorised, and `default._domainkey.tcnikorodu.org` exists, so cPanel signs the
   message.

   `MAIL_SENDMAIL_PATH` must be the binary PHP already uses - `php -i | grep
   sendmail_path` prints it (`/sbin/sendmail -t -i` on emerald3, while this
   config's fallback is `/usr/sbin/sendmail -t -i`). Before blaming Laravel,
   prove Exim accepts local submission on its own; `bool(true)` means it queued:

   ```bash
   php -r 'var_dump(mail("you@example.com", "Prod Exim test", "Hello"));'
   ```

   **Alternative: SMTP to loopback**, if the mailbox credentials must be used:

   ```dotenv
   MAIL_MAILER=smtp
   MAIL_HOST=127.0.0.1
   MAIL_PORT=25
   MAIL_ENCRYPTION=null
   MAIL_USERNAME=no-reply@tcnikorodu.org
   MAIL_PASSWORD=<that mailbox's real password>
   MAIL_FROM_ADDRESS=no-reply@tcnikorodu.org
   ```

   `MAIL_ENCRYPTION=null` must stay bare - quoting it sends the string `"null"`.
   If Exim refuses `AUTH` on 25, drop `MAIL_USERNAME`/`MAIL_PASSWORD`; loopback
   submission is trusted.

   The credentials that work on dev (`no-reply@lit-grp.com` on
   `mail.lit-grp.com`) do not work here - another mailbox on another server - so
   copy the shape of the block, not the values. `.env.example` ships Mailhog
   values (`MAIL_HOST=mailhog`, port `1025`), which resolve nowhere on a real host
   - one more way a send fails while the old code called it sent. Then re-run
   `php artisan app:post-deploy` (or `php artisan config:clear && php artisan
   config:cache`): a cached config keeps the old values.

   Later, if staff need to *receive* at this domain from phones and Outlook, fix
   DNS in cPanel -> *Zone Editor*: `mail` should be an `A` record to
   `147.124.220.188` (not a CNAME to the website host) and the MX an
   `mail.tcnikorodu.org`; only then does `MAIL_HOST=mail.tcnikorodu.org:587` make
   sense. Sending does not need it.
4. **Prove it.** Submit the form on prod. The answer must now be either the real
   delivery or *"We could not send the reset e-mail just now..."* with the reason
   - never `undefined`. If it says sent and the mail still does not arrive, the
   app handed the message over: check the recipient's *Spam/Junk*, cPanel ->
   *Track Delivery* for `no-reply@tcnikorodu.org`, and that the mailbox exists.
   If it says it could not send, the reason is one log line - it names the
   mailbox, host, port, encryption and From that were actually tried:

   ```bash
   grep -i "Password reset link could not be sent" storage/logs/laravel.log | tail -n 2
   ```

## Tests

`tests/Feature/PasswordResetFlowTest.php` pins the flow: the reset route the
notification builds, the modal pointing at an existing route, `TiuMember` having
`Notifiable`/`notify()`, the notification being dispatched to a member, the
`errors.email[0]` shape the modal reads, a `log` mailer being refused *and*
logged as an error on production, `password_resets` being in
`app:sync-schema`'s required tables, and the reset submission itself: the
`remember_token` column existing, the UPDATE a reset runs being accepted, and the
whole `POST /password/reset` storing the new password. It also pins what that
reset leaves behind: the session keys the left menu and the greeting are
rendered from (`member_role`, `first_name`, `userIsHODOrLead`, ...), the nav
partial rendered from them (the "Admin Console" block must be there), and a
disabled account being signed out again and sent to the login page instead of
being handed a session. Those writes happen inside a transaction that is always
rolled back, so the suite changes nothing - no token rows, no passwords, no
remember tokens are left behind.

`tests/Feature/SyncSchemaTest.php` covers `app:sync-schema` itself, including
`tiu_member.remember_token` as a required column and its migration being recorded.
