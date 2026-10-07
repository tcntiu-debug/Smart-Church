# Deploy Smart-Church to dev.lit-grp.com

This is the **short, filled-in version** of [`CPANEL-DEPLOYMENT.md`](CPANEL-DEPLOYMENT.md)
for this specific deployment. Read this one; use the other only for the deep detail.

| Setting | Value for this deployment |
| --- | --- |
| GitHub repo | `tcntiu-debug/Smart-Church` |
| Deploy branch | `main` (the workflow also accepts `master`) |
| Subdomain | `https://dev.lit-grp.com` |
| cPanel account (username) | `litgrpco` |
| App folder on server | `/home/litgrpco/public_html/dev.lit-grp.com` (already exists) |
| Document root of subdomain | `/home/litgrpco/public_html/dev.lit-grp.com` (cPanel default — keep it) |
| Database | `litgrpco_smart_church_db` (user `litgrpco_litgrpco`) |
| How it ships | GitHub Actions → SFTP (SSH) → cPanel (no manual upload) |

How it works: you `git push` to `main`; GitHub builds the app and ships it over SFTP (SSH)
straight into `/home/litgrpco/public_html/dev.lit-grp.com/` (the folder cPanel already created
for the subdomain). The repo's root `.htaccess` then forwards every
public request into that folder's `public/` subfolder, so the site appears at
`https://dev.lit-grp.com`. You do this setup **once**; after that, deploying is just
`git push`.

---

## Status — what's confirmed, what's left

**Confirmed (from you):**

- cPanel username: `litgrpco`
- FTP server: `ftp.lit-grp.com` · FTP username: `litgrpco` (this is the **main** cPanel
  account, so `CPANEL_REMOTE_DIR = public_html/dev.lit-grp.com/`)
- Database: `litgrpco_smart_church_db` · DB user: `litgrpco_litgrpco` ✅
- Data: you will **import the dump yourself** (Step 8)
- SSH/SFTP: **enabled** (you see **Security → SSH Access**; `ftp.lit-grp.com:22` is open; and
  the server accepts **password** logins — an unauthenticated `ssh` probe returns
  `Permission denied (publickey,gssapi-keyex,gssapi-with-mic,password)`)
- Deploy transport: **SFTP over SSH (port 22)**. The workflow sends one `smart-church-deploy.tar.gz`
  with `appleboy/scp-action` and extracts it with `appleboy/ssh-action` (see
  "Transports: SFTP vs FTPS" below). FTPS was **abandoned** — its passive data ports time out
  from GitHub's runners and it kept aborting halfway.

**Your local (XAMPP) database name** — you asked: it is **`tcnikoro_smart_church`** (from
your local `.env`: host `127.0.0.1`, user `root`). That is the name to use when you dump
or import on localhost.

### Transports: SFTP (chosen) vs FTPS (abandoned)

**Chosen: SFTP over SSH, port 22.** The workflow now:

1. builds one `smart-church-deploy.tar.gz` containing exactly the files that belong on the
   server (`Stage deployment payload (tar)` step);
2. uploads it with [`appleboy/scp-action@v1`](https://github.com/appleboy/scp-action)
   (`Publish to cPanel (SFTP)` step); and
3. extracts it in place with [`appleboy/ssh-action@v1`](https://github.com/appleboy/ssh-action)
   (`Extract build on server` step), which also runs `php artisan app:post-deploy` once a
   server `.env` exists.

It needs only the three existing secrets — nothing else.

**Why FTPS was dropped:** the workflow used to ship with `SamKirkland/FTP-Deploy-Action`
(FTP/FTPS only). Against this server it kept failing with
`Timeout when trying to open data connection to <ip>:<port>` — the FTPS **passive-mode data
ports** are filtered/flaky from GitHub's runners — which aborted the upload halfway and left a
half-written tree that showed **"Index of /"**. SFTP rides the single SSH connection, so it is
reliable, and the cPanel account already accepts SSH **password** logins (no key needed).

You *did* see **Security → SSH Access**, so the cPanel **Terminal** mentioned in Step 7
will work — handy for running `php artisan key:generate`.

### Where the password goes (never paste it in chat)

- **cPanel / FTP password** → GitHub → repo **Settings → Secrets and variables → Actions
  → Secrets** → `CPANEL_PASSWORD` (encrypted; you can't read it back). Optionally also save
  it in Windows Credential Manager for FileZilla/SSH.
- **Database password** → *only* in the server-side `.env` you create in Step 7
  (`DB_PASSWORD`). It never needs to leave the server, so don't send it to me.

> ⚠️ The DB password you posted earlier (`G*Bq4d!JRB%EWan?`) has now been shared in plain
> text — change it in cPanel → **MySQL Databases** → *Change Password* once you're set up,
> and put the new one in both the server `.env` and your local `.env`.

Everything is settled — work through **Steps 1–11** below.

---

## Step 1 — Confirm the subdomain's document root

You already have the subdomain, and File Manager shows the folder cPanel created for it:

```
/home/litgrpco/public_html/dev.lit-grp.com/     <- the document root (keep this)
```

You do **not** need to change anything here. The app is deployed *into* this folder, and
the repo's root `.htaccess` routes every public request into its `public/` subfolder.

1. cPanel → **Domains** → `dev.lit-grp.com` should show Document Root
   `/home/litgrpco/public_html/dev.lit-grp.com`. **Leave it as-is.**
2. cPanel → **SSL/TLS Status** → **Run AutoSSL** so `https://dev.lit-grp.com` gets a
   certificate.

Layout on the server after the first deploy:

```
/home/litgrpco/public_html/dev.lit-grp.com/   <- document root; repo root is uploaded here
├── .htaccess          (repo file — forwards everything into public/)
├── app/ bootstrap/ config/ routes/  .env  vendor/   <- blocked from HTTP by .htaccess
└── public/            <- real front controller (index.php, assets, uploads)
```

> **Extra-secure alternative (optional):** to keep `.env`/`vendor/` *outside* the web
> root entirely, create a folder `/home/litgrpco/smart-church`, point the subdomain's
> **Document Root** to `/home/litgrpco/smart-church/public`, then use
> `CPANEL_REMOTE_DIR = smart-church/` in Step 6. Everything else is identical. The default
> above (deploy into the existing folder) is simpler and is exactly what the repo's
> `.htaccess` is designed for.

## Step 2 — Set the PHP version for the subdomain

cPanel → **MultiPHP Manager** → tick `dev.lit-grp.com` → choose **PHP 8.0**
(or 7.4). Do **not** use 8.1+ — the app targets PHP 7.3–8.0 and the build uses 8.0.
Click **Apply**.

## Step 3 — FTP: use the main account, or create a dedicated one

> **Your setup:** you'll use the **main cPanel account** — `litgrpco` on `ftp.lit-grp.com`.
> Then **skip this step** and use `CPANEL_REMOTE_DIR = public_html/dev.lit-grp.com/`
> (Step 6). The dedicated account below is the safer option if you change your mind.

The main cPanel account is jailed to your whole home directory, which is easy to get
wrong. A dedicated FTP account locked to the app folder is safer:

1. cPanel → **FTP Accounts** → **Add FTP Account**.
2. **Log In** (username): `deploy` → becomes `deploy@dev.lit-grp.com`.
3. **Directory**: `/public_html/dev.lit-grp.com` (cPanel fills the rest of the path).
4. Set a strong password. **Create**.

Now the FTP account's `/` **is** `/home/litgrpco/public_html/dev.lit-grp.com/`, so the
remote directory for the deploy is simply `/`.

> If you would rather use the **main cPanel account** instead, skip this step and set
> `CPANEL_REMOTE_DIR` to `public_html/dev.lit-grp.com/` in Step 6 instead of `/`.

## Step 4 — Test the FTP details from your PC (before touching GitHub)

Wrong credentials cost you a red build. Test them locally first. In PowerShell, from the
project folder:

```powershell
cd c:\xampp\htdocs\Smart-Church

# Your setup: main cPanel account on ftp.lit-grp.com (FTPS, port 21)
.\scripts\test-cpanel-connection.ps1 -Server ftp.lit-grp.com -Username litgrpco -RemoteDir public_html/dev.lit-grp.com/

# If SSH is enabled, test SFTP as well:
.\scripts\test-cpanel-connection.ps1 -Server ftp.lit-grp.com -Username litgrpco -Protocol sftp -Port 22 -RemoteDir public_html/dev.lit-grp.com/
```

It prompts for the password securely, logs in, lists the folder, writes a test file and
deletes it. If it says **ALL CHECKS PASSED**, continue.

## Step 5 — Prepare the database user

You already have these, so this is just a sanity check:

1. cPanel → **MySQL Databases**.
2. Confirm the user **`litgrpco_litgrpco`** exists.
3. Confirm it is attached to **`litgrpco_smart_church_db`** with **ALL PRIVILEGES** (use
   *Add User To Database* if it isn't).

## Step 6 — Add the GitHub secrets and variables

GitHub → `tcntiu-debug/Smart-Church` → **Settings** → **Secrets and variables** → **Actions**.

**Secrets** tab (**New repository secret** for each):

| Name | Your value |
| --- | --- |
| `CPANEL_HOST` | `ftp.lit-grp.com` (now also used as the SSH host) |
| `CPANEL_USER` | `litgrpco` |
| `CPANEL_PASSWORD` | your cPanel password — now used for the SSH login (type it here, never in chat) |

**Variables** tab (**New repository variable** for each):

| Name | Your value |
| --- | --- |
| `CPANEL_SSH_PORT` | `22` |
| `CPANEL_REMOTE_DIR` | `public_html/dev.lit-grp.com/` |

> `CPANEL_REMOTE_DIR` is the #1 thing that breaks first deploys. Get it from the
> `test-cpanel-connection.ps1` output — it prints the exact value to paste.

## Step 7 — Create the server `.env` (once)

The pipeline **never** uploads `.env`, so you create it by hand **once** and it survives
every deploy.

1. cPanel → **File Manager** → open `/home/litgrpco/public_html/dev.lit-grp.com/`.
2. **+ File** → name it exactly `.env` → **Create**, then **Edit** it.
3. Paste the following and replace the `CHANGE-ME` values with the DB user/password from
   Step 5:

```dotenv
APP_NAME="Smart-Church"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://dev.lit-grp.com

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=litgrpco_smart_church_db
DB_USERNAME=litgrpco_litgrpco
DB_PASSWORD=CHANGE-ME

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DRIVER=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MAIL_MAILER=smtp
MAIL_HOST=mail.lit-grp.com
MAIL_PORT=587
MAIL_USERNAME=noreply@lit-grp.com
MAIL_PASSWORD=CHANGE-ME
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@lit-grp.com"
MAIL_FROM_NAME="${APP_NAME}"

TELEGRAM_BOT_TOKEN=
TELEGRAM_CHAT_ID=

PHP_MEMORY_LIMIT=512M
```

4. Generate the app key — cPanel → **Advanced** → **Terminal**:

   ```bash
   cd ~/public_html/dev.lit-grp.com
   php artisan key:generate
   ```

   If there is no Terminal, generate the key after the first deploy in Step 9 instead.

> `.env` is on the deploy archive's exclude list, and the deploy never deletes anything on the
> server, so your `.env` survives every redeploy.

## Step 8 — Load the data

- **Fresh database:** nothing to do now — Step 9 runs the migrations.
- **Importing from an existing site:** export a `.sql` dump from the old database, then
  in cPanel → **phpMyAdmin** → select `litgrpco_smart_church_db` → **Import** → choose the
  file → **Go**. Skip `--migrate` in Step 10 (a full dump already contains the schema).

  If the dump was taken from the **local** DB, remember your local name is
  `tcnikoro_smart_church`, but you are importing **into** `litgrpco_smart_church_db` — that
  is fine; the dump only carries tables, not the database name.

  **Symlinked media/uploads:** if the old site stored user uploads, also copy the
  `storage/app/public` contents and the `public/storage` symlink (Step 10 creates that
  symlink after deploy).


## Step 9 — Trigger the deploy

Your local branch is currently `master`, but this deployment should ship from `main`.
Run once:

```powershell
cd c:\xampp\htdocs\Smart-Church
git branch -M main
git push -u origin main
```

Then, in GitHub → **Settings** → **Branches**, set the **default branch** to `main`
(so future `git push` deploys without extra flags).

The push starts **Actions → Deploy to cPanel** automatically. Watch it there — the first
run uploads everything (a few minutes); later runs only send changed files.

No push handy? **Actions → Deploy to cPanel → Run workflow** starts a deploy on demand.

## Step 10 — Run the post-deploy step on the server (once)

After every deploy the pipeline now tries this for you (the `Extract build on server` step runs
`php artisan app:post-deploy` whenever the server `.env` is present). The **first** time, the
`.env` does not exist yet, so run it by hand once — cPanel → **Advanced** → **Terminal**:

```bash
cd ~/public_html/dev.lit-grp.com

php artisan app:post-deploy             # dirs, caches, storage link
php artisan app:post-deploy --migrate   # ...plus run the migrations (fresh DB only)
```

Safe to re-run after any deploy. *(No Terminal? Add a daily cron in cPanel → Cron Jobs:
`php /home/litgrpco/public_html/dev.lit-grp.com/artisan app:post-deploy`.)*

## Step 11 — Verify

- `https://dev.lit-grp.com/` shows the **login page** (the `/` route).
- `https://dev.lit-grp.com/.env` returns **403** and
  `https://dev.lit-grp.com/vendor/` returns **404** — proof the app code is protected.
- Log in, upload a member photo, confirm it appears.
- GitHub → **Actions**: the latest run is green with a summary table.

---

## Everyday workflow (after setup)

```powershell
cd c:\xampp\htdocs\Smart-Church
git add -A
git commit -m "Describe the change"
git push
```

That's the whole deploy. If the change included migrations or new config, also run
`php artisan app:post-deploy --migrate` in cPanel Terminal.

## Quick troubleshooting

| Symptom | Fix |
| --- | --- |
| `Missing repository secret: CPANEL_HOST` | Add the three values as **Secrets** (not Variables). |
| Deploy is green but the site is unchanged | Wrong `CPANEL_REMOTE_DIR` — re-run `test-cpanel-connection.ps1` and use the value it prints. |
| `530 Login authentication failed` | Wrong FTP password, or the FTP account is jailed to the wrong folder. |
| `test-cpanel-connection.ps1`: `227 Entering Passive Mode` on step 3 | The **login worked** — Windows' .NET FTP client just can't open the passive/tagged data channel (Pure-FTPd needs TLS session reuse). Confirm with `curl -v -u litgrpco --ssl-reqd ftp://ftp.lit-grp.com/public_html/dev.lit-grp.com/`, then deploy anyway — the SFTP deploy never uses FTP passive mode. |
| `getaddrinfo ENOTFOUND` / timeout | Wrong `CPANEL_HOST`, or the host blocks SSH. Check `CPANEL_SSH_PORT` (default `22`) and that **SSH Access** is enabled in cPanel. |
| 500 error / blank page | Set `APP_DEBUG=true` in the server `.env`, reload, read the message, set it back to `false`. |
| `Please provide a valid cache path` | Run `php artisan app:post-deploy` (creates the `storage/framework/*` folders). |

Full detail, security notes and the SSH/git alternative live in
[`CPANEL-DEPLOYMENT.md`](CPANEL-DEPLOYMENT.md).

