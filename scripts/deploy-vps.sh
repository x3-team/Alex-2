#!/usr/bin/env bash
# Runs on the production VPS after CI rsync. Requires APP_ROOT = current directory.
set -euo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_ROOT"

RUN_MIGRATIONS="${RUN_MIGRATIONS:-true}"

echo "==> Deploy in ${APP_ROOT}"

if [[ ! -f .env ]]; then
  echo "ERROR: .env is missing on the server. Create it once manually; CI never overwrites .env." >&2
  exit 1
fi

php artisan down --retry=60 || true

composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress

if [[ -f package-lock.json ]]; then
  npm ci --legacy-peer-deps
else
  npm install --legacy-peer-deps
fi

npm run build

if [[ "${RUN_MIGRATIONS}" == "true" ]]; then
  php artisan migrate --force
fi

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan up

echo "==> Deploy finished. Restart queue workers and Inertia SSR if you use them (not automated here)."
