# syntax=docker/dockerfile:1.7

# ---- Stage 1: front-end assets -------------------------------------------------------------
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
COPY Modules ./Modules
RUN npm run build

# ---- Stage 2: PHP base, shared so dependencies are resolved on the PHP that runs them --------
FROM php:8.3-fpm-alpine AS base

ARG UID=1000
ARG GID=1000

RUN apk add --no-cache libpq icu-libs libzip oniguruma fcgi supervisor \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev icu-dev libzip-dev oniguruma-dev linux-headers \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql pdo_pgsql intl bcmath pcntl opcache zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && addgroup -g ${GID} appuser && adduser -D -u ${UID} -G appuser appuser

# ---- Stage 3: PHP dependencies ---------------------------------------------------------------
FROM base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative --no-interaction

# ---- Stage 4: runtime --------------------------------------------------------------------------
FROM base AS runtime

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf

WORKDIR /var/www/html
COPY --from=vendor --chown=appuser:appuser /app ./
COPY --from=assets --chown=appuser:appuser /app/public/build ./public/build

RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R appuser:appuser storage bootstrap/cache

USER appuser
EXPOSE 9000
HEALTHCHECK --interval=15s --timeout=3s --retries=5 CMD \
    SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong
CMD ["php-fpm"]
