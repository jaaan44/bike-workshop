# Development Handoff

Concise by design. For depth, see `PROJECT_STATUS.md` (full audit), `ARCHITECTURE.md` (diagrams, request flow, workflow state machine), `DATABASE.md` (schema + ER diagram). This file is written to be handed directly to another AI session or developer with zero prior context.

**Audit date:** 2026-09-14. **Branch:** `claude/bicycle-workshop-app-6mp087`. **Commit:** `6bfc49e`.

---

## Where are we now?

A mobile-first Laravel + Blade + Tailwind + Alpine.js bicycle repair shop app. Six build phases are complete: Foundation/Auth, Bicycle Management, Repair Booking, Workshop Booking Management, Repair Job (inspection/items/technician/notes), Repair Workflow (the full status pipeline from inspection through completion). All work is committed and pushed to the branch above; working tree is clean.

## What works?

Everything end-to-end from customer registration through a bicycle being marked completed and handed back:

- Customer: register, add/edit bicycles, book a repair (5-step wizard: bike → parts → remarks → date → review), view booking status, approve/decline proposed repairs.
- Staff/technician: review and accept/cancel bookings, receive the bike, record inspection findings, assign a technician, add/complete repair items, add internal notes, send for customer approval, mark repairs complete, run a quality check (pass → pickup/delivery, or fail → rework loop), mark fulfilled.
- Full status audit trail on every transition (`booking_status_histories`), auto-generated human-readable reference numbers (`BR-2026-00001`), role-based access control (customer vs. staff/technician), ownership-based authorization on the customer side (can't see another customer's bicycle/booking).
- 82 automated tests, all passing. `npm run build` succeeds cleanly.

## What does not work / doesn't exist?

- **No bicycle delete** — only add/edit/view.
- **No customer-facing status-history timeline** — customers see the current status badge and (once shared) a one-time proposed-repairs snapshot, but not the full chronological history staff can see.
- **No notifications** (email/SMS/push) on any status change.
- **Staff dashboard has hardcoded `0` tiles** for "Awaiting Approval", "Quality Check", "In Repair", "Ready" — real data exists for these now (Phases 5–6 added the statuses) but `Staff\DashboardController` was never updated to query them. This is the most visible bug in the app today.
- **No per-technician authorization** — any staff or technician can act on any booking, not just their own assigned ones.
- **No admin role.**
- **`scheduled` status is defined but never used** by any code path.
- Appointment booking is a plain date picker — no time slots or capacity.

## What was most recently implemented?

Phase 6 — the full repair workflow from `inspection` through `completed`, including a quality-check pass/fail rework loop and the customer approve/decline step. See `git log --oneline -3` for the exact commits.

## What should be implemented next?

**Recommended: Phase 7, Customer Tracking.** Render `Booking::statusHistories` (already a working relationship, already used on the staff side) on `customer/repairs/show.blade.php` so customers get a real timeline instead of just a status badge. This is the natural next step and was explicitly deferred through Phases 5–6 for exactly this reason.

Quick win alongside or before that: fix the hardcoded dashboard tiles (`resources/views/staff/dashboard.blade.php` + `Staff\DashboardController::index()`) — five minutes of work, currently actively misleading.

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
Expected: 82 tests, 0 failures, Pint clean. Without Docker (native PHP/Composer/Node on the host, no MySQL reachable), temporarily point `.env` at SQLite to run tests — back up `.env` first, restore it after; don't leave it on SQLite, the real runtime is always MySQL.

## Are there known bugs?

One real one: the staff dashboard's four stat tiles are literally hardcoded to `0` for `awaiting_customer_approval`, `quality_check`, `repair_in_progress`, and the ready-for-pickup/delivery states (see above). Everything else audited works as designed — no data-loss bugs, no broken migrations, no failing tests.

## Architectural decisions the next developer must preserve

1. **`#[Fillable([...])]` attribute-based mass assignment**, not `protected $fillable`. Follow the existing pattern on any new model.
2. **Never make `status`, `assigned_technician_id`, or `completed_at` mass-assignable.** These are deliberately excluded from `Fillable` as a security boundary — set them via direct property assignment + `save()`, following the existing pattern in `Staff\BookingController`. This has been a recurring real bug (silently-dropped mass assignment) in earlier phases; the current code is deliberately written to avoid it everywhere.
3. **All status transitions go through `Booking::transitionTo()`** — it's what writes the audit-trail row atomically with the status change. Never set `$booking->status` directly outside that method.
4. **Booking is the job** — don't introduce a separate "RepairJob"/"WorkOrder" table; `bookings.status` is the single source of truth for where a repair is in its lifecycle, and `Booking::statusHistories()`/`inspection()`/`repairItems()`/`technicianNotes()` all hang directly off it.
5. **Staff and technician share one route group and one permission level** (`role:staff,technician`) — this is intentional for the "first release," not a gap to quietly fix without being asked.
6. **Customer `remarks` (and other customer-reported data) are never overwritten by the workflow** — historical customer input is preserved, not replaced, as staff add their own findings/items/notes alongside it.
7. **One shared `<x-app-layout>`** for both customer and staff/technician — role-aware only via `<x-bottom-nav>`'s item list, not a separate layout file. Don't fork the layout without a real reason.
8. Keep using Laravel Pint (`./vendor/bin/pint --dirty`) before committing — the codebase has been kept style-clean throughout.
9. **`compose.yaml` at the repo root is the one canonical Docker setup, and the VPS is the primary target** — local dev uses the exact same file. There is no devcontainer/Codespaces support; don't reintroduce it, and don't reintroduce a second/conflicting compose file. The `app` service bind-mounts the whole repo but uses named volumes (`bicycle_workshop_vendor`, `bicycle_workshop_build`) to protect `vendor/` and `public/build` from being hidden by that mount — if you add another directory that's built at image-build time and expected to survive the bind mount, it needs the same treatment. The `vite` service only runs via `--profile dev`; never make it part of the default `docker compose up`. Every container/volume/network name is prefixed `bicycle_workshop_` because this stack shares a VPS with other unrelated apps — keep that prefix on anything new.
