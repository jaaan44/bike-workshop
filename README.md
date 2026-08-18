# Bike Workshop

A mobile-first bicycle repair shop management app. Customers register bikes, book repairs, and track status; workshop staff manage bookings and repair jobs.

Stack: Laravel 13 + Blade + Tailwind CSS + Alpine.js (via Laravel Breeze), MySQL.

## Setup (GitHub Codespaces)

This repo includes a `.devcontainer` (a plain PHP 8.4 + Node container alongside a MySQL 8.4 service — no custom image build, so it works on first Codespace creation without `vendor/` needing to exist yet). Opening the repo in Codespaces provisions both containers and automatically runs `composer install`, `npm install`, `.env` setup, `key:generate`, and `migrate --seed`.

Once the container is ready, start the app:

```bash
php artisan serve --host=0.0.0.0 --port=8000   # forwarded automatically in Codespaces
npm run dev                                     # in a second terminal, for Vite/Tailwind hot reload
```

### Manual setup (if not using the devcontainer)

```bash
composer install
cp .env.example .env
php artisan key:generate

# Laravel Sail (Docker) is already set up (compose.yaml) if you'd rather
# run MySQL that way instead of a local/native MySQL install:
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed
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
php artisan test
```

## Roles

- **Customer** — registers via `/register` (always created as `customer`; role can't be self-elevated).
- **Staff** / **Technician** — provisioned manually (e.g. via seeder/tinker) for now; share the workshop area (`/staff/*`) in this first release.

## Project status

See the module-by-module build log in conversation history. Currently implemented: authentication, roles, mobile-first layout with role-aware bottom navigation, and placeholder customer/staff navigation screens. Bicycle registration, booking, and the repair workflow are not yet built.
