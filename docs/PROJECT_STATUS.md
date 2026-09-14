# Project Status — Bicycle Workshop Management App

**Audit date:** 2026-09-14
**Branch audited:** `claude/bicycle-workshop-app-6mp087`
**Commit audited:** `6bfc49e` ("Add Phase 6: repair workflow (inspection through completion)")

This document is a factual snapshot of what exists in the repository as of the commit above. It was produced by reading the actual code (models, controllers, migrations, routes, views, tests), not by inferring from commit messages or specs. Anything marked **PLANNED / FUTURE** does not exist yet — it is called out explicitly so it is never confused with working functionality.

---

## 1. Project Overview

A mobile-first web app for a bicycle repair shop. Customers register, add their bicycles, and book repairs by selecting which parts/areas need attention. Workshop staff and technicians review bookings, receive the bike, inspect it, get customer approval on proposed repairs, do the work, run a quality check, and hand the bike back. Every status change is recorded for audit purposes.

The application has been built incrementally in six phases (see commit history), each phase adding one vertical slice of functionality with tests and a manual verification pass. All six phases are complete and merged into this branch.

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
| Database | MySQL 8.4 (Codespaces/devcontainer), SQLite (used only for isolated local test runs) | |
| Test runner | PHPUnit ^12.5.12 via `php artisan test` | |
| Code style | Laravel Pint ^1.27 | |

**Discrepancy found:** `package.json` lists `@tailwindcss/vite: ^4.0.0` as a dev dependency, but it is **not** imported in `vite.config.js`, and `tailwindcss` itself is pinned to `^3.1.0` (resolved to 3.4.19 in `package-lock.json`). The project actually builds with classic Tailwind v3 tooling: `postcss.config.js` (`tailwindcss` + `autoprefixer` plugins) and `resources/css/app.css` using `@tailwind base/components/utilities` directives, driven by `tailwind.config.js` (v3-style config with a `content` array). The `@tailwindcss/vite` v4 package appears to be an unused leftover from the initial Breeze scaffold and has no effect on the build. Confirmed by running `npm run build`, which succeeds and produces a normal Tailwind v3 CSS bundle.

---

## 3. Development Environment

### Devcontainer (`.devcontainer/`)

- `devcontainer.json`: two services via `docker-compose.yml` — `app` (`mcr.microsoft.com/devcontainers/php:1-8.4-bookworm`, no custom image build) and `mysql` (`mysql:8.4`). Node 22 is added via a devcontainer feature. Ports 8000 (App), 5173 (Vite), 3306 (MySQL) are forwarded.
- `postCreateCommand` (runs once per container build) does, in order: enable `pdo_mysql`, `composer install`, `npm install`, `npm run build`, copy `.env.example` → `.env` if missing, `php artisan key:generate`, wait for MySQL to accept TCP connections (polling loop, up to 60s), then `php artisan migrate --graceful --seed`.
- **There is no `postStartCommand` or `postAttachCommand`.** The Laravel dev server (`php artisan serve`) and Vite dev server (`npm run dev`) are **not** started automatically — this must be done manually after the container is ready (see README / HANDOFF for the exact commands). This was a known pain point during earlier development and remains true today: automatic migration/seeding on container creation is handled, but the two dev servers are not auto-started.

### Root-level `compose.yaml`

A separate, **unused** Laravel Sail-style `compose.yaml` exists at the repository root (references `./vendor/laravel/sail/...` build context). This is a leftover from the initial `laravel new` scaffold. The actual Codespaces workflow uses `.devcontainer/docker-compose.yml` instead, which was specifically written to avoid Sail's build-time dependency on `vendor/` already existing (a chicken-and-egg problem documented in an early fix commit). `compose.yaml` is harmless but dead configuration — see Technical Debt.

### `.env.example`

Configured for the devcontainer's MySQL service: `DB_CONNECTION=mysql`, `DB_HOST=mysql`, `DB_DATABASE=bike_workshop`, `DB_USERNAME=sail`, `DB_PASSWORD=password`. Session/cache/queue all default to the `database` driver. Mail defaults to `log`.

### Startup procedure (Codespaces) — confirmed accurate

1. Open the repo in a Codespace — the devcontainer builds and `postCreateCommand` runs automatically (installs dependencies, builds assets, migrates + seeds).
2. In a terminal: `php artisan serve --host=0.0.0.0 --port=8000` (forwarded automatically).
3. In a second terminal: `npm run dev` (for Vite/Tailwind hot reload during active frontend work — not required just to view the already-built assets from step 1).

This sandbox environment (used for this audit) has no reachable MySQL host, so migration/DB commands here were run against a temporary local SQLite database and the `.env` was restored to its MySQL configuration afterward. No project files were left modified by this process.

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
- **Reverse proxy trust**: `bootstrap/app.php` calls `$middleware->trustProxies(at: '*')` so `asset()`/`url()` generate correct public URLs behind Codespaces' proxy — this was a fix for a real bug encountered early in development (assets/CSRF pointed at `localhost:8000` instead of the public Codespaces host).

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
| Repair/Booking Detail | `customer.repairs.show` | customer | Status, reported issues/remarks, proposed-repairs + approve/decline once shared | ✅ (no full history timeline — see §14) |
| Staff Dashboard | `staff.dashboard` | staff/technician | Stat tiles + active-jobs list | 🟡 partially wired (see Known Issues — some tiles are hardcoded `0`) |
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

**What "customer tracking" currently amounts to:** the customer sees the current status badge everywhere their booking appears, plus — once the booking reaches `awaiting_customer_approval` or later — a read-only summary of the inspection's recommended repairs and the repair item list, with Approve/Decline buttons while in that specific state. There is **no chronological status-history timeline shown to the customer** (staff see one; customers do not) and no notifications (email/SMS/push) of any kind on status change. This was the explicitly-scoped remaining item going into the next phase at the time of this audit.

---

## 15. Status Workflow

Statuses are implemented as a PHP 8.1+ **backed enum**, `App\Enums\BookingStatus` (string-backed, stored as a plain `string` column on `bookings.status`, cast to the enum via Eloquent's `casts()`). Thirteen values are defined:

`pending`, `accepted`, `scheduled`, `bike_received`, `inspection`, `awaiting_customer_approval`, `repair_in_progress`, `repair_completed`, `quality_check`, `ready_for_pickup`, `ready_for_delivery`, `completed`, `cancelled`.

**`scheduled` is defined but never used.** It has a `label()` string and a color mapping in the `<x-status-badge>` component, but no controller anywhere ever sets a booking to this status — it appears to be a placeholder for a possible future "confirmed appointment slot" concept that was never wired up. This is the only unused enum case.

Transitions are not validated against a formal state-transition table/graph — each controller action independently checks the booking's *current* status (`if ($booking->status !== X) { return back()->with('error', ...); }`) before calling `Booking::transitionTo()`, which atomically (DB transaction) writes a `booking_status_histories` row and updates `bookings.status`. This means the valid transitions are defined by scattered `if` guards across `Staff\BookingController` and `Customer\RepairController`, not by a single declarative source of truth. All of them were read directly for this audit; the transition list in §14 is exhaustive as of this commit.

---

## 16. Tests

Full results from this audit (run via `php artisan test` against a temporary SQLite database, since no MySQL server is reachable in this sandboxed audit environment):

```
Tests: 82, Passed: 82, Failed: 0, Assertions: 192
Duration: ~2.3–2.9s
```

Breakdown by file:

| File | Tests |
|---|---|
| `tests/Feature/StaffBookingManagementTest.php` | 28 |
| `tests/Feature/BookingTest.php` | 14 |
| `tests/Feature/BicycleTest.php` | 7 |
| `tests/Feature/RoleAccessTest.php` | 7 |
| `tests/Feature/ProfileTest.php` | 5 |
| `tests/Feature/Auth/AuthenticationTest.php` | 4 |
| `tests/Feature/Auth/PasswordResetTest.php` | 4 |
| `tests/Feature/Auth/EmailVerificationTest.php` | 3 |
| `tests/Feature/Auth/PasswordConfirmationTest.php` | 3 |
| `tests/Feature/Auth/RegistrationTest.php` | 3 |
| `tests/Feature/Auth/PasswordUpdateTest.php` | 2 |
| `tests/Feature/ExampleTest.php` | 1 |
| `tests/Unit/ExampleTest.php` | 1 |
| **Total** | **82** |

**Coverage is strong** on: role-based route access, the full repair-booking wizard (including cross-customer authorization), every staff booking-management transition and its guard conditions (including the two mass-assignment traps that were specifically regression-tested), customer approve/decline + cross-customer authorization, and standard Breeze auth flows.

**Areas without test coverage:**
- No test asserts anything about the `staff.dashboard` view's stat tiles (which is how the hardcoded-zero issue below went unnoticed — see §17).
- No Dusk/browser-level test exists in the repo today (a one-off Playwright script was used during development to verify a specific bug fix, then removed — it is not part of the committed test suite).
- No test covers the `bicycle_types`/`bicycle_part_categories` seeders directly (they are exercised indirectly via feature tests that depend on seeded/factory data).
- `npm run build` was run manually for this audit (succeeds, ~2.7s) — there is no automated frontend test or CI step that runs it.

`./vendor/bin/pint --dirty` / a full `pint` pass reports no style violations on the current tree (the project has been kept Pint-clean throughout development).

---

## 17. Known Issues

1. **Staff dashboard stat tiles are partially hardcoded.** `resources/views/staff/dashboard.blade.php` has four stat tiles — "Awaiting Approval", "Quality Check", "In Repair", "Ready" — that are hardcoded to the literal string `0` in the Blade template rather than being passed from `Staff\DashboardController::index()`. That controller only computes counts for `Pending`, `Accepted`, and `BikeReceived`. This means the dashboard has not been updated since Phase 4 despite Phases 5 and 6 adding five more statuses to the workflow — it currently under-reports the real state of the shop's work whenever any booking is in `awaiting_customer_approval`, `repair_in_progress`, `quality_check`, `ready_for_pickup`, or `ready_for_delivery`.
2. **`scheduled` status is dead code** — defined, styled, never set (§15).
3. **README's "Project status" section is stale.** It currently states "Bicycle registration, booking, and the repair workflow are not yet built," which was true when it was written (end of Phase 1) but has been false since Phase 2. This audit's documentation (and the README update made alongside it) corrects this.
4. **Root-level `compose.yaml`** (Sail) is unused dead configuration — the devcontainer uses its own `docker-compose.yml` instead (see §3). Not harmful, but could confuse a future developer into thinking Sail is the supported local-dev path.
5. **`@tailwindcss/vite` v4 package listed but unused** (§2) — cosmetic `package.json` inconsistency, no functional effect.
6. Dev servers (`php artisan serve`, `npm run dev`) are not auto-started on container creation (§3) — this is documented behavior, not a bug, but worth knowing before assuming "the app is running" right after a Codespace opens.

No data-loss, broken-migration, or failing-test issues were found.

---

## 18. Security Observations

Light review only — not a full security audit. No high-severity issues found.

- **Ownership checks are consistently applied** where they matter: `BicyclePolicy`/`BookingPolicy` gate customer access to their own bicycles/bookings (`$this->authorize(...)` calls present on every customer controller action that takes a route-bound model), and this is positively tested (`test_customer_cannot_view_another_customers_booking`, `test_customer_cannot_book_a_repair_for_another_customers_bicycle`, `test_customer_cannot_approve_another_customers_booking`, etc.).
- **Mass-assignment protection is deliberately used as a security boundary**, not just a convenience: `status`, `assigned_technician_id`, and `completed_at` are excluded from their models' `#[Fillable]` lists specifically so a crafted request body can't set them directly through `update()`/`create()`. Every write path for these fields was checked and uses direct property assignment instead.
- **Technician assignment is validated server-side** against the `technician` role (`Rule::exists('users', 'id')->where('role', UserRole::Technician->value)`), not just filtered in the UI — confirmed by a passing test that a non-technician user ID is rejected.
- **CSRF**: standard Laravel CSRF middleware applies to all state-changing routes; every form in every Blade view checked includes `@csrf`.
- **No admin/debug routes are exposed.** `APP_DEBUG=true` in `.env.example` is standard for local/Codespaces dev; nothing in this audit suggests it's set that way in any production-like context (none exists yet).
- **No secrets are committed.** `.env` is gitignored and confirmed absent from git history (`git log --all --diff-filter=A --name-only` shows no `.env` was ever added).
- **Staff/technician actions have no per-user restriction** (§6/§13) — any staff or technician can act on any booking, including one assigned to a different technician. This is a documented design choice for the "first release," not an oversight, but it does mean there's currently no way to restrict a technician to only their own assigned jobs at the authorization layer (only UI sort-order nudges toward it).
- **Email verification is scaffolded but not enforced** (§5) — not a vulnerability per se (nothing sensitive is gated on it), but worth knowing if a future requirement assumes verified emails.
- Validation was spot-checked across `BicycleRequest`, `BookingRequest`, and the inline `$request->validate([...])` calls in `Staff\BookingController` / `Customer\RepairController` — all user input reaching a write path has explicit validation rules; none were found relying on implicit/absent validation.

---

## 19. Technical Debt

**High Priority**
- Staff dashboard stat tiles hardcoded to `0` for half the workflow (§17.1) — actively misleading to whoever uses that screen daily.

**Medium Priority**
- No customer-facing status-history timeline (full chronological view is staff-only right now) — flagged as the natural next phase.
- No per-technician authorization boundary — anyone in the workshop role can touch any booking.
- `Booking` model relationships plus five new controller action methods have made `Staff\BookingController` the largest controller in the app (17 public actions). Still readable and each action is small/single-purpose, but it's a candidate for splitting (e.g. a separate controller for the quality-check sub-flow) if the workflow grows further.
- Status-transition guards are duplicated `if ($booking->status !== X)` checks scattered across two controllers rather than a single declarative transition table — works correctly today (every transition is tested) but makes it easy to introduce an inconsistency if a new transition is added without checking every existing guard.

**Low Priority**
- Root `compose.yaml` (Sail) and the unused `@tailwindcss/vite` dependency are dead but harmless.
- `scheduled` booking status is unused.
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
| Repair status history | ✅ | Full audit trail (`booking_status_histories`) — staff-visible only |
| Repair completion | ✅ | Guarded on all repair items being done |
| Quality check | ✅ | Pass (pickup/delivery choice) or fail (rework loop) |
| Ready for pickup | ✅ | Pickup and delivery both supported |
| Customer tracking | 🟡 | Status badge + one-time proposed-repairs snapshot only; no full timeline, no notifications |
| Repair history | 🟡 | Existing bookings list only; no per-bicycle history view |

---

## 21. Recommended Next Development Phase

Based on this audit, the single most logical next phase is **Customer Tracking** (the phase explicitly deferred throughout Phases 5–6): give the customer a real, chronological view of their booking's status history (mirroring the staff-only timeline that already exists on `staff/bookings/show.blade.php`), on the existing `customer.repairs.show` screen. This is a natural, self-contained extension of code that already exists (`Booking::statusHistories()` is already a working relationship; it's simply never loaded/rendered on the customer side) and directly closes the biggest gap identified in this audit (§14, §20) without requiring new tables or new workflow logic.

A secondary candidate, if notifications are wanted before a deeper tracking UI: wiring up Laravel notifications (mail, since `MAIL_MAILER=log` is already configured for local dev) to fire on key status transitions the customer cares about — booking accepted, awaiting your approval, ready for pickup/delivery.

This recommendation is **not implemented** as part of this audit, per the task's explicit scope (documentation only).
