#!/usr/bin/env bash
# Runs on the production VPS. Modes: backup | apply
# Never overwrite .env, storage/, or public/videos (those are excluded by rsync).
# Never pass --delete. Exclude public/storage without a trailing slash,
# and pass --filter 'P public/storage' so --delete cannot remove the symlink.
# Uploaded files live in storage/app/public and must stay excluded too.
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

  # Prod php-fpm has opcache.validate_timestamps=0 — new PHP never loads until FPM reload.
  echo "==> Reloading php-fpm so opcache picks up new PHP"
  if command -v systemctl >/dev/null 2>&1; then
    sudo -n systemctl reload php8.2-fpm 2>/dev/null         || sudo -n systemctl reload php8.3-fpm 2>/dev/null         || sudo -n systemctl reload php-fpm 2>/dev/null         || echo "==> WARN: could not reload php-fpm via systemctl (need passwordless sudo or manual reload)"
  fi
  php artisan inertia:stop-ssr 2>/dev/null || true
  if [[ "${RESTART_SERVICES}" == "true" ]]; then
    echo "==> RESTART_SERVICES=true — extra reload requested"
  fi

  # public/storage is not in git. If a deploy dropped the symlink, put it back.
  # A real directory is left alone so we never hide files that are already there.
  if [[ -L public/storage ]]; then
    echo "==> public/storage symlink already present"
  elif [[ -e public/storage ]]; then
    echo "ERROR: public/storage exists and is not a symlink. Refusing to replace it." >&2
    exit 1
  else
    php artisan storage:link
  fi

  smoke_after_apply

  echo "==> apply finished in ${APP_ROOT}"
}

# First real file under storage/app/public/<folder>, as a /storage URL path.
pick_storage_sample() {
  local folder="$1"
  local root="storage/app/public/${folder}"
  local file

  if [[ ! -d "$root" ]]; then
    echo "ERROR: нет папки ${root}" >&2
    return 1
  fi

  file="$(find "$root" -type f ! -name '.*' -print -quit 2>/dev/null || true)"
  if [[ -z "$file" ]]; then
    echo "ERROR: в ${root} нет файлов для смоука" >&2
    return 1
  fi

  printf '%s\n' "${file#storage/app/public/}"
}

# 0 when the URL is 200/206 and the body is not an HTML error page.
storage_url_ok() {
  local url="$1"
  local hdr code ctype
  hdr="$(mktemp)"
  code="$(curl -sS -D "$hdr" -o /dev/null --max-time 25 -w '%{http_code}' "$url" || echo "000")"
  ctype="$(grep -i '^content-type:' "$hdr" | head -1 | tr -d '\r' || true)"
  rm -f "$hdr"

  if [[ "$code" != "200" && "$code" != "206" ]]; then
    echo "FAIL ${code} ${url}" >&2
    return 1
  fi
  if [[ -z "$ctype" ]] || echo "$ctype" | grep -qi 'text/html'; then
    echo "FAIL content-type '${ctype:-<none>}' ${url}" >&2
    return 1
  fi

  echo "OK ${code} ${ctype} ${url}"
  return 0
}

# Пациентский хост — APP_URL из .env, врачебный — тот же хост с префиксом doc.
smoke_origins() {
  local app_url
  app_url="$(grep -E '^APP_URL=' .env | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'" | tr -d '[:space:]')"
  app_url="${app_url%/}"
  if [[ -z "$app_url" ]]; then
    echo "ERROR: APP_URL пуст в .env, смоук не знает хосты" >&2
    return 1
  fi
  if [[ "$app_url" == *"://doc."* ]]; then
    printf '%s\n' "${app_url/:\/\/doc./:\/\/}"
    printf '%s\n' "$app_url"
  else
    printf '%s\n' "$app_url"
    printf '%s\n' "${app_url/:\/\//:\/\/doc.}"
  fi
}

smoke_storage_samples() {
  local rel origin failed=0
  local -a samples=() origins=()

  mapfile -t origins < <(smoke_origins) || return 1

  for folder in allergens blog authors; do
    rel="$(pick_storage_sample "$folder")" || return 1
    samples+=("$rel")
  done

  for rel in "${samples[@]}"; do
    for origin in "${origins[@]}"; do
      storage_url_ok "${origin}/storage/${rel}" || failed=1
    done
  done

  return "$failed"
}

smoke_homes() {
  local origin body code asset expected failed=0
  expected="$(grep -oE "[0-9]+\.[0-9]+\.[0-9]+" resources/js/siteVersion.js | head -1 || true)"
  if [[ -z "$expected" ]]; then
    echo "ERROR: не прочитан SITE_VERSION из resources/js/siteVersion.js" >&2
    return 1
  fi

  asset="$(grep -oE 'assets/siteVersion-[A-Za-z0-9_-]+\.js' public/build/manifest.json | head -1 || true)"
  local -a origins=()
  mapfile -t origins < <(smoke_origins) || return 1

  for origin in "${origins[@]}"; do
    body="$(mktemp)"
    code="$(curl -sS -o "$body" --max-time 25 -w '%{http_code}' "${origin}/" || echo "000")"
    if [[ "$code" != "200" ]]; then
      echo "FAIL home ${code} ${origin}/" >&2
      failed=1
    elif grep -q "$expected" "$body"; then
      echo "OK home 200 ${origin}/ version ${expected}"
    elif [[ -n "$asset" ]] && curl -fsS --max-time 25 "${origin}/build/${asset}" | grep -q "$expected"; then
      echo "OK home 200 ${origin}/ version ${expected} via /build/${asset}"
    else
      echo "FAIL home ${origin}/ отдал ${code}, но версии ${expected} нет" >&2
      failed=1
    fi
    rm -f "$body"
  done

  return "$failed"
}

smoke_after_apply() {
  echo "==> smoke: главные и /storage"
  local storage_ok=0 home_ok=0

  if smoke_storage_samples; then
    storage_ok=1
  fi
  if smoke_homes; then
    home_ok=1
  fi

  if [[ "$storage_ok" == "1" && "$home_ok" == "1" ]]; then
    echo "==> smoke passed"
    return 0
  fi

  echo "ERROR: смоук после выкладки не прошёл. Пробую php artisan storage:link и повтор." >&2
  php artisan storage:link || true

  storage_ok=0
  home_ok=0
  if smoke_storage_samples; then
    storage_ok=1
  fi
  if smoke_homes; then
    home_ok=1
  fi

  if [[ "$storage_ok" == "1" && "$home_ok" == "1" ]]; then
    echo "==> smoke passed after storage:link"
    return 0
  fi

  echo "ERROR: /storage или главные всё ещё не отдают 200/206 с не-HTML типом. Выкладка остановлена." >&2
  exit 1
}

case "$MODE" in
  backup) backup ;;
  apply) apply ;;
  *)
    echo "Usage: $0 backup|apply" >&2
    exit 1
    ;;
esac
