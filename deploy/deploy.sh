#!/usr/bin/env bash
# Deploy the latest code on the server.  Usage:  sudo /var/www/mahale/deploy/deploy.sh
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/mahale}"
PHP_FPM="${PHP_FPM:-php8.3-fpm}"
cd "$APP_DIR"

php artisan down --retry=15 || true
trap 'php artisan up' EXIT

git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan optimize
chown -R www-data:www-data storage bootstrap/cache
systemctl reload "$PHP_FPM"

echo "Deployed $(git rev-parse --short HEAD)"
