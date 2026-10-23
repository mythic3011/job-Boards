# syntax=docker/dockerfile:1
# Security floor: PHP 8.5.12 fixes CVE-2026-109618 / CVE-2026-109619.
# Digest is the multi-platform Docker Hub index; update via reviewed PRs.
ARG PHP_IMAGE=php:8.5.12-apache-bookworm@sha256:747123a8d7d28c6f84960df94cc076a406314e4f9d556af0bcfddab81e33bd09
FROM node:24-bookworm-slim AS assets
WORKDIR /src
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN npm run build

FROM ${PHP_IMAGE} AS runtime
WORKDIR /var/www/html

# Fail closed if a caller overrides PHP_IMAGE to a vulnerable or wrong minor release.
RUN php -r 'if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 5 || version_compare(PHP_VERSION, "8.5.12", "<")) { fwrite(STDERR, "PHP >=8.5.12 and <8.6 required\n"); exit(1); }'

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
      curl git unzip libicu-dev libpq-dev libzip-dev libonig-dev \
      libpng-dev libjpeg62-turbo-dev libfreetype6-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
      bcmath exif gd intl mbstring pcntl pdo_pgsql pgsql zip; \
    a2enmod rewrite headers; \
    rm -rf /var/lib/apt/lists/*

RUN set -eux; \
    sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf; \
    printf '%s\n' \
      '<Directory /var/www/html/public>' \
      '    AllowOverride All' \
      '    Options -Indexes' \
      '    Require all granted' \
      '</Directory>' > /etc/apache2/conf-available/laravel.conf; \
    a2enconf laravel; \
    printf '%s\n' 'ServerTokens Prod' 'ServerSignature Off' > /etc/apache2/conf-available/security-local.conf; \
    a2enconf security-local

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY . .
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts \
    && mkdir -p storage/app/public storage/logs storage/framework/cache/data \
        storage/framework/sessions storage/framework/views bootstrap/cache \
    && ln -s ../storage/app/public public/storage \
    && chown -R www-data:www-data storage bootstrap/cache
RUN php artisan package:discover --ansi --no-interaction

COPY --from=assets /src/public/build ./public/build
COPY deploy/shp/php.ini /usr/local/etc/php/conf.d/zz-job-boards.ini
EXPOSE 80
