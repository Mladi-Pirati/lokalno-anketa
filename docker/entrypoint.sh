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
php artisan db:seed

echo "Caching config..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Linking storage..."
php artisan storage:link || true

echo "Running mapping"
php -d memory_limit=-1 artisan map:import ./data/OB.geojson --regions=./data/SR.geojson

echo "Starting php-fpm..."
exec php-fpm