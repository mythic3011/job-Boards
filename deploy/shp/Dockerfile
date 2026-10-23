# syntax=docker/dockerfile:1
# Minimal production runtime: multi-stage Vite assets + Laravel on official PHP 8.5.
ARG PHP_IMAGE=php:8.5-apache-bookworm
FROM node:24-bookworm-slim AS assets
WORKDIR /src
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN npm run build

FROM ${PHP_IMAGE} AS runtime
WORKDIR /var/www/html

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
      curl git unzip libicu-dev libpq-dev libzip-dev libonig-dev \
      libpng-dev libjpeg62-turbo-dev libfreetype6-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
      bcmath exif gd intl mbstring opcache pcntl pdo_pgsql pgsql zip; \
    a2enmod rewrite headers; \
    rm -rf /var/lib/apt/lists/*

# Use only public/ as DocumentRoot; Laravel's .htaccess handles clean URLs.
RUN set -eux; \
    sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf; \
    printf '%s\n' \
      '<Directory /var/www/html/public>' \
      '    AllowOverride All' \
      '    Require all granted' \
      '</Directory>' > /etc/apache2/conf-available/laravel.conf; \
    a2enconf laravel

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
