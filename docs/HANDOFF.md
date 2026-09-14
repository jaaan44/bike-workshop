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

## How do I start the project in GitHub Codespaces?

1. Open the repo in a Codespace. The devcontainer (`.devcontainer/`) builds automatically and its `postCreateCommand` runs `composer install`, `npm install`, `npm run build`, `.env` setup, `php artisan key:generate`, waits for MySQL, then `php artisan migrate --seed`. This happens without you doing anything.
2. **You must start the app server yourself** — nothing auto-starts it:
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   ```
   (Codespaces forwards port 8000 automatically; make sure its visibility is set to Public if you hit a 401.)
3. Only if you're actively editing CSS/Blade/JS and want hot reload, in a second terminal:
   ```bash
   npm run dev
   ```
4. Demo accounts (seeded, password `password` for all): `customer@example.com`, `staff@example.com`, `technician@example.com`.

If MySQL isn't reachable yet right after container creation, give it a few seconds — the `postCreateCommand` itself polls for it before migrating, but a manual `php artisan migrate` retry is safe if needed.

## How do I run the tests?

```bash
php artisan test
```
Expected: 82 tests, 0 failures. In a sandboxed/non-Codespaces environment with no MySQL reachable, temporarily point `.env` at SQLite to run tests (back up `.env` first, restore it after — this repo's DB config assumes the devcontainer's MySQL service by default). Don't leave `.env` on SQLite; the real dev/runtime environment is MySQL.

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
