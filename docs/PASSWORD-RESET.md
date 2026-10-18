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

Every failure is logged with the mailer, host and From address:

```
Password reset link could not be sent to member@example.com: Connection could not be established with host "mail.example.com:587"
```

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

## Tests

`tests/Feature/PasswordResetFlowTest.php` pins the flow: the reset route the
notification builds, the modal pointing at an existing route, `TiuMember` having
`Notifiable`/`notify()`, the notification being dispatched to a member, the
`errors.email[0]` shape the modal reads, a `log` mailer being refused *and*
logged as an error on production, and `password_resets` being in
`app:sync-schema`'s required tables. The tests are read-only - no token rows are
written by the suite.
