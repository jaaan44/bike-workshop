# Bicycle Workshop — application image.
#
# Runs PHP 8.4 + Apache on port 80 inside the container (the host-side port
# is chosen entirely in compose.yaml / .env — this image never knows or
# cares what host port it's published on). Frontend assets are built at
# image-build time with Vite so the production/VPS runtime never needs
# Node or a Vite dev server. Composer dependencies include dev packages
# (PHPUnit, Pint) so `docker compose exec app php artisan test` and
# `... ./vendor/bin/pint` work the same way in every environment this
# image runs in — this is a single-image setup, not a separate prod build.
FROM php:8.4-apache-bookworm

# System packages: libonig-dev/libzip-dev are build-time deps for the
# mbstring/zip PHP extensions; unzip+git are needed by Composer; curl+gnupg
# are needed to add the NodeSource apt repo for a Vite-capable Node.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libonig-dev \
        libzip-dev \
        unzip \
        git \
        curl \
        gnupg \
    && docker-php-ext-install pdo_mysql mbstring bcmath zip \
    && a2enmod rewrite \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Laravel's public/ directory is the document root, not the repo root.
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY . .

# A placeholder .env so `composer install`'s package:discover (which boots
# the framework) and `npm run build` have something to read at build time.
# It is never used at runtime: the real .env — bind-mounted from the host
# in every environment this image runs in — replaces it (see compose.yaml).
#
# compose.yaml bind-mounts the whole repo over /var/www/html and shadows
# just the vendor/ and public/build subpaths with named volumes, so this
# image's own build output survives that mount instead of being hidden by
# an empty host checkout (vendor/ and public/build are gitignored). Docker
# only seeds a named volume from image content the FIRST time it's used,
# though — a later `docker compose build` does not refresh an
# already-existing volume, so a stale volume can silently keep serving a
# previous image's Composer dependencies or Vite assets after a rebuild
# (this happened for public/build during the first real staging deploy).
# /opt/release-artifacts below is a pristine, never-mounted copy of exactly
# what THIS image built, plus a checksum of what determines each one's
# content — docker/entrypoint.sh compares those checksums against the
# named volumes on every container start and refreshes the volume whenever
# they differ, so a rebuilt image can never be shadowed by a stale volume.
RUN cp .env.example .env \
    && composer install --no-interaction --no-progress --optimize-autoloader \
    && npm ci \
    && npm run build \
    && npm prune --omit=dev \
    && rm .env \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && mkdir -p /opt/release-artifacts \
    && cp -a vendor /opt/release-artifacts/vendor \
    && cp -a public/build /opt/release-artifacts/build \
    && sha256sum composer.lock | cut -d' ' -f1 > /opt/release-artifacts/vendor.sha256 \
    && sha256sum public/build/manifest.json | cut -d' ' -f1 > /opt/release-artifacts/build.sha256

COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
