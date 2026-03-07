#!/bin/sh
set -eu

echo "[entrypoint] starting..."

mkdir -p \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

for dir in /var/www/storage /var/www/bootstrap/cache storage bootstrap/cache; do
  if [ -d "$dir" ]; then
    chown -R www-data:www-data "$dir" 2>/dev/null || true
    chmod -R ug+rwX "$dir" 2>/dev/null || true
  fi
done

if [ ! -d "vendor" ]; then
  echo "[entrypoint] vendor/ missing -> composer install"
  composer install --no-interaction --prefer-dist
fi

if [ ! -f ".env" ] && [ -f ".env.example" ]; then
  echo "[entrypoint] .env missing -> copying from .env.example"
  cp .env.example .env
fi

if [ -f ".env" ] && ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
  echo "[entrypoint] generating APP_KEY"
  php artisan key:generate --force
fi

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
  echo "[entrypoint] running migrations"
  php artisan migrate --force
fi

echo "[entrypoint] ready."

exec docker-php-entrypoint "$@"
