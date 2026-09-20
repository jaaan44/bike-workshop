# Development Handoff

Concise by design. For depth, see `PROJECT_STATUS.md` (full audit), `ARCHITECTURE.md` (diagrams, request flow, workflow state machine), `DATABASE.md` (schema + ER diagram). This file is written to be handed directly to another AI session or developer with zero prior context.

**Audit date:** 2026-09-14 (original); updated 2026-09-20 for Phase 7; **updated 2026-09-20 for Phase 8**. **Branch:** `claude/phase-8-in-app-notifications-y2zjme` (Phase 8); prior work on `claude/phase-7-customer-tracking-dashboard-xgv1hy` (Phase 7) and `claude/bicycle-workshop-app-6mp087`, commit `6bfc49e`.

---

## Where are we now?

A mobile-first Laravel + Blade + Tailwind + Alpine.js bicycle repair shop app. Eight build phases are complete: Foundation/Auth, Bicycle Management, Repair Booking, Workshop Booking Management, Repair Job (inspection/items/technician/notes), Repair Workflow (the full status pipeline from inspection through completion), Phase 7 (Customer Repair Tracking + Staff Dashboard Fix), and Phase 8 (In-App Notifications). All work is committed and pushed to the branch above; working tree is clean.

## What works?

Everything end-to-end from customer registration through a bicycle being marked completed and handed back:

- Customer: register, add/edit bicycles, book a repair (5-step wizard: bike → parts → remarks → date → review), view a full repair-tracking page for each booking (status, bicycle, submitted/appointment dates, inspection findings + recommended work once shared, itemized proposed repairs with approval state, contextual quality-check/pickup/delivery/completion messaging, and a chronological repair timeline built from the existing status-history audit trail), approve/decline proposed repairs, and — **as of Phase 8** — see and read **in-app notifications** (a bell icon with an unread-count badge in the bottom nav, a notifications list page, mark-as-read-and-open, mark-all-as-read) whenever their booking reaches a meaningful stage (accepted, bike received, awaiting approval, repair started/completed, ready for pickup/delivery, completed).
- Staff/technician: review and accept/cancel bookings, receive the bike, record inspection findings, assign a technician, add/complete repair items, add internal notes, send for customer approval, mark repairs complete, run a quality check (pass → pickup/delivery, or fail → rework loop), mark fulfilled. Dashboard stat tiles are all real, accurate counts.
- Full status audit trail on every transition (`booking_status_histories`), auto-generated human-readable reference numbers (`BR-2026-00001`), role-based access control (customer vs. staff/technician), ownership-based authorization on the customer side (can't see another customer's bicycle/booking/notification) — this same `BookingPolicy::view` check now also gates the customer tracking page, and is what a notification's "open" redirect still passes through even though the notification itself is looked up scoped to the authenticated customer.
- 121 automated tests, all passing (98 pre-Phase-8 + 23 new: `tests/Feature/CustomerNotificationTest.php`). `npm run build` succeeds cleanly.

## What does not work / doesn't exist?

- **No bicycle delete** — only add/edit/view.
- **No notification delivery beyond in-app/database** — email/SMS/push/WebSockets remain explicitly out of scope; Phase 8 only added the in-app channel. See `docs/PROJECT_STATUS.md` §22.6 for the full deferred list.
- **No per-technician authorization** — any staff or technician can act on any booking, not just their own assigned ones.
- **No admin role.**
- **`scheduled` status is defined but never used** by any code path — remains a known, harmless piece of dead code; it also does not notify (Phase 8 excluded it deliberately, consistent with it being unreachable).
- Appointment booking is a plain date picker — no time slots or capacity.
- No per-bicycle repair-history view (the customer repairs list is still all bookings, not filtered per bicycle).

## What was most recently implemented?

**Phase 8 — In-App Notifications.** See `docs/PROJECT_STATUS.md` §22 and `docs/ARCHITECTURE.md` §11 for full detail. In short:
- One migration (`notifications`, Laravel's standard database-notification schema) — no other schema changes.
- `Booking::transitionTo()` now dispatches `App\Notifications\BookingStatusUpdated` to the booking's owner (via `DB::afterCommit()`, so a rolled-back transition never leaves a stray notification) whenever the target status is one of 8 customer-meaningful stages — see `BookingStatus::customerNotificationMessage()`, the single source of truth for which statuses notify. No controller was changed to wire this up; every existing transition call site gained it automatically.
- New customer-facing UI: `customer.notifications.index` (list page, newest first, unread visually distinguished by more than color alone), mark-as-read-and-redirect, mark-all-as-read, and an unread-count badge on a new "Alerts" bottom-nav item (customer nav went from 4 to 5 items; staff/technician nav unchanged).
- Cross-customer notification access is rejected by construction (notifications are always looked up scoped through `$request->user()->notifications()`, never by a globally-resolvable id) — covered by dedicated tests, including a test that a notification's stored `booking_id` can't be used to bypass `BookingPolicy::view` on the destination page.
- The Phase 7 repair-tracking timeline is unchanged and remains the authoritative history; notifications are a separate, complementary alert layer. See `git log --oneline -3` for the exact commit(s).

## What should be implemented next?

See `docs/PROJECT_STATUS.md` §21 for the current recommended-next-phase list (per-technician authorization and bicycle delete are the top candidates; delivery channels beyond in-app — e.g. email — are noted as a possible future extension of Phase 8's notification plumbing, not started). Nothing has been started on any of these yet.

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
- Demo accounts are **not** seeded automatically (avoids blindly reseeding a real deployment on every restart) — run once: `docker compose exec app php artisan db:seed`. Password `password` for all three: `customer@example.com`, `staff@example.com`, `technician@example.com`
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
Expected: 121 tests, 0 failures, Pint clean. Without Docker (native PHP/Composer/Node on the host, no MySQL reachable), temporarily point `.env` at SQLite to run tests — back up `.env` first, restore it after; don't leave it on SQLite, the real runtime is always MySQL. `npm run build` (or `npm ci && npm run build` if `node_modules` isn't present) must be run at least once before `php artisan test`, since some feature tests render full pages through `@vite(...)` and fail with `ViteManifestNotFoundException` if `public/build/manifest.json` doesn't exist yet.

## Are there known bugs?

None outstanding as of Phase 8. The staff dashboard's stat tiles (previously hardcoded to `0` for `awaiting_customer_approval`, `quality_check`, `repair_in_progress`, and the ready-for-pickup/delivery states) were fixed in Phase 7 — see `docs/PROJECT_STATUS.md` §17.1. Everything audited works as designed — no data-loss bugs, no broken migrations, no failing tests.

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
