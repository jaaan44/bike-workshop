# Bike Workshop

A mobile-first bicycle repair shop management app. Customers register bikes, book repairs, and track status; workshop staff manage bookings and repair jobs.

Stack: Laravel 13 + Blade + Tailwind CSS + Alpine.js (via Laravel Breeze), MySQL.

## Setup (Docker — the supported workflow, VPS or local)

This project is developed and deployed as a Docker Compose stack (`compose.yaml` at the repo root). One `app` container (PHP 8.4 + Apache, serving on port 80 internally) and one `mysql` container (MySQL 8.4). There is no separate Sail setup, no devcontainer, and no GitHub Codespaces support — local development and the VPS both use this exact same workflow.

```bash
cp .env.example .env      # first time only — defaults already work as-is
docker compose build
docker compose up -d
```

That's it — the app container's entrypoint automatically installs Composer/npm dependencies if needed, generates `APP_KEY` if missing, waits for MySQL, and runs `php artisan migrate --force` on every start (safe/forward-only, never destructive). **You do not need to run `php artisan serve` or `npm run dev`** — Apache starts automatically inside the container and serves the app the whole time it's running.

- App: **http://127.0.0.1:8013**
- MySQL (from the host, e.g. a GUI client): **127.0.0.1:3348** — inside Docker the app always reaches it as `mysql:3306`, regardless of this host-side port
- Rebuild after changing `composer.json`/`composer.lock` or frontend source (`resources/`): `docker compose build`
- Stop: `docker compose down` (never `docker compose down -v` unless you explicitly want to delete this project's MySQL data volume)
- Logs: `docker compose logs -f`
- Run any Artisan command: `docker compose exec app php artisan ...`
- Demo data isn't seeded automatically (see below) — run it once: `docker compose exec app php artisan db:seed`

Ports are configurable via `.env` (`APP_PORT`, `DB_FORWARD_PORT`, `VITE_FORWARD_PORT` — see `.env.example`) in case 8013/3348/5175 collide with something else already running on the host.

**Shared-host safety:** every container, volume, and network name in `compose.yaml` is prefixed `bicycle_workshop_` and this stack never uses host networking — safe to run alongside other unrelated projects on the same machine without touching their containers, volumes, or ports.

### Frontend hot-reload (optional)

Assets are built into the image at `docker compose build` time — a plain `docker compose up -d` never needs Node or Vite running. If you're actively editing Blade/CSS/JS and want Vite's hot reload, start the optional `vite` service explicitly:

```bash
docker compose --profile dev up -d vite   # http://127.0.0.1:5175
```

It never starts on its own and is not part of the default `docker compose up -d`.

### Without Docker

Not the supported path, but works if you have PHP 8.4, Composer, Node 22, and a MySQL 8.4 server available locally:

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install && npm run build
php artisan migrate --seed
php artisan serve
```

## Demo accounts

Seeded by `php artisan db:seed` (password for all: `password`):

| Role       | Email                   |
|------------|--------------------------|
| Customer   | customer@example.com     |
| Staff      | staff@example.com        |
| Technician | technician@example.com   |

## Running tests

```bash
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint --dirty   # code style
```

## Roles

- **Customer** — registers via `/register` (always created as `customer`; role can't be self-elevated).
- **Staff** / **Technician** — provisioned manually (e.g. via seeder/tinker) for now; share the workshop area (`/staff/*`) in this first release.

## Project status

The full customer + staff repair workflow is implemented end-to-end — registration through bicycle management, repair booking, staff booking review, inspection, technician assignment, repair items, quality check, and completion. For the current, accurate state of the project (what's implemented, what isn't, known issues, and recommended next steps), see:

- **[docs/PROJECT_STATUS.md](docs/PROJECT_STATUS.md)** — full feature-by-feature audit, this is the primary status document
- **[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)** — application structure, models, request flow, repair-workflow state machine (with diagrams)
- **[docs/DATABASE.md](docs/DATABASE.md)** — schema and ER diagram
- **[docs/HANDOFF.md](docs/HANDOFF.md)** — short "start here" briefing for picking up development, suitable to hand to another developer or AI session
