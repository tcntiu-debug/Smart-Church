# Account activation (the verification e-mail)

A member account is created with `tiu_member.email_verified = 0`, and the login
form refuses such an account (`MemberSessionService::canSignIn()`: `status` is
not 2 **and** `email_verified` is 1). The activation mail is therefore the
thing that makes a new account usable — not a courtesy.

## The bug this fixes

Until October 2026 nothing sent that mail:

| Piece | State before |
|---|---|
| `MemberController::publicStore()` | inserted the row with `email_verified = 0` and answered *"check your email to activate your account"* — no `Mail::`, `notify()` or queue job anywhere on the path |
| `app/Notifications/` | did not exist; the only mailable was `BirthdayReminderMail` |
| `App\Models\TiuMember` | did not implement `MustVerifyEmail` |
| `EventServiceProvider` (`Registered` → `SendEmailVerificationNotification`) | dead wiring: registration uses `DB::table()->insert()` (no model event) |
| `VerificationController`, `resources/views/auth/verify.blade.php` | Laravel scaffolding with no routes (`php artisan route:list --name=verification` was empty) |
| anything setting `email_verified = 1` | only the admin registration form, at insert time — no code ever flipped 0 → 1 afterwards |

The legacy PHP app mailed an activation link; the Laravel tree dropped that code
in the initial commit (`eeb3fca`). Only the password-reset mail had been ported
(and fixed in `2902a1d`), which is why *Forgot Password* arrived and the
verification link never did. Publicly registered members could only get in via
*Forgot Password*, or after somebody edited the column by hand.

## The flow now

1. `MemberController::publicStore()` inserts the row (`email_verified = 0`), then
   calls `MemberVerificationService::send()`.
2. The service builds a **temporary signed URL** (7 days,
   `MemberVerificationService::LINK_DAYS`) for route `member.verify`, with a
   `hash` of the address (`sha1(email)`) beside the HMAC — the same idea as
   `MustVerifyEmail`, and it means a later address change breaks the link.
3. `MemberVerificationMail` (`resources/views/emails/member-verification.blade.php`
   + `-text`) carries that link.
4. The member opens `GET /member/verify/{id}/{hash}`. The controller checks the
   signature, the member, the hash and the current flag, then sets
   `email_verified = 1`.
5. The link does **not** stop at the login form: public registration stores a
   random password nobody is told, so the member would have nothing to type.
   The activation therefore continues on `GET /member/set-password`, where they
   choose a password (twice, at least 8 characters — the reset form's rule) and
   are handed to the login page with *"Password saved. Sign in with …"*.
   That page opens only for the member the link just verified **in this
   browser** (session id + `email_verified = 1`), is dropped after saving, and
   a second click on the mailed link goes straight to the login page — the link
   never becomes a way to change an existing password.
6. A stale, edited or already-used link never shows an exception page: it
   redirects to `/` with an explanation, and **Resend activation email?** on the
   login page opens `GET /member/verification/notice` (`auth.verify`), which
   posts to `POST /member/verification/resend` (`throttle:6,1`).

The first-timer **Integrated** action (`AdminFirstTimerController`) creates the
same kind of account, so it mails the same link after `DB::commit()`; the flash
message says whether the mail went out.

### Routes (all guests — the member cannot sign in yet)

| Route | Name | What it does |
|---|---|---|
| `GET /member/verify/{id}/{hash}` | `member.verify` | the signed link from the mail; sets the flag, then continues to the password page |
| `GET /member/set-password` | `member.password.create` | choose a password (only right after activation, same browser) |
| `POST /member/set-password` | `member.password.store` | saves it hashed, then redirects to the login page |
| `GET /member/verification/notice` | `member.verification.notice` | resend page |
| `POST /member/verification/resend` | `member.verification.resend` | sends a fresh link, `throttle:6,1` |

## Honest answers

`MemberVerificationService::send()` never throws — a mail failure must not turn a
successful registration into an error — and it returns whether the message left
the server:

- registration answered *"check your email"* only when the mail was handed to
  the transport; otherwise it says the activation e-mail could not be sent and
  points at the resend page;
- the resend page answers `errors.email` for an unknown address, for an
  already-verified address (no mail) and when the transport fails, so nobody is
  told to wait for a link that was never built;
- `MAIL_MAILER=log` (or `array`) is refused on production, exactly like the
  password reset: the link would be written to `storage/logs/laravel.log`, not
  delivered. In `local` it only logs a warning so the link can be read there.

Every failure is logged (member id, mailer, host, port, username, `from`) —
the mailbox, not the password, is the usual cause of *Failed to authenticate on
SMTP server*. Production `.env` files run `LOG_LEVEL=error`, so these are
written at **error** level.

## Server checklist

1. `MAIL_MAILER` is a real transport (`smtp`) with a mailbox that exists —
   the same settings *Forgot Password* needs.
2. `APP_URL` matches the host members use: the signed link is built from the
   incoming request, and a proxy that changes scheme/host can make a valid link
   look tampered with.
3. The account being tested has an address in `tiu_member.email`.

## Tests

`tests/Feature/MemberVerificationFlowTest.php` (read-only apart from rolled-back
transactions):

- the three routes exist and the notice page renders as a guest page;
- the mail carries the signed link, opening it sets `email_verified = 1` and
  lands on the password form;
- choosing a password stores it hashed and that pair signs the member in;
- a mismatched password is refused and writes nothing;
- the password form does not open without the activation link in that browser;
- a second click on the link goes to the login page, not to a password change;
- a changed query string **and** a valid signature over the wrong hash both
  leave the account unverified;
- `POST /register` sends the mailable and leaves the row unverified;
- the resend page mails an unverified member, refuses an unknown address and an
  already-verified one;
- the service refuses a member without an address and one already verified.

```bash
php vendor/phpunit/phpunit/phpunit tests/Feature/MemberVerificationFlowTest.php --no-coverage
```
