#!/usr/bin/env bash
# Deploy sidebar typography fix (1.0.106) on Immunotech VPS.
# Requires SSH: alexadmin@82.202.142.248, app root /var/www/alexallergotest.ru
set -euo pipefail

APP_ROOT="${APP_ROOT:-/var/www/alexallergotest.ru}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/alexallergotest.ru}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"

echo "==> Pre-deploy backup on server (${STAMP})"
ssh alexallergotest "mkdir -p '${BACKUP_DIR}/${STAMP}' && \
  cp -a '${APP_ROOT}/resources/css/main.css' '${BACKUP_DIR}/${STAMP}/' && \
  cp -a '${APP_ROOT}/resources/js/siteVersion.js' '${BACKUP_DIR}/${STAMP}/' && \
  cp -a '${APP_ROOT}/public/build' '${BACKUP_DIR}/${STAMP}/build'"

echo "==> Upload sources"
scp resources/css/main.css alexallergotest:"${APP_ROOT}/resources/css/main.css"
scp resources/js/siteVersion.js alexallergotest:"${APP_ROOT}/resources/js/siteVersion.js"

echo "==> Build frontend + clear caches"
ssh alexallergotest "cd '${APP_ROOT}' && npm run build && php artisan optimize:clear"

echo "==> Done. Verify v1.0.106 on patient + doc home HTML."
