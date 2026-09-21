# Deployment — Bicycle Workshop Management App

**Phase:** 11C — Deployment Hardening & Operations Cleanup, following the completed real staging deployment, plus a Phase 11C follow-up (§0.5) hardening `docker/entrypoint.sh` against a stale Laravel config cache.
**Status:** the app has been **deployed and manually validated** on staging: `https://bikeworkshop.storm-ark.com`, release `3d37101a3ba2ad0b6ecfce222979cc982d667c5a`, branch `claude/bicycle-workshop-app-6mp087`. A subsequent staging credential rotation exposed a real operational defect (stale `bootstrap/cache/config.php` breaking `migrate --force`) — see §0.5 for the incident and its repository-level fix. Sections below marked **[Phase 11A — pre-deployment assumption]** were written before that deployment and are superseded by §0 and the notes layered into each section; they are kept for historical/audit-trail reasons, not as current guidance.
**This phase's changes are repository-only.** No live/staging VPS was accessed, no SSH or remote server operation was performed, and no application business logic changed — Phase 11C (and this follow-up) fixes deployment tooling (`Dockerfile`, `docker/entrypoint.sh`, `compose.yaml`, plus a new `compose.override.yaml`) and this runbook, based on lessons from the real deployment and the credential-rotation incident described in §0.

---

## 0. Phase 11C — what changed, and why (read this first)

The real staging deployment (§28's sequence, executed manually against the live VPS, not by this or any automated phase) succeeded, but surfaced three real deployment weaknesses. This phase's repository changes exist to fix or clearly document each one. Nothing here was verified against the live VPS itself (out of scope, §"Status" above) — these are repository-level fixes plus documentation of what was observed and manually corrected on the server.

### 0.1 Stale `public/build` volume — root cause and fix

**What happened:** `compose.yaml` bind-mounts the whole repo over `/var/www/html`, then shadows `vendor/` and `public/build/` with named volumes (`bicycle_workshop_vendor`, `bicycle_workshop_build`) so the image's own build output isn't hidden by the (gitignored, therefore empty-on-checkout) host directories at those paths. Docker's actual behavior for a named volume is: **it is seeded from the image's content only the first time it is used** (i.e. the first time a container mounts an empty/nonexistent volume at that path). Rebuilding the image and recreating the container does **not** re-seed an already-existing, already-populated volume — Docker has no mechanism that does this automatically. During the real deployment, the image was rebuilt with new hashed asset filenames (`app-DLoW7O-p.css` replacing `app-B4CkT01A.css`), but the already-existing `bicycle_workshop_build` volume kept serving the previous deployment's assets until it was manually removed (`docker compose stop app && docker compose rm app && docker volume rm bicycle_workshop_build && docker compose up -d app`) and Docker re-seeded the now-empty volume from the new image.

**Fix (this phase):** the `Dockerfile` now additionally copies its own `vendor/` and `public/build/` into a pristine, never-mounted location, `/opt/release-artifacts/`, alongside a checksum of whatever determines each artifact's content (`composer.lock` for `vendor`, `public/build/manifest.json` for the frontend build — see the `Dockerfile`'s own comments for why those two files). `docker/entrypoint.sh` now compares that checksum against a `.release-checksum` marker it maintains inside each named volume on **every** container start, and refreshes the volume from `/opt/release-artifacts/` whenever they differ (including "volume exists but has no marker yet" — i.e. a volume from before this change, or one Docker seeded natively on first use). A plain `docker compose build && docker compose up -d` can therefore never again leave a rebuilt image's dependencies or assets shadowed by a stale volume — the manual removal step the real deployment required is no longer necessary and should not be needed on future deployments.

This preserves the existing local-dev bind-mount workflow exactly as-is (nothing about editing PHP/Blade/JS source changes) and does not touch the MySQL data volume at all.

### 0.2 Was `vendor/` exposed to the same risk?

**Yes, structurally identical.** `vendor/` is shadowed by its own named volume (`bicycle_workshop_vendor`) for exactly the same reason `public/build` is, and Docker's "seed once, never again" volume behavior applies to it identically. The real deployment did not happen to trigger it (`composer.lock` did not change in that release), but a future release that adds/updates a Composer dependency would hit the same class of bug: the rebuilt image's new `vendor/` would sit unused behind the existing volume's older dependency set, with no error — just subtly wrong/missing classes at runtime. The fix in §0.1 (checksum-compared against `composer.lock`) applies identically to `vendor/` and closes this before it can happen for real.

### 0.3 Network exposure — compose.yaml's staging-safe defaults

**What happened:** the desired staging security posture (app reachable from the host only via `127.0.0.1:8013`; MySQL not published to the host at all) required editing `compose.yaml` directly on the VPS, leaving that checkout permanently and intentionally dirty (`M compose.yaml`) with no way to `git pull` a future update without re-losing or re-applying that edit by hand, plus a stray untracked `compose.yaml.backup-20260920`.

**Fix (this phase):** `compose.yaml`'s own defaults are now the staging-safe ones — `app` publishes only to `127.0.0.1:${APP_PORT:-8013}`, and `mysql` publishes no host port at all. A new, git-tracked `compose.override.yaml` restores the previous local-dev convenience (a host-published MySQL port for GUI clients) — Compose auto-merges any `compose.override.yaml` present next to `compose.yaml` for a plain `docker compose ...` command, with no flag needed. Staging/production sets **one** `.env` line, `COMPOSE_FILE=compose.yaml`, so every `docker compose ...` command there loads only the safe base file and ignores the override — no edit to any tracked file is needed, and a future `git pull`/rebuild never conflicts with or silently reverts a manual security fix again. See §27 and §32 for the network-exposure detail this replaces, and `compose.yaml`/`compose.override.yaml`'s own comments for the mechanism.

This was validated statically with `docker compose config` (default local-dev merge → app on `127.0.0.1:8013` + mysql on `127.0.0.1`-unbound `3348`; `COMPOSE_FILE=compose.yaml` → app on `127.0.0.1:8013` only, no `ports:` key at all for `mysql`) — see §"Docker/Compose validation" for the exact commands and output. It was not deployed or tested against the live VPS.

### 0.4 Other Phase 11C findings (no repository change)

- **`php artisan migrate --force` on every container start:** retained as-is. See §8/§30 for the full reasoning — briefly, migrations here are fast, forward-only, and additive, and the maintenance-mode-first deployment sequence (§28) is what actually protects against surprise schema changes hitting live traffic, not preventing the entrypoint from running `migrate --force`. What changed is documentation, not behavior: §30 more explicitly calls out that `docker compose up -d app` (not just a full deploy) can run migrations against the configured database, so it must never be run casually against a staging/production `.env`.
- **Backup command:** §21/§28 now specify `mysqldump --no-tablespaces` using the application's own DB user (not root), matching what the real deployment actually needed (a normal `mysqldump` failed without it — the app's DB user lacks the `PROCESS` privilege that tablespace metadata collection requires) and verified by exit code, file size, SHA-256, and the dump's own completion footer. `PROCESS` is deliberately not granted just to make an unqualified `mysqldump` work — `--no-tablespaces` is the correct least-privilege fix, not a workaround to route around.
- **`.env` backup:** §22 now explicitly calls out backing up the VPS `.env` before any deployment or configuration change, alongside the database backup — not a new requirement, just made explicit as its own checklist step given how the real deployment used it.
- **`APP_URL`:** §7/§17 already called for `https://bikeworkshop.storm-ark.com`, exactly right — the real deployment briefly ran with the `.env.example` default (`http://localhost:8013`) before this was corrected. No document was wrong; the runbook now calls this out as a specific, easy-to-miss step in the deployment sequence (§28) rather than only in the environment-checklist table.
- **Legacy `staff@example.com` demo account:** `DemoAccountSeeder::shouldRun()` (§9) is confirmed still correct and was never the cause — this account predates Phase 10B's hardening and was created before that guard existed. §9 now documents how to review/remove such legacy accounts manually; **no repository code was added to auto-delete users**, per this phase's explicit instructions, and no such cleanup was performed against the live database.
- **Apache `AH00558` `ServerName` warning:** investigated and deliberately left unchanged — see §16 for the conclusion.

### 0.5 Stale `bootstrap/cache/config.php` breaking migration after a credential rotation — root cause and fix

**What happened (post-Phase-11C, real staging credential rotation):** staging's `DB_PASSWORD` was rotated (the `.env` value and the MySQL `sail`@`%` password were both changed to the same new value, and direct MySQL authentication with it succeeded). The `app` container was then recreated. It entered a restart loop with `SQLSTATE[HY000] [1045] Access denied for user 'sail'`.

**Root cause, confirmed by reading the entrypoint and Laravel's own config-loading behavior:** `compose.yaml` bind-mounts the whole repo (`.:/var/www/html`), so `bootstrap/cache/` — like `.env` — persists on the host across every container recreation. At some point before the rotation, `php artisan config:cache` had been run (the optional performance step this runbook itself recommends, §12/§28 step 11), which compiled `.env`'s *then-current* `DB_PASSWORD` into `bootstrap/cache/config.php`. Laravel always prefers a present config cache file over re-reading `config/*.php` + `.env` — editing `.env` does **not** invalidate it. `docker/entrypoint.sh` **did** call `php artisan config:clear` on every boot, but only as its next-to-last step, *after* `php artisan migrate --force` had already run. So `migrate --force` booted against the stale cached password, authentication failed, the entrypoint (`set -euo pipefail`) exited non-zero, and Docker's `restart: unless-stopped` policy kept recreating the container into the same failure.

**Fix:** `docker/entrypoint.sh` now deletes `bootstrap/cache/config.php` specifically (not source-controlled — covered by the wildcard ignore in `bootstrap/cache/.gitignore`, confirmed by reading it; not produced by the image at build time) **unconditionally, at the start of every container boot, before any `php artisan` command runs** — not just before `migrate --force`, but before the `key:generate` check too. The old post-migrate `config:clear` call is removed as redundant: by the time execution reaches where it used to run, no `config.php` cache file has existed for the entire boot. See `docker/entrypoint.sh`'s own comment above `clear_stale_config_cache()` for the full reasoning, and `docker/entrypoint.test.sh` for a regression test.

**Why this is safe, and why it's scoped to `config.php` only:**
- `rm -f` is a silent no-op when the file doesn't exist — safe on a first-ever boot (no cache file yet), an ordinary restart (`.env` unchanged, nothing was cached), and this exact incident (`.env` changed, the cache file exists) alike. Idempotent by construction (requirement: startup must be idempotent).
- `config.php` is not source-controlled and not written by the `Dockerfile` at image-build time — it only exists because an operator ran a `config:cache`-style command against filesystem/`.env` state that may since have changed, so deleting it can never lose anything that isn't trivially regenerable.
- **Deliberately not extended to the other generated `bootstrap/cache/*.php` files** (`routes-v7.php`, `events.php`, `services.php`, `packages.php`): only `config.php` causes Laravel to ignore a changed `.env` — that's the specific mechanism behind `SQLSTATE[HY000] [1045]` here. The route/event caches and the Composer package-discovery manifest (`services.php`/`packages.php`) were not responsible for this incident, and clearing them too would needlessly invalidate unrelated production optimizations on every ordinary restart, not just after a credential rotation. Package-discovery caches in particular are not `.env`-derived configuration and should not be treated as equivalent to it.
- This does **not** touch the application's own `cache`/`cache_locks` database tables (`php artisan cache:clear`, part of `optimize:clear` but deliberately *not* used here) — that requires migrations to have already run and a live DB connection, which is exactly the ordering problem this fix avoids depending on. It also doesn't touch `storage/framework/views` (Blade view cache) — irrelevant to this incident and never written to automatically by this entrypoint.

**File ownership — no host-side `sudo` dependency:** during the real incident, recovery required deleting `bootstrap/cache/config.php` by hand, but it was `www-data`-owned from a previous container's `chown -R www-data:www-data storage bootstrap/cache` (`docker/entrypoint.sh`, unchanged by this fix), and the deploy user lacked permission/`sudo` to remove it directly on the host. The container itself always runs as **root** (no `USER` directive in the `Dockerfile`, confirmed by re-reading it) — root can delete a `www-data`-owned file inside its own container (and, because of the bind mount, on the host filesystem too) unconditionally. Because the new `clear_stale_config_cache` step now runs automatically, as root, on every boot, an operator should never again need to manually delete this file — with or without `sudo` — to recover from this class of failure.

**Consequence for the optional Laravel optimization caches (§12/§28 step 11/§33):** `config:cache` specifically is now **ephemeral, not persistent** — every subsequent container start (not just one after an `.env` change) clears it again, so an operator who wants that optimization active must re-run `php artisan config:cache` after every `docker compose up -d`/restart, not just once after a deploy. `route:cache` and `view:cache` are unaffected by this fix and remain persistent across restarts exactly as before. This is a deliberate, narrowly-scoped safety-over-performance tradeoff for `config.php` alone: it guarantees requirement "startup always uses the current `.env`" unconditionally, at the cost of the config optimization needing to be reapplied more often than before, while leaving every other production optimization untouched.

---

## 1. Deployment model: this is a Docker Compose deployment, not a bare-metal one

Read this section first — it reframes several of the "standard Laravel deployment" questions below, because this application's actual deployment model already answers them differently than a typical Nginx+PHP-FPM VPS setup would.

Per `README.md`, `docs/PROJECT_STATUS.md` §3, and `docs/HANDOFF.md`: **Docker Compose (`compose.yaml` at the repo root) is the one supported deployment workflow, used identically for local development and the VPS.** There is no separate "build server," no CI pipeline, and no bare-metal Composer/npm install step against the live server's PHP/Node — all of that happens inside the Docker image build (`docker compose build`).

Confirmed components (all read directly from the repository):

| Piece | What it does |
|---|---|
| `Dockerfile` | `php:8.4-apache-bookworm` base image. Installs `pdo_mysql`, `mbstring`, `bcmath`, `zip` PHP extensions, Node 22 (via NodeSource), Composer 2 (copied from the official `composer:2` image). Runs `composer install --optimize-autoloader` (not `--no-dev` — deliberate, see §15) and `npm ci && npm run build` **at image-build time**, then `chown -R www-data:www-data storage bootstrap/cache`. |
| `docker/apache/000-default.conf` | Apache vhost with `DocumentRoot /var/www/html/public` — Laravel's `public/` is already the web root (§17). |
| `docker/entrypoint.sh` | Runs on every `app`/`vite` container start. Deletes a stale `bootstrap/cache/config.php` left over from a previous boot, if present, **before anything else** (Phase 11C follow-up — see §0.5; the route/event/package-discovery caches are deliberately left alone), creates `.env` from `.env.example` only if missing (never overwrites an existing one), refreshes the `vendor`/`public/build` named volumes from the image whenever they're stale (Phase 11C — see §0.1), generates `APP_KEY` only if missing, `chown`s `storage`/`bootstrap/cache`, waits for MySQL to accept TCP connections, then **always** runs `php artisan migrate --force` (forward-only, confirmed non-destructive — see §8) against a guaranteed-fresh read of `.env`, optionally seeds if `DB_SEED_ON_BOOT=true` (default `false` everywhere including the VPS), then hands off to `apache2-foreground`. |
| `compose.yaml` | `app` (built from `Dockerfile`, published on `127.0.0.1:${APP_PORT:-8013}` — Phase 11C, see §0.3/§27) + `mysql` (official `mysql:8.4` image, no host port published by default — Phase 11C, see §0.3/§27 — named volume `bicycle_workshop_mysql_data`) + optional `vite` (dev-only, `profiles: ["dev"]`, never started by a plain `up -d`). `compose.override.yaml` restores a host-published MySQL port for local dev only (§0.3). Every container/volume/network name is prefixed `bicycle_workshop_` because the VPS also runs other, unrelated apps (Pickleverse, Company App) — never touch unprefixed resources. |

**What this means for "deployment" in practice:** deploying a new commit means rebuilding the `app` image (which reruns `composer install`/`npm run build` inside the image) and recreating the container (whose entrypoint reruns `migrate --force`) — not SSHing in and running `composer install`/`npm run build`/`php artisan migrate` by hand against a bare checkout. §28 reflects this.

**Reverse proxy and real staging request path (confirmed by the completed staging deployment, not a [Phase 11B] placeholder anymore):**

```
Internet
  -> Cloudflare (in front of the staging domain)
  -> host Nginx, listening on :443 (HTTPS/Let's Encrypt/Certbot — configured on the host, outside this repository)
  -> 127.0.0.1:8013 (the app container's published port — Phase 11C binds this to loopback only, see §0.3)
  -> the `app` container's Apache, listening on container port 80
  -> Laravel
```

`bootstrap/app.php`'s `$middleware->trustProxies(at: '*')` means Laravel trusts `X-Forwarded-Proto`/`X-Forwarded-Host`/`X-Forwarded-For` from whatever sits immediately in front of it — here, host Nginx — which is exactly why `url()`/`route()`/`asset()` correctly generate `https://bikeworkshop.storm-ark.com/...` links even though Apache inside the container only ever sees plain HTTP. The host Nginx vhost's exact configuration file lives on the VPS, outside this repository, and was not created, inspected, or modified by this phase (per its own repository-only scope) — this section documents the request path that was verified working end-to-end during the real deployment (public HTTPS root and `/up` both returned 200), not the vhost file's literal contents.

---

## 2. Runtime requirements (verified, not assumed)

Confirmed by reading `composer.json`, `package.json`, `Dockerfile`, and by running these tools directly in this audit environment:

| Requirement | Value | Source |
|---|---|---|
| PHP | 8.4 (composer.json requires `^8.3`; the Docker image pins `8.4-apache-bookworm`) | `composer.json`, `Dockerfile` — confirmed running `php -v` → 8.4.19 |
| PHP extensions | `pdo_mysql`, `mbstring`, `bcmath`, `zip` explicitly installed; everything else (`openssl`, `pdo`, `tokenizer`, `xml`, `ctype`, `json`, `curl`, `mbstring` base, etc.) ships with the `php:8.4-apache` base image | `Dockerfile` line 23 |
| Composer | 2.x | `Dockerfile` (`COPY --from=composer:2 ...`) — confirmed 2.8.12 locally |
| Laravel Framework | 13.25.0 (composer.json requires `^13.17`) | `composer.json` |
| Node.js | 22.x | `Dockerfile` (NodeSource `setup_22.x`) — confirmed `v22.22.2` locally |
| npm | ships with Node 22 | confirmed `10.9.7` locally |
| MySQL | 8.4 (official `mysql:8.4` image) | `compose.yaml` |
| Web server | Apache (`php:8.4-apache-bookworm`), **not** Nginx/PHP-FPM, **not** `php artisan serve` | `Dockerfile` (`apache2-foreground`) |
| Test runner | PHPUnit ^12.5.12 via `php artisan test` | `composer.json` |
| Code style | Laravel Pint ^1.27, no custom `pint.json` (uses Laravel's default preset) | `composer.json` |

**Filesystem:** `storage/` and `bootstrap/cache/` must be writable by the process user (§16). **`public/`** must be the web root (§17). **No storage symlink is required** — see §5.

**Session/cache/queue/mail drivers actually used** (from `.env.example`, cross-checked against config files and code — see §4–§6 for why each is safe as-is):
- `SESSION_DRIVER=database` (uses the `sessions` table, bundled in the base `users` migration — no separate migration needed)
- `CACHE_STORE=database` (uses the `cache`/`cache_locks` tables)
- `QUEUE_CONNECTION=database` — **configured but not exercised by any application code** (§4)
- `MAIL_MAILER=log` — mail is only used by Laravel Breeze's own password-reset flow, not by any application feature (§6)
- `FILESYSTEM_DISK=local` — configured but unused; no file uploads exist anywhere in the app (§7)
- `LOG_CHANNEL=stack` → `LOG_STACK=single` → `storage/logs/laravel.log`, no rotation (§20)

**External services:** none. No third-party API keys are read anywhere in `app/` beyond the unused `config/services.php` defaults (AWS S3, Slack, Resend — none referenced by application code). No payment processor, no SMS/push provider, no analytics/error-tracking SaaS.

---

## 3. Scheduler audit — not required

`routes/console.php` contains only the framework's default `inspire` Artisan command:

```php
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
```

No `Schedule::` calls exist anywhere (`routes/console.php`, `bootstrap/app.php`, `app/Providers/`). `app/Providers/AppServiceProvider.php` is empty (`register()`/`boot()` are both no-ops).

**No application feature depends on `php artisan schedule:run`.** Cron/`schedule:run` is **not required** for V1. Do not configure it in Phase 11B unless a future phase adds a scheduled feature.

---

## 4. Queue audit — no queue worker required

Searched `app/` for every queue-related signal:
- `ShouldQueue`: zero matches.
- `->dispatch(`, `::dispatch(`, `Dispatchable`: zero matches. No `app/Jobs/` directory exists.
- Queued notifications: `App\Notifications\BookingStatusUpdated::via()` returns `['database']` only (confirmed by reading `app/Notifications/BookingStatusUpdated.php`) — this is a **synchronous** database-channel notification, not a queued one; it does not implement `ShouldQueue`.
- `jobs`/`job_batches`/`failed_jobs` tables exist (bundled in the base Laravel migrations) but are never written to by any code path.
- `QUEUE_CONNECTION=database` in `.env.example` is the Laravel default and is not acted on by anything.

This matches `docs/PROJECT_STATUS.md` §22.4/§22.6's own documented scope: Phase 8's notifications are deliberately synchronous, specifically to avoid needing queue infrastructure.

**No queue worker is required for the current V1.** Do not run `php artisan queue:work`, do not add Supervisor/Horizon, and do not change `QUEUE_CONNECTION` — there is nothing for a worker to process.

---

## 5. Mail audit — not required for any V1 workflow; needed only if password reset must actually deliver

Searched `app/` for `Mail::`: zero matches. No application code sends mail directly.

Two places *can* send mail, both inherited unmodified from the Laravel Breeze scaffold:
1. **Password reset** (`Auth\PasswordResetLinkController`, reachable from `/forgot-password`) sends Laravel's built-in `ResetPassword` notification over the `mail` channel. This is a real, user-reachable feature (the "Forgot your password?" link on the login screen) and is covered by `tests/Feature/Auth/PasswordResetTest.php`.
2. **Email verification** (`Auth\EmailVerificationNotificationController`) can send Laravel's `VerifyEmail` notification, but — confirmed via `docs/PROJECT_STATUS.md` §5 and by reading `app/Models/User.php` — `MustVerifyEmail` is commented out and no route applies the `verified` middleware, so **verification is never enforced**. This path exists but nothing in the app requires it to work.

**Conclusion:** V1's core repair workflow (booking, staff management, notifications, service history) sends **zero** email — it only uses the synchronous `database` notification channel (§4). The only V1-adjacent feature that needs a working SMTP provider is the "Forgot password?" flow. With `MAIL_MAILER=log` (the `.env.example` default), password-reset requests still complete successfully from the user's perspective at the request stage, but the email is written to the log file instead of delivered — a user cannot actually complete a password reset this way.

**Recommendation for Phase 11B:** decide whether staging needs working password-reset email. If yes, configure a real `MAIL_MAILER`/SMTP provider in the (untouched-by-this-phase) staging `.env`; if not, leave `MAIL_MAILER=log` and document that password reset is non-functional on staging. Either way, this is a Phase 11B `.env` decision, not a code change.

---

## 6. Storage/upload audit — no persistent uploads exist; `storage:link` is not required

Searched `app/` and `resources/views/` exhaustively for every file-writing/serving signal the audit asked for:

| Searched for | Result |
|---|---|
| `Storage::` | zero matches anywhere in `app/` or `resources/` |
| `->store(`, `->storeAs(` | zero matches |
| `public_path(`, `storage_path(` | zero matches in `app/` (only in framework `config/*.php` defaults) |
| `type="file"` in any Blade view | zero matches — no file input exists in any form in the app |
| Repair photos, bicycle photos, profile images, generated documents/exports | none exist — confirmed against `docs/PROJECT_STATUS.md`/`docs/DATABASE.md`, which document every column in every table; no image/file/path column exists on any model |

`config/filesystems.php` defines the standard Laravel `local`/`public`/`s3` disks (all framework defaults, `FILESYSTEM_DISK=local`), and the standard `storage:link` mapping (`public_path('storage') → storage_path('app/public')`) is present in config — but **nothing in the application ever writes to or reads from any of these disks.**

**Conclusions:**
- `php artisan storage:link` is **not required** — no code path serves a file through it, and the Dockerfile/entrypoint don't run it.
- The only persistent, non-reproducible filesystem data this app has is **the MySQL database itself** and the **`.env` file** (§22–§23) — there are no uploaded files, generated documents, or exports to back up.
- `storage/logs/` is operational (application log output), not business data — see §20 for its own handling.

Do not implement upload functionality in this phase — this section is audit-only, confirming the current (correct) absence of it.

---

## 7. Environment configuration audit

Derived from `.env.example` cross-referenced with actual usage in `config/*.php` and `app/`. Only variables that materially matter to this V1 are listed — Laravel ships many more defaults in `.env.example` that this app never reads differently from the framework default and that don't need operator attention.

### Application
| Variable | `.env.example` default | Staging/production guidance |
|---|---|---|
| `APP_NAME` | `"Bike Workshop"` | Cosmetic; keep as-is or set to taste |
| `APP_ENV` | `local` | **Must be `production` (or a distinct non-`local`/`testing` value) on the VPS** — see §9/§10 for why this matters beyond convention |
| `APP_KEY` | empty | **Generate once, never regenerate** — see §12 |
| `APP_DEBUG` | `true` | **Must be `false`** on any real deployment (§10, §21) |
| `APP_URL` | `http://localhost:8013` | **Must be `https://bikeworkshop.storm-ark.com`** exactly (§18) |

### Database
| Variable | Notes |
|---|---|
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | `mysql` in the Docker network (the Compose service name) — **never** a host-facing port number |
| `DB_PORT` | `3306` (the container-internal port, not `DB_FORWARD_PORT`) |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Real staging/production values live only in the untouched VPS `.env` — never in git, never in this document |

### Session
`SESSION_DRIVER=database` (no Redis/Memcached needed — see §8's note on the bundled `sessions` table), `SESSION_LIFETIME=120`, `SESSION_ENCRYPT=false`, `SESSION_PATH=/`, `SESSION_DOMAIN=null`. **`SESSION_SECURE_COOKIE` is not set in `.env.example`** (defaults to Laravel's `null`, meaning the cookie's `Secure` flag isn't explicitly forced) — since the site is HTTPS-only, Phase 11B should consider setting `SESSION_SECURE_COOKIE=true` explicitly in the real `.env` for defense-in-depth (not changed by this phase).

### Cache
`CACHE_STORE=database` — uses the bundled `cache`/`cache_locks` tables, no Redis/Memcached required. `REDIS_*`/`MEMCACHED_*` variables exist in `.env.example` only as unused framework defaults.

### Queue
`QUEUE_CONNECTION=database` — present but inert (§4). No operator action needed.

### Mail
`MAIL_MAILER=log` by default. See §6 for the staging decision this implies.

### Filesystem
`FILESYSTEM_DISK=local` — unused (§6). No operator action needed.

### Docker-only (read by Compose, not by Laravel)
`APP_PORT` (default `8013`), `DB_FORWARD_PORT` (default `3348`), `VITE_FORWARD_PORT` (default `5175`), `DB_SEED_ON_BOOT` (**must stay `false`** on the VPS — it's already `false` by default and nothing in this audit found a reason to change that).

---

## 8. Database deployment audit

All 16 migrations (3 framework + 13 application, per `docs/DATABASE.md`) were re-verified in this audit by running `php artisan migrate --force` against a fresh, empty database — **all 16 ran cleanly, in order, with no errors**:

```
0001_01_01_000000_create_users_table .......................... DONE
0001_01_01_000001_create_cache_table ........................... DONE
0001_01_01_000002_create_jobs_table ............................ DONE
2026_08_18_100803_add_role_and_phone_to_users_table ............ DONE
2026_08_18_154634_create_bicycle_types_table ................... DONE
2026_08_18_154635_create_bicycles_table ........................ DONE
2026_08_18_234826_create_bicycle_part_categories_table ......... DONE
2026_08_18_234827_create_bicycle_parts_table ................... DONE
2026_08_18_234828_create_bookings_table ........................ DONE
2026_08_18_234829_create_booking_items_table ................... DONE
2026_08_19_002832_create_booking_status_histories_table ........ DONE
2026_08_19_004037_add_assigned_technician_id_to_bookings_table . DONE
2026_08_19_004038_create_repair_inspections_table .............. DONE
2026_08_19_004039_create_repair_items_table .................... DONE
2026_08_19_004040_create_technician_notes_table ................ DONE
2026_09_20_000001_create_notifications_table ................... DONE
```

- **Every migration is additive** (`Schema::create` or a single `Schema::table` adding columns). None contains `Schema::drop`, `dropColumn`, or `truncate` in its `up()` method (those only appear symmetrically in `down()`, as expected).
- **None assumes demo/development data** — no migration seeds rows or references `customer@example.com`-style data.
- `php artisan migrate --force` is **safe to run on staging/production** exactly as `docker/entrypoint.sh` already does on every container start.
- **Master/reference data required for the app to function:** `bicycle_types` (10 rows) and `bicycle_part_categories`/`bicycle_parts` (8 categories, ~50 parts) — without these, the booking wizard has nothing to populate its dropdowns/checkboxes with. These are **not** created by a migration; they come from seeders (§9).

**Production/staging initialization sequence:**
1. `php artisan migrate --force` (schema — already automatic via `docker/entrypoint.sh`).
2. `php artisan db:seed --force` **once**, to populate `bicycle_types`/`bicycle_part_categories`/`bicycle_parts` (idempotent — safe to repeat, see §9). This step is **not** automatic (`docker/entrypoint.sh` only seeds when `DB_SEED_ON_BOOT=true`, which defaults to `false`) — it must be run manually once per environment, exactly as `README.md`'s existing "Demo accounts" section already documents for local dev.

**Confirmed by the real deployment:** the manually-tested customer booking wizard successfully exercised its bicycle-type/part dropdowns, which requires this initial `db:seed` to have already been run on staging.

---

## 9. Seeder audit

`database/seeders/DatabaseSeeder.php` (current implementation, Phase 10B):

```php
public function run(): void
{
    $this->call(BicycleTypeSeeder::class);
    $this->call(BicyclePartSeeder::class);

    if (DemoAccountSeeder::shouldRun(app()->environment())) {
        $this->call(DemoAccountSeeder::class);
    }
}
```

**What `php artisan db:seed` does per environment:**

| Environment | `bicycle_types`/`bicycle_part_categories`/`bicycle_parts` | Demo accounts (`customer@example.com`, `staff@example.com`, `technician@example.com`, password `password` for all) |
|---|---|---|
| `local` | Seeded (idempotent — existence-checked) | **Created** (`DemoAccountSeeder::shouldRun('local')` → `true`) |
| `testing` | Seeded | **Created** (`shouldRun('testing')` → `true`) — this is why the test suite's own `DatabaseSeederTest` can assert they exist |
| `staging` (or any custom value) | Seeded | **Not created** (`shouldRun('staging')` → `false`, verified directly by `tests/Unit/DemoAccountSeederTest.php`) |
| `production` | Seeded | **Not created** (`shouldRun('production')` → `false`) |

This is a pure function (`DemoAccountSeeder::shouldRun(string $environment): bool`), independently re-verified in this audit by reading the method directly — it checks `in_array($environment, ['local', 'testing'], true)` with no other logic, so the guard cannot be bypassed by anything except literally setting `APP_ENV` to `local` or `testing` on a real deployment (which nothing in this runbook recommends).

**Should staging contain demo accounts?** No — and as designed, `db:seed` against a staging `APP_ENV` (anything other than `local`/`testing`) already can't create them, regardless of what string is chosen for staging's `APP_ENV`. This is a genuine safety property, not just a convention: even an operator who runs the exact command the README documents for local dev cannot accidentally create the publicly-known `staff@example.com`/`password` credentials on staging.

**Recommendation for Phase 11B:** if staging needs staff/technician/customer test accounts for manual QA, create them **manually** (e.g. `php artisan tinker`, exactly as `README.md`'s "Demo accounts" section already recommends for production) with distinct, non-public credentials — never rely on `DemoAccountSeeder`, and never lower `APP_ENV` to `local`/`testing` on a reachable server just to get it to run (doing so would also relax other environment-sensitive behavior, e.g. verbose error handling assumptions some tooling makes around `app()->environment()`). See §25 for a concrete staging test-account naming convention.

**No real password is included anywhere in this document.**

### 9.1 Legacy demo account on staging (Phase 11C finding)

The real staging database currently contains a `staff@example.com` account that **predates** Phase 10B's `DemoAccountSeeder::shouldRun()` guard above — it was not created by a bypass of that guard, and re-verifying the guard in this phase (reading `DemoAccountSeeder::shouldRun()` directly, and its dedicated test) confirms it still works exactly as designed: nothing in the current codebase can recreate a `staff@example.com`/publicly-known-password account against a staging-configured `APP_ENV`.

**Per this phase's explicit scope, no repository code was written to automatically delete existing users** — a seeder or migration whose purpose is deleting user rows is exactly the kind of thing that becomes dangerous the moment it is ever run against the wrong environment, for a problem that is a one-time cleanup, not a recurring one. Instead, if this legacy account should be removed or rotated on staging, do it as a **manual, deliberate** action, separately from any code deployment:

1. Confirm what the account actually is (last login, any bookings/notes it authored) before touching it — `technician_notes` rows authored by a staff/technician account block deletion via the normal profile flow by design (§25's "Hardening" checklist), and the same FK consideration applies to a manual deletion.
2. Prefer **rotating its password** (`php artisan tinker` → look up the user, set a new `password` via `Hash::make()`, save) over deleting it outright, unless it's confirmed to have no associated workshop data and deletion is genuinely wanted.
3. Never expose the account's current password, hash, or any other credential value in a commit, an issue, or this documentation.
4. This is a staging-database action, not a repository change — it is intentionally **not** performed by this phase (which does not access the live VPS) and is called out here so it is not forgotten, not so that it gets automated.

---

## 10. Production-safe Laravel settings

Inspected against actual code, not assumed defaults:

| Setting | Current `.env.example` | Required for staging/production | Why (verified) |
|---|---|---|---|
| `APP_ENV` | `local` | `production` (or another non-`local`/`testing` value) | Gates `DemoAccountSeeder` (§9); also the conventional switch Laravel's own internals and third-party packages check |
| `APP_DEBUG` | `true` | `false` | With `true`, an uncaught exception renders full stack traces, file paths, and (for some exception types) environment/config values to any visitor — confirmed this is standard Laravel `Whoops`/Ignition behavior, not overridden anywhere in this app. `docs/PROJECT_STATUS.md` §18 already flags this as a must-set-false item |
| `APP_URL` | `http://localhost:8013` | `https://bikeworkshop.storm-ark.com` | Used by `url()`/`route()`/mail link generation and the `public` filesystem disk's URL prefix (§6, §18) |
| `LOG_LEVEL` | `debug` | Recommend `warning` or `error` for production (currently defaults to the noisiest level) | `debug`-level logging writes every SQL-adjacent/framework debug line in some configurations; not itself a security issue here (no sensitive payloads found, §20) but unnecessarily verbose and contributes to unbounded log growth (§20) |
| `SESSION_SECURE_COOKIE` | unset (`null`) | Recommend explicit `true` | Site is HTTPS-only (§7) |
| `DB_CONNECTION` | `mysql` | unchanged | Already correct |
| `CACHE_STORE` | `database` | unchanged | Already correct — no Redis needed for this app's load (§7) |
| Trusted proxy | `trustProxies(at: '*')` (`bootstrap/app.php`) | Works correctly behind the existing reverse proxy, **verified** (§18) | Confirmed no hardcoded scheme/host anywhere in app code — see §18 for the live verification performed |

None of these are code changes — every one is an `.env` value, and this phase does not modify the real `.env` (per the audit-only mandate). This table is what Phase 11B should confirm is actually set on the server.

---

## 11. `APP_KEY` safety

`APP_KEY` is Laravel's application encryption key (AES-256-CBC), used to:
- Encrypt/sign session cookies and the session payload itself (`SESSION_DRIVER=database` here, but the cookie referencing the session ID is still signed with `APP_KEY`).
- Sign CSRF tokens.
- Encrypt anything passed through Laravel's `Crypt` facade or an Eloquent `encrypted` cast (none currently exist in this app's models, confirmed by inspecting every model's `casts()` — but this could change in a future phase).

**Runbook rule, to be followed exactly in Phase 11B and every deployment after it:**
1. **Generate it once, the first time this app is ever deployed to a given environment** (`php artisan key:generate --force`, or let `docker/entrypoint.sh` auto-generate it on first boot if `.env` has no `APP_KEY=base64:...` line — confirmed this is exactly what the entrypoint already does, and only then).
2. **Never regenerate it on a normal deployment.** Since `.env` is git-ignored (confirmed: `.env`, `.env.backup`, `.env.production` are all in `.gitignore`) and lives on the VPS host filesystem via the bind mount (not baked into the Docker image, not touched by `git pull`), the same key is preserved automatically across every ordinary redeploy as long as nobody deletes or overwrites the VPS's `.env` file.
3. **What breaks if it changes:** every existing signed/encrypted session cookie becomes invalid immediately — all currently logged-in users (customers, staff, technicians) are silently logged out and must log in again. If the app ever gains an `encrypted` cast or uses `Crypt::encrypt()` in the future, changing the key would make all previously-encrypted data permanently unreadable.

Phase 11B's runbook execution must **confirm the VPS's actual `APP_KEY` is present and unchanged** before/after each deployment step — this document does not (and per the task's constraints, must not) print or generate an actual key value.

---

## 12. Laravel optimization/cache command audit — verified locally, not assumed

The task brief specifically warned against blindly including every Laravel optimization command — so each one below was **actually run** in this audit (against a fully migrated local SQLite database, with real HTTP requests through `php artisan serve` afterward to confirm the cache didn't break anything), not just checked for the absence of an obvious error.

| Command | Result (verified) | Notes |
|---|---|---|
| `php artisan config:cache` | ✅ Safe | No `env()` calls found anywhere outside `config/*.php` (the one apparent hit, `pb-[env(safe-area-inset-bottom)]` in `bottom-nav.blade.php`, is CSS `env()`, not PHP — confirmed by reading the line) |
| `php artisan route:cache` | ✅ Safe | `routes/web.php` has two closure-based routes (`/` and `/dashboard`) — closure routes are the classic reason `route:cache` fails on many Laravel apps. **Verified this is not a problem here**: `route:cache` completed without error, and a live HTTP request against the cached routes (`GET /` → 200, `GET /dashboard` → 302 redirect) worked correctly. Re-verify this specific check after any future change to `routes/web.php`'s closure routes, since it's the one command with real precedent for breaking silently |
| `php artisan view:cache` | ✅ Safe | Precompiled all Blade templates without error; live requests to `/`, `/login`, `/register` all returned 200 |
| `php artisan event:cache` | ✅ Runs without error, but is a no-op | No custom event/listener classes exist anywhere in `app/` (`Event::listen`, a custom `EventServiceProvider`, and `app/Listeners/` were all searched — none found). Optional, not necessary |

**Recommended production command set** (smallest reliable set, per the task's own instruction not to blindly include everything):

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`event:cache` is safe but adds nothing (no events to cache) — omit it unless a future phase adds custom events/listeners.

**Important existing-behavior note (updated — see §0.5):** `docker/entrypoint.sh` no longer relies on a post-migrate `config:clear` call. Phase 11A's original choice here (keep `.env` changes picked up immediately, without a stale-cache foot-gun) was correct in intent but wrong in ordering: `config:clear` ran *after* `php artisan migrate --force`, which is exactly what let a stale `bootstrap/cache/config.php` reach `migrate --force` during a real staging credential rotation (§0.5). The entrypoint now deletes `bootstrap/cache/config.php` specifically **before** any `php artisan` command runs, every boot — a strictly stronger version of the same original intent, not a reversal of it. `route:cache`'s and `event:cache`'s generated files are untouched by this fix; they were never responsible for the incident and remain persistent across restarts exactly as before.

An operator can still layer the three optimization cache commands below into the deployment sequence manually after each `docker compose up -d` (§26/§28 step 11) for a minor performance benefit, given this app's modest expected traffic (a single small workshop) — it remains optional, not required for V1 to function correctly. What's changed: `config:cache`'s output is now cleared again on every subsequent container start (not just one following an `.env` change), so it must be reapplied after every restart to stay in effect, not just once after a deploy. `route:cache`/`view:cache` are unaffected and stay in effect across restarts as before.

**Troubleshooting/rollback:** `php artisan optimize:clear` clears all of the above (config, route, view, event, and compiled-class caches) in one command — verified working. Use this first whenever a deployment produces unexpected behavior after caching was applied.

---

## 13. Frontend build audit

`package.json` scripts: `"build": "vite build"`, `"dev": "vite"`. `vite.config.js` uses `laravel-vite-plugin` against `resources/css/app.css` and `resources/js/app.js`. Tailwind is classic v3 tooling (`postcss.config.js` + `tailwind.config.js`), confirmed by `docs/PROJECT_STATUS.md` §2's own discrepancy note (the `@tailwindcss/vite` v4 devDependency is an unused leftover — cosmetic only, not touched by this phase).

**`npm run build` was run in this audit and succeeds cleanly:**

```
vite v8.2.1 building client environment for production...
✓ 4 modules transformed.
public/build/manifest.json             0.33 kB
public/build/assets/app-Sx9vZP1X.css  30.02 kB
public/build/assets/app-BiY2chGF.js   52.95 kB
✓ built in 1.00s
```

**Generated assets required for production:** `public/build/manifest.json` plus the hashed `assets/app-*.css`/`assets/app-*.js` files it references. Every Blade layout uses `@vite(['resources/css/app.css', 'resources/js/app.js'])`; if `manifest.json` is missing at request time, Laravel throws `ViteManifestNotFoundException` (this is exactly why `docs/HANDOFF.md` warns that `npm run build` must run at least once before `php artisan test`, since some feature tests render full pages).

**Where the build happens (confirmed, not a recommendation to change anything):** assets are built **inside the Docker image at `docker compose build` time** (`Dockerfile` lines 41–48: `npm ci && npm run build`, then `npm prune --omit=dev` to shrink the image afterward), baked into the image, and preserved across the bind mount via the named `bicycle_workshop_build` volume (`compose.yaml`). **No CI pipeline builds assets, and the live VPS filesystem's `resources/` source is never built in place against a running container** — a plain `docker compose up -d` never touches Node at all. The only case that needs `npm` on the VPS is the optional `--profile dev` `vite` service (hot-reload for active development), which is not part of the production/staging runtime path.

**Practical deployment implication:** any commit that changes `composer.json`/`composer.lock` **or** any frontend source under `resources/` requires `docker compose build app` (not just `up -d`) for the new dependencies/assets to take effect, because `vendor/` and `public/build` are shadowed by named volumes specifically to survive the full-repo bind mount (`compose.yaml`'s own comment explains this). This is already documented in `README.md`/`docs/HANDOFF.md` and is reflected in the deployment sequence (§26).

---

## 14. Composer deployment audit

`composer.json`'s `require`/`require-dev` were reviewed. Relevant scripts:
- `post-autoload-dump` → `Illuminate\Foundation\ComposerScripts::postAutoloadDump` + `php artisan package:discover` — **this does execute an Artisan command during `composer install`**, which requires a bootable Laravel app (an `.env` file must exist, though `APP_KEY` need not be set for `package:discover` specifically). This is exactly why `Dockerfile` line 41 does `cp .env.example .env` immediately before `composer install`, then `rm .env` afterward (line 46) — a deliberate, already-correct pattern that avoids ever needing the *real* `.env`/database during the image build. **Deployment ordering implication:** none for the real Docker-based deployment, since the image build is fully self-contained and never touches the live `.env` or database; this only matters for the "Without Docker" fallback path in `README.md`, where `composer install`'s `post-root-package-install`/`post-create-project-cmd` hooks also copy `.env.example` if `.env` is absent (harmless idempotent behavior for a fresh non-Docker checkout, not relevant to the VPS).
- The `Dockerfile`'s actual production install command is `composer install --no-interaction --no-progress --optimize-autoloader` — **`--no-dev` is deliberately NOT used** (see the `Dockerfile`'s own comment: dev packages — PHPUnit, Pint — are kept in every environment including the VPS so `docker compose exec app php artisan test`/`pint` work the same way everywhere, since this is a single-image setup, not a separate prod build).

**Verified this is a deliberate, already-established project convention, not an oversight** — Phase 11A does not recommend changing it (that would be an infrastructure change outside this phase's scope), but flags it for awareness: the production image is larger and includes dev tooling (PHPUnit, Faker, Mockery, Collision, Pail, Sail definitions) that a stricter `--no-dev` build would exclude. If a future phase wants a leaner production image, that's a `Dockerfile`/multi-stage-build change to consider deliberately — not something Phase 11B should silently introduce.

**Correct sequence (already what `Dockerfile` does, confirmed no reordering needed):** `composer install` → `npm ci && npm run build` → `npm prune --omit=dev` → set directory ownership. All of it happens at image-build time, before the container (and therefore the real database) exists at all — there is no dependency-ordering risk between Composer's `post-autoload-dump` hook and the live database.

---

## 15. File permissions

Confirmed by reading `Dockerfile` and `docker/entrypoint.sh` directly (not assumed):

- **`storage/`** and **`bootstrap/cache/`** are the only two directories the Laravel runtime writes to (logs, compiled views, session/cache-adjacent framework files, sqlite-independent framework bootstrap cache). Both are `chown -R www-data:www-data`'d **twice**: once at image-build time (`Dockerfile`, so a fresh image already has correct ownership) and again on every container start (`docker/entrypoint.sh`'s `chown -R www-data:www-data storage bootstrap/cache` step — necessary because the full repo is bind-mounted from the VPS host filesystem, whose ownership can differ from the image's baked-in state).
- The container runs as **root** (no `USER` directive in `Dockerfile`; Apache's `apache2-foreground` itself drops worker processes to `www-data` per Debian's standard `apache2.conf`, but the entrypoint script and PHP-triggered file writes via Apache's configured user run as `www-data`). This is why the `chown` calls succeed unconditionally — root can always `chown` inside its own container, and because it's a bind mount, that `chown` **also changes ownership on the VPS host filesystem itself**, not just inside the container.
- **This is also why `docker/entrypoint.sh`'s `clear_stale_config_cache` step (§0.5) never depends on the host deploy user having `sudo`:** it runs as root, before the `chown` above, so it can delete a `www-data`-owned `bootstrap/cache/config.php` regardless of who owns it — exactly the permission problem that blocked manual recovery during the real credential-rotation incident, now handled automatically on every boot instead of requiring host-side `sudo rm`.
- **No `chmod -R 777` is used anywhere in this repository** — confirmed by searching `Dockerfile`, `docker/entrypoint.sh`, and `compose.yaml`. Do not introduce one; the existing `chown`-to-`www-data` pattern is already the correct, narrower approach.
- **[Still open — verify on server if friction is ever observed]** which host-level OS user owns the VPS checkout directory that's bind-mounted into the container, and confirm that whichever user performs `git pull`/`git fetch` on the VPS has write access to that same directory (the container's repeated `chown -R www-data:www-data storage bootstrap/cache` changes those two subdirectories' *host-side* ownership to `www-data`'s UID/GID every time the container starts). No such friction was reported during the real deployment, which completed its full sequence including a `git`-based source update without issue — a weak positive signal, not a confirmation.

---

## 16. Web root

Confirmed via `docker/apache/000-default.conf`:

```apache
<VirtualHost *:80>
    DocumentRoot /var/www/html/public
    <Directory /var/www/html/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    ...
</VirtualHost>
```

Apache's document root is already Laravel's `public/` directory, exactly as required — **not** the repository root. `.env`, `composer.json`, `app/`, etc. all live outside the served document root and are therefore never reachable over HTTP through this Apache instance by construction (no separate check needed — this is a structural guarantee of the vhost config, not a runtime behavior that could regress silently).

**Confirmed by the completed staging deployment:** the host reverse proxy forwards `bikeworkshop.storm-ark.com` to `127.0.0.1:8013` (the `app` container's published port, per §1's request-path diagram) — both the local root/`  /up` checks and the public HTTPS root/`/up` checks returned 200 after deployment. The reverse proxy's own vhost file lives on the VPS, outside this repository, and was not created or modified by any phase.

### 16.1 Apache `AH00558` `ServerName` warning — investigated, no change made

The `app` container logs `AH00558: apache2: Could not reliably determine the server's fully qualified domain name` on startup. This is Apache's standard notice that no global `ServerName` directive is set anywhere in its configuration (confirmed: neither the base `php:8.4-apache-bookworm` image's default config nor `docker/apache/000-default.conf`, per §16 above, sets one) — Apache falls back to resolving its own hostname, logs the notice, and continues serving normally regardless.

**Conclusion: no action taken, deliberately.** This warning is purely cosmetic in this deployment:
- The container has exactly one vhost (`docker/apache/000-default.conf`, a single `<VirtualHost *:80>` block with no `ServerName`/`ServerAlias` distinction to make — there is nothing for name-based virtual hosting to disambiguate).
- Apache never uses its own guessed hostname for anything the application depends on — `APP_URL`/`trustProxies` (§17) are what actually determine every generated URL, not Apache's `ServerName`.
- The warning does not appear in the request/response path, does not affect HTTP responses, and both local and public HTTPS health checks passed cleanly with it present.
- Adding a `ServerName` directive (e.g. `ServerName bikeworkshop.storm-ark.com` or `ServerName localhost`) would silence the log line but changes nothing observable about the running application — exactly the kind of Apache change the task's own instructions warn against making "merely to eliminate a harmless warning."

If a future phase wants the log line gone for cleanliness, adding `ServerName localhost` to `docker/apache/000-default.conf` is the safe, minimal way to do it — but Phase 11C found no functional reason to make that change now.

---

## 17. HTTPS and application URL audit

Searched `app/`, `resources/views/`, `routes/` for hardcoded `http://`/`localhost`/`127.0.0.1` URLs: **zero matches.** The only matches anywhere in the repository are Laravel's own framework-default fallback values inside `config/*.php` (e.g. `config/app.php`'s `'url' => env('APP_URL', 'http://localhost')`) — these are never used once `APP_URL` is set, which it always is (`.env.example` sets it, and the real `.env` must too, per §7/§10).

Every URL in the app is generated via Laravel's `route()`/`url()`/`asset()` helpers (25 distinct usages counted across `resources/views/`), which all key off `APP_URL` and the current request's detected scheme/host — confirmed no view or controller ever concatenates a raw string URL.

**Live-verified HTTPS/proxy correctness:** `bootstrap/app.php`'s `$middleware->trustProxies(at: '*')` means the app trusts `X-Forwarded-Proto`/`X-Forwarded-Host`/`X-Forwarded-For` headers from *any* upstream proxy. In the real deployment, this is exactly the mechanism that lets `url()`/`route()`/`asset()` correctly generate `https://bikeworkshop.storm-ark.com/...` links even though the Apache container itself only ever sees plain HTTP internally (per §16's vhost, listening on `*:80`) — **confirmed working**: the public HTTPS root and `/up` both returned 200, and the full customer/staff/tracking/notification workflow was manually verified over HTTPS with no mixed-content issues.

**`APP_URL` correction (Phase 11C finding, confirmed):** the staging `.env` initially carried the `.env.example` default, `APP_URL=http://localhost:8013` (the internal Docker/host endpoint, correct for local dev, wrong for any real deployment), and was corrected during the real deployment to `APP_URL=https://bikeworkshop.storm-ark.com` — the externally-accessible HTTPS URL, exactly as this section already required. **Staging/production `APP_URL` must always be the externally accessible HTTPS URL the public actually visits, never `http://localhost:<port>` or any other internal/Docker-facing address** — a mismatch here is exactly what would cause mixed content or broken redirects, per the assessment below. §28's deployment sequence now calls this out as its own explicit step rather than only as an environment-checklist table entry, since it's easy to deploy successfully and only notice the wrong `APP_URL` once a generated link looks wrong.

**Mixed-content risk assessment:** none found in application code. The one thing that *would* cause mixed content or broken redirects is `APP_URL` in the real `.env` not exactly matching `https://bikeworkshop.storm-ark.com` (e.g. a stale `http://` scheme, a leftover `:8013` port, or a trailing slash mismatch) — confirmed corrected during the real deployment, as above.

---

## 18. Health/smoke endpoint

`bootstrap/app.php` registers Laravel 11+'s built-in framework health route:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

Confirmed via `php artisan route:list` — `GET|HEAD up` exists, resolves to the framework's built-in health-check closure (no override exists anywhere in `app/`). Verified locally: `curl http://127.0.0.1:8099/up` → `200`.

**What it actually verifies:** by default, this route only confirms that the PHP process can boot the framework and route a request successfully (i.e., Apache + PHP + the Laravel bootstrap are alive) — it fires an `Illuminate\Foundation\Events\DiagnosingHealth` event that nothing in this app listens to (`app/Providers/AppServiceProvider.php` is empty, confirmed), so **it does not verify database connectivity, cache connectivity, or anything beyond "the app booted."** It exposes no sensitive information (confirmed — it's the framework's default minimal response, not a custom view).

**Recommendation, not implemented in this phase** (per the task's instruction to recommend rather than build a new health check unless documentation absolutely requires it): `/up` is sufficient as a basic "is the process alive" check for Phase 11B's smoke test (§27), but should be paired with an actual authenticated-flow check (e.g. logging in as a test account, §25) to catch a broken `DB_HOST`/credentials, since `/up` alone would not catch that failure mode.

---

## 19. Logging audit

`config/logging.php` (framework defaults, unmodified): `LOG_CHANNEL=stack` → `LOG_STACK=single` → writes to `storage/logs/laravel.log` via the `single` Monolog driver, at `LOG_LEVEL=debug` by default.

- **No custom application logging exists** — searched `app/` for `Log::`: zero matches. All logging is Laravel's own default exception/error handling; no code path logs request bodies, passwords, tokens, or other sensitive payloads.
- **Rotation:** the `single` driver **never rotates** — `storage/logs/laravel.log` grows indefinitely until something external truncates or rotates it. This is a real, if slow-building, operational risk for a long-running VPS deployment.
- **Recommendation for Phase 11B (an `.env`-only change, not implemented here):** switch to `LOG_STACK=daily` (Laravel's built-in daily-rotation driver, already defined in `config/logging.php` with `max_files` controllable via `LOG_DAILY_DAYS`, default 14) in the real staging/production `.env`, or configure host-level `logrotate` on `storage/logs/laravel.log`. Either is a one-line `.env` change or a host-level ops task — no code change needed.
- **`LOG_LEVEL`** recommendation: `warning` or `error` for production (see §10) — `debug` is unnecessarily verbose for a live deployment and contributes to the unbounded-growth risk above.

**[Still open]:** current size of `storage/logs/laravel.log` on the VPS, current `LOG_LEVEL`/`LOG_STACK` values in the live `.env`, and whether `logrotate` (or equivalent) is already configured at the host level for this or any other app sharing the VPS — not part of the real deployment's reported verification, and not something this repository-only phase can check.

---

## 20. Error-page behavior

With `APP_DEBUG=false` (required per §10), Laravel's standard production exception handling applies: generic error pages for 403/404/419/429/500/503 (Laravel's bundled `resources/views/errors/` templates — confirmed no custom error views exist in this repository's own `resources/views/`, so the framework's defaults are what will render), with no stack trace, file path, or query content exposed. This is standard, well-tested Laravel behavior and was not modified or found to need modification in this audit — **no custom error pages are recommended or needed for V1.**

**What Phase 11B should smoke-test (§27):** trigger a deliberate 404 (a nonexistent URL) and a deliberate 403 (an authenticated customer requesting a staff-only route, or vice versa) on the live staging site with `APP_DEBUG=false` set, and confirm neither leaks a stack trace or internal path — this is the one item repository inspection alone cannot fully prove, since it depends on the live `.env`'s actual `APP_DEBUG` value.

---

## 21. Database backup requirements

The application's only business-critical persistent state is the MySQL database (confirmed by §6 — there are no user uploads or generated files). It holds:

customers (`users`), bicycles, bookings, booking items, the full status-history audit trail, repair inspections, repair items, technician notes, and in-app notifications.

**Method — confirmed working during the real deployment (Phase 11C update):**

```bash
docker compose exec mysql sh -c \
  'mysqldump -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" --no-tablespaces "$MYSQL_DATABASE"' \
  | gzip > backup-$(date +%F-%H%M).sql.gz
```

- **Use the application's own DB user (`MYSQL_USER`/`MYSQL_PASSWORD`, already granted full privileges on its own database by the `mysql:8.4` image's env vars), not `root`.** A plain `mysqldump` against this user initially **failed** during the real deployment because the app's DB user lacks the `PROCESS` privilege that `mysqldump` uses by default to collect tablespace metadata — the fix is `--no-tablespaces` (which skips that metadata; it is schema/data-agnostic and does not omit any table's rows), **not** granting `PROCESS` to the application's DB user just to satisfy an unqualified `mysqldump` invocation. Granting `PROCESS` would give that user visibility into other sessions/queries running on the same MySQL server — unnecessary privilege for a backup command that doesn't need it.
- **Verify every backup**, exactly as the real deployment did: check the command's exit code, the resulting file's size is non-trivial (a near-zero-byte file usually means an auth or privilege failure that didn't otherwise surface), the file's SHA-256 (`sha256sum backup-*.sql.gz`, recorded alongside the backup so a later restore can confirm integrity), and that the uncompressed dump ends with `mysqldump`'s own `-- Dump completed on <timestamp>` footer line (its absence means the dump was truncated or interrupted, and must not be trusted).
- **Frequency:** daily is a reasonable minimum for a small workshop's operational data; more frequent (e.g. every few hours) if the shop's booking volume justifies it — this is a judgment call for whoever owns the VPS, not something this repository dictates. **Always** take one immediately before any deployment that will migrate the schema (§28).
- **Retention:** a simple rolling window (e.g. 7 daily + a handful of weekly snapshots) is sufficient for this scale — avoid an elaborate retention policy that itself becomes an operational burden.
- **Off-server requirement:** backups stored only on the same VPS are not real backups (a VPS-level failure or compromise takes both the live data and its backup with it) — copy dumps to a separate location (e.g. DigitalOcean Spaces, another host, or even a periodically-synced local machine) on whatever cadence the budget allows.
- **Restore-test requirement:** periodically (e.g. monthly) actually restore a dump into a scratch database and spot-check it — an untested backup is not a verified backup. This is a process recommendation, not something to automate in this phase.

**Never include a real password, connection string, or backup file's actual content in this repository or in any commit message.** The command above reads credentials from the container's own environment (`$MYSQL_USER`/`$MYSQL_PASSWORD`), never a literal value.

**This phase does not configure any backup tooling, cron job, or off-server sync automation** — this section documents the verified-working command and procedure; scheduling it (e.g. a host `cron` entry) remains an operational task for whoever owns the VPS.

---

## 22. Filesystem backup requirements

Per §6's storage audit, this application has **no user-uploaded or generated files** to back up. The only filesystem data outside the database that is genuinely non-reproducible is:

- **The VPS's `.env` file** — contains `APP_KEY` (§11) and live database credentials, is git-ignored, and exists only on the server. Losing it without a backup means losing the ability to decrypt existing sessions and reconnect to the database without manual reconfiguration. **Back it up before every deployment or configuration change**, alongside the database backup (§21) and as its own explicit step in §28's sequence — a quick `cp .env .env.backup-$(date +%F-%H%M)` kept outside the web-served path (§16 already guarantees `.env` itself isn't web-reachable, but a stray backup copy under `public/` would be) is enough for a same-host safety net; keep a secure, access-controlled copy off the VPS too (e.g. in a password manager or secrets vault, never in git, never in this document) for the same off-server reasoning as §21's database backups.
- **The `bicycle_workshop_mysql_data` named Docker volume** is the database's on-disk storage — this is what `mysqldump` (§21) already captures at the logical level; a raw volume-level backup is a possible supplementary method but `mysqldump` is the primary, portable recommendation.

**Explicitly not needed:** `vendor/`, `node_modules/`, `public/build/` (all reproducible from git + `docker compose build`), and any other git-tracked source — none of it is runtime-generated data.

---

## 23. Deployment strategy

Given: one DigitalOcean VPS, an already-working HTTPS site, Git-based source control, a Dockerized Laravel app, MySQL, and modest expected usage (a single small workshop) — this calls for a **simple, manual, Git-based deployment with a brief maintenance window**, not zero-downtime infrastructure. Verified as appropriate against the actual repository (fast, additive-only migrations per §8; no queue/scheduler to coordinate per §3/§4).

Priorities, in the order the sequence below actually protects them:
1. **Database safety** — always back up before migrating (§21), never `migrate:fresh`/`db:wipe`, record the pre-deployment commit.
2. **Reproducibility** — the exact same `docker compose build && docker compose up -d` sequence every time, no ad-hoc manual steps on the live filesystem.
3. **Easy rollback** — `PREVIOUS_GOOD_COMMIT` recorded before every deployment (§28).
4. **No accidental demo data** — already structurally guaranteed by `DemoAccountSeeder::shouldRun()` (§9) as long as `APP_ENV` stays non-`local`/`testing`; the sequence below never changes `APP_ENV`.
5. **No accidental `APP_KEY` changes** — already structurally guaranteed by `.env` being git-ignored and host-persistent (§11); the sequence below never regenerates or overwrites the VPS `.env`.

---

## 24. Maintenance mode

`APP_MAINTENANCE_DRIVER=file` (the `.env.example` default, unmodified) means `php artisan down`/`php artisan up` work with **no database dependency** — the maintenance flag is a plain file (`storage/framework/maintenance.php`), not a database row. This matters concretely for this app's deployment sequence: maintenance mode can be enabled *before* touching the database at all, and — since `storage/` is part of the full-repo **bind mount** (not one of the named volumes shadowing `vendor/`/`public/build`) — **the maintenance flag file persists across a full `docker compose build && docker compose up -d` cycle**, confirmed by reading `compose.yaml`'s volume configuration. This is not true of every Docker Compose setup (a setup that stored `storage/` in a container-only or rebuilt path would lose the flag on recreate) — this one is safe.

**Recommendation:** use `php artisan down`/`up` around every deployment that touches the database or rebuilds the image, given migrations are fast and additive-only (§8) — a short window (likely well under a minute for this schema size) is an acceptable, honest tradeoff for the safety it buys, consistent with the task's own guidance that a brief maintenance window is fine for this scale.

**Emergency recovery note:** if a deployment fails while the app is in maintenance mode (see §28 for the migration-failure case specifically), **do not run `php artisan up`** until the underlying problem is actually fixed or the pre-deployment backup is restored — leaving the maintenance page up is strictly better than exposing a half-migrated or broken application to real customers/staff. `docker compose logs app` and `php artisan migrate:status` (via `docker compose exec app ...`, or `docker compose run --rm app ...` if the `app` container itself failed to start — see §28) are the first two things to check.

---

## 25. Staging smoke-test plan

Concrete checklist for Phase 11B, using the app's real route names (`docs/PROJECT_STATUS.md` §9) — not implemented or run against staging by this phase.

**Public/auth**
- [ ] `https://bikeworkshop.storm-ark.com/` loads over HTTPS with a valid certificate, no mixed-content warnings
- [ ] Register a new customer account (`register`) — lands on `customer.home`, role is forced to `customer` (cannot self-elevate)
- [ ] Log in (`login`) as the new account, log out (`logout`)
- [ ] Visit a nonexistent URL → generic 404, no stack trace (§20)
- [ ] As a logged-in customer, request a `/staff/*` URL directly → 403, no stack trace

**Customer**
- [ ] `customer.home` dashboard loads
- [ ] Add a bicycle (`customer.bikes.store`), view it (`customer.bikes.show`), edit it (`customer.bikes.update`)
- [ ] Book a repair through the full 5-step wizard (`customer.repairs.store`)
- [ ] View the booking's tracking page (`customer.repairs.show`) — status badge, timeline
- [ ] Confirm a second customer account cannot view the first customer's bicycle/booking directly by URL (403)
- [ ] After a staff-side transition (below), confirm the customer sees an in-app notification (`customer.notifications.index`) and the unread-count badge
- [ ] View a bicycle's service history (`customer.bikes.show`) once a booking is completed

**Staff**
- [ ] `staff.dashboard` stat tiles show real counts
- [ ] Accept a booking (`staff.bookings.accept`), mark bicycle received (`staff.bookings.receive`)
- [ ] Record an inspection (`staff.bookings.inspection.update`) — auto-transitions to `inspection`
- [ ] Assign a technician (`staff.bookings.technician.update`) — only technician-role users selectable
- [ ] Add a repair item (`staff.bookings.repair-items.store`), send for customer approval (`staff.bookings.approval.request`)
- [ ] Approve as the customer (`customer.repairs.approve`), confirm staff side reflects `repair_in_progress`
- [ ] Complete repair items (`staff.bookings.repair-items.complete`), mark repairs complete (`staff.bookings.repairs.complete`)
- [ ] Run quality check: start → pass (pickup) on one booking, start → fail → rework → pass (delivery) on another, to exercise the full loop
- [ ] Mark fulfilled (`staff.bookings.fulfill`) → `completed`
- [ ] Confirm the "Previous Repairs for This Bicycle" section appears on a second booking for the same bicycle

**Technician**
- [ ] Log in as a technician-role account, confirm access to the same `/staff/*` area as staff (§13 of `docs/PROJECT_STATUS.md` — no separate technician screen exists by design)
- [ ] Confirm the technician's own assigned jobs sort first on `staff.jobs.index`
- [ ] Add a technician note (`staff.bookings.notes.store`)

**Hardening (Phase 10B regressions — confirm they hold on staging, not just in the test suite)**
- [ ] Customer account: confirm "Delete Account" is not offered in the UI, and a direct `DELETE /profile` request is rejected
- [ ] Staff/technician account that has authored a technician note: confirm deletion is blocked with a clear message, not a crash
- [ ] Decline a proposed repair as a customer, then confirm staff can close it via "Close — Customer Declined Repair" (`staff.bookings.close-declined`)
- [ ] On a `Completed` booking, confirm inspection/repair-item/note/technician-assignment edits are rejected server-side (not just hidden in the UI) — the data should still be visible read-only

---

## 26. Test data strategy for staging

**Do not** run `migrate:fresh`, wipe the staging database, or use real customer data for testing (per the task's explicit constraints).

**Recommendation:** create a small number of clearly-named, disposable staging test accounts and records, distinct from the guarded `DemoAccountSeeder` accounts (which — per §9 — cannot be created on staging anyway):
- e.g. `staging-test-customer@<real-domain-you-control>`, `staging-test-staff@...`, `staging-test-technician@...`, created manually via `php artisan tinker` (the same mechanism `README.md` already recommends for production staff/technician accounts), each with a unique, non-public password recorded securely (never in this repository).
- Use obviously-fake bicycle nicknames (e.g. "TEST — do not use") and booking remarks when exercising the workflow, so any staff member later reviewing real data can immediately recognize and disregard test records, or delete them cleanly after the smoke test if desired (a booking/bicycle with no real customer interest can be safely removed via `tinker` — there is no cascading concern here since these are test-only rows created for this purpose).
- After Phase 11B's smoke test, decide whether to leave the disposable records in place (harmless clutter, clearly labeled) or delete them — either is acceptable; **do not** reset or truncate any table to "clean up."

---

## 27. Security deployment checklist

| Item | Status | Notes |
|---|---|---|
| `APP_DEBUG=false` | ✅ Confirmed on staging | Verified during the real deployment (§0/§25) |
| `.env` not web-accessible | ✅ Structurally guaranteed | Apache's document root is `public/` only (§16); `.env` lives outside it by construction, not by convention |
| No demo/privileged credentials in production | ✅ Verified by code + test; see §9.1 for a pre-existing legacy account | `DemoAccountSeeder::shouldRun()` (§9) structurally prevents `staff@example.com`-style accounts outside `local`/`testing` |
| HTTPS working | ✅ Confirmed (Cloudflare + host Nginx/Certbot, §1) | Public HTTPS root and `/up` both verified 200 |
| Correct file permissions | ✅ Verified in repo | `storage/`/`bootstrap/cache/` owned by `www-data`, no `777` anywhere (§15) |
| Database not publicly exposed | ✅ Fixed at the repository level (Phase 11C) | See callout below — `compose.yaml` now publishes no MySQL host port by default at all |
| Secrets not committed to git | ✅ Verified | `.env`/`.env.backup`/`.env.production` all gitignored; no secrets found anywhere in the repository or git history |
| No `php artisan serve` as the production web server | ✅ Verified | The Docker image runs Apache (`apache2-foreground`); `php artisan serve` appears nowhere in `Dockerfile`/`compose.yaml`/`docker/entrypoint.sh` |

**Callout — MySQL/app port exposure (Phase 11C: fixed in the repository, see §0.3):** the real staging deployment discovered that achieving the intended posture — app reachable only via `127.0.0.1:8013`, MySQL not published to the host at all — required editing `compose.yaml` directly on the VPS (leaving that checkout permanently `git`-dirty) plus an untracked backup file. `compose.yaml`'s own defaults are now exactly that posture (`app` on `"127.0.0.1:${APP_PORT:-8013}:80"`, no `ports:` key at all for `mysql`); local development regains a host-published MySQL port via the new, git-tracked `compose.override.yaml`, which Compose auto-merges for a plain `docker compose ...` command. **A staging/production host must set `COMPOSE_FILE=compose.yaml` in its own `.env`** so that override never applies there — this is a one-line `.env` change, not a `compose.yaml` edit, and survives every future `git pull` without conflict. The host's own firewall (`ufw`/`iptables`/a cloud firewall) exposing only SSH, HTTP, and HTTPS remains the outer layer of defense regardless — this repository-level fix narrows what Docker itself publishes, it does not replace the firewall.

---

## 28. Deployment sequence

Ordered for this app's actual Docker-based deployment model (§1). This reflects both the original Phase 11A planning and the real deployment's actual, executed sequence (§0) — the two matched closely; the differences (stale-volume detection, the `COMPOSE_FILE` step, and the explicit `.env`/`APP_URL` steps) are called out inline below and are exactly what Phase 11C's repository changes now handle automatically or make explicit. `<DEPLOY_PATH>`, `<DEPLOY_USER>` remain VPS-specific placeholders — this repository does not record real VPS paths/usernames.

1. **Verify current health.** `curl -I https://bikeworkshop.storm-ark.com/up` → expect `200`. `docker compose ps` on the VPS → confirm `app`/`mysql` are `healthy`/`running`.
2. **Confirm the currently deployed commit.** On the VPS, in `<DEPLOY_PATH>`: `git rev-parse HEAD`. **Record this as `PREVIOUS_GOOD_COMMIT`** — required before every deployment (§29). For this release, `PREVIOUS_GOOD_COMMIT` was `60847e9e5d6ef6cdf6a2ef9c272bf04efad36f95`.
3. **Back up the VPS `.env`** (§22, Phase 11C addition — this was implicit before, now its own step): `cp .env .env.backup-$(date +%F-%H%M)` outside any web-served path.
4. **Take a database backup** (§21, Phase 11C — corrected command using the app's own DB user, not root): `docker compose exec mysql sh -c 'mysqldump -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" --no-tablespaces "$MYSQL_DATABASE"' | gzip > /path/outside/repo/backup-$(date +%F-%H%M).sql.gz`, verify it (exit code, size, SHA-256, completion footer — §21), then copy it off-server.
5. **Enable maintenance mode**: `docker compose exec app php artisan down --retry=60` (§24 — safe to do before any further step, since it's file-based and survives the coming rebuild).
6. **Fetch and check out the target commit**: `git fetch origin <branch>` then `git checkout <target-commit-or-branch>` (or `git pull` if deploying the branch's current tip) in `<DEPLOY_PATH>`.
7. **Rebuild the image** (bakes `composer install --optimize-autoloader` and `npm ci && npm run build`, per §13/§14): `docker compose build app`.
8. **Recreate the container** (this is what actually runs the new code + triggers the entrypoint's `migrate --force`, per §1/§8; **as of Phase 11C, the entrypoint also automatically detects and refreshes a stale `vendor`/`public/build` volume here — see §0.1 — so the manual volume-removal step the first real deployment needed should no longer be necessary**): `docker compose up -d`. Watch `docker compose logs -f app` during this step specifically to catch a migration failure or a "refreshing from image (checksum mismatch...)" line immediately (§29).
9. **Verify migrations applied cleanly**: `docker compose exec app php artisan migrate:status` → confirm no `Pending` rows.
10. **Confirm `APP_URL`** (§17, Phase 11C addition — easy to miss, bit the real first deployment): `docker compose exec app php artisan tinker --execute="echo config('app.url');"` (or `grep APP_URL .env`) → must be `https://bikeworkshop.storm-ark.com` exactly, never `http://localhost:<port>` or any other internal address.
11. **(Optional) Apply the Laravel optimization caches** (§12, verified safe in this audit): `docker compose exec app php artisan config:cache && docker compose exec app php artisan route:cache && docker compose exec app php artisan view:cache`. **Note (§0.5):** `docker/entrypoint.sh` clears `config:cache`'s output again on every container start, including a plain restart — that one command must be repeated after each `docker compose up -d`/restart, not just once after a deploy, to keep the config optimization active. `route:cache`/`view:cache` are unaffected and remain in effect across restarts.
12. **Verify filesystem permissions** were reapplied correctly (the entrypoint already does this automatically, per §15 — this step is a confirmation, not a manual fix): `docker compose exec app ls -la storage bootstrap/cache | head` → confirm `www-data` ownership.
13. **Verify frontend assets are current** (§13, Phase 11C addition): `docker compose exec app cat public/build/manifest.json` and confirm the hashed asset filenames it references match what `docker compose build app`'s own log output reported — this should now always be true automatically (§0.1), but a quick confirmation costs nothing.
14. **Exit maintenance mode**: `docker compose exec app php artisan up`.
15. **Run the health check**: `curl -I https://bikeworkshop.storm-ark.com/up` → expect `200` locally and over the public HTTPS URL.
16. **Perform the smoke tests** (§25) — at minimum the "Public/auth" section on every deploy; the full checklist after any change touching the booking workflow, exactly as the real deployment's browser smoke test did.
17. **Review logs** for anything unexpected: `docker compose logs --since=30m app` and `tail -100 <bind-mount-path>/storage/logs/laravel.log`.

**On staging/production, every `docker compose` command above must be run with `COMPOSE_FILE=compose.yaml` set in that host's `.env`** (§0.3/§27) so `compose.override.yaml`'s local-dev-only MySQL port publishing never applies there.

If any step from 7 onward fails, go to §29/§30 rather than improvising.

---

## 29. Rollback strategy

**Always require `PREVIOUS_GOOD_COMMIT`** to have been recorded (§28 step 2) before any deployment proceeds past step 4 — a rollback without a known-good commit to return to is not a rollback, it's guessing.

### Application-code rollback
1. `docker compose exec app php artisan down --retry=60` (if not already in maintenance mode from the failed deploy).
2. `git checkout <PREVIOUS_GOOD_COMMIT>` in `<DEPLOY_PATH>` (this VPS checkout is deploy-only, not a shared development branch — a clean checkout to a specific recorded commit is appropriate here, unlike on a real feature branch).
3. `docker compose build app` — rebuild against the old commit's `composer.lock`/frontend source, since dependencies may have changed between commits (§13/§14 — never assume a straight `up -d` is sufficient after changing which commit is checked out).
4. `docker compose up -d`.
5. `docker compose exec app php artisan optimize:clear` (clear any caches built for the newer commit's code — §12).
6. `docker compose exec app php artisan up`.
7. Re-run the "Public/auth" smoke-test subset (§25) to confirm the rollback actually restored service.

### Database rollback — be conservative
**Do not casually run `php artisan migrate:rollback` against a database that has received real production writes since the migration ran.** A migration's `down()` method was written and tested against an empty/test database, not against live rows — a `dropColumn`/`Schema::drop` in a `down()` method (even though, per §8, no current migration's `up()` is destructive, several `down()` methods necessarily are, since they're symmetrical) could silently destroy real customer/booking data that accumulated after the migration ran forward.

**When to restore the pre-deployment backup instead (the generally safer path):** if the application-code rollback above doesn't resolve the issue, or if the failed deployment's migration already wrote data in a new column/table that the application-code rollback's older code doesn't know how to handle correctly, restoring the database backup taken in §28 step 4 is safer than trusting an automatic `down()` migration against data that's no longer in the state it assumes. This does mean accepting the loss of any writes made between the backup and the rollback decision — an explicit, known tradeoff, which is exactly why §21's backup-frequency recommendation and §28's "always back up immediately before deploying" step matter.

**This release's own rollback baseline, for reference:** `PREVIOUS_GOOD_COMMIT=60847e9e5d6ef6cdf6a2ef9c272bf04efad36f95` (the commit checked out on staging immediately before this deployment), deployed release `3d37101a3ba2ad0b6ecfce222979cc982d667c5a`. A verified pre-deployment database backup was taken before migrating, and the deployment used maintenance mode throughout, per this section's and §28's procedure. Rollback was not needed for this release — the deployment succeeded — but these values are recorded here exactly as §28/§29 require for every future deployment.

---

## 30. Migration-on-boot behavior and migration failure handling

**Phase 11C re-examined whether `docker/entrypoint.sh` should keep running `php artisan migrate --force` automatically on every `app` container start, per this phase's explicit instruction to audit rather than assume.** Conclusion: **retained as-is, unchanged.**

**Why keep it:**
- Every migration in this app is additive-only (§8) — no current migration's `up()` drops or destructively alters anything.
- The alternative (a separate manual migration step) adds a step an operator can forget, and gains nothing here: `docker compose up -d app` already needs the maintenance-mode-first sequence (§28) around it regardless of whether migrations run inside that same command or as a separate one, because rebuilding/recreating the container itself briefly interrupts service either way.
- It keeps the deployment procedure simple, which matters for this scale (a single small workshop, one operator, no dedicated ops team) — the task's own guidance favors simplicity here over introducing a second manual gate that mirrors what maintenance mode already provides.

**The real consequence, made explicit rather than left implicit:** because this runs automatically, **any** command that starts or recreates the `app` container — not just a deliberate deployment — can execute pending migrations against whatever database `DB_HOST`/`DB_DATABASE` in the active `.env` currently points to. Concretely: `docker compose up -d app` (or a bare `docker compose up -d`, or Docker/the host restarting the container after a reboot) run against a staging/production `.env` will migrate that database, with no separate confirmation prompt. This is safe **only because**:
1. Migrations are additive-only (above), so there is nothing for an accidental/unplanned run to destroy going forward, and
2. §28's procedure always takes a verified backup (§21) before any deployment that could trigger a new migration, so even an unplanned run is recoverable.

**Operator rule this implies (documented, not enforced in code):** never run `docker compose up -d`/`docker compose restart app`/`docker compose build && up` against a staging/production `.env` casually, and never point a staging/production `.env`'s `DB_HOST`/`DB_DATABASE` at a database by copy-pasting an example without checking it first — the entrypoint will migrate whatever it finds, immediately, with no dry-run. If a future migration needs to be reviewed before it ever touches staging data, that review has to happen at the pull-request stage (before the branch is deployed), not at container-start time — this entrypoint has no gate for that.

**Known, verified operational risk:** `docker/entrypoint.sh` runs under `set -euo pipefail` and calls `php artisan migrate --force` unconditionally before handing off to Apache. **If a migration fails partway through, the entrypoint script exits non-zero, the container itself fails to start, and Apache never runs** — this is a real behavior of the current, unmodified entrypoint script (confirmed by reading it), not a hypothetical. A failed migration during `docker compose up -d` therefore does not leave the *old* container running (Compose stops/removes it as part of recreating) — it can result in the site being fully down (connection-refused/502 from the reverse proxy), which is worse than a clean maintenance-mode 503 page. This is exactly why §28's maintenance-mode-first sequence and pre-deployment backup are not optional steps.

If `php artisan migrate --force` fails partway through a deployment:

1. **Stay in maintenance mode conceptually** — do not attempt to serve traffic from a partially-migrated schema. If the container has already crashed (per the risk above), the site is already down regardless; do not try to "fix" this by repeatedly re-running `docker compose up -d` hoping it resolves itself.
2. **Inspect the actual error** first: `docker compose logs app` (the failure will be in there, since the entrypoint's `migrate --force` output is part of the container's stdout before it exits).
3. **Inspect migration state** without triggering another destructive attempt: `docker compose run --rm app php artisan migrate:status` (using `run --rm` rather than `exec`, since the failed container may not be running to `exec` into) — identify exactly which migration is `Pending` vs. already `Ran`.
4. **Do not blindly rerun `migrate --force` repeatedly** hoping a transient issue resolves — if the failure is a real schema conflict (not a transient DB-connectivity blip), rerunning risks a partially-applied migration being retried against a schema it wasn't designed to encounter twice. Diagnose the specific error first (a duplicate-column error, a connectivity timeout, a permissions issue, etc. all require different next actions).
5. **If a transient cause is confirmed** (e.g. MySQL genuinely wasn't ready yet despite the entrypoint's own wait-loop, or a momentary network blip) and `migrate:status` shows a clean "all previous migrations ran, only the newest is pending" state, it is reasonable to retry once (`docker compose run --rm app php artisan migrate --force`) after confirming the transient cause is resolved.
6. **If data integrity is uncertain** after any of the above (e.g. a migration partially wrote rows/columns before failing), **restore the pre-deployment backup** (§21/§29) rather than guessing — this is the conservative, correct choice per §29's own guidance.
7. **Return the application code to `PREVIOUS_GOOD_COMMIT`** (§29's application-code rollback) if the new commit's migration is the root cause and won't be fixed immediately — don't leave the VPS checked out to a commit whose migration doesn't apply cleanly while investigating.
8. Only after the schema and code are both confirmed consistent (either the new commit's migration is fixed and reapplied, or the rollback is complete) should `php artisan up` be run.

---

## 31. Known technical debt relevant to deployment safety

Per the task's own instruction, Phase 10's deferred items (accessibility, dashboard UX, `User::role` mass-assignment, `scheduled` status removal, email verification cleanup, per-technician authorization, bicycle archive/delete — full list in `docs/PROJECT_STATUS.md` §26.2) are **not** reproduced here, since none of them affect deployment safety. The only deployment-relevant observations from this audit are already captured above:

- The Docker image intentionally ships dev dependencies (Pint, PHPUnit) in every environment (§14) — a deliberate size/convenience tradeoff, not a defect.
- `storage/logs/laravel.log` has no rotation configured by default (§19/§20) — an `.env`-level fix (`LOG_STACK=daily`) or host `logrotate`, not a code change; still not applied by this phase (it's an ops/`.env` decision, not a deployment-tooling defect).
- ~~MySQL's and the app's host ports are published to all interfaces by default~~ — **fixed at the repository level in Phase 11C** (§0.3/§27): `compose.yaml`'s defaults now bind the app port to `127.0.0.1` and publish no MySQL host port at all. The host firewall remains the outer layer of defense regardless.
- A failed `php artisan migrate --force` currently crashes the `app` container rather than failing gracefully (§30) — investigated again in Phase 11C and deliberately left unchanged: the maintenance-mode-first sequence (§28) and mandatory pre-deployment backup (§21) are what actually protect against this, not a change to the entrypoint's failure behavior, and a more graceful failure mode would add complexity disproportionate to this app's scale without removing the need for those same safeguards.

None of these block Phase 11C being ready for review — see the final report for the complete list.

---

## 32. Server-only verification items (historical — from Phase 11A, before the real deployment)

This list was written before the real staging deployment happened. Every item below has since been resolved by that deployment (see §0, §1, §9.1, §17, §25, §27, §28's rollback-baseline note) **except** where marked still-open. It is kept here as a historical record of what Phase 11A correctly identified as needing live verification, not as a current to-do list.

1. ~~Actual PHP version and extension set running in the deployed `app` container~~ — confirmed matching this repository (PHP 8.4, per the task brief's runtime summary).
2. ~~The reverse-proxy layer's exact configuration~~ — confirmed as host Nginx (behind Cloudflare) forwarding to `127.0.0.1:8013`; see §1's request-path diagram. The vhost file's literal contents remain outside this repository, by design (host-level config, not app config).
3. ~~The live `.env`'s actual values for `APP_ENV`, `APP_DEBUG`, `APP_URL`~~ — confirmed: `APP_ENV=staging`, `APP_DEBUG=false`, `APP_URL=https://bikeworkshop.storm-ark.com` (corrected during deployment, §17). `LOG_LEVEL`/`LOG_STACK`/`SESSION_SECURE_COOKIE`/`MAIL_MAILER`/`DB_SEED_ON_BOOT` values were not part of the reported verification and remain **[still open — verify directly if needed]**, though none of them are deployment blockers.
4. ~~Whether the initial `php artisan db:seed` has already been run on staging~~ — implied yes: the booking wizard's dropdowns (bicycle types/parts) were exercised during the manually-verified customer/staff workflow, which requires that lookup data to exist.
5. VPS firewall rules for the app/MySQL ports — **still recommended to confirm** (`ufw` exposing only SSH/HTTP/HTTPS, per the task's stated desired posture) — this repository cannot verify host firewall state, only what Docker itself publishes (now narrowed, §27).
6. Host-level OS user/permissions on the VPS checkout directory — **[still open]**, not reported either way; no friction was reported during the real deployment, which is a weak positive signal but not a confirmation.
7. Current `storage/logs/laravel.log` size and host-level log rotation — **[still open]**, not part of this deployment's scope.
8. MySQL backup method/retention on the VPS beyond the one-time pre-deployment backup described in §21/§28 — **[still open]**; §21 documents the recommended recurring approach, but whether it's actually scheduled on the VPS is an ops task outside this phase.
9. Disk space available on the VPS — **[still open]**, not reported.
10. ~~Currently deployed Git commit (`PREVIOUS_GOOD_COMMIT`)~~ — confirmed: `60847e9e5d6ef6cdf6a2ef9c272bf04efad36f95` (§29).
11. ~~Whether any manual staff/technician accounts already exist on staging~~ — confirmed yes: a legacy `staff@example.com` account predating Phase 10B exists; see §9.1 for the (manual, out-of-repository) review/cleanup guidance.
12. Active Docker/system services on the VPS beyond this app (Pickleverse, Company App) — no collision was reported during the real deployment, consistent with the container/volume/network name prefixing (§1) working as designed; not independently re-verified by this phase.

---

## 33. Summary — smallest reliable production command set

For quick reference, pulling together §12/§14/§28. Run every command below with `COMPOSE_FILE=compose.yaml` set in that host's `.env` (§0.3/§27), so `compose.override.yaml`'s local-dev-only MySQL port publishing never applies on staging/production:

```bash
# Deployment (run from the VPS checkout, <DEPLOY_PATH>)
docker compose exec app php artisan down --retry=60
cp .env .env.backup-$(date +%F-%H%M)
docker compose exec mysql sh -c 'mysqldump -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" --no-tablespaces "$MYSQL_DATABASE"' | gzip > backup-$(date +%F-%H%M).sql.gz
git fetch origin <branch> && git checkout <target-commit>
docker compose build app
docker compose up -d
docker compose exec app php artisan migrate:status   # confirm clean before proceeding
docker compose exec app php artisan config:cache   # optional — cleared again on the container's NEXT start, see §0.5
docker compose exec app php artisan route:cache    # optional — persists across restarts, unaffected by §0.5
docker compose exec app php artisan view:cache     # optional — persists across restarts, unaffected by §0.5
docker compose exec app php artisan up
curl -I https://bikeworkshop.storm-ark.com/up

# Troubleshooting / rollback cache issues
docker compose exec app php artisan optimize:clear
```

`event:cache` is intentionally omitted (§12 — safe but a no-op today). `--no-dev` is intentionally not part of the Composer step (§14 — it happens inside `docker compose build`, not as a separate manual command, and is deliberately not used here). `storage:link` is intentionally omitted (§6 — not required). None of the three optional cache commands above are needed to keep the app *correct* — `docker/entrypoint.sh` itself now guarantees a fresh `.env`/config read on every start regardless (§0.5). They only affect a minor performance optimization: `config:cache` specifically must be re-run after every subsequent restart to stay in effect (§0.5), while `route:cache`/`view:cache` persist across restarts unaffected.
