#!/bin/sh
set -e

# Ensure Laravel writable paths are owned by the web user.
for dir in /var/www/storage /var/www/bootstrap/cache; do
  if [ -d "$dir" ]; then
    chown -R www-data:www-data "$dir" 2>/dev/null || true
    chmod -R ug+rwX "$dir" 2>/dev/null || true
  fi
done

exec docker-php-entrypoint "$@"
