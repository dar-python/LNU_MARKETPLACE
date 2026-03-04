#!/usr/bin/env sh
set -eu

echo "[entrypoint] starting..."

# Ensure Laravel writable dirs exist
mkdir -p \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

# Fix permissions (Alpine php-fpm user is usually www-data)
# If your image uses another user, update accordingly.
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwX storage bootstrap/cache || true

# If vendor missing (fresh container), install deps
if [ ! -d "vendor" ]; then
  echo "[entrypoint] vendor/ missing -> composer install"
  composer install --no-interaction --prefer-dist
fi

# If .env missing, create from example (dev convenience)
if [ ! -f ".env" ] && [ -f ".env.example" ]; then
  echo "[entrypoint] .env missing -> copying from .env.example"
  cp .env.example .env
fi

# Generate app key if missing
if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
  echo "[entrypoint] generating APP_KEY"
  php artisan key:generate --force
fi

# Skip cache clearing on boot to avoid startup stalls when dependent
# services (e.g., DB-backed cache/session) are not ready yet.

# Optionally run migrations automatically (dev only)
if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
  echo "[entrypoint] running migrations"
  php artisan migrate --force
fi

echo "[entrypoint] ready."

# Execute the container CMD (e.g., php-fpm)
exec "$@"
