#!/bin/sh
set -e

cd /var/www

if [ ! -f ".env" ]; then
    cp .env.example .env
fi

# Write env vars to .env if not already set there
for var in APP_NAME APP_ENV APP_DEBUG APP_URL DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD CACHE_DRIVER SESSION_DRIVER FRONTEND_URL; do
    eval "val=\$$var"
    if [ -n "$val" ]; then
        if grep -q "^${var}=" .env; then
            sed -i "s|^${var}=.*|${var}=${val}|" .env
        else
            echo "${var}=${val}" >> .env
        fi
    fi
done

php artisan key:generate --force
php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction

exec php-fpm
