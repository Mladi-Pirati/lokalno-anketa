#!/usr/bin/env bash
set -e

echo "Syncing public assets to shared volume..."
cp -r /var/www/html/public_source/. /var/www/html/public/

echo "Clearing stale caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan package:discover --ansi

echo "Waiting for database connection..."
until php artisan db:show; do
  echo "Database not ready yet, retrying in 2s..."
  sleep 2
done
echo "Database is up."

echo "Running migrations..."
php artisan migrate --force
php artisan db:seed --force

echo "Caching config..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Linking storage..."
php artisan storage:link || true

echo "Running mapping"
php -d memory_limit=-1 artisan map:import ./data/OB.geojson --regions=./data/SR.geojson

case "${APP_SERVER:-fpm}" in
  http)
    echo "Starting Laravel HTTP server on port ${APP_HTTP_PORT:-8080}..."
    exec php artisan serve --host=0.0.0.0 --port="${APP_HTTP_PORT:-8080}"
    ;;
  fpm)
    echo "Starting php-fpm..."
    exec php-fpm
    ;;
  *)
    echo "Unknown APP_SERVER value: ${APP_SERVER} (expected 'http' or 'fpm')" >&2
    exit 1
    ;;
esac
