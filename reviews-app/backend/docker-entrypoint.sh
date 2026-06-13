#!/bin/sh
set -e

cd /var/www

if [ ! -f ".env" ]; then
    cp .env.example .env
fi

if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    php artisan key:generate --force
fi

php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction

php-fpm
