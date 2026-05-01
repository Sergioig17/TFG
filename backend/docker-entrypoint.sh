#!/bin/sh
set -e

PORT="${PORT:-8000}"

cd /var/www/html

# Railway builds usually don't include .env; create one from the example if missing.
if [ ! -f .env ] && [ -f .env.example ]; then
	cp .env.example .env
fi

# Ensure APP_KEY exists so Laravel commands can boot.
if [ -f .env ] && ! grep -q '^APP_KEY=base64:' .env; then
	php artisan key:generate --force --no-interaction || true
fi

# Prevent stale cached config/routes from breaking startup.
php artisan optimize:clear --no-interaction >/dev/null 2>&1 || true

exec php artisan serve --host=0.0.0.0 --port="${PORT}"
