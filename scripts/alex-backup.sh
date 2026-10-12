#!/usr/bin/env bash
# Ночной бэкап сайта на VPS (лежит на сервере как ~/bin/alex-backup.sh, cron у alexadmin).
# Кладёт в ~/backups/daily/<дата>/: дамп MySQL (gzip), storage/app/public (tar.gz) и .env.
# Доступ к БД — из ~/.my.cnf (права 600), пароль в командную строку не попадает.
# Удаляет только папки-даты в ~/backups/daily старше KEEP_DAYS дней.
set -euo pipefail

APP="${APP:-/var/www/alexallergotest.ru}"
ROOT="${BACKUP_DAILY_ROOT:-$HOME/backups/daily}"
KEEP_DAYS="${KEEP_DAYS:-14}"
LOG="$ROOT/backup.log"

umask 077
mkdir -p "$ROOT"
chmod 700 "$ROOT"

log() { printf '%s %s\n' "$(date '+%F %T %z')" "$*" >> "$LOG"; }
trap 'log "FAILED (line $LINENO)"' ERR

[[ -f "$HOME/.my.cnf" ]] || { log "FAILED: no ~/.my.cnf"; exit 1; }
DB="$(grep -E '^DB_DATABASE=' "$APP/.env" | tail -1 | cut -d= -f2- | tr -d "\r\"'")"
[[ -n "$DB" ]] || { log "FAILED: DB_DATABASE not found in .env"; exit 1; }

DAY="$(date +%F)"
DEST="$ROOT/$DAY"
TMP="$ROOT/.tmp-$DAY-$$"
rm -rf "$TMP"
mkdir -p "$TMP"
chmod 700 "$TMP"
log "start -> $DEST"

mysqldump --single-transaction --routines --triggers --no-tablespaces "$DB" | gzip -9 > "$TMP/db.sql.gz"
tar -C "$APP/storage/app" -czf "$TMP/storage-app-public.tar.gz" public
cp -p "$APP/.env" "$TMP/env"
chmod 600 "$TMP/env"

gzip -t "$TMP/db.sql.gz"
gzip -t "$TMP/storage-app-public.tar.gz"
zcat "$TMP/db.sql.gz" | tail -1 | grep -q 'Dump completed' || { log "FAILED: dump not completed"; exit 1; }

# Повторный запуск в тот же день заменяет папку этого дня.
rm -rf "$DEST"
mv "$TMP" "$DEST"
chmod 700 "$DEST"

find "$ROOT" -mindepth 1 -maxdepth 1 -type d -regextype posix-extended \
    -regex '.*/[0-9]{4}-[0-9]{2}-[0-9]{2}' -mtime +"$KEEP_DAYS" -print0 \
    | while IFS= read -r -d '' old; do rm -rf -- "$old"; log "removed old $old"; done

log "ok $DEST db=$(stat -c %s "$DEST/db.sql.gz") storage=$(stat -c %s "$DEST/storage-app-public.tar.gz")"
