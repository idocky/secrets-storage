#!/bin/bash
set -euo pipefail

cd /app

mkdir -p \
    storage/app/public \
    storage/app/private \
    storage/framework/views \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/logs \
    bootstrap/cache \
    /var/log/nginx \
    /var/cache/nginx \
    /var/lib/nginx \
    /run/nginx

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

php artisan package:discover --ansi
php artisan config:clear
php artisan view:clear
php artisan storage:link --force
php artisan migrate --force
php artisan cache:clear || true
php artisan tus:ensure-lifecycle || true
chmod +x /usr/local/bin/run-tusd.sh /usr/local/bin/tusd 2>/dev/null || true

export PORT="${PORT:-80}"
envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

exec supervisord -c /etc/supervisor/supervisord.conf -n
