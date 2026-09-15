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
RUN cp .env.example .env \
    && composer install --no-interaction --no-progress --optimize-autoloader \
    && npm ci \
    && npm run build \
    && npm prune --omit=dev \
    && rm .env \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
