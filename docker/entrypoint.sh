#!/bin/sh
set -e

cd /var/www/html

mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ -f .env ]; then
  php artisan config:cache || true
  php artisan view:cache || true
fi

php-fpm -D
exec nginx -g 'daemon off;'
