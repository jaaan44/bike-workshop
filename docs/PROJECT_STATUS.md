# Project Status — Bicycle Workshop Management App

**Audit date:** 2026-09-14 (original audit below); updated 2026-09-20 for Phase 7 — Customer Tracking + Staff Dashboard Fix; **updated 2026-09-20** for Phase 8 — In-App Notifications.
**Branch audited:** `claude/bicycle-workshop-app-6mp087` (original); Phase 7 developed on `claude/phase-7-customer-tracking-dashboard-xgv1hy`; Phase 8 developed on `claude/phase-8-in-app-notifications-y2zjme`
**Commit audited:** `6bfc49e` ("Add Phase 6: repair workflow (inspection through completion)")

This document is a factual snapshot of what exists in the repository as of the commit above, plus Phase 7 and Phase 8 updates layered on top (marked **"(Phase 7)"** / **"(Phase 8)"** where they change an earlier finding). It was produced by reading the actual code (models, controllers, migrations, routes, views, tests), not by inferring from commit messages or specs. Anything marked **PLANNED / FUTURE** does not exist yet — it is called out explicitly so it is never confused with working functionality.

---

## 1. Project Overview

A mobile-first web app for a bicycle repair shop. Customers register, add their bicycles, and book repairs by selecting which parts/areas need attention. Workshop staff and technicians review bookings, receive the bike, inspect it, get customer approval on proposed repairs, do the work, run a quality check, and hand the bike back. Every status change is recorded for audit purposes, and (as of Phase 7) customers can see that full audit trail as a chronological repair timeline on their own booking's detail page.

The application has been built incrementally in seven phases (see commit history), each phase adding one vertical slice of functionality with tests and a manual verification pass. Phase 7 (Customer Repair Tracking + Staff Dashboard Fix) is complete and merged into this branch.

---

## 2. Technology Stack

Versions below were confirmed by running the actual tools in this environment, not assumed:

| Component | Version (confirmed) | Notes |
|---|---|---|
| PHP | 8.4.19 (CLI, NTS) | `composer.json` requires `^8.3` |
| Laravel Framework | 13.25.0 | `composer.json` requires `^13.17` |
| Composer | 2.8.12 | |
| Node.js | v22.22.2 | |
| npm | 10.9.7 | |
| Laravel Breeze | ^2.4 (dev dependency) | Blade stack, scaffolded auth |
| Tailwind CSS | **3.4.19** (actually installed/used) | See discrepancy note below |
| Alpine.js | ^3.4.2 | |
| Vite | ^8.0.0 (via `laravel-vite-plugin` ^3.1) | |
| Database | MySQL 8.4 (via Docker Compose), SQLite (used only for isolated local test runs) | |
| Test runner | PHPUnit ^12.5.12 via `php artisan test` | |
| Code style | Laravel Pint ^1.27 | |

**Discrepancy found:** `package.json` lists `@tailwindcss/vite: ^4.0.0` as a dev dependency, but it is **not** imported in `vite.config.js`, and `tailwindcss` itself is pinned to `^3.1.0` (resolved to 3.4.19 in `package-lock.json`). The project actually builds with classic Tailwind v3 tooling: `postcss.config.js` (`tailwindcss` + `autoprefixer` plugins) and `resources/css/app.css` using `@tailwind base/components/utilities` directives, driven by `tailwind.config.js` (v3-style config with a `content` array). The `@tailwindcss/vite` v4 package appears to be an unused leftover from the initial Breeze scaffold and has no effect on the build. Confirmed by running `npm run build`, which succeeds and produces a normal Tailwind v3 CSS bundle.

---

## 3. Development Environment

**Updated since the original audit above:** the project is now Dockerized with one canonical Compose setup, and the shared VPS is its primary target — local development uses the exact same setup. There is no GitHub Codespaces/devcontainer support (deliberately removed, not a gap). This superseded the devcontainer-only setup and the stale root `compose.yaml` described in the original audit findings (§17.3–17.4 below are now resolved; left in place as a historical record of what was found and fixed).

### `compose.yaml` (repository root) — the one supported Docker workflow

Two services by default, both purpose-built for this project (no Laravel Sail):
- **`app`** — built from the root `Dockerfile` (PHP 8.4 + Apache on Debian bookworm). Apache serves Laravel's `public/` directly on container port 80; the host-side port is configurable (`APP_PORT`, default `8013`) and is never hardcoded inside the container or the app itself. Frontend assets (`npm run build`) are compiled into the image at build time, so the runtime container never needs Node/Vite to serve the app. The full repo is bind-mounted for live editing, with `vendor/` and `public/build` each shadowed by a dedicated named volume (`bicycle_workshop_vendor`, `bicycle_workshop_build`) so the image's build output survives the bind mount instead of being hidden by an empty/stale host directory.
- **`mysql`** — official `mysql:8.4` image, host port configurable (`DB_FORWARD_PORT`, default `3348`), persisted to a project-specific named volume (`bicycle_workshop_mysql_data`), with a healthcheck that `depends_on: condition: service_healthy` waits on before starting `app`.
- **`vite`** (optional) — same image, running `npm run dev` instead of Apache, for hot-reload during active frontend work. Gated behind a Compose `profiles: ["dev"]` entry, so it is never started by a plain `docker compose up -d`. Started explicitly with `docker compose --profile dev up -d vite`.

All container names, volume names, and the network name are explicitly prefixed `bicycle_workshop_` to avoid collisions with other projects sharing the same VPS (it runs at least two other applications: Pickleverse and Company App). No host networking is used anywhere in the stack.

`docker/entrypoint.sh` runs on every `app`/`vite` container start (not just first creation): creates `.env` from `.env.example` if missing, runs `composer install`/`npm run build` as a fallback safety net if the named volumes are somehow empty, generates `APP_KEY` if missing, fixes `storage`/`bootstrap/cache` ownership, waits for MySQL to accept TCP connections, then runs `php artisan migrate --force` (forward-only, never destructive) before handing off to Apache. Seeding is **not** run automatically — `DB_SEED_ON_BOOT=true` in `.env` opts in (intended for throwaway local databases only); the existing seeders are all existence-checked/`updateOrCreate`, so running `php artisan db:seed` manually at any time is safe to repeat.

### `.env.example`

Same MySQL application config as before (`DB_CONNECTION=mysql`, `DB_HOST=mysql`, `DB_PORT=3306`, `DB_DATABASE=bike_workshop`, `DB_USERNAME=sail`, `DB_PASSWORD=password` — unchanged, and must stay `mysql:3306` since that's the Docker-network hostname, not a host-facing port). Also: `APP_PORT=8013`, `DB_FORWARD_PORT=3348`, `VITE_FORWARD_PORT=5175` (host-side Docker port mappings, read by Compose's own `${VAR:-default}` substitution from this same file — not read by Laravel) and `DB_SEED_ON_BOOT=false`. `APP_URL` is a plain literal (`http://localhost:8013`) rather than an interpolated `${APP_PORT}` reference — confirmed during this work that phpdotenv only resolves `${VAR}` interpolation against variables already defined *earlier* in the file, and `APP_PORT` is defined further down (next to the other Docker-only settings), so the interpolated form silently produced `http://localhost:` with nothing after the colon.

### Startup procedure — confirmed accurate

```bash
cp .env.example .env    # first time only
docker compose build
docker compose up -d
```
Confirmed via `docker compose config` (valid) in this audit environment. **`docker compose build`/`up` could not be executed end-to-end in this specific sandboxed audit environment** — its egress policy blocks Docker Hub image pulls entirely (`php`, `mysql`, `composer`, even `alpine` all rejected with a policy-level 403 on `production.cloudfront.docker.com`, confirmed both via a plain `docker pull` and via `docker compose build`). This is an environment restriction, not a defect in the Compose/Dockerfile setup — the real VPS has normal internet access and is expected to pull these images without issue. The compose file, Dockerfile, and entrypoint were reviewed line-by-line and validated with every check that doesn't require a registry (`docker compose config`, plus re-running the full native test suite, Pint, and `npm run build` outside Docker to confirm nothing in the application itself was affected). Whoever next has a working Docker daemon with normal internet access should run through steps 2–13 of the standard verification checklist (`docker compose build`, `up`, `ps`, a `curl` against port 8013, `ss`/`netstat` against port 3348, `exec ... php artisan migrate:status`, `exec ... php artisan test`, `exec ... npm run build`, `exec ... pint`, a `down`/`up` restart check, and a log-tail check) before this is considered fully verified end-to-end.

---

## 4. Current Architecture

Standard Laravel MVC, no service layer, no repository layer, no API. Server-rendered Blade views with Alpine.js for client-side interactivity (multi-step wizard, modals) and Tailwind for styling. See `docs/ARCHITECTURE.md` for diagrams and request-flow detail.

---

## 5. Authentication

Laravel Breeze (Blade stack) is installed and in active use, lightly customized.

- **Registration** (`/register`, `RegisteredUserController`): creates a `User` with `role` forced to `UserRole::Customer` — there is no way for a public registration to create a staff/technician account. Accepts an optional `phone`. Logs the user in immediately after creation and redirects to `/dashboard`.
- **Login** (`/login`, `AuthenticatedSessionController` + `LoginRequest`): standard Breeze session-based login with rate limiting, redirects to `/dashboard` (which then redirects again based on role — see below).
- **Logout**: standard Breeze, invalidates session and regenerates CSRF token.
- **Password reset**: full Breeze flow present (`forgot-password`, `reset-password/{token}`) and covered by tests (`tests/Feature/Auth/PasswordResetTest.php`).
- **Password confirmation**: Breeze's `confirm-password` flow present, with tests.
- **Email verification**: the routes and controllers (`EmailVerificationPromptController`, `VerifyEmailController`, `EmailVerificationNotificationController`) exist from the Breeze scaffold and are tested (`tests/Feature/Auth/EmailVerificationTest.php`), **but verification is not actually enforced anywhere in the application.** `App\Models\User` has the `MustVerifyEmail` import commented out and does **not** implement that interface, and no route group in `routes/web.php` applies Laravel's `verified` middleware. In practice, a newly registered user can use the full application immediately with an unverified email. This is dead-but-present scaffolding, not a bug in the strict sense, but worth flagging (see Security Observations).
- **Session-based auth**: `SESSION_DRIVER=database` (per `.env.example`); standard `auth` middleware guards all authenticated routes.
- **Reverse proxy trust**: `bootstrap/app.php` calls `$middleware->trustProxies(at: '*')` so `asset()`/`url()` generate correct public URLs behind a reverse proxy — originally fixed for a real bug when the project ran under GitHub Codespaces' proxy (assets/CSRF pointed at `localhost:8000` instead of the public host), and equally relevant now on the VPS if any reverse proxy (Nginx, a load balancer, etc.) sits in front of the `app` container's published port.

---

## 6. Roles and Permissions

### Database representation

`users.role` is a plain `string` column (migration `2026_08_18_100803_add_role_and_phone_to_users_table.php`), indexed, default `'customer'`. Cast to the `App\Enums\UserRole` backed enum (`Customer`, `Staff`, `Technician`) on the `User` model.

### Middleware

`App\Http\Middleware\EnsureUserHasRole` (aliased as `role` in `bootstrap/app.php`) takes one or more role-value strings as parameters (e.g. `role:staff,technician`) and calls `abort(403)` if the authenticated user's role isn't in that list. Applied at the route-group level:
- `role:customer` — wraps the entire `/customer/*` route group.
- `role:staff,technician` — wraps the entire `/staff/*` route group.

There is currently **no distinction between `staff` and `technician` permissions** — both roles pass the same `role:staff,technician` gate and see the same `/staff/*` area, same navigation, same booking-management actions. The `User::isWorkshopUser()` helper and `UserRole::workshopRoles()` explicitly document this as a deliberate "share the workshop area for the first release" decision, not an oversight. There is no `Admin` role anywhere in the codebase.

### Policies

Two policies exist, both auto-discovered by Laravel's naming convention (no explicit registration in a service provider — Laravel 11+ resolves `App\Policies\{Model}Policy` from `App\Models\{Model}` automatically):
- `BicyclePolicy`: `view` and `update` abilities, both just `$user->id === $bicycle->user_id`.
- `BookingPolicy`: `view` ability only, `$user->id === $booking->user_id`. Reused for the customer-side approve/decline actions (same ownership check).

Staff-side authorization relies entirely on the `role` middleware — there is no `BookingPolicy` ability (e.g. `manage`) gating individual staff actions; any authenticated staff/technician user can act on any booking. This is consistent with the "share the workshop area" decision above but means there's no per-technician restriction (e.g. a technician acting on a booking not assigned to them is not blocked).

### Route protection

Confirmed via `php artisan route:list` and reading `routes/web.php`: every `/customer/*` route requires `auth` + `role:customer`; every `/staff/*` route requires `auth` + `role:staff,technician`; `/profile/*` requires only `auth` (available to any authenticated role). No route is unintentionally left open.

### UI authorization

Blade views don't re-check roles (the middleware already guarantees the right audience per area); the bottom navigation (`x-bottom-nav`) renders a different item set based on `$user->isWorkshopUser()`.

**Status: fully implemented for the two-tier model (customer vs. workshop) the app currently has.** A finer-grained technician-vs-staff permission split, or an admin role, does not exist and is not stubbed.

---

## 7. Database Schema Summary

See `docs/DATABASE.md` for the full table-by-table breakdown and ER diagram. In brief, 10 application tables beyond Laravel's framework tables (users, cache, jobs, sessions, password_reset_tokens): `bicycle_types`, `bicycles`, `bicycle_part_categories`, `bicycle_parts`, `bookings`, `booking_items`, `booking_status_histories`, `repair_inspections`, `repair_items`, `technician_notes`. All 15 migrations (3 framework + 12 application) run cleanly in sequence on a fresh database — verified in this audit via `migrate:fresh` against a disposable SQLite database. No obsolete, duplicated, or conflicting migrations were found.

---

## 8. Models and Relationships

See `docs/ARCHITECTURE.md` for the full relationship diagram. Nine Eloquent models exist: `User`, `Bicycle`, `BicycleType`, `BicyclePart`, `BicyclePartCategory`, `Booking`, `BookingStatusHistory`, `RepairInspection`, `RepairItem`, `TechnicianNote`.

Notable pattern used throughout: this codebase uses PHP 8 **attribute-based** mass-assignment configuration (`#[Fillable([...])]` from `Illuminate\Database\Eloquent\Attributes\Fillable`) instead of the classic `protected $fillable = [...]` property. Several fields are **deliberately excluded** from each model's `Fillable` list because they must never be settable from a raw HTTP request body (`Booking::status`, `Booking::assigned_technician_id`, `RepairItem::completed_at`). Every place that sets these fields does so via direct property assignment + `save()` rather than `update()`/`create()` with request data — this was a recurring bug class caught and fixed multiple times during development (mass assignment silently drops non-fillable attributes with no error), so it's now a deliberate, consistently-applied pattern rather than an oversight.

---

## 9. Current Routes

54 total routes (`php artisan route:list`). Framework/Breeze auth routes (`/login`, `/register`, `/forgot-password`, `/reset-password`, `/confirm-password`, `/verify-email`, `/logout`) are standard and not re-listed here. Application routes, grouped:

**Customer** (`auth`, `role:customer`, prefix `/customer`, name prefix `customer.`)
| Method | URL | Name | Controller@action |
|---|---|---|---|
| GET | `/customer/home` | `customer.home` | `HomeController@index` |
| GET | `/customer/bikes` | `customer.bikes.index` | `BikeController@index` |
| GET | `/customer/bikes/create` | `customer.bikes.create` | `BikeController@create` |
| POST | `/customer/bikes` | `customer.bikes.store` | `BikeController@store` |
| GET | `/customer/bikes/{bicycle}` | `customer.bikes.show` | `BikeController@show` |
| GET | `/customer/bikes/{bicycle}/edit` | `customer.bikes.edit` | `BikeController@edit` |
| PUT | `/customer/bikes/{bicycle}` | `customer.bikes.update` | `BikeController@update` |
| GET | `/customer/repairs` | `customer.repairs.index` | `RepairController@index` |
| GET | `/customer/repairs/create` | `customer.repairs.create` | `RepairController@create` |
| POST | `/customer/repairs` | `customer.repairs.store` | `RepairController@store` |
| GET | `/customer/repairs/{booking}` | `customer.repairs.show` | `RepairController@show` |
| POST | `/customer/repairs/{booking}/approve` | `customer.repairs.approve` | `RepairController@approve` |
| POST | `/customer/repairs/{booking}/decline` | `customer.repairs.decline` | `RepairController@decline` |

**Staff / Technician** (`auth`, `role:staff,technician`, prefix `/staff`, name prefix `staff.`)
| Method | URL | Name | Controller@action |
|---|---|---|---|
| GET | `/staff/dashboard` | `staff.dashboard` | `DashboardController@index` |
| GET | `/staff/bookings` | `staff.bookings.index` | `BookingController@index` |
| GET | `/staff/bookings/{booking}` | `staff.bookings.show` | `BookingController@show` |
| POST | `/staff/bookings/{booking}/accept` | `staff.bookings.accept` | `BookingController@accept` |
| POST | `/staff/bookings/{booking}/receive` | `staff.bookings.receive` | `BookingController@receive` |
| POST | `/staff/bookings/{booking}/cancel` | `staff.bookings.cancel` | `BookingController@cancel` |
| PUT | `/staff/bookings/{booking}/technician` | `staff.bookings.technician.update` | `BookingController@assignTechnician` |
| PUT | `/staff/bookings/{booking}/inspection` | `staff.bookings.inspection.update` | `BookingController@updateInspection` |
| POST | `/staff/bookings/{booking}/repair-items` | `staff.bookings.repair-items.store` | `BookingController@storeRepairItem` |
| POST | `/staff/bookings/{booking}/repair-items/{repairItem}/complete` | `staff.bookings.repair-items.complete` | `BookingController@completeRepairItem` |
| POST | `/staff/bookings/{booking}/notes` | `staff.bookings.notes.store` | `BookingController@storeTechnicianNote` |
| POST | `/staff/bookings/{booking}/request-approval` | `staff.bookings.approval.request` | `BookingController@requestApproval` |
| POST | `/staff/bookings/{booking}/repairs/complete` | `staff.bookings.repairs.complete` | `BookingController@completeRepairs` |
| POST | `/staff/bookings/{booking}/quality-check/start` | `staff.bookings.quality-check.start` | `BookingController@startQualityCheck` |
| POST | `/staff/bookings/{booking}/quality-check/pass` | `staff.bookings.quality-check.pass` | `BookingController@passQualityCheck` |
| POST | `/staff/bookings/{booking}/quality-check/fail` | `staff.bookings.quality-check.fail` | `BookingController@failQualityCheck` |
| POST | `/staff/bookings/{booking}/fulfill` | `staff.bookings.fulfill` | `BookingController@fulfill` |
| GET | `/staff/jobs` | `staff.jobs.index` | `JobController@index` |

**Shared** (`auth` only)
| Method | URL | Name |
|---|---|---|
| GET | `/dashboard` | `dashboard` (role-based redirect to `customer.home` or `staff.dashboard`) |
| GET/PATCH/DELETE | `/profile` | `profile.edit` / `profile.update` / `profile.destroy` |

There is no separate "Technician" route group — technicians use the same `/staff/*` routes as staff.

---

## 10. Current Screens

| Screen | Route | Role | Purpose | Complete? |
|---|---|---|---|---|
| Login | `login` | guest | Session login | ✅ |
| Register | `register` | guest | Customer signup only | ✅ |
| Forgot/reset password | `password.request`, `password.reset` | guest | Breeze default | ✅ |
| Customer Home | `customer.home` | customer | Greeting, "Book a Repair" CTA, bike list, 3 most recent repairs | ✅ |
| Bike List | `customer.bikes.index` | customer | List of the customer's own bicycles | ✅ |
| Add Bike | `customer.bikes.create` | customer | Full bicycle form | ✅ |
| Bike Detail | `customer.bikes.show` | customer | Read-only field list + Edit link | ✅ |
| Edit Bike | `customer.bikes.edit` | customer | Same form as create, pre-filled | ✅ |
| Repair List | `customer.repairs.index` | customer | List of the customer's bookings with status badges | ✅ |
| Book a Repair | `customer.repairs.create` | customer | 5-step Alpine wizard (bike → parts → remarks → date → review) | ✅ |
| Repair/Booking Detail | `customer.repairs.show` | customer | Status, submitted/appointment dates, reported issues/remarks, inspection findings + recommended work once shared, proposed-repairs + approve/decline once shared, contextual quality-check/pickup/delivery/completion messaging, full chronological repair timeline | ✅ (Phase 7 — see §14) |
| Staff Dashboard | `staff.dashboard` | staff/technician | Stat tiles (all seven now real counts) + active-jobs list | ✅ (Phase 7 — see §17.1) |
| Bookings List | `staff.bookings.index` | staff/technician | All bookings, priority-sorted | ✅ |
| Booking Detail | `staff.bookings.show` | staff/technician | Full booking management surface — the single largest view in the app: accept/receive/cancel, technician assignment, inspection form, repair items, technician notes, and every workflow-transition action | ✅ |
| Jobs List | `staff.jobs.index` | staff/technician | Bookings past acceptance (active work), technician's own assignments sorted first | ✅ |
| Profile | `profile.edit` | any authenticated | Breeze default: update name/email, change password, delete account | ✅ |

No screen was found to be a stub or placeholder with no working logic behind it, aside from the dashboard stat tiles noted above.

---

## 11. Customer Features

- ✅ Add bicycle, edit bicycle, view bicycle, list bicycles
- ❌ **Delete bicycle** — no route, no controller action, no UI button exists for this
- ✅ Bicycle type (dropdown, from a seeded 10-value lookup table)
- ✅ Nickname, brand, model, color, wheel size, serial number, year, notes — all present as columns, form fields, and detail-view rows
- ✅ Book a repair (5-step wizard)
- ✅ Select multiple bicycle parts/problem areas across multiple categories (many-to-many via `booking_items`)
- ✅ Free-text remarks
- ✅ Appointment date (a single date picker — no time slot or capacity concept, see §12)
- ✅ View booking history/status
- ✅ Approve or decline the shop's proposed repairs
- 🟡 Repair history is just the existing repairs list (`customer.repairs.index`) filtered to that customer — there's no bicycle-specific "past repairs for this bike" view

---

## 12. Staff Features

- ✅ Incoming bookings list + detail view
- ✅ Accept / cancel a pending or accepted booking
- ✅ Mark bicycle received
- ✅ Record inspection findings + recommended repairs (auto-transitions `bike_received` → `inspection` on first save)
- ✅ Assign/unassign a technician (restricted at the validation layer to users with the `technician` role)
- ✅ Add repair items, mark each complete independently
- ✅ Add free-text technician notes (append-only, timestamped, attributed)
- ✅ Send proposed repairs to the customer for approval (blocked if there are zero repair items)
- ✅ Mark repairs completed (blocked unless every repair item is marked done)
- ✅ Quality check: start → pass (choose pickup or delivery) or fail (sends back to `repair_in_progress` for rework)
- ✅ Mark picked up / delivered → completed
- ✅ Full status-change audit trail (`booking_status_histories`, `old_status`/`new_status`/`changed_by`/timestamp per change)

Everything above is implemented for both `staff` and `technician` roles identically — there is no staff-only or technician-only action.

---

## 13. Technician Features

There is no dedicated technician-only screen or route group. Technicians use the exact same `/staff/*` area as staff, with two differences that exist in code:
1. Only users with the `technician` role can be selected in the "Assigned Technician" dropdown (enforced server-side via a validation rule, not just hidden client-side).
2. On the Jobs list (`staff.jobs.index`), bookings assigned to the currently logged-in technician are sorted to the top.

Beyond that, a technician has identical permissions to staff (can accept/cancel bookings, manage any booking's inspection/items/notes, run quality checks, etc.) — this is a deliberate "first release" simplification, not a bug (see §6).

---

## 14. Current Repair Workflow

Fully implemented, end-to-end, verified in this audit with a live HTTP walkthrough of a real booking through every step (including a deliberate quality-check failure and rework loop):

```
pending → accepted → bike_received → inspection → awaiting_customer_approval
  → repair_in_progress → repair_completed → quality_check
  → (pass) ready_for_pickup | ready_for_delivery → completed
  → (fail from quality_check) repair_in_progress  [rework loop]
pending | accepted → cancelled                     [cancellation, terminal]
```

Every arrow above corresponds to an actual, tested, guarded controller action — none of this is aspirational. See `docs/ARCHITECTURE.md` for the full transition table and guard conditions.

**What "customer tracking" amounts to as of Phase 7:** the customer sees the current status badge everywhere their booking appears; on the booking detail page (`customer.repairs.show`) they additionally see the bicycle, submitted date, appointment date, a "last update" relative timestamp, and — once the booking reaches a "shareable" status (`awaiting_customer_approval` or later, same gate as before) — the inspection's findings and recommended repairs, the itemized repair-item list with each item's completed/pending state, Approve/Decline while awaiting approval (or "You approved these repairs on {date}" afterward), contextual messaging for quality-check/ready-for-pickup/ready-for-delivery/completed, and a full **chronological repair timeline** built directly from `Booking::statusHistories()` (the same relationship/audit trail staff already used — no new table, no duplicated data). The timeline's first entry ("Booking Submitted") is synthesized from `booking->created_at` because booking creation itself doesn't write a status-history row (see `Booking::booted()`); every later entry is a real `booking_status_histories` row rendered with its existing `BookingStatus::label()`, so a quality-check rework loop (`quality_check → repair_in_progress → repair_completed → quality_check`) shows every real pass through those stages rather than a deduplicated/linear view.

**As of Phase 8**, the timeline/tracking page above is complemented — not replaced — by in-app **notifications**: a database-backed alert fires automatically whenever a booking transitions to one of eight customer-meaningful statuses (see §22 below for the full list and architecture). The repair timeline remains the authoritative, complete history; notifications are alerts the customer can read/dismiss and are not a substitute for it. Email/SMS/push notifications remain out of scope and unimplemented — only in-app/database notifications exist.

---

## 15. Status Workflow

Statuses are implemented as a PHP 8.1+ **backed enum**, `App\Enums\BookingStatus` (string-backed, stored as a plain `string` column on `bookings.status`, cast to the enum via Eloquent's `casts()`). Thirteen values are defined:

`pending`, `accepted`, `scheduled`, `bike_received`, `inspection`, `awaiting_customer_approval`, `repair_in_progress`, `repair_completed`, `quality_check`, `ready_for_pickup`, `ready_for_delivery`, `completed`, `cancelled`.

**`scheduled` is defined but never used.** It has a `label()` string and a color mapping in the `<x-status-badge>` component, but no controller anywhere ever sets a booking to this status — it appears to be a placeholder for a possible future "confirmed appointment slot" concept that was never wired up. This is the only unused enum case.

Transitions are not validated against a formal state-transition table/graph — each controller action independently checks the booking's *current* status (`if ($booking->status !== X) { return back()->with('error', ...); }`) before calling `Booking::transitionTo()`, which atomically (DB transaction) writes a `booking_status_histories` row and updates `bookings.status`. This means the valid transitions are defined by scattered `if` guards across `Staff\BookingController` and `Customer\RepairController`, not by a single declarative source of truth. All of them were read directly for this audit; the transition list in §14 is exhaustive as of this commit.

---

## 16. Tests

Full results as of Phase 8 (run via `php artisan test` against a temporary SQLite database, since no MySQL server is reachable in this sandboxed environment — see `docs/HANDOFF.md` for the native-SQLite test procedure):

```
Tests: 121, Passed: 121, Failed: 0, Assertions: 309
Duration: ~3.0–3.3s
```

Breakdown by file:

| File | Tests |
|---|---|
| `tests/Feature/StaffBookingManagementTest.php` | 28 |
| `tests/Feature/CustomerNotificationTest.php` | 23 (Phase 8) |
| `tests/Feature/BookingTest.php` | 14 |
| `tests/Feature/StaffDashboardTest.php` | 7 (Phase 7) |
| `tests/Feature/BicycleTest.php` | 7 |
| `tests/Feature/RoleAccessTest.php` | 7 |
| `tests/Feature/CustomerRepairTrackingTest.php` | 9 (Phase 7) |
| `tests/Feature/ProfileTest.php` | 5 |
| `tests/Feature/Auth/AuthenticationTest.php` | 4 |
| `tests/Feature/Auth/PasswordResetTest.php` | 4 |
| `tests/Feature/Auth/EmailVerificationTest.php` | 3 |
| `tests/Feature/Auth/PasswordConfirmationTest.php` | 3 |
| `tests/Feature/Auth/RegistrationTest.php` | 3 |
| `tests/Feature/Auth/PasswordUpdateTest.php` | 2 |
| `tests/Feature/ExampleTest.php` | 1 |
| `tests/Unit/ExampleTest.php` | 1 |
| **Total** | **121** |

**Coverage is strong** on: role-based route access, the full repair-booking wizard (including cross-customer authorization), every staff booking-management transition and its guard conditions (including the two mass-assignment traps that were specifically regression-tested), customer approve/decline + cross-customer authorization, standard Breeze auth flows, the customer repair-tracking page (timeline ordering, minimal-history bookings, the quality-check rework loop rendering every real pass through the loop, inspection/repair-item rendering when present and when absent, ready-for-pickup/delivery and completed states), the staff dashboard's seven stat-tile counts, and — as of Phase 8 — **notifications**: generation on all eight notifiable transitions (data-provider-driven, one case per status), non-generation on internal-only transitions (`inspection`, `quality_check`, `cancelled`, `scheduled`), no notification from booking creation itself, the QC-rework loop producing a second `repair_in_progress`/`repair_completed` notification pair rather than being deduplicated, that a guarded/rejected transition (the controller never reaching `transitionTo()`) leaves no stray notification, newest-first ordering on the notifications index, unread vs. read rendering, cross-customer notification access being rejected (both reading another customer's notification in one's own list and attempting to mark it read), that a notification's redirect still can't bypass `BookingPolicy::view` even if the stored `booking_id` pointed at a booking the notifiable customer doesn't own, mark-as-read-and-redirect behavior, mark-all-as-read scoped to only the authenticated customer, and the nav unread-count badge reflecting only the authenticated customer's own count.

**Areas without test coverage:**
- No Dusk/browser-level test exists in the repo today (a one-off Playwright script was used during development to verify a specific bug fix, then removed — it is not part of the committed test suite). Phase 7 and Phase 8's manual verification (see each phase's HTTP walkthrough) covered the same ground a browser test would; Phase 8's walkthrough (booking → accept → notification → read → redirect → inspection → approval-required notification → approve → repair → QC rework loop → ready-for-pickup → badge count → mark-all-as-read → cross-customer rejection) was run as a temporary, non-committed feature test exercising the real HTTP kernel end-to-end, then discarded.
- No test covers the `bicycle_types`/`bicycle_part_categories` seeders directly (they are exercised indirectly via feature tests that depend on seeded/factory data).
- `npm run build` was run manually for Phase 7 and Phase 8 (succeeds, <1s) — there is no automated frontend test or CI step that runs it. Phase 8 added no new frontend assets/JS — the nav badge and notifications page are plain Blade/Tailwind, same as the rest of the app.

`./vendor/bin/pint --dirty` / a full `pint` pass reports no style violations on the current tree (the project has been kept Pint-clean throughout development, including Phase 7 and Phase 8's changes).

---

## 17. Known Issues

1. ~~**Staff dashboard stat tiles are partially hardcoded.**~~ — **resolved in Phase 7.** `Staff\DashboardController::index()` now computes all seven tile counts (`newBookingsCount`, `acceptedCount`, `bikeReceivedCount`, `awaitingApprovalCount`, `inRepairCount`, `qualityCheckCount`, `readyCount`) from one grouped `count(*) ... GROUP BY status` query (no N+1, no loading bookings into PHP just to count them) and `resources/views/staff/dashboard.blade.php` renders all of them instead of the four literal `0`s. `readyCount` sums both `ready_for_pickup` and `ready_for_delivery` since the UI's "Ready" tile always meant both fulfillment methods together. See `tests/Feature/StaffDashboardTest.php` for regression coverage.
2. **`scheduled` status is dead code** — defined, styled, never set (§15).
3. **README's "Project status" section is stale.** It currently states "Bicycle registration, booking, and the repair workflow are not yet built," which was true when it was written (end of Phase 1) but has been false since Phase 2. This audit's documentation (and the README update made alongside it) corrects this.
4. ~~Root-level `compose.yaml` (Sail) is unused dead configuration~~ — **resolved**: `compose.yaml` is now the project's single canonical Docker Compose setup (see updated §3); the stale Sail version it used to contain is gone.
5. **`@tailwindcss/vite` v4 package listed but unused** (§2) — cosmetic `package.json` inconsistency, no functional effect. Still present; out of scope for the Docker work.
6. ~~Dev servers (`php artisan serve`, `npm run dev`) are not auto-started on container creation~~ — **resolved**: `docker compose up -d` now starts Apache automatically inside the `app` container; neither command needs to be run by hand anymore (see updated §3).

No data-loss, broken-migration, or failing-test issues were found.

---

## 18. Security Observations

Light review only — not a full security audit. No high-severity issues found.

- **Ownership checks are consistently applied** where they matter: `BicyclePolicy`/`BookingPolicy` gate customer access to their own bicycles/bookings (`$this->authorize(...)` calls present on every customer controller action that takes a route-bound model), and this is positively tested (`test_customer_cannot_view_another_customers_booking`, `test_customer_cannot_book_a_repair_for_another_customers_bicycle`, `test_customer_cannot_approve_another_customers_booking`, etc.). The Phase 7 repair-tracking page reuses this same `BookingPolicy::view` check (`RepairController::show()` already called `$this->authorize('view', $booking)` before Phase 7; that call was not changed) — confirmed with a live HTTP walkthrough (log in as one customer, request another customer's booking URL directly) returning 403, matching the existing `test_customer_cannot_view_another_customers_booking` coverage.
- **Mass-assignment protection is deliberately used as a security boundary**, not just a convenience: `status`, `assigned_technician_id`, and `completed_at` are excluded from their models' `#[Fillable]` lists specifically so a crafted request body can't set them directly through `update()`/`create()`. Every write path for these fields was checked and uses direct property assignment instead.
- **Technician assignment is validated server-side** against the `technician` role (`Rule::exists('users', 'id')->where('role', UserRole::Technician->value)`), not just filtered in the UI — confirmed by a passing test that a non-technician user ID is rejected.
- **CSRF**: standard Laravel CSRF middleware applies to all state-changing routes; every form in every Blade view checked includes `@csrf`.
- **No admin/debug routes are exposed.** `APP_DEBUG=true` in `.env.example` is standard for local dev; this must be set to `false` in the real `.env` used on the VPS (not committed — see `.env.example`'s own guidance) since APP_DEBUG=true on a production-facing deployment leaks stack traces/config to visitors.
- **No secrets are committed.** `.env` is gitignored and confirmed absent from git history (`git log --all --diff-filter=A --name-only` shows no `.env` was ever added).
- **Staff/technician actions have no per-user restriction** (§6/§13) — any staff or technician can act on any booking, including one assigned to a different technician. This is a documented design choice for the "first release," not an oversight, but it does mean there's currently no way to restrict a technician to only their own assigned jobs at the authorization layer (only UI sort-order nudges toward it).
- **Email verification is scaffolded but not enforced** (§5) — not a vulnerability per se (nothing sensitive is gated on it), but worth knowing if a future requirement assumes verified emails.
- Validation was spot-checked across `BicycleRequest`, `BookingRequest`, and the inline `$request->validate([...])` calls in `Staff\BookingController` / `Customer\RepairController` — all user input reaching a write path has explicit validation rules; none were found relying on implicit/absent validation.

---

## 19. Technical Debt

**High Priority**
- ~~Staff dashboard stat tiles hardcoded to `0` for half the workflow~~ — resolved in Phase 7 (§17.1).

**Medium Priority**
- ~~No customer-facing status-history timeline~~ — resolved in Phase 7 (§14): `customer.repairs.show` now renders a full chronological timeline from `Booking::statusHistories()`.
- No per-technician authorization boundary — anyone in the workshop role can touch any booking.
- `Booking` model relationships plus five new controller action methods have made `Staff\BookingController` the largest controller in the app (17 public actions). Still readable and each action is small/single-purpose, but it's a candidate for splitting (e.g. a separate controller for the quality-check sub-flow) if the workflow grows further.
- Status-transition guards are duplicated `if ($booking->status !== X)` checks scattered across two controllers rather than a single declarative transition table — works correctly today (every transition is tested) but makes it easy to introduce an inconsistency if a new transition is added without checking every existing guard.

**Low Priority**
- The unused `@tailwindcss/vite` dependency is dead but harmless (root `compose.yaml` is no longer dead — see §17.4).
- `scheduled` booking status is unused.
- The named-volume-over-bind-mount pattern used for `vendor/` and `public/build` in `compose.yaml` means an image rebuild is required after changing `composer.json`/`composer.lock` or frontend source for those changes to actually take effect in a running container — a normal Docker tradeoff, but worth knowing (`docker compose build app && docker compose up -d` covers it).
- No repository/service layer — all business logic lives directly in controllers and the `Booking` model. This has not caused a problem yet (controllers stay small and single-purpose), but is worth watching as more workflow phases are added.
- README's project-status blurb needed updating (fixed alongside this audit).

No TODO/FIXME/XXX comments exist anywhere in the codebase (`grep` returned zero matches across `app/`, `resources/`, `routes/`, `database/`, `tests/`) — the codebase does not have "unfinished code" markers left behind.

---

## 20. Feature Completion Matrix

| Feature | Status | Notes |
|---|---|---|
| Authentication | ✅ | Breeze, session-based; email verification scaffolded but not enforced |
| Customer profile | ✅ | Breeze default: edit name/email, change password, delete account |
| Bicycle management | 🟡 | Add/edit/view/list all work; no delete |
| Bicycle types | ✅ | Seeded lookup table (10 types) |
| Bicycle part categories | ✅ | Seeded lookup table (8 categories, ~50 parts) |
| Repair booking | ✅ | 5-step wizard, fully working incl. multi-category part selection |
| Appointment schedule | 🟡 | Single date picker only — no time slots, no capacity/availability logic |
| Multiple repair issues | ✅ | Many-to-many bicycle parts per booking |
| Staff booking review | ✅ | List + detail view |
| Booking acceptance | ✅ | Accept/cancel with status guards |
| Bike received | ✅ | |
| Inspection | ✅ | Findings + recommended repairs, auto-transitions status |
| Technician assignment | ✅ | Server-side role validation |
| Repair items | ✅ | Add + independent completion tracking |
| Technician notes | ✅ | Append-only, timestamped, attributed |
| Repair status history | ✅ | Full audit trail (`booking_status_histories`) — visible to both staff and, as of Phase 7, the owning customer |
| Repair completion | ✅ | Guarded on all repair items being done |
| Quality check | ✅ | Pass (pickup/delivery choice) or fail (rework loop) |
| Ready for pickup | ✅ | Pickup and delivery both supported |
| Customer tracking | ✅ | Phase 7: status badge, submitted/appointment/last-update info, inspection findings + recommended work, itemized proposed repairs with approval state, contextual QC/pickup/delivery/completion messaging, and a full chronological timeline built from `booking_status_histories`. |
| Staff dashboard accuracy | ✅ | Phase 7: all seven stat tiles are real, efficiently-queried counts (§17.1) |
| Repair history | 🟡 | Existing bookings list only; no per-bicycle history view |
| In-app notifications | ✅ | Phase 8: database-backed customer notifications on 8 lifecycle transitions, notifications index page, unread-count nav badge, mark-as-read/mark-all-as-read, cross-customer authorization enforced. Email/SMS/push remain out of scope — see §22. |

---

## 21. Recommended Next Development Phase

Phase 7 (Customer Tracking + Staff Dashboard Fix) and Phase 8 (In-App Notifications) closed the gaps this document previously flagged as the natural next steps. Based on the current state of the app, reasonable candidates for a Phase 9 (not implemented, not started — listed here only as a recommendation per this document's own convention) are, roughly in priority order:

1. **Per-technician authorization boundary** — restrict a technician to acting only on bookings assigned to them, rather than any staff/technician being able to touch any booking (§6, §19).
2. **Bicycle delete** and a **per-bicycle repair history view** (§11, §20) — both small, self-contained gaps.
3. **Delivery channels beyond in-app** — email (or SMS) delivery for the same notification events, now that the database-notification plumbing and the one centralized trigger point (`Booking::transitionTo()`) already exist; would mean adding a `mail` (or other) channel to `via()` in `App\Notifications\BookingStatusUpdated` and, per Phase 8's own explicit scope boundary, introducing queue infrastructure first so mail sending doesn't block the request. Deliberately not started in Phase 8.
4. Splitting `Staff\BookingController` (still the largest controller in the app) if the workflow grows further, and/or formalizing the scattered status-transition guards into a single declarative table (§19) — cleanup, not user-facing.

This recommendation is **not implemented** — Phase 9 has not been started.

---

## 22. Phase 8 — In-App Notifications

Phase 8 adds database-backed, in-app-only customer notifications on top of the existing repair workflow. No email/SMS/push/WebSocket/queue infrastructure was introduced — per the phase's own scope, delivery is synchronous and confined to Laravel's standard `database` notification channel.

### 22.1 Architecture

**Migration:** one new table, `notifications` — Laravel's own standard schema (the same columns `php artisan make:notifications-table` generates, matching `Illuminate\Notifications\DatabaseNotification`'s expectations: UUID primary key, `type`, polymorphic `notifiable_type`/`notifiable_id`, `data`, `read_at`). No project-specific notification table was introduced; `App\Models\User` already `use`d the `Notifiable` trait (present since the initial Breeze scaffold, previously only wired for Breeze's own email-verification notification) so no model change was needed on that side.

**Trigger point:** `Booking::transitionTo()` (`app/Models/Booking.php`) — the same single funnel every controller action already used to change a booking's status. After writing the `booking_status_histories` row and saving the new status (inside the existing `DB::transaction()`), it checks `BookingStatus::customerNotificationMessage()` for the target status; if it returns a message, a `DB::afterCommit()` callback is registered to send `App\Notifications\BookingStatusUpdated` to `$this->user` (the booking's owner). Using `DB::afterCommit()` rather than sending the notification directly means a rolled-back transition (an exception thrown mid-transaction) never leaves a stray notification behind — the callback simply never runs. No controller was touched to add this; every existing call site (`Staff\BookingController`, `Customer\RepairController`) automatically gained notification dispatch for free, which is exactly the "one reliable place" the phase's brief asked for instead of scattering `notify()` calls across controllers.

**Which statuses notify:** `BookingStatus::customerNotificationMessage()` (`app/Enums/BookingStatus.php`) is the single source of truth — a `match` expression returning a customer-facing message string for 8 of the enum's 13 cases, and `null` for the rest:

| Status | Notifies? | Message |
|---|---|---|
| `accepted` | ✅ | "Your repair booking has been accepted." |
| `bike_received` | ✅ | "We've received your bicycle." |
| `awaiting_customer_approval` | ✅ | "Your bicycle inspection is complete. Please review and approve the proposed repair work." |
| `repair_in_progress` | ✅ | "Repair work on your bicycle has started." |
| `repair_completed` | ✅ | "Repair work on your bicycle has been completed and is awaiting quality inspection." |
| `ready_for_pickup` | ✅ | "Your bicycle is ready for pickup." |
| `ready_for_delivery` | ✅ | "Your bicycle is ready for delivery." |
| `completed` | ✅ | "Your bicycle repair has been completed." |
| `pending`, `scheduled`, `inspection`, `quality_check`, `cancelled` | ❌ | n/a — purely internal/administrative stages, or (for `pending`) never reached via `transitionTo()` at all (see below) |

`pending` is set directly in `Booking::booted()` at creation time, not via `transitionTo()`, so it was never a candidate — this also means **no notification fires merely because a booking was created**, consistent with the phase's "avoid noisy notifications" requirement. `inspection` and `quality_check` are intermediate/internal stages the customer doesn't need an alert for (the *next* stage after each — `awaiting_customer_approval` and `ready_for_pickup`/`ready_for_delivery`/back to `repair_in_progress` — is what's actually notified). `cancelled` and `scheduled` (the latter already dead code, see §15/§17) were deliberately left out of the 8-status spec and are not notified.

**QC rework loop:** the enum method is keyed purely on the *target status of this transition*, not "has this booking ever reached this status before" — so `quality_check → repair_in_progress` (rework) fires the same `repair_in_progress` notification a second time, and the following `repair_completed` fires again too. Nothing in the implementation deduplicates by status value; `tests/Feature/CustomerNotificationTest.php::test_quality_check_rework_loop_produces_a_second_repair_in_progress_notification` asserts exactly 2 notifications of each kind after two full passes through the loop.

**Notification payload** (`App\Notifications\BookingStatusUpdated::toArray()`, `app/Notifications/BookingStatusUpdated.php`): `booking_id`, `booking_reference_number`, `status` (the enum's string value), and `message` — identifiers plus the pre-rendered customer-facing message, not a snapshot of the booking, inspection, or repair items. The notifications index view reads `booking_id` to build the "open" link and `booking_reference_number`/`message`/`created_at` to render the list; nothing else in the booking is duplicated into the notification.

### 22.2 Customer UI

- **Notifications index** (`customer.notifications.index` → `GET /customer/notifications`, `App\Http\Controllers\Customer\NotificationController::index`, view `resources/views/customer/notifications/index.blade.php`): the authenticated customer's own notifications (`$request->user()->notifications()`, Laravel's own `Notifiable::notifications()` relation — ordered newest-first by default), paginated 20 per page. Each row shows the message, a relative timestamp (`created_at->diffForHumans()`), the booking reference number, and unread state. Unread items are distinguished by **both** a background tint (`bg-indigo-50`) **and** a small solid dot plus bold text plus a screen-reader-only "(unread)" label — not color alone, per the phase's accessibility requirement. Each row is itself a `POST` form (consistent with every other state-changing action in this app's UI, e.g. approve/decline) that marks the notification read and redirects to its booking. Empty state reuses the existing `<x-empty-state>` component.
- **Mark all as read**: a button in the page header, shown only when at least one notification is unread, `POST`s to `customer.notifications.read-all`.
- **Unread-count nav badge** (`resources/views/components/bottom-nav.blade.php`): the customer bottom-nav gained a fifth item ("Alerts", 🔔), and the grid changed from a fixed 4-column to a conditional 4/5-column layout (`grid-cols-4` for staff/technician, unchanged; `grid-cols-5` for customers) rather than displacing an existing item. The badge count comes from `auth()->user()->unreadNotifications()->count()` (again, Laravel's own `Notifiable` relation, scoped to the authenticated user only — never another customer's), rendered as a small red circle over the bell icon (capped display at "9+"), plus a screen-reader-only "(N unread)" suffix on the label text so the count isn't conveyed by color/position alone.

### 22.3 Authorization

No new policy class was added; ownership is enforced by **query scoping** rather than a post-hoc ability check, mirroring the existing pattern where customer-side controllers already scope by relationship (e.g. `$request->user()->bookings()`):
- `NotificationController::index()` reads through `$request->user()->notifications()` — structurally impossible to return another customer's rows.
- `NotificationController::read()` takes the notification's id as a plain route *value* (not a route-model-bound `DatabaseNotification`, which would resolve globally by primary key with no ownership check) and resolves it via `$request->user()->notifications()->findOrFail($notification)`. A notification id belonging to a different customer 404s (`ModelNotFoundException`) rather than ever being loaded, so there's no window where an unauthorized notification is fetched and then rejected — it's simply never found for the wrong user.
- `NotificationController::readAll()` operates on `$request->user()->unreadNotifications` — same scoping.
- **Redirect can't bypass booking authorization:** after marking a notification read, the controller redirects to `route('customer.repairs.show', $notification->data['booking_id'])`, which is unconditionally guarded by the existing `$this->authorize('view', $booking)` call in `Customer\RepairController::show()` (`BookingPolicy::view`, unchanged by Phase 8). Even in the contrived case of a notification whose stored `booking_id` pointed at a booking its notifiable user doesn't actually own, the destination route still 403s — verified directly by `test_notification_redirect_cannot_bypass_booking_authorization`, which manually constructs such a notification and confirms the follow-through redirect is forbidden.
- All three actions are covered by cross-customer tests in `tests/Feature/CustomerNotificationTest.php` (view, mark-as-read, mark-all-as-read).

### 22.4 Transaction safety

Handled entirely by `DB::afterCommit()` inside `Booking::transitionTo()`'s existing `DB::transaction()` closure (see §22.1) — no queue was introduced for this (Phase 8 explicitly keeps notification creation synchronous, consistent with the rest of the app, which has no queue worker in active use). `test_failed_transition_does_not_leave_a_stray_notification` covers the case where a controller-level guard rejects a transition before `transitionTo()` is ever called (the overwhelmingly common "failure" case in this codebase, since every transition is guarded by an explicit status check before the call) — no notification exists in that case, because the code path that would create one never runs.

### 22.5 Database

One migration: `database/migrations/2026_09_20_000001_create_notifications_table.php` — Laravel's standard `notifications` table (`id` UUID primary key, `type`, polymorphic `notifiable_type`/`notifiable_id`, `data` JSON/text, `read_at` nullable, timestamps). No other schema changes — `bookings` and `booking_status_histories` are untouched, per the phase's explicit instruction not to add lifecycle columns to `bookings` for this.

### 22.6 Deferred / explicitly out of scope

Per the phase's own brief, none of the following exist and were not started: email, SMS, WhatsApp, push notifications (Firebase/APNs), WebSockets/Laravel Echo/Pusher, per-notification delivery preferences, scheduled/reminder jobs, queue infrastructure, staff/technician notification inbox or task system, chat, repair photos, GPS tracking, or marketing/promotional notifications. §21 above records "delivery channels beyond in-app" as a possible future phase, not something Phase 8 attempted.
