# API access tokens

How the mobile app - and from now on every third-party front end - authenticates
against `/api/*`, and what to do when a token has to be killed.

**Status: live on cPanel.** This is production-ready on the current shared host exactly as
it stands - no server configuration, no database change, no pipeline change. The VPS and
multi-frontend items at the end of this document are inactive and out of scope for this
deploy.

## The hole this closes

`App\Http\Middleware\VerifyApiToken` used to read the incoming token as
`base64_decode($token)`, take everything before the first `|` as a member id, and hand it
to `Auth::loginUsingId()`. There was no signature, no expiry and no revocation, so
`base64_encode('123|')` was a complete credential for every one of the ~35 protected
routes: member records, first-timer records, airtime and attendance data. `config/cors.php`
(`allowed_origins => ['*']`) let any website in the world drive that API from a visitor's
browser.

The token is now a signed JWT, verified before the member id it carries is trusted for
anything.

## What a token is

```
base64url({"alg":"HS256","typ":"JWT"})
  . base64url({"iss":…,"sub":…,"iat":…,"exp":…,"jti":…,"ver":1})
  . base64url(HMAC-SHA256(first two segments, api.token.key))
```

| claim | meaning |
| --- | --- |
| `iss` | issuer URL (`API_TOKEN_ISSUER`, defaults to `APP_URL`) |
| `sub` | `tiu_member_id` - the only identity the token carries |
| `iat` | issued-at, Unix seconds |
| `exp` | expiry, Unix seconds. A token without one is rejected |
| `jti` | unique id, so two logins never mint the same token |
| `ver` | claim-set version, `1` today |

The verifier enforces, in this order: signature → `alg` is HS256 → `sub` present → `iat`
not in the future (300 s leeway) → `exp` in the future → the member still exists and is not
disabled. Only HS256 is accepted, so the header can never talk the verifier into `alg: none`.

**The token is signed, not encrypted.** Anyone holding it can read its claims, so never put
a password, a phone number or anything else sensitive in one.

Clients treat it as opaque:

```http
POST /api/login          ->  {"success":true,"token":"eyJ…","expires_at":…}
GET  /api/pending-tasks  ->  Authorization: Bearer eyJ…
```

## Configuration

`config/api.php`, all environment-driven:

| env | default | purpose |
| --- | --- | --- |
| `API_TOKEN_KEY` | `APP_KEY` | HMAC secret. Optional - see below |
| `API_TOKEN_TTL_DAYS` | `90` | how long a token stays valid |
| `API_TOKEN_ISSUER` | `APP_URL` | recorded as the `iss` claim |
| `API_TOKEN_CLOCK_SKEW` | `300` | leeway in seconds on `iat` |

**Nothing has to be added to the server's `.env` for this to work.** With `API_TOKEN_KEY`
unset the tokens are signed with `APP_KEY`, which is already a long random secret, and the
verifier accepts Laravel's `base64:` form. Both paths are covered by
`Tests\Unit\ApiTokenServiceTest`, and the whole suite runs with `API_TOKEN_KEY` unset - which
is exactly the state the server is in.

Give it its own value only when you want to rotate `APP_KEY` (which re-encrypts sessions and
cookies) without signing every phone out at the same time:

```bash
php -r "echo base64_encode(random_bytes(48)).PHP_EOL;"
```

> **Warning - do not add `API_TOKEN_KEY` months after the deploy.** Setting it, like changing
> it, changes the signing key: every outstanding token dies and the whole church signs in
> again. If you want a dedicated key, set it in the *same* deploy as this code so users sign
> in once; otherwise leave it unset and `APP_KEY` keeps doing the job.

There is **no migration and no new Composer package**, so the SFTP-only cPanel pipeline
deploys this unchanged.

## Which routes are public

| Route | Public? | Why |
| --- | --- | --- |
| `POST /api/login` | yes | issues the token |
| `POST /api/logout` | yes | no-op for a stateless token; lets a device with a broken token clear its own session |
| `POST /api/register` | yes | answers `403` to every caller - registration is admin-only by design |
| everything else under `/api/*` | **no** | `verify.token` guard |

`GET /api/user`, `POST /api/update-profile`, `POST /api/check-profile`,
`POST /api/check-policy` and `POST /api/sign-policy` used to sit in the anonymous group
and took the member id from the request, so an unauthenticated caller could read - or
rewrite - any member's profile by passing `user_id`. They are now behind the token, which
also fixes them: the guard merges `sub` over whatever the client sends, so `?user_id=`
can no longer choose whose record is returned or changed.

`GET /api/test-db` was removed: it returned the member count and, on failure, the raw
database exception message to anyone who asked.

## Adding or fixing a route under `/api/*`

Two traps cost real time here, and both are enforced by tests in
`Tests\Feature\ApiRouteProtectionTest`:

1. **A route outside the `verify.token` group is public.** The guard is applied by the group
   in `routes/api.php`, not by the controller, so a route added outside it is open to the
   internet even though every sibling is not.
2. **Never put `auth` on an `/api` controller method.** `App\Http\Middleware\Authenticate`
   implements `Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests`, which sits in the
   framework's `$middlewarePriority`, so the pipeline hoists it *above* `verify.token`
   whatever order `routes/api.php` lists them in. The session guard then answers first and a
   caller holding a valid token gets `{"message":"Unauthenticated."}` - which looks like a
   token problem and is not one. `PendingTasksController` keeps `auth` for its Blade board
   and excludes the API methods with `->except([...])`.

A route can be reachable and still broken. `GET /api/pending-tasks`,
`GET /api/pending-tasks/touchpoints` and `GET /api/first-timers/{id}` each pointed at a
method that had never been written: the first two were invisible because trap 2 turned their
500 into a 401, the third only ever answered with a `BadMethodCallException`. All three
exist now, and `test_every_api_route_points_at_a_method_that_exists` fails if a new route
repeats it.

### What the mobile app reads

| Endpoint | Response |
| --- | --- |
| `GET /api/pending-tasks[?touchpoint=…]` | `success`, `data[]`, `total`, `campus_id`. Each row carries `tracking_id`, `ft_first_name`/`ft_last_name`/`ft_phone`, `attendant_type`, `week_label`, `next_task`, `latest_pending_log` (`{timestamp,guide_id,outcome,comment}`) and the `guide_*` columns |
| `GET /api/pending-tasks/touchpoints` | `success`, `data[]`, each row an object - the picker renders `tp.subgroup` |
| `GET /api/first-timers/{id}` | `success`, `data` (one record; `404` for an id outside the caller's campus) |

## Rolling it out on cPanel

1. Deploy as usual. No database change, no `app:sync-schema` run, no pipeline change, and no
   `.env` edit - the signing key falls back to `APP_KEY` (see Configuration above). If you
   would rather give the tokens their own key, add `API_TOKEN_KEY=` to the server's `.env`
   (cPanel → File Manager) in the *same* deploy; the pipeline never uploads `.env`, so it
   survives every future deploy.
2. Expect every signed-in device to sign in once more - see below.
3. Verify:

```bash
# base64('123|') - exactly what the old guard accepted as a credential
curl -s -o /dev/null -w '%{http_code}\n' https://<host>/api/pending-tasks \
  -H 'Authorization: Bearer MTIzfA=='                      # must print 401

# a real login returns the token AND its expiry
curl -s -X POST https://<host>/api/login -H 'Content-Type: application/json' \
  -d '{"email":"you@example.com","password":"…"}'
```

## Behaviour changes to expect

- **Every token issued before this change is invalid.** They were never signed, so they
  cannot verify. Each signed-in user signs in once more; no data is touched.
- Failures return HTTP 401 with `{"success":false,"message":…,"code":…}`. `code` is new and
  additive, so clients that read only `success`/`message` keep working. Values:
  `token_missing`, `token_invalid`, `token_expired`, `user_not_found`, `account_disabled`.
- A client can no longer name a member: any `user_id` sent in the query string or body is
  overwritten by the token's `sub` before a controller sees it.
- Disabling a member (`status = 2`) ends API access immediately, instead of at token expiry.
- Tokens now expire. A device idle for longer than `API_TOKEN_TTL_DAYS` receives
  `token_expired` and must sign in again - raise the value if support asks for it, never to
  dodge a single re-login.

## Runbook

| Situation | Action |
| --- | --- |
| "Log every device out, now" | change whichever key is signing, in the server's `.env`: `API_TOKEN_KEY` if you set one, otherwise `APP_KEY` (which also ends web sessions). Every outstanding token dies at once; no redeploy needed |
| One device is lost | there is no per-device token yet: rotate the key (logs everyone out) or let it expire |
| Key suspected compromised | rotate immediately, then review `/api/*` access logs for the exposure window |
| `500` mentioning "No API token signing key" | `API_TOKEN_KEY` (or `APP_KEY`) is empty on the server - or the server carries a stale `bootstrap/cache/config.php` from an old `config:cache`, which hides `config/api.php` entirely. Delete that one file in File Manager |

## Not now - the VPS / multi-frontend phase

**Inactive.** Nothing below is implemented, required or blocking on the cPanel deployment that
runs today; it is written down so the reasoning survives, not as work queued for this deploy.

- **No refresh token, no per-device revocation** - a token is all-or-nothing until it
  expires. The next step is either Sanctum personal access tokens (needs a
  `personal_access_tokens` migration applied through `app:sync-schema`, plus a string
  `tokenable_id` to match the `tiu_member_id` primary key) or JWKS verification of tokens
  minted by whichever identity service ADR-0001 settles on. `ApiTokenService::verify()` is
  the single seam either one plugs into.
- **`config/cors.php` still allows every origin.** Once the front-end domains are known, set
  `allowed_origins` to that list.
- **The API is unversioned** (`/api/...`, not `/api/v1/...`), so its response shapes cannot
  change without breaking an app already installed on someone's phone.

### Deliberately absent, so it does not have to be remembered

The token change adds **no** new Composer package, **no** migration or table (`app:sync-schema`
has nothing new to apply), **no** queue worker, **no** background job, **no** scheduled entry,
**no** Redis/Memcached/S3 dependency (`CACHE_DRIVER=file`, `SESSION_DRIVER=file`,
`QUEUE_CONNECTION=sync`, `FILESYSTEM_DRIVER=local` - all unchanged), **no** hostname, **no**
`/api/v1` rewrite and **no** pipeline change. The deploy workflow and the `.env` on the server
are untouched.
