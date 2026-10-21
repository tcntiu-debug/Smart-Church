# cPanel Auto-Deploy Guide

Every push to `main` (or `master`) builds the application on GitHub's runners and
publishes it to your cPanel account. No manual uploading, ever.

> **Dev deploys itself; production waits for you.** The dev workflow
> (`.github/workflows/deploy-cpanel.yml`) publishes on every push, unreviewed. The
> **production** workflow (`.github/workflows/deploy-cpanel-prod.yml`) ships the
> live folder from **`main` only** and sits in *Pending* until a required reviewer
> clicks **Approve and deploy** — nothing is checked out, built or uploaded before
> that click. Both rules are GitHub settings; see
> [§3e](#3e-gate-production-with-a-required-reviewer). Adding a second developer
> without giving them that button: [§3f](#3f-sharing-the-repository-without-handing-over-production-approval).

```
 git push
    │
    ▼
 GitHub Actions  (.github/workflows/deploy-cpanel.yml)
    │  1. checkout the commit
    │  2. composer install --no-dev --optimize-autoloader   ← vendor/ is git-ignored,
    │                                                        so it is built here
    │  3. php artisan package:discover
    │  4. smoke test the build
    │  5. SFTP/FTPS upload of changed files only
    ▼
 cPanel  →  public_html/<domain>/   (app, config, vendor, storage, public, .htaccess)
```

What is deliberately **not** uploaded: `.env`, `vendor/` sources are replaced by the
built copy, `tests/`, `docs/`, `node_modules/`, git metadata, debug scripts and every
user upload (`public/uploads`, `public/display_photo`, `public/gallery_uploads`,
`public/member_photos`).

**Deletions are propagated as well.** The upload itself only adds and overwrites, so a
file removed from the repository would linger on the server forever. The *Prune files
deleted from the repository* step closes that gap: it diffs the commit of the last
successful deploy against the commit being pushed and deletes exactly the paths that
`git rm` took out of the tree (paths the publish step never uploads, such as `tests/` and
`docs/`, are skipped). Paths that only ever exist on the server are protected by a
hard-coded list — `.env`, `storage/**`, `bootstrap/cache/**`, `public/uploads/**`,
`public/display_photo/**`, `public/gallery_uploads/**`, `public/member_photos/**`,
`public/storage`,
`.well-known/**`, `vendor/**`, `node_modules/**` — so live member photos, PDFs and the
production environment file can never be removed by a commit.

---

## 1. Create the GitHub repository

Already done: <https://github.com/tcntiu-debug/Smart-Church> (keep it **private** —
it describes a real member database).

## 2. Push the code (first time)

```powershell
cd c:\xampp\htdocs\Smart-Church
git remote add origin https://github.com/tcntiu-debug/Smart-Church.git
git add -A
git commit -m "Initial commit"
git push -u origin master
```

> Your Git default branch is `master` (not `main`), so the examples use `master`.
> The dev workflow listens for **both** `main` and `master`, so either name works —
> rename it in GitHub → Settings → Branches if you prefer `main`.
>
> The **production** workflow is **`main`-only**: pushing `master` deploys the dev
> copy and nothing else. Ship the live site from `main`.

The first push opens a browser window (Windows Credential Manager). Sign in to GitHub
once and every future push is silent. Nothing is stored in the project.

## 3. Connect GitHub to cPanel

**There is no "link your hosting" button.** GitHub Actions is a headless Linux machine in
Microsoft's cloud, and it reaches cPanel exactly the way FileZilla does: hostname +
username + password over FTP/SFTP. "Connecting" the two means storing those three values
as encrypted **GitHub Secrets**, which the workflow reads at deploy time. GitHub cannot
discover them from your domain — you must supply them.

### 3a. Find the three values in cPanel

| What you need | Where to find it |
| --- | --- |
| **Hostname** | cPanel right-hand sidebar → **Server Information** / *Shared IP Address*. Usually `serverNNN.webhost.com`. `ftp.yourdomain.com` also works on most hosts. |
| **Username** | cPanel right-hand sidebar → **Current User**, e.g. `tcnikoro`. The full list is in **FTP Accounts** → *Special FTP Accounts*. |
| **Password** | Your main cPanel password. **FTP Accounts** → *Configure FTP Client* also shows the hostname, port and encryption mode the host recommends — the fastest way to get all three. |

### 3b. Verify them before touching GitHub

Wrong credentials cost you a red build and an unhelpful FTP log. Test them from your PC
first — this logs in, lists the directory, writes a test file and deletes it again:

```powershell
cd c:\xampp\htdocs\Smart-Church
.\scripts\test-cpanel-connection.ps1 -Server server123.webhost.com -Username tcnikoro
```

It prompts for the password securely, then prints the exact secret values to paste. It
also tells you whether the app is already deployed in the target directory.

### 3c. Add the secrets

GitHub → your repository → **Settings** → **Secrets and variables** → **Actions**.

### Secrets tab

| Name | Value |
| --- | --- |
| `CPANEL_HOST` | FTP hostname. Use the server hostname from cPanel → **FTP Accounts** (e.g. `server123.webhost.com`). If unsure, try your domain, e.g. `ftp.yourdomain.com`. |
| `CPANEL_USER` | Your cPanel username (e.g. `tcnikoro`), or a dedicated FTP account created in cPanel → **FTP Accounts**. |
| `CPANEL_PASSWORD` | The password for that account. |

### Variables tab (optional — sensible defaults are built in)

| Name | Default | Notes |
| --- | --- | --- |
| `CPANEL_SSH_PORT` | `22` | The SFTP port. Use `22`, not `21`. |
| `CPANEL_REMOTE_DIR` | `public_html/` | The directory to publish into. **Must end with a `/`**. See the warning below. |

> ⚠️ **There is no `CPANEL_PROTOCOL` and no `CPANEL_PORT` variable.** The workflow is
> deliberately hardcoded to SFTP on `CPANEL_SSH_PORT` — FTPS passive data ports are
> filtered when reached from GitHub's runners, which aborts the upload halfway and
> leaves a half-written tree (the *"Index of /"* symptom). Adding either variable on
> GitHub changes nothing; the reason is in the header comment of the deploy workflow.
> A protocol variable scoped to the `production` **environment** is doubly inert: it
> is not read by the production workflow either.

> ⚠️ **`CPANEL_REMOTE_DIR` is the setting that breaks most first deploys.** The main cPanel
> account is jailed to your **home directory**, so the FTP path `/` means `/home/tcnikoro/`
> — the app would land *beside* `public_html/`, where Apache never serves it, and the live
> site would look completely unchanged even though the deploy went green. Keep the default
> `public_html/`.
> A dedicated FTP account jailed to `public_html/` is different: its `/` **is**
> `public_html/`, so in that case set the value to `/`. Run
> `.\scripts\test-cpanel-connection.ps1` to have it list the directory and show you exactly
> where you are landing.

> A dedicated FTP account scoped to the target directory is safer than the main cPanel
> account. If you create one in cPanel → FTP Accounts, its username looks like
> `smartchurch@yourdomain.com` and its remote directory is the subfolder you chose —
> in that case set `CPANEL_REMOTE_DIR` to `/` because the FTP account is already jailed
> to the right folder.

### 3d. Your setup: deploying to a subdomain

Create the subdomain **first**. cPanel gives every domain on this account its own folder
inside `public_html/` (`public_html/church.yourdomain.com/`), and **that folder is the
deploy target** — `CPANEL_REMOTE_DIR` is relative to the account home, so it reads
`public_html/church.yourdomain.com/`. Never point it at `public/`: the repository root is
what gets uploaded.

1. cPanel → **Domains** → **Create A New Domain** (older themes: **Subdomains**).
2. Domain: `church.yourdomain.com` — cPanel creates (or asks you to confirm) the folder
   `public_html/church.yourdomain.com/`.
3. Set `CPANEL_REMOTE_DIR` to `public_html/church.yourdomain.com/`.
4. cPanel → **SSL/TLS Status** → **Run AutoSSL** so HTTPS works on the subdomain.

Resulting layout on the server:

```
/home/tcnikoro/public_html/church.yourdomain.com/   <- CPANEL_REMOTE_DIR
├── app/ bootstrap/ config/ routes/   <- NOT web-accessible
├── .env                              <- NOT web-accessible (root .htaccess denies it)
├── vendor/
├── .htaccess                         <- repo root: forwards every request into public/
└── public/                           <- Laravel front controller
    ├── .htaccess        (Laravel's own, already in the repo)
    ├── index.php
    ├── assets/  vendors/
    ├── storage/         (symlink created by app:post-deploy)
    └── uploads/  display_photo/  gallery_uploads/  member_photos/
```

Set `APP_URL=https://church.yourdomain.com` in the server `.env` so uploads, links and
redirects all resolve to the subdomain.

> **Document root — pick one. Both work, and the upload target never changes;
> `smartchurch.tcnikorodu.org` and `dev.lit-grp.com` on this account use the
> first:**
> * **the domain folder** (`public_html/church.yourdomain.com/`) — the repo's root
>   `.htaccess` forwards every request into `public/`. This is how
>   `public_html/dev.lit-grp.com/` and `public_html/smartchurch.tcnikorodu.org/`
>   run today; `.env` is then protected by the rewrite rules (and the `FilesMatch`
>   block) in that same file — so never delete or replace the repo's root
>   `.htaccess` on the server.
> * **`…/public`** (cPanel → **Domains** → *Manage* → **Document Root**, set it to
>   `public_html/church.yourdomain.com/public`) — then `.env`, `app/` and `vendor/` sit
>   physically *above* the web root, so no rewrite rule has to protect them.


### 3e. Gate production with a required reviewer

The **dev** target is allowed to deploy itself: every push lands in the dev folder,
unreviewed, exactly as before. The **live** site is not. The production workflow
declares `environment: production`, and a GitHub Environment is where a deploy can be
forced to wait for a human.

> **Set both settings below *before* the first push that carries
> `.github/workflows/deploy-cpanel-prod.yml` to `main`.** The gate is an environment
> setting, not workflow YAML: a push that lands while `production` still has no required
> reviewer publishes to the live folder immediately. Rules first, then push.

Two settings, both under **Settings → Environments → `production`**. Neither replaces
the other:

| Setting | What it does | Why you need it |
| --- | --- | --- |
| **Required reviewers** | The job sits in *Pending* until a reviewer clicks **Approve and deploy**. | This *is* the review. Without it, a push to `main` publishes the live site immediately. |
| **Deployment branches and tags** → *Selected branches and tags* → `main` | Refuses to deploy from any other branch. | The environment-level twin of the `branches:` filter in the workflow file, and it also blocks a *Run workflow* started from another branch. |

Click path:

1. **Settings → Environments → `production`** (create it if the list is empty).
2. Under **Deployment protection rules**, tick **Required reviewers**.
3. Type your GitHub username → **Add**. Up to 6 people or teams; **one** of them
   approving is enough.
4. Leave **Prevent self-review** *unticked* on a one-admin repository — it would stop
   the only admin from approving their own deploy and the run would wait until it
   failed.
5. Under **Deployment branches and tags**, choose **Selected branches and tags** →
   **Add deployment branch or tag rule** → *Ref type*: **Branch** → name pattern
   `main` → **Add rule**. (The pattern is `fnmatch`, so `main` matches exactly `main`;
   `release/*` would match any release branch.)
6. **Save protection rules**.
7. Push to `main` and open the run: the job shows **Waiting** with a **Review
   deployments** button. Approve it, and only then do the build steps and the SFTP
   upload start.

Notes:

- The approval is requested when the run is **queued**, before any step executes, so a
  deploy you regret costs nothing: reject it (or cancel the run) and zero files were
  written to the server.
- Required reviewers and wait timers are only available for **public** repositories on
  Free/Pro/Team plans. This repository is public, so both work; converting it to private
  would silently disable the rules *and* the environment secrets.
- A repository admin can bypass the gate from the run page (**Start all waiting jobs**)
  while a job is *Pending* — the escape hatch for a 2 a.m. hotfix. The gate stops
  accidents, not you.
- Approving also unlocks the environment's secrets: a run cannot read `PROD_CPANEL_*`
  values scoped to `production` before the rules pass.
- Optional extra: protect the `main` branch itself (**Settings → Branches → Add branch
  protection rule**, pattern `main`, *Require a pull request before merging*). Pushing to
  `main` then has to go through a pull request. It is irrelevant to the deploy gate —
  only to how commits reach the live branch.

### 3f. Sharing the repository without handing over production approval

A collaborator is added under **Settings → Collaborators and teams → Add people**:
enter the GitHub account or e-mail, pick a role, **Add**. If the person has no GitHub
account yet, GitHub e-mails an invitation and the access starts when they accept it.
Like the gate in §3e, this is an account setting — nothing in the repository can grant
or take it back.

This repository is **public**, so *Read* gives nothing that an anonymous visitor cannot
already see (clone, issues, pull requests, run logs). Give **Write** only to someone who
will push code; anything above Write is the mistake to avoid:

| Role | What it allows | Use it for |
| --- | --- | --- |
| **Read** | Clone, open issues and pull requests, read run logs | Anyone who only needs the source |
| **Write** | Push branches, open/merge pull requests, edit workflow files | A developer who contributes to dev |
| **Maintain / Admin** | Repository settings, secrets, environment protection rules, bypassing a pending run | The owner only |

Two rules keep a Write collaborator away from the live site:

1. **Do not add them to the `production` environment's required reviewers.** Only the
   accounts listed there can approve a run; everyone else sees the run *Waiting* with no
   approve control. Never give the person **Admin**: an admin can edit that list, or
   press **Start all waiting jobs** on the run page and skip the gate entirely.
2. **Keep `PROD_CPANEL_*` on the `production` environment, not at repository level.**
   The `environment:` line is not the lock — the lock is what it protects. Someone with
   Write can push a commit that removes `environment: production` from
   `deploy-cpanel-prod.yml`, and the run that follows is ungated. What still stops it is
   **Validate deployment secrets**: a job only receives an environment's secrets after
   the environment's rules pass, so `PROD_CPANEL_*` arrive empty and that step fails
   before anything is built or uploaded. Repository-level secrets get no such
   protection — *any* branch they can push can read them. When the same name exists at
   both levels the **environment copy wins**, so deleting the repository copy changes
   nothing about what an approved run reads.

So, under **Settings → Secrets and variables → Actions**:

- `PROD_CPANEL_HOST`, `PROD_CPANEL_USER` and `PROD_CPANEL_PASSWORD` belong on
  **Environments → `production`**, and the repository-level copies of them must be
  **deleted** (the trash icon on each row) — the same for the `PROD_CPANEL_REMOTE_DIR` and
  `PROD_CPANEL_SSH_PORT` *variables*, which are duplicated there today. A leftover copy is
  exactly what a workflow without the environment would read, and the environment copy
  wins for every run that does declare it, so nothing an approved deploy does changes.
- **Delete `CPANEL_PORT` and `CPANEL_PROTOCOL` outright.** Neither workflow reads them;
  both are hardcoded to SFTP on `CPANEL_SSH_PORT`/`PROD_CPANEL_SSH_PORT` (§3c has the
  "why"). They are leftovers from the FTPS attempt and are worse than useless in an audit:
  they advertise port `21` and `ftps` for a job that connects on `22`, so the next person
  to debug a deploy chases a transport that is not in use.

**Optional: move the dev credentials out of the repository too.** The dev `CPANEL_*` set
is repository-level in the same way, and `deploy-cpanel.yml` has no environment to protect
it. The move is four steps, and the order is what matters — get it wrong and dev deploys
fail with an SFTP login error:

1. **Environments → New environment** → name it `development`, and add **no** protection
   rules (dev must keep deploying on every push, unreviewed);
2. add `CPANEL_HOST`, `CPANEL_USER` and `CPANEL_PASSWORD` to that environment, plus
   `CPANEL_REMOTE_DIR` (`CPANEL_SSH_PORT` only if the SSH port is not 22) — those are the
   only names `deploy-cpanel.yml` reads, see §3c;
3. add `environment: development` to the `deploy` job in `deploy-cpanel.yml`, beside its
   `runs-on:` — commit that before touching step 4;
4. only then delete the repository-level copies.

After step 2 the job still cannot see the new environment's secrets; until step 4 the old
copies remain readable by any branch a Write collaborator pushes. Actions masks a secret
in the log, but a workflow can copy it anywhere.

Optional, and worth it as soon as a second person has Write:

- **Settings → Rules → Rulesets** (or *Branches*) → new rule for `main`: *Require a pull
  request before merging* (1 approval), *Require review from Code Owners*, *Block force
  pushes*, *Block deletions*. `.github/CODEOWNERS` names `@tcntiu-debug` as the owner of
  `.github/` and this file, so a change to a workflow — or to this runbook — cannot land
  without the owner's review. Put your own account in the ruleset's **bypass** list, or a
  solo hotfix becomes a two-person job.
- **Settings → Actions → General**: *Workflow permissions* → **Read repository
  contents**, and untick *Allow GitHub Actions to create and approve pull requests*.

Verify before relying on it — ask the collaborator to:

1. open **Actions → Deploy to cPanel (production)** on a run that is waiting: there must
   be no **Review deployments** / **Approve and deploy** control;
2. open **Settings → Environments**: a Write collaborator is refused;
3. start a **Run workflow** from a throwaway branch: the run stays *Waiting*, and the
   `main`-only deployment-branch rule from §3e refuses it outright.

> Removing the collaborator (**Settings → Collaborators → … → Remove**) revokes the
> access at once. Their commits, branches and pull requests stay.
>
> The dev workflow (`deploy-cpanel.yml`) has no gate by design: their pushes publish the
> dev folder, which is where their work is meant to be seen. Only `main` and the
> `production` environment reach the live site.

## 4. Create the server `.env` file

This is the only file the pipeline will never touch, so it survives every deploy.

1. cPanel → **MySQL Databases**: create the database and a user, and add the user to the
   database with **ALL PRIVILEGES**. cPanel prefixes names with your account, e.g.
   `tcnikoro_smart_church`.
2. cPanel → **File Manager** → open the app folder (`public_html/church.yourdomain.com/`).
3. Using the template at `.env.cpanel.example` in this repo, create a new file named
   exactly `.env` and fill in `APP_KEY`, `APP_URL`, `DB_*`, `MAIL_*` and the Telegram
   values.
4. Generate the key. cPanel → **Advanced** → **Terminal** (if your host enables it):

   ```bash
   cd ~/public_html/church.yourdomain.com
   php artisan key:generate
   ```

   If Terminal is unavailable, run it once from your local machine against the server
   copy, or paste a known-good `base64:...` value from your local `.env`.

> `APP_DEBUG=false` and `APP_ENV=production` are essential on the live site.

## 5. Trigger the first deploy

Commit and push. The pipeline starts automatically:

```powershell
git add -A
git commit -m "Add cPanel deployment pipeline"
git push
```

Watch it at GitHub → **Actions** → *Deploy to cPanel*. The first run takes several
minutes because `vendor/` is uploaded for the first time; later runs only transfer
changed files.

You can also start a run on demand: **Actions** → *Deploy to cPanel* → **Run workflow**.

> On the **production** workflow a run does not start by itself — it stops in *Pending*
> until you approve it ([§3e](#3e-gate-production-with-a-required-reviewer)). The dev
> workflow has no such gate, by design.

## 6. Run the post-deploy command on the server

The pipeline cannot run PHP on your server, so run these once from
cPanel → **Advanced** → **Terminal**:

```bash
cd ~/public_html/church.yourdomain.com

php artisan app:post-deploy             # directories, caches, storage link, app:sync-schema
php artisan app:post-deploy --migrate   # ...plus pending database migrations
```

`app:post-deploy` is idempotent and safe to re-run after any deploy. It:

1. creates any missing runtime directory (`storage/framework/*`, `storage/logs`,
   `public/uploads`, `public/display_photo`, `public/gallery_uploads`,
   `public/member_photos`) — FTP does not create empty directories;
2. clears and rebuilds the config cache;
3. clears and pre-compiles the Blade views;
4. creates the `public/storage` symlink when missing;
5. attempts `route:cache` and **warns instead of failing** if your routes cannot be
   serialised (see *Known issue* below);
6. with `--migrate`, runs pending migrations using `--force`.
7. always runs `app:sync-schema`, which creates the tables the code needs (password
   reset tokens, notification bell, birthday reminder log) and drops the tables of the
   retired Transport (bus route) and standalone FOF modules — idempotent, see
   *Schema sync* below.

### If cPanel Terminal is not available

Add a cron job (cPanel → **Cron Jobs**) that runs once a day:

```
php /home/<cpanel-user>/public_html/church.yourdomain.com/artisan app:post-deploy
```

Adjust the PHP binary if your host pins a version, e.g.
`/usr/local/bin/ea-php80 /home/<user>/public_html/church.yourdomain.com/artisan app:post-deploy`.

### Scheduled jobs: Welcome Center birthday reminders

The app ships a **daily** job that e-mails + pushes the upcoming birthdays of the
**Welcome Center** (`dept_id 28`) admins at the **3 days**, **2 days** and **1 day** to go
milestones. It runs through Laravel's scheduler, so add **one** cron entry
(cPanel → **Cron Jobs**, every minute):

```
* * * * * /usr/local/bin/ea-php80 /home/<cpanel-user>/public_html/church.yourdomain.com/artisan schedule:run >> /dev/null 2>&1
```

That single entry drives every entry in `app/Console/Kernel.php::schedule()` — `birthdays:remind` at
`07:00`, `app:sync-schema` at `03:20` and `firsttimers:auto-unassign` on Mondays at `04:10`, all
`Africa/Lagos`. Use the same PHP binary as the post-deploy cron above. This is **separate** from the
daily `app:post-deploy` cron: keep both.

> **Production (`smartchurch.tcnikorodu.org`): this entry is the only thing that makes
> scheduled work happen, and the deploy does not create it.** The publish pipeline is
> SFTP-only — it has no shell — so on the production cPanel account (`tcnikoro`) nothing
> runs by itself. Until the heartbeat exists there, `birthdays:remind`,
> `app:sync-schema` and `firsttimers:auto-unassign` never fire, and the tables they own
> (`app_notifications`, `birthday_reminder_logs`, `member_tracking_followup_archive`) are
> never created either. Add it in cPanel → **Cron Jobs** of *that* account:
>
> ```
> * * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home/tcnikoro/public_html/smartchurch.tcnikorodu.org/artisan schedule:run >> /dev/null 2>&1
> ```
>
> Keep the dev entry too — dev needs the same heartbeat for its `03:20` `app:sync-schema`
> after each deploy. Before saving, confirm the two things that fail silently because the
> line throws its output away:
>
> ```bash
> cd ~/public_html/smartchurch.tcnikorodu.org
> ls artisan                     # path exists?
> php -v; php artisan schedule:list   # which interpreter?
> ```
>
> `schedule:list` prints the binary it resolved — on `emerald3` that is
> `'/opt/cpanel/ea-php83/root/usr/bin/php'`, i.e. **PHP 8.3** (verified 2026-10-17). Use that
> path in the cron line. The generic `ea-php80` examples in this document do not exist on this
> host, and a cron line naming a missing binary dies into `/dev/null`, indistinguishable from
> "nothing was due".
>
> A wrong path or a missing binary writes `No such file or directory` to `/dev/null` and
> looks exactly like "nothing was due". Verify the tick afterwards with
> `php artisan schedule:list` in that directory (see *Verifying the cron is actually
> alive* below).
>
> **Before the first production tick:**
>
> * Take a database backup (cPanel → **Backup** → *Download a Full Account Backup* or a
>   database backup). The `03:20` `app:sync-schema` **drops the retired Transport / FOF
>   tables and empties the campus columns** — that is intended, but not reversible without
>   the backup.
> * Fill in the mail settings in the production `.env` (`MAIL_MAILER`, `MAIL_HOST`,
>   `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`,
>   `MAIL_FROM_NAME`). Use a mailbox on the domain: cPanel's Exim rejects a forged From.
>   A failing mail does **not** abort the run — the reason lands in
>   `birthday_reminder_logs.notes` and `storage/logs/laravel.log`, and the in-app bell
>   (`database` channel) still gets the digest.
> * Set `TELEGRAM_BOT_TOKEN` (and `BIRTHDAY_TELEGRAM_CHAT_IDS`, or `TELEGRAM_CHAT_ID`) only
>   if the push channel is wanted.
> * Once production sends for real, stop dev from notifying real people: set
>   `BIRTHDAY_CHANNELS=database` in the **dev** `.env` (keeps the bell for testing, drops
>   mail + Telegram) or remove the dev cron. The weekly `firsttimers:auto-unassign` on dev
>   only touches the dev database, so it can stay.

> **Do not leave this entry on *Once Per Day* (`0 0 * * *`).** The cron line is only the
> heartbeat that wakes the scheduler — Laravel compares its own expression (`0 7 * * *`
> in `Africa/Lagos`) on every tick and fires `birthdays:remind` only when they match. A
> daily tick at `00:00` server time (which is `01:00` in Lagos) never coincides with the
> `07:00` due time, so the reminder would silently never send.

> **Alternative — if your host refuses a per-minute cron:** point cron straight at the
> command instead of the scheduler. `07:00` `Africa/Lagos` is `06:00` UTC, so on a UTC
> server:
>
> ```
> 0 6 * * * /usr/local/bin/ea-php80 /home/<cpanel-user>/public_html/church.yourdomain.com/artisan birthdays:remind >> /dev/null 2>&1
> ```
>
> This works, but it is a dead end: it drives *only* this one command (nothing else you
> ever add to `Kernel::schedule()` will run), it breaks silently if the server clock is not
> UTC, and it must be re-timed whenever `BIRTHDAY_SEND_AT` changes. Use the per-minute
> `schedule:run` entry above unless you truly cannot.

> **If the Cron Jobs form refuses to save (406 Not Acceptable).** On `tcnikoro` the cPanel
> *Cron Jobs* page answers every save with *AJAX Error — Please refresh the page and try
> again*, and the failing request itself returns **406 Not Acceptable** from the host's WAF
> (mod_security / Imunify360). The rule it trips is the cron-injection signature: a request
> body whose line starts with `* * * * *` and carries `>> /dev/null 2>&1`. Nothing typed
> into that form gets through, and refreshing or re-logging in does not help — the block is
> upstream of cPanel, so it is **not** the security token and **not** your cron syntax.
>
> Write the crontab directly instead (cPanel → **Advanced** → **Terminal**). The wrapper
> keeps the metacharacters out of every request and leaves a tick log, which the plain
> `/dev/null` line never does:
>
> ```bash
> mkdir -p ~/bin
> cat > ~/bin/church-scheduler.sh <<'EOF'
> #!/bin/bash
> cd /home/tcnikoro/public_html/smartchurch.tcnikorodu.org || exit 1
> /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >> storage/logs/scheduler.log 2>&1
> EOF
> chmod 755 ~/bin/church-scheduler.sh
>
> ( crontab -l 2>/dev/null | grep -v 'church-scheduler' ; \
>   echo '* * * * * /bin/bash /home/tcnikoro/bin/church-scheduler.sh' ) | crontab -
> crontab -l
> ```
>
> `crontab -l` must show exactly one entry; run it by hand once and read
> `storage/logs/scheduler.log` — `No scheduled commands are ready to run.` means the heartbeat
> is alive, `No such file or directory` means the PHP binary in the script is wrong (use the
> interpreter `php artisan schedule:list` prints). If Terminal is unavailable, ask the host to add
> `* * * * * /bin/bash /home/tcnikoro/bin/church-scheduler.sh` and to whitelist
> `Cron::add_line` for the account so the form works again.

### Weekly release of first timers who reached 8 weeks

`firsttimers:auto-unassign` releases every first timer who has had a guide for eight weeks or
more. The follow-up is no longer thrown away: the row is copied into
`member_tracking_followup_archive` **before** it is deleted, so the last guide and the last
comment stay visible under **`/weeklist/guide-history`** (Admins / Super Users; `?search=` and
`?reason=` filter it). The archive table is created on demand — the command applies its own
migration, and so do `app:sync-schema` and `app:post-deploy --migrate` — so nothing has to be
run by hand on the SFTP-only host.

Look before you leap, and run it by hand any time:

```bash
php artisan firsttimers:auto-unassign --dry-run   # who would be released
php artisan firsttimers:auto-unassign             # release + archive
```

**Alternative — if a weekly `schedule:run` is not possible:** `public/auto_unassign.php` runs the
same release over HTTP (`https://yourdomain.com/auto_unassign.php`; add `?dry-run=1` to look
first). Running it alongside the scheduler is harmless: a released first timer no longer has a
follow-up row, so the second run finds nothing to do.

**Verifying the cron is actually alive.** The tick is silent by design: its own output goes to
`/dev/null`, and the event is what writes `storage/logs/birthday-reminders.log` - a tick where
nothing is due writes nothing. An empty (or missing) log therefore means *"no run yet"*, not
*"broken"*:

```bash
cd ~/public_html/church.yourdomain.com

php artisan schedule:list      # expect 0 7 * * * with Next Due 06:00:00 +00:00 (07:00 Lagos)
php artisan schedule:run       # "No scheduled commands are ready to run." = the tick works
```

To prove the whole chain end to end without waiting for 07:00, nudge the send time to the next
minute or two in Lagos time:

```bash
cd ~/public_html/church.yourdomain.com

# .env: BIRTHDAY_SEND_AT="21:47"   (a couple of minutes ahead of Lagos "now")
php artisan config:clear && php artisan config:cache   # required - post-deploy caches the config
sleep 120
tail -n 40 storage/logs/birthday-reminders.log          # the run is appended here
```

Then restore `BIRTHDAY_SEND_AT="07:00"` and run `php artisan app:post-deploy` again. `--force`
re-sends a milestone that `birthday_reminder_logs` already recorded, so use it only while
testing.

> **One lock to know about:** `withoutOverlapping()` holds a cache mutex for up to 24 hours. If
> a run dies half way through, later ticks are skipped until it expires - `cache:clear` (which
> `app:post-deploy` already runs) releases it immediately.

Test it before leaving it to the scheduler:

```bash
cd ~/public_html/church.yourdomain.com

php artisan birthdays:remind --dry-run                  # show what the next run would do
php artisan birthdays:remind --days=3 --campus=1 --channel=mail --force
php artisan birthdays:remind --days=3,2,1,0             # include the birthday itself
```

Each digest goes out on three channels: `--channel=mail` (e-mail to every Welcome Center
admin with an address), `--channel=database` (the in-app **🔔 bell** + `/notifications`
page) and `--channel=telegram` (push to the bot chat already configured for airtime
alerts). Every attempt is written to `birthday_reminder_logs`, so the same milestone is
never sent twice — use `--force` to override that while testing.

#### Settings (optional)

Everything below has a working default and only needs adding to the server `.env` to
change it:

| Variable | Default | Purpose |
| --- | --- | --- |
| `BIRTHDAY_DAYS` | `3,2,1` | Milestones (days before the birthday) that trigger a reminder |
| `BIRTHDAY_SEND_AT` | `07:00` | Local time of the daily run |
| `BIRTHDAY_TIMEZONE` | `Africa/Lagos` | Timezone used to work out "today" |
| `BIRTHDAY_DEPARTMENT_IDS` | `28` | Departments whose **Admins** receive the e-mail (28 = Welcome Center) |
| `BIRTHDAY_CHANNELS` | `mail,database,telegram` | Channels used by the scheduled run |
| `BIRTHDAY_EXTRA_EMAILS` | *(empty)* | Extra recipients, comma separated |
| `BIRTHDAY_TELEGRAM_CHAT_IDS` | falls back to `TELEGRAM_CHAT_ID` | Telegram chats to push to |
| `BIRTHDAY_WISH_COUNTRY_CODE` | `234` | Country code prepended to phone numbers in the wish links |

Recipients are resolved from the database on every run, so a **newly appointed Welcome
Center admin is e-mailed automatically** — there is nothing to configure.

#### Database tables

The feature needs two tables and **creates them itself** the first time
`birthdays:remind` runs — it applies only its own two migration files. To create them by
hand (or if you prefer to see the output), run:

```bash
php artisan migrate --force \
  --path=database/migrations/2026_10_09_000001_create_birthday_reminder_logs_table.php \
  --path=database/migrations/2026_10_09_000002_create_app_notifications_table.php
```

> A plain `php artisan migrate` (and therefore `app:post-deploy --migrate`) can abort
> with `Base table or view already exists` on this database, because it was seeded
> before the `migrations` table was kept in step. Use the `--path` form above for this
> feature and let the command self-provision.

`birthday_reminder_logs` is the send history / de-duplication record (one row per campus
+ milestone + birthday date); `app_notifications` feeds the navbar bell and the
`/notifications` page.

### Route cache

`php artisan route:cache` runs cleanly. (The duplicate `transport.save-registration`
route name that previously blocked caching was removed on 2026-05-25 when the
transport feature was retired.)

## Schema sync (`app:sync-schema`)

The pipeline cannot run PHP on the server, so the two migrations that must reach the
database are applied by `php artisan app:sync-schema` instead — it runs from the daily
`schedule:run` cron (03:20 `Africa/Lagos`) and inside every `app:post-deploy`:

| What | Why it is needed | Result |
|------|------------------|--------|
| `password_resets`, `birthday_reminder_logs`, `app_notifications` | created by the migrations that shipped with the password reset flow, the birthday reminders and the notification bell | created when missing; a table that already exists is left untouched and its migration is recorded as applied, so a later `migrate` cannot abort with "table already exists" |
| `transport_routes`, `transport_stops`, `bus_attendance`, `fof_cohort_setting`, `fof_register_table`, `fof_mark_attendance_table` plus the `tiu_member` transport foreign keys | the Transport (bus route) and standalone FOF modules were retired on 2026-05-25 | dropped (`dropIfExists` / guarded drops, so a re-run is a no-op) |

The **files** of those retired modules are removed by the *Prune files deleted from the
repository* step of the deploy (`deploy-cpanel.yml`), which deletes exactly the paths a
commit deleted. Neither part needs phpMyAdmin or a manual SFTP delete.

Verify it, or apply it immediately without waiting for the schedule:

```bash
cd ~/public_html/church.yourdomain.com

php artisan app:sync-schema --dry-run   # what would change
php artisan app:sync-schema             # apply + print both state tables
php artisan schedule:list               # app:sync-schema should be listed next to birthdays:remind
```

The output ends with two tables: *Table the code needs* must read `present` for every row
and *Retired table* must read `gone` for every row — that is the proof the deployment
reached the database. (It applies a curated list of migrations rather than running
`migrate`: this database was seeded before the `migrations` table was kept in step, so an
older migration can abort a full run — see *Troubleshooting*.)

---

## 7. Verify the deployment

- `https://your-domain.com/` loads the login page. That *is* the login route
  (`GET /` -> `showLoginForm`, named `login`), so `https://your-domain.com/login`
  answers **404** — no such web route exists (`POST /api/login` is the phones'
  endpoint). That 404 is correct behaviour, not a fault.
- `https://your-domain.com/.env` returns **403**; `/artisan`, `/composer.json` and
  `/vendor/` return **404**, because the root `.htaccess` rewrites them into
  `public/` before the `FilesMatch` deny is reached. The rule that matters is
  *never* `200` and never a directory listing — proof the `.htaccess` is protecting
  application code.
- `https://your-domain.com/api/public/announcements?limit=1` returns **200** with
  `{"success":true,...}`. It is the one route whose path contains the segment
  `public`, so it also proves the rewrite still hands such URLs to Laravel rather
  than answering with the web server's own 404 (`RewriteRule ^public/ - [L]`).
- Upload a member photo; it appears under `public/display_photo` on the server.
- **Actions** → the run shows green with a summary table.

## Everyday workflow

```powershell
# make changes locally, test them
git add -A
git commit -m "Describe the change"
git push
```

Then, if the change included migrations or you want the caches rebuilt, run
`php artisan app:post-deploy --migrate` in cPanel Terminal.

Deploys are serialised per branch (`concurrency`), so two quick pushes queue instead of
colliding. Only changed files are transferred.

A push reaches **both** targets at once: the dev copy publishes immediately, and the live
copy waits for your approval ([§3e](#3e-gate-production-with-a-required-reviewer)). The
dev workflow accepts `main` and `master`; the production workflow accepts **`main`
only** — ship the live site from `main`.

**Shipping to the live site, end to end.** Nothing else publishes it, so the sequence is:

1. Work on a branch, open a pull request, merge it into `main` (or push straight to
   `main` if you are working alone — the review gate is what protects the live folder).
2. The merge starts **both** runs: *Deploy to cPanel* (dev) publishes on its own, while
   *Deploy to cPanel (production)* stops in **Waiting** before its first step.
3. Test the change on `dev.lit-grp.com`.
4. Go to **Actions → the production run → Review deployments → Approve and deploy**, or
   reject/cancel it if the dev test failed — nothing was uploaded up to that point.

**Optional: rehearse on dev only.** The production workflow is `main`-only, so pushing
the `main` commit to `master` deploys **dev alone** — no production run is created and
nothing waits for an approval. Useful for a first push that carries a workflow file:

```powershell
git push origin main:master     # dev only - watch it publish
git push origin main            # then the real thing: dev + a pending prod approval
```

`main:master` only works while `master` is an ancestor of `main` (a fast-forward).
If the remote `master` has commits `main` does not, the push is rejected — reconcile the
two branches instead of forcing it.

## Troubleshooting

| Symptom | Likely cause / fix |
| --- | --- |
| `Missing repository secret: CPANEL_HOST` | Secrets not added, or added as *variables* instead of *secrets*. |
| Deploy is green but the website is unchanged | `CPANEL_REMOTE_DIR` is wrong — the app was uploaded *beside* `public_html` instead of into it. Re-run `.\scripts\test-cpanel-connection.ps1` to see where you land. |
| Everything uploads on every single run | The state file `.ftp-deploy-sync-state.json` is not surviving between runs. Harmless — just slower. |
| `530 Login authentication failed` | Wrong password, or the FTP account was created for a different directory. Change the password and re-add the secret. |
| `getaddrinfo ENOTFOUND` / timeout | Wrong `CPANEL_HOST`, or the host blocks FTP. Try `sftp` (port 22) via the variables. |
| *Publish to cPanel (SFTP)* → `connect to host ... port 22: Connection timed out` | A **connect** timeout, not a login failure: the host or port in the `PROD_CPANEL_*` values is wrong, so the connection never reaches the server. The live folder is untouched (nothing is uploaded before the connection opens). See *SFTP publish times out on port 22* below. |
| TLS/certificate errors | Keep `security: loose` (already set) or switch to `sftp`. |
| Deploy succeeded but the site shows an old page | Run `php artisan app:post-deploy` — `config:cache`/`view:cache` are stale. |
| `Base table or view already exists` while migrating | The legacy database was seeded before the `migrations` table was kept in step, so an old migration aborts the run. Migrate the file you actually need with `--path` (see *Scheduled jobs*) — `birthdays:remind` also self-provisions its own tables. |
| No birthday reminder e-mail arrives | The status was already recorded in `birthday_reminder_logs` (columns `campus_id`, `days_before`, `reminder_date`). Re-test with `php artisan birthdays:remind --days=3 --force --channel=mail`, and check the recipients: only `member_role` `Admin`/`Super User` members whose `department_name` JSON contains the configured department id receive it. |
| Bell shows no numbers / `/notifications` is empty | Nothing has been written to `app_notifications` yet, or the logged-in member is not a Welcome Center admin. Run `php artisan birthdays:remind --days=3 --channel=database --force`. |
| Birthday reminders still not running | The `schedule:run` cron entry is missing, uses the wrong PHP binary, or is set to *Once Per Day* (`0 0 * * *`) instead of *Once Per Minute* (`* * * * *`) — a daily tick never coincides with the `07:00` due time, so nothing ever fires. See *Scheduled jobs*. Verify with `php artisan birthdays:remind --dry-run` over SSH/Terminal. |
| Cron Jobs page: *AJAX Error — Please refresh the page and try again* | The save request is blocked **before** cPanel by the host WAF and returns **406 Not Acceptable** (curl it and you get that page instead of JSON). The cron-injection rule matches a line beginning `* * * * *` and carrying `>> /dev/null 2>&1`, so the form can never save — no syntax change fixes it. Install the entry from cPanel Terminal with `crontab -l \| grep -v …`; see *Scheduled jobs*. |
| `Please provide a valid cache path` | Missing `storage/framework/*` directories. `php artisan app:post-deploy` creates them. |
| 500 error, blank page | Set `APP_DEBUG=true` temporarily in the server `.env`, load the page, read the message, then set it back to `false`. |
| Assets missing (CSS/JS) | The `public/assets` and `public/vendors` folders were not uploaded. Check the *Publish to cPanel* step log for skipped files. |
| Site redirects in a loop | A second copy of the root `.htaccess` or a conflicting rewrite rule exists in `public_html`. |

### SFTP publish times out on port 22

Production run `38922972663` (2026-10-16) failed exactly this way: steps 1–11 green —
including *Validate deployment secrets*, so the names and values were all present and
non-empty — and then step 12 died with

```
mkdir: Fatal error: max-retries exceeded (connect to host *** port 22: Connection timed out)
mirror: Fatal error: max-retries exceeded (connect to host *** port 22: Connection timed out)
```

Nothing was uploaded (the connection is opened before the first file is transferred, and the
prune step is skipped), so the live folder stays exactly as it was. Measured the same day:

| Target | port 22 |
| --- | --- |
| `ftp.tcnikorodu.org` = `147.124.220.188`, reverse DNS `emerald3.doveserver.com` — **the production server** | **open**, offers `publickey,password` → this is the hostname `PROD_CPANEL_HOST` must hold |
| `lit-grp.com` / `ftp.lit-grp.com` = `147.124.220.162` — the **dev** account's server | open, but it is a *different* machine: the production account does not exist there |
| `tcnikorodu.org` (bare domain) = `216.198.79.1` | **times out** — the apex is served by another platform, not by cPanel |
| port `2222` on the production server | **closed** — only `22` answers (`21` and `2083` are open, but SFTP must use 22) |

Verify the pair from Windows before you re-run — the port answer must be `True`:

```powershell
Test-NetConnection ftp.tcnikorodu.org -Port 22
```

cPanel → **SSH Access** (on the production account) prints the same hostname and port. The
secrets are read when the job **starts**, so a run still sitting in *Waiting* picks up the
corrected values — save them, then approve; there is no need to re-run anything.

## Security notes

- `.env`, `storage/`, `vendor/` and `app/` are unreachable over HTTP thanks to the root
  `.htaccess`, which funnels every request into `public/` and denies `.env`/`composer.*`
  even if the rewrite is bypassed.
- Deletions are propagated: the *Prune files deleted from the repository* step removes
  exactly the paths a commit deleted, and its protected-prefix list keeps everything
  that only exists on the server (`.env`, `storage/**`, `bootstrap/cache/**`,
  `public/uploads/**`, `public/display_photo/**`, `public/gallery_uploads/**`,
  `public/member_photos/**`, `public/storage`, `.well-known/**`) out of reach. A dry run
  of the same diff is
  `git diff --no-renames --diff-filter=D --name-only <last-deployed-sha> HEAD`.
- Rotate the cPanel password if it is ever pasted anywhere other than the GitHub secrets
  form. The Telegram bot token currently in your local `.env` should be rotated too, as
  it has been shared in plain text.
- Consider a dedicated FTP account limited to the deploy directory instead of the main
  cPanel account.

## Alternative: SSH / git-based deploys

If your host enables SSH and cPanel → **Git Version Control**, you can clone this repo on
the server and pull instead of uploading over FTP. cPanel runs the tasks listed in a
`.cpanel.yml` file placed at the repository root. The FTP pipeline above needs no such
configuration, so it is the default here — but ask if you would like the SSH variant set
up as well.

