#!/bin/sh
set -e

PORT="${PORT:-8000}"

cd /var/www/html

exec php artisan serve --host=0.0.0.0 --port="${PORT}"
