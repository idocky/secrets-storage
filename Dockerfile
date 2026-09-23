FROM php:8.3-fpm-bookworm AS php-base

RUN apt-get update && apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS \
        git \
        unzip \
        libzip-dev \
        libpq-dev \
        libpng-dev \
        libicu-dev \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        bcmath \
        gd \
        zip \
        pcntl \
        intl \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app


FROM php-base AS vendor

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist

COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY routes ./routes
COPY artisan ./

RUN composer dump-autoload --optimize --no-dev --no-scripts


FROM node:22-bookworm-slim AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js artisan composer.json ./
COPY resources ./resources
COPY public ./public

RUN npm run build


FROM php-base AS runtime

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        gettext-base \
        curl \
        ca-certificates \
    && install -d /usr/share/postgresql-common/pgdg \
    && curl -fsSL -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc \
        https://www.postgresql.org/media/keys/ACCC4CF8.asc \
    && echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt bookworm-pgdg main" \
        > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends postgresql-client-16 \
    && apt-get purge -y --auto-remove postgresql-client-15 || true \
    && ln -sfn /usr/lib/postgresql/16/bin/pg_dump /usr/local/bin/pg_dump \
    && ln -sfn /usr/lib/postgresql/16/bin/pg_restore /usr/local/bin/pg_restore \
    && ln -sfn /usr/lib/postgresql/16/bin/psql /usr/local/bin/psql \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default

ARG TUSD_VERSION=2.8.0
RUN curl -fsSL -o /tmp/tusd.tar.gz \
        "https://github.com/tus/tusd/releases/download/v${TUSD_VERSION}/tusd_linux_amd64.tar.gz" \
    && tar -xzf /tmp/tusd.tar.gz -C /tmp \
    && install -m 0755 "$(find /tmp -name tusd -type f | head -n1)" /usr/local/bin/tusd \
    && rm -rf /tmp/tusd.tar.gz /tmp/tusd*

WORKDIR /app

COPY . /app
COPY --from=vendor /app/vendor /app/vendor
COPY --from=frontend /app/public/build /app/public/build

COPY _docker/prod/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY _docker/prod/php-fpm.conf /usr/local/etc/php-fpm.conf
COPY _docker/prod/nginx.conf.template /etc/nginx/nginx.conf.template
COPY _docker/prod/supervisord.conf /etc/supervisor/supervisord.conf
COPY _docker/prod/start.sh /usr/local/bin/start.sh
COPY _docker/tusd/run.sh /usr/local/bin/run-tusd.sh

RUN chmod +x /usr/local/bin/start.sh /usr/local/bin/run-tusd.sh \
    && mkdir -p \
        storage/app/public \
        storage/app/private \
        storage/framework/views \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/logs \
        bootstrap/cache \
    && rm -f bootstrap/cache/packages.php bootstrap/cache/services.php \
    && APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr \
        php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

ENV PORT=80

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD ["sh", "-c", "curl -fsS http://127.0.0.1:${PORT:-80}/up || exit 1"]

CMD ["/usr/local/bin/start.sh"]
