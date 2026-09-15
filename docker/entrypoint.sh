#!/usr/bin/env bash
#
# Runs once each time the `app` container starts (local dev or the VPS —
# same script everywhere). Handles the setup that has to happen against a
# live filesystem/database rather than at image-build time, then hands
# off to the real process (Apache) via `exec "$@"`.
#
# Deliberately does NOT run anything destructive (no migrate:fresh,
# db:wipe) and does NOT reseed automatically — see DB_SEED_ON_BOOT below.

set -euo pipefail

cd /var/www/html

# .env is bind-mounted from the host in every environment this image runs
# in, so this only fires on a genuinely fresh checkout that hasn't been
# configured yet.
if [ ! -f .env ]; then
    echo "No .env found — copying .env.example. Review it before relying on this in a real deployment."
    cp .env.example .env
fi

# vendor/ is normally already baked into the image and preserved across the
# bind mount by the named `vendor` volume (see compose.yaml). This is a
# safety net for the rare case that volume is empty (e.g. it was created
# before the image had this vendor tree, or was pruned).
if [ ! -f vendor/autoload.php ]; then
    echo "vendor/ missing — running composer install..."
    composer install --no-interaction --no-progress --optimize-autoloader
fi

# Same idea for the built frontend assets (public/build), which live in
# their own named volume so the source bind mount doesn't hide them.
if [ ! -f public/build/manifest.json ]; then
    echo "public/build missing — running npm run build..."
    npm ci && npm run build
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    echo "Generating APP_KEY..."
    php artisan key:generate --force --ansi
fi

# The bind mount can bring host-side ownership/permissions that don't match
# the container's www-data user; Laravel needs to write to both of these.
chown -R www-data:www-data storage bootstrap/cache

# Wait for MySQL to actually accept connections before touching the schema
# (compose's healthcheck+depends_on already does most of this, but this is
# a cheap extra guard for the odd out-of-order start).
if [ -n "${DB_HOST:-}" ]; then
    echo "Waiting for ${DB_HOST}:${DB_PORT:-3306}..."
    for _ in $(seq 1 60); do
        (echo > "/dev/tcp/${DB_HOST}/${DB_PORT:-3306}") >/dev/null 2>&1 && break
        sleep 2
    done
fi

# Forward-only and safe to run on every boot — never migrate:fresh/db:wipe.
php artisan migrate --force

# Off by default everywhere, including the VPS. Set DB_SEED_ON_BOOT=true
# (e.g. in a local .env) to opt in. The project's seeders are all
# existence-checked/updateOrCreate, so this is safe to run repeatedly.
if [ "${DB_SEED_ON_BOOT:-false}" = "true" ]; then
    echo "DB_SEED_ON_BOOT=true — seeding..."
    php artisan db:seed --force
fi

php artisan config:clear >/dev/null 2>&1 || true

exec "$@"
