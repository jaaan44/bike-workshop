# Development Handoff

Concise by design. For depth, see `PROJECT_STATUS.md` (full audit), `ARCHITECTURE.md` (diagrams, request flow, workflow state machine), `DATABASE.md` (schema + ER diagram). This file is written to be handed directly to another AI session or developer with zero prior context.

**Audit date:** 2026-09-14 (original); updated 2026-09-20 for Phase 7; updated 2026-09-20 for Phase 8; updated 2026-09-20 for Phase 9; updated 2026-09-21 for Phase 10A (audit-only, no code changes); updated 2026-09-21 for Phase 10B; **updated 2026-09-21 for Phase 11A** (audit-only, documentation only). **Branch:** `claude/laughing-fermi-xzuztm` (Phase 11A, based on the merged Phase 10B baseline at commit `839d2b6f`); prior work on `claude/phase-10b-v1-release-hardening` (Phase 10B, based on `claude/bicycle-workshop-app-6mp087` at commit `37e138d`), `claude/bicycle-service-repair-history-9gtkn9` (Phase 9), `claude/phase-8-in-app-notifications-y2zjme` (Phase 8), `claude/phase-7-customer-tracking-dashboard-xgv1hy` (Phase 7).

---

## Where are we now?

A mobile-first Laravel + Blade + Tailwind + Alpine.js bicycle repair shop app. Ten build phases are complete: Foundation/Auth, Bicycle Management, Repair Booking, Workshop Booking Management, Repair Job (inspection/items/technician/notes), Repair Workflow (the full status pipeline from inspection through completion), Phase 7 (Customer Repair Tracking + Staff Dashboard Fix), Phase 8 (In-App Notifications), Phase 9 (Bicycle Service & Repair History), Phase 10A (V1 Readiness & Workflow Hardening Audit — audit-only, no code), Phase 10B (V1 Release Hardening), and Phase 11A (V1 Release & Staging Readiness Audit + Deployment Runbook — audit-only, documentation only). All work is committed and pushed to the branch above; working tree is clean.

**As of Phase 11A**, the authoritative deployment/rollback/validation runbook for the staging VPS lives at `docs/DEPLOYMENT.md` — read it before doing anything with the live `bikeworkshop.storm-ark.com` deployment. It documents (all independently re-verified, not just re-read from earlier docs): this app's actual runtime requirements (no queue worker, no scheduler, no SMTP required for V1's core workflow, no file uploads anywhere), the Docker Compose deployment model itself (`compose.yaml`/`Dockerfile`/`docker/entrypoint.sh` already encode most of what a deployment runbook needs), the environment-variable checklist, safe Laravel optimization commands (`config:cache`/`route:cache`/`view:cache` all verified working, including against this app's two closure-based routes), backup requirements, and the ordered deploy/rollback/migration-failure sequences — plus a list of items only a live VPS inspection can confirm (Phase 11B's job, not done here). See `docs/PROJECT_STATUS.md` §27 for the full findings summary.

## What works?

Everything end-to-end from customer registration through a bicycle being marked completed and handed back:

- Customer: register, add/edit bicycles, book a repair (5-step wizard: bike → parts → remarks → date → review), view a full repair-tracking page for each booking (status, bicycle, submitted/appointment dates, inspection findings + recommended work once shared, itemized proposed repairs with approval state, contextual quality-check/pickup/delivery/completion messaging, and a chronological repair timeline built from the existing status-history audit trail), approve/decline proposed repairs, see and read **in-app notifications** (a bell icon with an unread-count badge in the bottom nav, a notifications list page, mark-as-read-and-open, mark-all-as-read) whenever their booking reaches a meaningful stage, and — **as of Phase 9** — see a bicycle's own **service history** (current repair(s) separated from a paginated, newest-first list of completed repairs, plus a completed-service count and last-serviced date) directly on that bicycle's detail page.
- Staff/technician: review and accept/cancel bookings, receive the bike, record inspection findings, assign a technician, add/complete repair items, add internal notes, send for customer approval, mark repairs complete, run a quality check (pass → pickup/delivery, or fail → rework loop), mark fulfilled. Dashboard stat tiles are all real, accurate counts. **As of Phase 9**, the booking detail page also shows a "Previous Repairs for This Bicycle" section so staff have context on a returning bike without leaving the booking they're working on.
- Full status audit trail on every transition (`booking_status_histories`), auto-generated human-readable reference numbers (`BR-2026-00001`), role-based access control (customer vs. staff/technician), ownership-based authorization on the customer side (can't see another customer's bicycle/booking/notification/service history) — this same `BicyclePolicy::view`/`BookingPolicy::view` combination now also gates the bicycle service-history page and its links back to `customer.repairs.show`, and is what a notification's "open" redirect still passes through even though the notification itself is looked up scoped to the authenticated customer.
- 161 automated tests, all passing (139 pre-Phase-10B + 22 Phase 10B: see "What was most recently implemented?" below for the breakdown). `npm run build` succeeds cleanly.
- **As of Phase 10B**: staff/technician account deletion no longer crashes; customer account deletion can no longer destroy workshop repair history (it's disabled for customers); demo/seed credentials can no longer be created outside local/testing; a `Completed`/`Cancelled` booking's operational data can no longer be edited via a direct request; and staff can now explicitly close out a booking the customer declined, with clear messaging on both sides. See `docs/PROJECT_STATUS.md` §25 for full detail on each.

## What does not work / doesn't exist?

- **No bicycle delete** — only add/edit/view. Confirmed by Phase 10A that a naive hard-delete would hit the same class of FK problem Phase 10B fixed for `technician_notes` (`bookings.bicycle_id` has no `nullOnDelete`/`cascadeOnDelete`) — still deferred, not attempted.
- **Customer self-service account deletion is intentionally unavailable** (Phase 10B, `docs/PROJECT_STATUS.md` §25.2) — preserves workshop repair history; profile editing (name/email/phone) and password changes are unaffected. Staff/technician deletion remains available (guarded per §25.1).
- **No notification delivery beyond in-app/database** — email/SMS/push/WebSockets remain explicitly out of scope; Phase 8 only added the in-app channel. See `docs/PROJECT_STATUS.md` §22.6 for the full deferred list.
- **No per-technician authorization** — any staff or technician can act on any booking, not just their own assigned ones. Phase 10A confirmed this is intentional, not accidental; Phase 10B deliberately left it deferred.
- **No admin role.**
- **`scheduled` status is defined but never used** by any code path — remains a known, harmless piece of dead code; it also does not notify (Phase 8 excluded it deliberately, consistent with it being unreachable). Phase 10A recommended removing it; Phase 10B deliberately left it deferred (out of approved scope).
- Appointment booking is a plain date picker — no time slots or capacity.
- **No scheduled/recurring maintenance reminders, mileage/odometer tracking, service intervals, or manually-entered/imported historical service records** — Phase 9 explicitly deferred all of these; see `docs/PROJECT_STATUS.md` §23.8.
- General accessibility cleanup (dashboard tile clickability, remaining unlabeled fields), `User::role` mass-assignment hardening, splitting `Staff\BookingController`, and the stale `@tailwindcss/vite` dependency were all identified by Phase 10A and deliberately left deferred by Phase 10B — see `docs/PROJECT_STATUS.md` §26.2 for the complete list.

## What was most recently implemented?

**Phase 10B — V1 Release Hardening.** See `docs/PROJECT_STATUS.md` §25 and `docs/ARCHITECTURE.md` §13 for full detail. In short, five fixes, all approved from Phase 10A's audit findings, **no migration** for any of them:
- **Staff/technician account deletion** no longer throws an uncaught DB exception when the account authored a technician note — blocked with a clear message instead (`ProfileController::destroy()`, `User::technicianNotes()`).
- **Customer account deletion** is disabled entirely (both the "Delete Account" UI and the controller reject it) rather than letting it cascade-delete the workshop's own bicycle/booking/repair records.
- **Demo-account seeding** (`staff@example.com`/`password`, etc.) moved to a new `database/seeders/DemoAccountSeeder.php`, only called by `DatabaseSeeder` when `DemoAccountSeeder::shouldRun(app()->environment())` says the environment is `local`/`testing` — a plain `php artisan db:seed` against a production-configured environment now silently skips it.
- **Terminal-state guard**: `BookingStatus::isTerminal()` + `Staff\BookingController::rejectIfTerminal()` reject inspection/repair-item/note/technician-assignment edits (server-side) once a booking is `Completed`/`Cancelled`; the booking-detail view still shows that data, just read-only.
- **Declined-repair closure**: a new `Staff\BookingController::closeDeclinedRepair()` action (only available when `Booking::wasJustDeclinedByCustomer()`) lets staff conclude a job the customer declined by reusing the existing `Cancelled` status — `Booking::wasCancelledAfterCustomerDecline()` is how both the staff and customer views tell this apart from an ordinary early cancellation, with matching explanatory messaging on each side.
- 22 new tests across `tests/Feature/ProfileTest.php`, `tests/Feature/StaffBookingManagementTest.php`, `tests/Feature/DeclinedRepairWorkflowTest.php` (new), `tests/Unit/DemoAccountSeederTest.php` (new), and `tests/Feature/DatabaseSeederTest.php` (new). 161 tests total, all passing.
- **Phase 10A** (immediately prior, same date) was audit-only — no code changes — and is what identified all five items above, plus everything Phase 10B deliberately left deferred (see `docs/PROJECT_STATUS.md` §24/§26.2).

## What should be implemented next?

**Phase 11B** (staging deployment + live validation, using `docs/DEPLOYMENT.md`) is the immediate next step — Phase 11A deliberately stopped at producing the runbook and did not deploy, SSH into the server, or touch the live `.env`/DNS/Nginx/Certbot/firewall. `docs/DEPLOYMENT.md` §32 lists exactly what Phase 11B needs to verify on the actual VPS that repository inspection alone couldn't prove.

Beyond that, Phase 10A's full findings report (`docs/PROJECT_STATUS.md` §24) and Phase 10B's explicit deferred-items list (§26.2) are the current product/code backlog — nothing on it has been started. In rough priority order for a future phase: per-technician authorization, bicycle delete/archive, `scheduled` status removal, `User::role` mass-assignment hardening, general accessibility cleanup (dashboard tile clickability, remaining unlabeled fields), delivery channels beyond in-app notification (e.g. email — the Phase 8 plumbing already supports adding a channel, and would need queue infrastructure first per `docs/PROJECT_STATUS.md` §21), and the product-scoping work needed before any of Phase 9's deliberately-deferred service-history features (§23.8) could be attempted.

## How do I start the project?

**The project is Dockerized and the VPS is the primary environment** — there is no GitHub Codespaces/devcontainer support (deliberately removed; don't reintroduce it). Local development uses the exact same `compose.yaml`. See `README.md` for the full writeup; short version:

```bash
cp .env.example .env   # first time only
docker compose build
docker compose up -d
```

- App: **http://127.0.0.1:8013** (Apache runs *inside* the `app` container on port 80; 8013 is just the host-side mapping — nothing runs `php artisan serve` anywhere in this setup)
- MySQL from the host: **127.0.0.1:3348**; from inside the app container, always `mysql:3306`
- Nothing needs to be started manually — the app container's entrypoint (`docker/entrypoint.sh`) installs dependencies if missing, generates `APP_KEY`, waits for MySQL, and runs `php artisan migrate --force` automatically every time it starts
- Demo accounts are **not** seeded automatically (avoids blindly reseeding a real deployment on every restart) — run once: `docker compose exec app php artisan db:seed`. Password `password` for all three: `customer@example.com`, `staff@example.com`, `technician@example.com`. **As of Phase 10B**, this only actually creates those accounts when `APP_ENV` is `local` or `testing` (`database/seeders/DemoAccountSeeder::shouldRun()`) — running `db:seed` against a production-configured environment is safe and a no-op for the demo accounts (the real lookup-table seeders still run).
- Optional Vite hot-reload (only if you want it): `docker compose --profile dev up -d vite` — never starts on its own, never required
- Rebuild (`docker compose build`) after changing `composer.json`/`composer.lock` or frontend source — the image bakes those in, and a plain restart won't pick up changes to them
- Stop: `docker compose down`. Never `docker compose down -v` unless you explicitly want to delete this project's MySQL volume
- **Shared VPS**: this stack also runs alongside other unrelated apps on the same host — every container/volume/network name is prefixed `bicycle_workshop_`, no host networking is used, and nothing here should ever touch another project's containers, volumes, or ports

If MySQL isn't ready the instant the app container starts, the entrypoint polls for it (up to ~2 minutes) before migrating — this is normal on first boot while MySQL initializes its data directory.

## How do I run the tests?

```bash
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint --dirty
```
Expected: 161 tests, 0 failures, Pint clean. Without Docker (native PHP/Composer/Node on the host, no MySQL reachable), temporarily point `.env` at SQLite to run tests — back up `.env` first, restore it after; don't leave it on SQLite, the real runtime is always MySQL. `npm run build` (or `npm ci && npm run build` if `node_modules` isn't present) must be run at least once before `php artisan test`, since some feature tests render full pages through `@vite(...)` and fail with `ViteManifestNotFoundException` if `public/build/manifest.json` doesn't exist yet.

## Are there known bugs?

None outstanding as of Phase 10B. Phase 10A's audit (§24) found and Phase 10B fixed the five issues summarized above (§"What was most recently implemented?"); everything else Phase 10A found is deliberately deferred, not a live bug (§26.2). The staff dashboard's stat tiles (previously hardcoded to `0` for `awaiting_customer_approval`, `quality_check`, `repair_in_progress`, and the ready-for-pickup/delivery states) were fixed in Phase 7 — see `docs/PROJECT_STATUS.md` §17.1. Everything audited works as designed — no data-loss bugs, no broken migrations, no failing tests.

## Architectural decisions the next developer must preserve

1. **`#[Fillable([...])]` attribute-based mass assignment**, not `protected $fillable`. Follow the existing pattern on any new model.
2. **Never make `status`, `assigned_technician_id`, or `completed_at` mass-assignable.** These are deliberately excluded from `Fillable` as a security boundary — set them via direct property assignment + `save()`, following the existing pattern in `Staff\BookingController`. This has been a recurring real bug (silently-dropped mass assignment) in earlier phases; the current code is deliberately written to avoid it everywhere.
3. **All status transitions go through `Booking::transitionTo()`** — it's what writes the audit-trail row atomically with the status change, and (as of Phase 8) is also the single place customer notifications get dispatched from. Never set `$booking->status` directly outside that method — doing so silently skips both the audit trail and any notification.
4. **Booking is the job** — don't introduce a separate "RepairJob"/"WorkOrder" table; `bookings.status` is the single source of truth for where a repair is in its lifecycle, and `Booking::statusHistories()`/`inspection()`/`repairItems()`/`technicianNotes()` all hang directly off it.
5. **Staff and technician share one route group and one permission level** (`role:staff,technician`) — this is intentional for the "first release," not a gap to quietly fix without being asked.
6. **Customer `remarks` (and other customer-reported data) are never overwritten by the workflow** — historical customer input is preserved, not replaced, as staff add their own findings/items/notes alongside it.
7. **One shared `<x-app-layout>`** for both customer and staff/technician — role-aware only via `<x-bottom-nav>`'s item list, not a separate layout file. Don't fork the layout without a real reason.
8. Keep using Laravel Pint (`./vendor/bin/pint --dirty`) before committing — the codebase has been kept style-clean throughout.
9. **`compose.yaml` at the repo root is the one canonical Docker setup, and the VPS is the primary target** — local dev uses the exact same file. There is no devcontainer/Codespaces support; don't reintroduce it, and don't reintroduce a second/conflicting compose file. The `app` service bind-mounts the whole repo but uses named volumes (`bicycle_workshop_vendor`, `bicycle_workshop_build`) to protect `vendor/` and `public/build` from being hidden by that mount — if you add another directory that's built at image-build time and expected to survive the bind mount, it needs the same treatment. The `vite` service only runs via `--profile dev`; never make it part of the default `docker compose up`. Every container/volume/network name is prefixed `bicycle_workshop_` because this stack shares a VPS with other unrelated apps — keep that prefix on anything new.
10. **Derived data over duplicated/cached data** — as of Phase 9, `Bicycle::completedBookings()`/`activeBookings()` compute a bicycle's service history from existing `bookings` rows on every request rather than storing a summary anywhere. Follow this pattern for similar "history"/"summary" features: a small query (optionally with a LIMIT or `paginate()`) is preferable to a new table or a cached counter column unless there's a demonstrated performance need — there wasn't one here, since each page's query count stays fixed regardless of how much history exists.
11. **Prefer a controller-level guard over a migration when both would work** — as of Phase 10B, both `ProfileController::destroy()`'s technician-notes check and its customer block were fixes that could have altered a foreign key or a cascade instead; the schema-level fix was passed over because it carried real migration risk for no benefit over an equally-safe controller check. Reach for a migration only when a code-level guard genuinely can't achieve the same safety.
12. **Reuse an existing enum case over adding a new one when the semantics genuinely match** — Phase 10B's declined-repair closure (`Staff\BookingController::closeDeclinedRepair()`) reuses `BookingStatus::Cancelled` rather than introducing e.g. `declined`/`returned_without_repair`, because every existing consumer of `Cancelled` (active/completed-service queries, dashboard counts, the jobs list, notifications) already treats it exactly the way this outcome needs to be treated. Check every one of those consumers, the way `docs/PROJECT_STATUS.md` §25.5 documents, before reusing a status for a new purpose — don't reuse one whose existing meaning would be misleading (e.g. `Completed` would have falsely implied repair work was done).
