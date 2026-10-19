#!/usr/bin/env bash
# Runs on the production VPS. Keeps the Inertia SSR process alive.
# Without SSR Laravel silently serves an empty <div id="app">, and robots see no text.
# Called by deploy-vps.sh after `inertia:stop-ssr` and by cron every 5 minutes.
# Exit 0 when /health answers (possibly after a start), 1 otherwise.
set -uo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_ROOT"

WAIT_SECONDS="${SSR_WAIT_SECONDS:-30}"
LOG="${APP_ROOT}/storage/logs/ssr-watchdog.log"

log() {
  printf '%s %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" | tee -a "$LOG" >&2
}

ssr_url() {
  local u=""
  if [[ -f .env ]]; then
    u="$(grep -E '^INERTIA_SSR_URL=' .env | tail -1 | cut -d= -f2- | tr -d "\"' \r" || true)"
  fi
  u="${u:-http://127.0.0.1:13714}"
  printf '%s\n' "${u%/}"
}

ssr_up() {
  curl -fsS --max-time 3 "$(ssr_url)/health" >/dev/null 2>&1
}

wait_ssr() {
  local i
  for ((i = 0; i < WAIT_SECONDS; i++)); do
    if ssr_up; then
      return 0
    fi
    sleep 1
  done
  ssr_up
}

# supervisor first (it owns the process on this VPS); a detached process only as last resort.
start_ssr() {
  local prog="" ctl
  if command -v supervisorctl >/dev/null 2>&1; then
    for ctl in "sudo -n supervisorctl" "supervisorctl"; do
      prog="$($ctl status 2>/dev/null | awk 'tolower($1) ~ /ssr/ { print $1; exit }')"
      if [[ -n "$prog" ]]; then
        if $ctl start "$prog" >/dev/null 2>&1 || $ctl restart "$prog" >/dev/null 2>&1; then
          log "SSR: ${ctl} start ${prog}"
          return 0
        fi
      fi
    done
  fi
  log "SSR: supervisor недоступен, запускаю php artisan inertia:start-ssr отдельно"
  setsid nohup php artisan inertia:start-ssr >> storage/logs/inertia-ssr.log 2>&1 < /dev/null &
  return 0
}

if ssr_up; then
  exit 0
fi

if wait_ssr; then
  exit 0
fi

log "SSR не отвечает на $(ssr_url)/health ${WAIT_SECONDS} с"
start_ssr
if wait_ssr; then
  log "SSR снова отвечает"
  exit 0
fi

log "ERROR: SSR так и не поднялся"
exit 1
