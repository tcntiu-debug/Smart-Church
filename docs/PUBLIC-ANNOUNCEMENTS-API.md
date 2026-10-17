# Public announcements API — hand-off for the website developer

The church website (the Vercel build) needs to show the same notices the app's
`/notice` page shows. That page sits behind a login, so it cannot be scraped.
This document is the contract for the supported way in: one anonymous, read-only
endpoint.

Everything below was checked against the **live dev deployment** on 17 Oct 2026
(the sample responses are real ones).

---

## 1. Environments

| Environment | Base URL |
|-------------|----------|
| Production | `https://smartchurch.tcnikorodu.org` |
| Dev / staging | `https://dev.lit-grp.com` |
| Local (this machine) | `http://localhost:8000` (after `php artisan serve`) |

Use **dev** until the page is signed off, then switch one constant in the site's
environment to production. Nothing else changes — both run the same code.

> **Production status check before you hand this over.** On 17 Oct 2026 the dev
> host answered this URL with `200` and production still answered with the host's
> own `404` page, because production had not yet taken the deploy that carries the
> endpoint (and the `.htaccess` fix it needs). Confirm before telling the web
> developer to point at production:
>
> ```bash
> curl -s -o /dev/null -w "%{http_code}\n" \
>   https://smartchurch.tcnikorodu.org/api/public/announcements?limit=1
> ```
>
> `200` = ready. A `404` that is **HTML** (not JSON) = not deployed yet.


## 2. Authentication, CORS, rate limit

| | |
|---|---|
| **Auth** | None. No token, no API key, no cookie. The endpoint is anonymous **by design**. |
| **CORS** | `Access-Control-Allow-Origin: *` — the browser may call it from any origin, including the Vercel domain. Preflight `OPTIONS` answers `204`. |
| **Rate limit** | 60 requests per minute per IP. Exceeding it answers `429` with `Retry-After` (seconds) and `X-RateLimit-Limit` / `X-RateLimit-Remaining`. |
| **Cache** | `Cache-Control: public, max-age=60` — the browser and any CDN may cache a response for 60 seconds. A newly published notice can take up to a minute to appear. |

It returns **published announcements only** and never reads a member table, so no
phone number or e-mail address can leak from it.

---

## 3. `GET /api/public/announcements`

### Query parameters (all optional)

| Name | Type | Default | Rules | Notes |
|------|------|---------|-------|-------|
| `limit` | integer | `10` | `1` – `50` | How many notices to return. `0`, `51`, `500` or a non-number → `422`. There is **no** offset/page parameter; ask for the newest N. |
| `campus_id` | integer | – | `≥ 1` | Ignored while the church runs a single campus (`SINGLE_CAMPUS=true`, the current setting), because no row carries a campus id. Safe to send anyway. |

Ordering: **newest first** (`created_at` descending, `id` descending as the
tie-breaker).

### Success — `200 application/json`

```http
GET /api/public/announcements?limit=1 HTTP/1.1
Host: dev.lit-grp.com
```

```http
HTTP/1.1 200 OK
Content-Type: application/json
Cache-Control: max-age=60, public
Access-Control-Allow-Origin: *
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 57

{
  "success": true,
  "count": 1,
  "data": [
    {
      "id": 4,
      "title": "test",
      "content": "Test test",
      "excerpt": "Test test",
      "posted_at": "2026-10-17T19:02:57+00:00",
      "posted_at_human": "Oct 17, 2026 8:02 PM",
      "campus_id": null,
      "campus": null
    }
  ],
  "meta": {
    "limit": 1,
    "generated_at": "2026-10-17T19:09:14+00:00"
  }
}
```

### Field reference

| Field | Type | Meaning |
|-------|------|---------|
| `success` | boolean | Always `true` on `200`. |
| `count` | integer | `data.length`. Never more than `limit`. |
| `data` | array | Newest first. Empty array when there is nothing published. |
| `data[].id` | integer | Stable notice id. Use it as the React/`key` and as the link anchor. |
| `data[].title` | string | Plain text, 1–255 characters. |
| `data[].content` | string | **Plain text, exactly as the author typed it** — may contain newlines; it is **not** HTML and **not** escaped. Escape it before inserting into HTML, or render it as text so React/Vue escape it for you. |
| `data[].excerpt` | string | Same text with runs of whitespace collapsed and cut to 160 characters. Convenience only — you can compute your own from `content`. |
| `data[].posted_at` | string (ISO-8601, UTC) | Publication time, e.g. `2026-10-17T19:02:57+00:00`. Feed to `new Date(...)`. `null` only for a row with no timestamp. |
| `data[].posted_at_human` | string | Pre-formatted in church local time (Africa/Lagos, WAT): `Oct 17, 2026 8:02 PM`. For display when you do not want to format dates yourself. |
| `data[].campus_id` | integer \| null | `null` in single-campus mode (every notice today). |
| `data[].campus` | string \| null | Campus name (`campus.cname`) when `campus_id` is set, otherwise `null`. |
| `meta.limit` | integer | The limit that was applied (the default, if you sent none). |
| `meta.generated_at` | string (ISO-8601, UTC) | When the server built the response — useful as an "as of" line and for debugging cache behaviour. |

The eight keys inside each notice are fixed; the test suite fails if one of them
disappears. Extra keys may be added in the future, so read by name, never by
position.

### Errors

| Status | When | Body |
|--------|------|------|
| `422` | `limit` or `campus_id` is out of range or not a number | `{"success":false,"message":"The given data was invalid.","errors":{"limit":["The limit must not be greater than 50."]}}` |
| `429` | More than 60 requests/minute from one IP | Laravel's throttle response, plus `Retry-After: <seconds>` |
| `404` (HTML) | The host's own 404 page, not the API's JSON | The request never reached the application — see *Troubleshooting* below |

A `422` is always JSON, whatever `Accept` header the client sends (that was a
bug once: a `fetch` without `Accept: application/json` used to get a `302`).

---

## 4. Client code

### TypeScript shape

```ts
export type PublicAnnouncement = {
  id: number;
  title: string;
  content: string;        // plain text, escape before rendering as HTML
  excerpt: string;
  posted_at: string | null;       // ISO-8601 UTC
  posted_at_human: string | null; // "Oct 17, 2026 8:02 PM" (Africa/Lagos)
  campus_id: number | null;
  campus: string | null;
};

export type PublicAnnouncementResponse = {
  success: boolean;
  count: number;
  data: PublicAnnouncement[];
  meta: { limit: number; generated_at: string };
};
```

### Plain browser fetch

```js
const API = "https://dev.lit-grp.com"; // production: https://smartchurch.tcnikorodu.org

async function loadNotices(limit = 5) {
  const res = await fetch(`${API}/api/public/announcements?limit=${limit}`, {
    headers: { Accept: "application/json" },
  });

  if (!res.ok) throw new Error(`Announcements: HTTP ${res.status}`);

  const payload = await res.json();
  return payload.data; // newest first
}
```

### Next.js (Vercel) — server-side, cached for a minute

```tsx
// app/notices/page.tsx  (App Router)
const API = process.env.ANNOUNCEMENTS_API ?? "https://smartchurch.tcnikorodu.org";

async function getNotices(): Promise<PublicAnnouncement[]> {
  const res = await fetch(`${API}/api/public/announcements?limit=10`, {
    headers: { Accept: "application/json" },
    next: { revalidate: 60 }, // matches the endpoint's Cache-Control
  });

  if (!res.ok) return []; // never let a notice outage 500 the page

  return (await res.json()).data;
}

export default async function NoticesPage() {
  const notices = await getNotices();

  if (notices.length === 0) return <p>No notices yet.</p>;

  return (
    <ul>
      {notices.map((n) => (
        <li key={n.id}>
          <h2>{n.title}</h2>
          <p>{n.excerpt}</p>
          <time dateTime={n.posted_at ?? undefined}>{n.posted_at_human}</time>
        </li>
      ))}
    </ul>
  );
}
```

`{n.title}` / `{n.excerpt}` are escaped by JSX automatically. **Do not** pass
`content` to `dangerouslySetInnerHTML` — it is plain text, not markup.

### Rules of thumb

* Render `title` / `content` / `excerpt` as **text**, never as HTML.
* Empty `data` is not an error — show "No notices yet", do not retry in a loop.
* Treat a non-`200` as "notices unavailable" and keep the rest of the page alive.
* Cache for ~60 s (or set `revalidate: 60`); do not poll faster than the rate
  limit allows.

---

## 5. Publishing a notice (for the app developer, not the public site)

The website only **reads**. Notices are written from the dashboard
(`/admin-announcements`) or from the app with a Bearer token:

```http
POST /api/login
Content-Type: application/json

{ "email": "worker@example.com", "password": "…" }
```

```json
{
  "success": true,
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9…",
  "expires_at": 1790000000,
  "user": { "id": 12, "name": "Ada Obi", "campus_id": 1, … }
}
```

```http
POST /api/announcements
Authorization: Bearer <token>
Content-Type: application/json
Accept: application/json

{ "title": "Generator maintenance", "content": "Saturday 9am, main hall." }
```

```json
{ "success": true, "message": "Announcement posted successfully!", "data": { "id": 42 } }
```

* The token is an HS256 JWT, valid **90 days**; send it as
  `Authorization: Bearer <token>`.
* Publishing rings every member's in-app bell once (same as the dashboard form).
  Editing a notice does not ring it again.
* `GET /api/announcements` (token) lists everything for the caller's campus;
  `DELETE /api/announcements/{id}` (token) deletes.
* **The public endpoint never needs any of this.**

---

## 6. Endpoints the website must not use

| Endpoint | Why not |
|----------|---------|
| `GET /notice` | HTML page behind `auth` — a server-side fetch gets the login redirect. |
| `GET /api/announcements` | Requires a Bearer token; `401` without one. Holds the full table, not the public shape. |
| `POST /api/announcements`, `DELETE /api/announcements/{id}` | Write operations. Public site must stay read-only. |

---

## 7. How to test it yourself (5 minutes)

### 7.1 The fastest check — a browser tab

Open this (dev):

```
https://dev.lit-grp.com/api/public/announcements?limit=5
```

You should see JSON starting with `{"success":true,...}`. If you get a **404 page
with the host's own styling** ("Not Found — LiteSpeed"), the request never
reached Laravel — see *Troubleshooting*.

### 7.2 Publish something, then read it back

1. Sign in to the app → **Announcements** (`/admin-announcements`) → post a
   notice titled `API test` with the body `If you can read this, the feed works.`
2. Reload the browser URL above. Within 60 seconds (the cache window) the new
   notice is the **first** item, with the `id` the dashboard shows and `excerpt`
   cut to 160 characters.

If it does not appear, the notice was not saved — check `/notice` in the app
first; the API only reflects what is in the `announcements` table.

### 7.3 `curl` (bash / macOS / Linux / Git Bash / Windows 10+)

```bash
# happy path
curl -s "https://dev.lit-grp.com/api/public/announcements?limit=2"

# headers only: expect 200, Content-Type: application/json,
# Cache-Control: max-age=60, public, Access-Control-Allow-Origin: *
curl -sI "https://dev.lit-grp.com/api/public/announcements?limit=2"

# the documented 422, with the field named
curl -s "https://dev.lit-grp.com/api/public/announcements?limit=500"

# CORS preflight: expect 204 and Access-Control-Allow-Origin: *
curl -s -i -X OPTIONS "https://dev.lit-grp.com/api/public/announcements" \
  -H "Origin: https://church.example.org" \
  -H "Access-Control-Request-Method: GET"
```

### 7.4 PowerShell (this machine)

```powershell
$u = "https://dev.lit-grp.com/api/public/announcements?limit=2"
$r = Invoke-RestMethod -Uri $u -Headers @{ Accept = "application/json" }
"count: $($r.count)  limit: $($r.meta.limit)"
$r.data | Format-Table id, title, posted_at_human, excerpt -AutoSize -Wrap
```

The `422` case (PowerShell throws on 4xx, so catch it):

```powershell
try {
    Invoke-RestMethod "https://dev.lit-grp.com/api/public/announcements?limit=500"
} catch {
    "HTTP " + [int]$_.Exception.Response.StatusCode
    $_.ErrorDetails.Message      # {"success":false,...,"errors":{"limit":[...]}}
}
```

### 7.5 Postman / Insomnia / Thunder Client

1. Method `GET`, URL `https://dev.lit-grp.com/api/public/announcements`.
2. **Params**: `limit` = `1`; send. Change it to `500`; send again.
3. **Headers**: nothing needed. Optionally `Accept: application/json`.
4. Save two requests: *feed ok* (expect `200`) and *feed bad limit* (expect `422`
   with `errors.limit`). Add a third, `OPTIONS` to the same URL with `Origin`
   and `Access-Control-Request-Method: GET`, to prove CORS (expect `204`).

### 7.6 Locally, without the internet

```powershell
php artisan serve                 # http://localhost:8000
# then, in another terminal:
curl.exe -s "http://localhost:8000/api/public/announcements?limit=3"
```

Or run the automated suite that pins this contract:

```powershell
php artisan test --filter=PublicAnnouncementApiTest
```

It checks: a guest reads the newest notices, the payload keys, "no member data",
`limit` validation (`500`, `0`, and with no `Accept` header → `422` not `302`),
the wildcard CORS header, and the document-root `.htaccess` rewrite this URL
depends on.

### 7.7 What a good result looks like

| Check | Expected |
|-------|----------|
| `GET …?limit=1` | `200`, JSON, `count` ≤ 1, one object with the eight keys |
| `GET …` with no `limit` | `200`, `meta.limit` = `10` |
| `GET …?limit=500` | `422`, body lists `limit` |
| `GET …?limit=0` / `?limit=abc` | `422` |
| Response headers | `Cache-Control: max-age=60, public`, `Access-Control-Allow-Origin: *`, `X-RateLimit-Limit: 60` |
| `OPTIONS` with `Origin` | `204`, `Access-Control-Allow-Origin: *` |

---

## 8. Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| **404 HTML page from the host** (nothing like our app, `Server: LiteSpeed`) | The document root's `.htaccess` did not forward the URL to Laravel. A condition `RewriteCond %{REQUEST_URI} !/public/` there skips any path containing `/public/`, and this URL does. | Deploy current `main`: the root `.htaccess` must contain `RewriteRule ^public/ - [L]` and **no** `!/public/` condition. `PublicAnnouncementApiTest` guards it. Never replace the repo's root `.htaccess` on the server. |
| **302** instead of `422` for a bad `limit` | Old build (before `PublicAnnouncementController` used `Validator::make()`); `$request->validate()` redirects when the client sends no `Accept: application/json`. | Deploy current `main`. |
| `200` but `data: []` | Nothing published yet, or you sent a `campus_id` that matches no row. | Publish a notice (7.2); drop `campus_id` in single-campus mode. |
| New notice not showing | `Cache-Control` 60 s — the browser/CDN served a cached copy. | Wait 60 s, hard-reload, or add a cache-busting `&t=` while testing. |
| Browser console: CORS error | Should not happen (`*`). It means the answer came from the host, not Laravel (404 row), or a proxy stripped the header. | Run `curl -i` to see the real headers. |
| `429` | More than 60 calls a minute from that IP. | Back off using `Retry-After`; the site should cache for 60 s anyway. |
| `401` | You called `GET /api/announcements` instead of `/api/public/announcements`. | Use the public path; it needs no token. |

---

## 9. Short message you can paste to the developer

> The church website can read the notice board anonymously from:
>
> **`GET https://dev.lit-grp.com/api/public/announcements?limit=10`** (dev)
> **`GET https://smartchurch.tcnikorodu.org/api/public/announcements?limit=10`**
> (production)
>
> No auth, no key. CORS is open (`Access-Control-Allow-Origin: *`). Rate limit 60
> req/min per IP. `limit` is 1–50, default 10; out of range returns `422` JSON.
>
> `200` body: `{ success, count, data[(id, title, content, excerpt, posted_at,
> posted_at_human, campus_id, campus)], meta{limit, generated_at} }` — newest
> first. `content` is plain text (escape it; do not render as HTML). `posted_at`
> is ISO-8601 UTC; `posted_at_human` is pre-formatted Africa/Lagos time.
>
> Responses carry `Cache-Control: public, max-age=60`, so cache/revalidate for 60
> seconds. Empty `data` means "no notices", not an error. `campus_id` / `campus`
> are `null` while the church is single-campus.
>
> Full contract (fields, errors, Next.js example, test commands):
> `docs/PUBLIC-ANNOUNCEMENTS-API.md`.

---

## 10. Where this is implemented

| File | Role |
|------|------|
| `routes/api.php` | `Route::get('/public/announcements', …)` — outside the `verify.token` group |
| `app/Http/Controllers/Api/PublicAnnouncementController.php` | the endpoint (validation, query, payload, cache header) |
| `config/cors.php` | `paths: ['api/*']`, `allowed_origins: ['*']` |
| `app/Providers/RouteServiceProvider.php` | `throttle:api` = 60/min per user-or-IP |
| `config/birthday.php` | `timezone` = `Africa/Lagos`, used for `posted_at_human` |
| `tests/Feature/PublicAnnouncementApiTest.php` | the contract tests, including the `.htaccess` guard |
| `.htaccess` (repo root) | forwards every non-`public/` URL to `public/index.php` |
