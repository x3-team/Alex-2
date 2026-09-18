#!/usr/bin/env bash
# Runs on the production VPS. Modes: backup | apply
# Never overwrite .env, storage/, or public/videos (those are excluded by rsync).
# operator must bump SITE_VERSION in the release commit; script does not auto-bump
set -euo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_ROOT"

MODE="${1:-apply}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-false}"
RESTART_SERVICES="${RESTART_SERVICES:-false}"
BACKUP_ROOT="${BACKUP_ROOT:-$HOME/backups/alexallergotest.ru}"

backup() {
  local stamp
  stamp="$(date -u +%Y%m%dT%H%M%SZ)"
  local dest="${BACKUP_ROOT}/${stamp}"
  mkdir -p "$dest"
  if [[ -d public/build ]]; then
    cp -a public/build "${dest}/build"
  fi
  if [[ -f resources/js/siteVersion.js ]]; then
    cp -a resources/js/siteVersion.js "${dest}/"
  fi
  if [[ -f resources/css/main.css ]]; then
    cp -a resources/css/main.css "${dest}/"
  fi
  if [[ -f public/build/manifest.json ]]; then
    cp -a public/build/manifest.json "${dest}/manifest.json"
  fi
  echo "==> Backup written to ${dest}"
}

apply() {
  echo "==> operator must bump SITE_VERSION in the release commit; script does not auto-bump"
  if [[ -f resources/js/siteVersion.js ]]; then
    echo "==> SITE_VERSION on disk:"
    grep -E "SITE_VERSION" resources/js/siteVersion.js || true
  fi

  if [[ ! -f .env ]]; then
    echo "ERROR: .env is missing on the server. CI/CD never creates or overwrites .env." >&2
    exit 1
  fi

  composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress

  if [[ -f package-lock.json ]]; then
    npm ci --legacy-peer-deps
  else
    npm install --legacy-peer-deps
  fi

  npm run build

  if [[ "${RUN_MIGRATIONS}" == "true" ]]; then
    php artisan migrate --force
  else
    echo "==> Skipping migrations (RUN_MIGRATIONS=${RUN_MIGRATIONS})"
  fi

  php artisan optimize:clear
  php artisan config:cache
  # Known prod issue: duplicate named route [logout] breaks route:cache.
  if ! php artisan route:cache; then
    echo "==> route:cache failed (duplicate route names); falling back to route:clear"
    php artisan route:clear
  fi
  php artisan view:cache

  if [[ "${RESTART_SERVICES}" == "true" ]]; then
    echo "==> RESTART_SERVICES=true — reload php-fpm so opcache picks up new PHP"
    if command -v systemctl >/dev/null 2>&1; then
      sudo -n systemctl reload php8.2-fpm 2>/dev/null         || sudo -n systemctl reload php8.3-fpm 2>/dev/null         || sudo -n systemctl reload php-fpm 2>/dev/null         || echo "==> WARN: could not reload php-fpm via systemctl (need passwordless sudo or manual reload)"
    fi
    if command -v php >/dev/null 2>&1; then
      php -r 'if (function_exists("opcache_reset")) { opcache_reset(); echo "==> opcache_reset via CLI\n"; }' 2>/dev/null || true
    fi
  else
    echo "==> Leaving php-fpm and Inertia SSR untouched. Restart them manually if the new SSR bundle must load."
  fi

  echo "==> apply finished in ${APP_ROOT}"
}

case "$MODE" in
  backup) backup ;;
  apply) apply ;;
  *)
    echo "Usage: $0 backup|apply" >&2
    exit 1
    ;;
esac
