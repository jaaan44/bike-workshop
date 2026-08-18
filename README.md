# Bike Workshop

A mobile-first bicycle repair shop management app. Customers register bikes, book repairs, and track status; workshop staff manage bookings and repair jobs.

Stack: Laravel 13 + Blade + Tailwind CSS + Alpine.js (via Laravel Breeze), MySQL, Laravel Sail for local Docker development.

## Setup (GitHub Codespaces)

This repo includes a `.devcontainer` that reuses Sail's `compose.yaml`, so opening it in Codespaces should bring up the app container and a MySQL service automatically. If you need to do it manually:

```bash
composer install
cp .env.example .env
php artisan key:generate

./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed

npm install
npm run build   # or: ./vendor/bin/sail npm run dev
```

App runs at `http://localhost` (port 80, forwarded automatically in Codespaces).

## Demo accounts

Seeded by `php artisan db:seed` (password for all: `password`):

| Role       | Email                   |
|------------|--------------------------|
| Customer   | customer@example.com     |
| Staff      | staff@example.com        |
| Technician | technician@example.com   |

## Running tests

```bash
./vendor/bin/sail artisan test
```

## Roles

- **Customer** — registers via `/register` (always created as `customer`; role can't be self-elevated).
- **Staff** / **Technician** — provisioned manually (e.g. via seeder/tinker) for now; share the workshop area (`/staff/*`) in this first release.

## Project status

See the module-by-module build log in conversation history. Currently implemented: authentication, roles, mobile-first layout with role-aware bottom navigation, and placeholder customer/staff navigation screens. Bicycle registration, booking, and the repair workflow are not yet built.
