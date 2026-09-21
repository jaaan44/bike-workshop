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

# Laravel compiles .env-derived config into bootstrap/cache/config.php the
# moment anything runs `config:cache` (see docs/DEPLOYMENT.md §12/§28's
# optional post-deploy step). Because bootstrap/cache/ lives inside the
# whole-repo bind mount (`.:/var/www/html` in compose.yaml), that file
# persists on the host across every container recreation, exactly like
# .env itself does. Unlike .env, though, editing .env does NOT invalidate
# an already-cached bootstrap/cache/config.php — Laravel trusts the cache
# file over config/*.php + .env whenever it's present. During a real
# staging credential rotation, this meant `php artisan migrate --force`
# below read the OLD DB_PASSWORD baked into a config.php cached before the
# rotation, not the new value in .env, and migration failed with
# SQLSTATE[HY000] [1045] Access denied — see docs/DEPLOYMENT.md §0.5 for
# the full incident.
#
# config.php is not source-controlled (covered by the wildcard ignore in
# bootstrap/cache/.gitignore) and is not produced by the image at build
# time — it only ever exists because an operator ran a `config:cache`-style
# command against a filesystem/.env state that may since have changed.
# Removing it unconditionally, every boot, before anything below reads
# config/DB credentials, guarantees this container always boots against
# the CURRENT bind-mounted .env. `rm -f` is a no-op on a file that doesn't
# exist, so this is safe on every single start, not just after a
# credential rotation.
#
# Deliberately scoped to config.php only, not the other generated
# bootstrap/cache/*.php files (routes-v7.php, events.php, services.php,
# packages.php): those weren't responsible for this incident — only
# config.php causes Laravel to ignore a changed .env — and clearing them
# too would needlessly invalidate route/event/package-discovery caches
# (and the production optimizations they represent) on every ordinary
# restart, not just after a credential rotation.
#
# This entrypoint always runs as root (no `USER` directive in the
# Dockerfile), so it can remove this file regardless of what host-side
# ownership a previous www-data-owned run left it with — an operator
# should never need `sudo rm` on the host to recover from this again.
clear_stale_config_cache() {
    local app_root="${1:-.}"
    rm -f "${app_root}/bootstrap/cache/config.php"
}

# Lets docker/entrypoint.test.sh source this file and exercise
# clear_stale_config_cache() directly, without running the
# container-boot sequence below (which needs a real filesystem/DB and
# isn't unit-testable).
if [[ "${ENTRYPOINT_SOURCE_ONLY:-}" == "1" ]]; then
    return 0 2>/dev/null || exit 0
fi

cd /var/www/html

# .env is bind-mounted from the host in every environment this image runs
# in, so this only fires on a genuinely fresh checkout that hasn't been
# configured yet.
if [ ! -f .env ]; then
    echo "No .env found — copying .env.example. Review it before relying on this in a real deployment."
    cp .env.example .env
fi

# vendor/ and public/build live in named volumes (see compose.yaml) so the
# whole-repo bind mount above doesn't shadow the image's own build output.
# Docker only seeds a named volume from image content the FIRST time it's
# used — a later `docker compose build` does NOT refresh an already-existing
# volume, so without this check a rebuilt image can sit unused behind a
# stale volume still serving a previous deployment's Composer dependencies
# or Vite assets (this is exactly what happened to public/build on the
# first real staging deploy, and vendor/ is structurally exposed to the
# same risk even though it hadn't actually gone stale yet).
#
# The Dockerfile leaves a pristine, never-mounted copy of what THIS image
# built at /opt/release-artifacts, tagged with a checksum of whatever
# determines that artifact's content (composer.lock for vendor,
# public/build/manifest.json for the frontend build). Compare that against
# a checksum stored inside the volume itself and refresh the volume
# whenever they differ — including "volume exists but was never checked"
# and "volume is empty" — so a rebuilt image can never be left stale.
sync_release_artifact() {
    local name="$1" target="$2"
    local source="/opt/release-artifacts/${name}"

    if [ ! -f "${source}.sha256" ]; then
        # Defensive fallback only: an image built without this mechanism.
        return 0
    fi

    local source_checksum target_checksum
    source_checksum="$(cat "${source}.sha256")"
    target_checksum="$(cat "${target}/.release-checksum" 2>/dev/null || true)"

    if [ "$source_checksum" != "$target_checksum" ]; then
        echo "${target}: refreshing from image (checksum mismatch or first run)..."
        mkdir -p "$target"
        find "$target" -mindepth 1 -delete
        cp -a "${source}/." "$target/"
        echo "$source_checksum" > "${target}/.release-checksum"
    fi
}

sync_release_artifact vendor vendor
sync_release_artifact build public/build

# Must run before any `php artisan` command below (including the
# composer/APP_KEY/migrate steps that follow) — see the comment on
# clear_stale_config_cache() above for why.
clear_stale_config_cache .

# Extreme fallback only, for an image built without the mechanism above
# (e.g. one predating this change) or a volume refresh that somehow still
# left things incomplete — never the normal path.
if [ ! -f vendor/autoload.php ]; then
    echo "vendor/ missing — running composer install..."
    composer install --no-interaction --no-progress --optimize-autoloader
fi

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

# No config:clear here: clear_stale_config_cache already guaranteed no
# stale bootstrap/cache/config.php exists for this boot, and nothing above
# recreates one, so there is nothing left to clear.

exec "$@"
