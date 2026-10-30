#!/usr/bin/env bash
# Staging-контур на том же VPS, что и прод. Запускается workflow «Staging» (.github/workflows/staging.yml)
# из ~/staging-tools. Режимы: inspect | setup | refresh-db | apply-secrets | certs | deploy.
#
# Жёсткие правила:
# - пишем только в STAGING_ROOT, БД alex2_staging, ~/staging и свои файлы nginx/supervisor/php-fpm;
# - из прода только читаем (дамп БД через ~/.my.cnf, storage/app/public, public/videos, имена ключей .env);
# - сервисы прода не перезапускаем, единственное общее действие — nginx -t && systemctl reload nginx;
# - пароли и ключи не печатаем, они лежат в ~/staging (права 700/600).
# Подробности: docs/STAGING.md.
set -euo pipefail

MODE="${1:-inspect}"

STAGING_DOMAIN="${STAGING_DOMAIN:-test.alexallergotest.ru}"
STAGING_DOC_DOMAIN="${STAGING_DOC_DOMAIN:-doc.test.alexallergotest.ru}"
STAGING_ROOT="/var/www/test.alexallergotest.ru"
PROD_ROOT="${PROD_ROOT:-/var/www/alexallergotest.ru}"
SERVER_IP="${SERVER_IP:-82.202.142.248}"

STAGING_HOME="$HOME/staging"
STAGING_DB="alex2_staging"
STAGING_DB_USER="alex2_staging"
DB_CNF="$STAGING_HOME/db.cnf"
BASIC_AUTH_FILE="$STAGING_HOME/basic-auth.txt"
ADMIN_PASSWORD_FILE="$STAGING_HOME/admin-password.txt"

HTPASSWD="/etc/nginx/alex-staging.htpasswd"
NGINX_SITE="/etc/nginx/sites-available/test.alexallergotest.ru.conf"
NGINX_LINK="/etc/nginx/sites-enabled/test.alexallergotest.ru.conf"
ACME_ROOT="/var/www/letsencrypt"
CERT_DIR="/etc/letsencrypt/live/${STAGING_DOMAIN}"
FPM_DIR="/etc/alex-staging"
FPM_CONF="$FPM_DIR/php-fpm.conf"
FPM_SOCK="/run/php/alex-staging-fpm.sock"
SUPERVISOR_CONF="/etc/supervisor/conf.d/alex-staging.conf"
CRON_MARK="# alex-staging"

TOOLS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SUDO="sudo -n"

log() { echo "==> $*"; }
warn() { echo "==> WARN: $*" >&2; }
die() { echo "==> ERROR: $*" >&2; exit 1; }

guard_paths() {
  [[ "$STAGING_ROOT" == "/var/www/test.alexallergotest.ru" ]] || die "STAGING_ROOT изменён: $STAGING_ROOT"
  [[ "$(realpath -m "$STAGING_ROOT")" != "$(realpath -m "$PROD_ROOT")" ]] || die "STAGING_ROOT совпадает с продом"
}

need_sudo() {
  $SUDO true 2>/dev/null || die "нужен sudo без пароля для alexadmin (nginx, supervisor, mysql, certbot)"
}

# Значение ключа из .env без вывода на экран.
env_get() {
  local file="$1" key="$2"
  [[ -f "$file" ]] || return 0
  grep -E "^${key}=" "$file" | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//" | tr -d '\r'
}

# Заменить или дописать KEY=VALUE в staging .env, сохраняя права файла.
env_set() {
  local key="$1" value="$2" file="$STAGING_ROOT/.env" tmp
  tmp="$(mktemp)"
  awk -v k="$key" -v v="$value" 'BEGIN { done = 0 }
    $0 ~ "^" k "=" { if (!done) { print k "=" v; done = 1 } ; next }
    { print }
    END { if (!done) print k "=" v }' "$file" > "$tmp"
  cat "$tmp" > "$file"
  rm -f "$tmp"
}

prod_owner() { stat -c %U "$PROD_ROOT"; }

php_version() { php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;'; }

fpm_pool_user() {
  local conf="/etc/php/$(php_version)/fpm/pool.d/www.conf"
  local u=""
  [[ -f "$conf" ]] && u="$(grep -E '^\s*user\s*=' "$conf" | head -1 | sed 's/.*=\s*//')"
  echo "${u:-www-data}"
}

staging_mysql() { mysql --defaults-file="$DB_CNF" "$@"; }

table_exists() {
  [[ "$(staging_mysql -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${STAGING_DB}' AND table_name='$1'")" == "1" ]]
}

column_exists() {
  [[ "$(staging_mysql -N -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='${STAGING_DB}' AND table_name='$1' AND column_name='$2'")" == "1" ]]
}

random_secret() { openssl rand -base64 48 | tr -dc 'A-Za-z0-9' | head -c "${1:-24}"; }

# ---------------------------------------------------------------- inspect
inspect() {
  log "Кто и где"
  id; hostname; uptime
  df -h / | tail -1
  free -m | head -2
  log "sudo без пароля"
  if $SUDO true 2>/dev/null; then echo "yes"; $SUDO -l 2>/dev/null | sed -n '1,40p'; else echo "no"; fi
  log "Версии"
  php -v | head -1 || true
  ls /usr/sbin/php-fpm* 2>/dev/null || true
  ls /run/php 2>/dev/null || true
  nginx -v 2>&1 || true
  certbot --version 2>&1 || echo "certbot: нет"
  supervisorctl version 2>/dev/null || $SUDO supervisorctl version 2>/dev/null || echo "supervisor: нет доступа"
  mysql --version || true
  node -v 2>/dev/null || true; npm -v 2>/dev/null || true; composer --version 2>/dev/null | head -1 || true
  command -v htpasswd || echo "htpasswd: нет (используем openssl passwd)"
  log "nginx: сайты и ключевые директивы прода"
  ls -la /etc/nginx/sites-enabled/ 2>/dev/null || true
  for f in /etc/nginx/sites-enabled/*; do
    [[ -e "$f" ]] || continue
    echo "--- $f"
    grep -nE 'listen|server_name|root |fastcgi_pass|ssl_certificate|client_max_body_size|include |auth_basic|proxy_pass' "$f" || true
  done
  log "Let's Encrypt"
  $SUDO ls /etc/letsencrypt/live 2>/dev/null || echo "нет доступа или нет сертификатов"
  $SUDO ls /etc/letsencrypt/renewal 2>/dev/null || true
  systemctl list-timers 2>/dev/null | grep -i certbot || echo "certbot.timer не найден"
  log "supervisor"
  $SUDO supervisorctl status 2>/dev/null || supervisorctl status 2>/dev/null || true
  ls /etc/supervisor/conf.d 2>/dev/null || true
  log "php-fpm пула прода"
  grep -hE '^\s*(user|group|listen)\s*=' /etc/php/*/fpm/pool.d/*.conf 2>/dev/null || true
  grep -hE 'opcache\.(enable|validate_timestamps)' /etc/php/*/fpm/conf.d/*opcache* /etc/php/*/fpm/php.ini 2>/dev/null | grep -v '^;' || true
  log "MySQL"
  mysql -N -e 'SELECT CURRENT_USER(); SHOW GRANTS;' 2>&1 | sed -e 's/IDENTIFIED BY .*//' || true
  if $SUDO mysql -N -e 'SELECT 1' >/dev/null 2>&1; then echo "sudo mysql (root по сокету): yes"; else echo "sudo mysql: no"; fi
  mysql -N -e "SELECT schema_name FROM information_schema.schemata" 2>/dev/null || true
  log "cron alexadmin"
  crontab -l 2>/dev/null | grep -v '^#' || true
  log "Прод: владельцы, размеры"
  ls -ld /var/www /var/www/* 2>/dev/null || true
  stat -c '%U:%G %a %n' "$PROD_ROOT/storage" "$PROD_ROOT/storage/logs" "$PROD_ROOT/bootstrap/cache" 2>/dev/null || true
  du -sh "$PROD_ROOT/public/videos" "$PROD_ROOT/storage/app/public" 2>/dev/null || true
  log "Прод .env: имена ключей и несекретные значения"
  if [[ -f "$PROD_ROOT/.env" ]]; then
    grep -oE '^[A-Z0-9_]+' "$PROD_ROOT/.env" | tr '\n' ' '; echo
    for k in APP_ENV APP_URL APP_LOCALE APP_DOCTORS_HOST DOCTORS_PATH_PREVIEW DOCTORS_SUBDOMAIN_REDIRECT SESSION_DOMAIN SESSION_DRIVER SESSION_COOKIE QUEUE_CONNECTION CACHE_STORE MAIL_MAILER FILESYSTEM_DISK INERTIA_SSR_ENABLED INERTIA_SSR_URL DB_DATABASE ORDER_BRIDGE_ENABLED; do
      echo "$k=$(env_get "$PROD_ROOT/.env" "$k")"
    done
  fi
  log "Порты"
  ss -ltn 2>/dev/null | awk 'NR>1 {print $4}' | sort -u | tr '\n' ' '; echo
  log "Staging сейчас"
  ls -la "$STAGING_ROOT" 2>/dev/null | head -20 || echo "каталога нет"
  ls -la "$STAGING_HOME" 2>/dev/null || echo "~/staging нет"
  [[ -f "$NGINX_SITE" ]] && echo "nginx staging: есть" || echo "nginx staging: нет"
  if $SUDO test -d "$CERT_DIR" 2>/dev/null; then echo "сертификат staging: есть"; else echo "сертификат staging: нет"; fi
  log "DNS"
  getent hosts "$STAGING_DOMAIN" || echo "$STAGING_DOMAIN не резолвится"
  getent hosts "$STAGING_DOC_DOMAIN" || echo "$STAGING_DOC_DOMAIN не резолвится"
}

# ---------------------------------------------------------------- setup
setup_dirs() {
  local owner
  owner="$(prod_owner)"
  install -d -m 700 "$STAGING_HOME"
  if [[ ! -d "$STAGING_ROOT" ]]; then
    $SUDO install -d -o "$owner" -g www-data -m 775 "$STAGING_ROOT"
  fi
  mkdir -p "$STAGING_ROOT"/storage/{app/public,framework/{cache/data,sessions,views,testing},logs} "$STAGING_ROOT/bootstrap/cache" "$STAGING_ROOT/public"
  fix_permissions
  $SUDO install -d -m 755 "$ACME_ROOT"
}

fix_permissions() {
  # Пишут и php-fpm (www-data), и artisan под alexadmin: общая группа www-data, setgid на каталогах.
  $SUDO chgrp -R www-data "$STAGING_ROOT/storage" "$STAGING_ROOT/bootstrap/cache"
  $SUDO chmod -R ug+rwX "$STAGING_ROOT/storage" "$STAGING_ROOT/bootstrap/cache"
  $SUDO find "$STAGING_ROOT/storage" "$STAGING_ROOT/bootstrap/cache" -type d -exec chmod g+s {} +
}

setup_db() {
  if [[ -f "$DB_CNF" ]] && staging_mysql -e 'SELECT 1' >/dev/null 2>&1; then
    log "БД ${STAGING_DB}: доступ уже есть"
    return
  fi
  local pw
  pw="$(random_secret 32)"
  log "Создаю БД ${STAGING_DB} и пользователя ${STAGING_DB_USER}"
  $SUDO mysql <<SQL || die "не удалось создать БД через sudo mysql (нужен root MySQL по сокету)"
CREATE DATABASE IF NOT EXISTS \`${STAGING_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${STAGING_DB_USER}'@'localhost' IDENTIFIED BY '${pw}';
ALTER USER '${STAGING_DB_USER}'@'localhost' IDENTIFIED BY '${pw}';
GRANT ALL PRIVILEGES ON \`${STAGING_DB}\`.* TO '${STAGING_DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
  ( umask 077; printf '[client]\nuser=%s\npassword=%s\ndatabase=%s\nhost=127.0.0.1\n' "$STAGING_DB_USER" "$pw" "$STAGING_DB" > "$DB_CNF" )
  staging_mysql -e 'SELECT 1' >/dev/null
}

refresh_db() {
  guard_paths
  [[ -f "$DB_CNF" ]] || die "нет $DB_CNF, сначала setup"
  [[ -f "$HOME/.my.cnf" ]] || die "нет ~/.my.cnf для чтения прод-БД"
  local prod_db
  prod_db="$(env_get "$PROD_ROOT/.env" DB_DATABASE)"
  [[ -n "$prod_db" ]] || die "не нашёл DB_DATABASE прода"
  [[ "$prod_db" != "$STAGING_DB" ]] || die "прод-БД называется как staging"

  log "Пересоздаю ${STAGING_DB} и заливаю копию прода"
  staging_mysql -e "DROP DATABASE IF EXISTS \`${STAGING_DB}\`; CREATE DATABASE \`${STAGING_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  mysqldump --defaults-file="$HOME/.my.cnf" --single-transaction --quick --no-tablespaces --skip-triggers "$prod_db" \
    | staging_mysql "$STAGING_DB"

  anonymize_db
}

anonymize_db() {
  log "Обезличиваю копию"
  local admin_pw hash
  if [[ ! -s "$ADMIN_PASSWORD_FILE" ]]; then
    ( umask 077; random_secret 20 > "$ADMIN_PASSWORD_FILE" )
  fi
  admin_pw="$(cat "$ADMIN_PASSWORD_FILE")"
  hash="$(php -r 'echo password_hash(trim(fgets(STDIN)), PASSWORD_BCRYPT, ["cost" => 12]);' <<< "$admin_pw")"

  # Пароли всех учёток — один staging-пароль (~/staging/admin-password.txt), хеши прода сюда не попадают.
  staging_mysql -e "UPDATE users SET password = '${hash}', remember_token = NULL;"
  # Почта и телефоны: у админов почта остаётся (вход в админку), у остальных — заглушка.
  staging_mysql -e "UPDATE users SET email = CONCAT('user', id, '@staging.invalid') WHERE is_admin = 0;"
  column_exists users phone && staging_mysql -e "UPDATE users SET phone = NULL;"
  if column_exists users is_doctor; then
    local doc_cols="name = CONCAT('Врач ', id)"
    for c in slug bio credentials profile_url avatar education career_history; do
      if column_exists users "$c"; then
        case "$c" in
          slug) doc_cols="$doc_cols, slug = CONCAT('doctor-', id)" ;;
          *) doc_cols="$doc_cols, $c = NULL" ;;
        esac
      fi
    done
    staging_mysql -e "UPDATE users SET ${doc_cols} WHERE is_doctor = 1 AND is_admin = 0;"
  fi

  # Таблицы корзины и связки заказа: заявки обезличиваем, лабораторные данные пациентов не копируем.
  if table_exists orders; then
    staging_mysql -e "UPDATE orders SET customer_name = CONCAT('Тестовый клиент ', id), customer_phone = CONCAT('+7900', LPAD(id, 7, '0')), customer_email = IF(customer_email IS NULL, NULL, CONCAT('order', id, '@staging.invalid')), comment = NULL;"
  fi
  local t
  for t in $(staging_mysql -N -e "SELECT table_name FROM information_schema.tables WHERE table_schema='${STAGING_DB}' AND (table_name LIKE 'lab\\_%' OR table_name LIKE 'patient%')"); do
    staging_mysql -e "SET FOREIGN_KEY_CHECKS=0; TRUNCATE TABLE \`$t\`; SET FOREIGN_KEY_CHECKS=1;"
  done
  for t in sessions password_reset_tokens personal_access_tokens jobs job_batches failed_jobs cache cache_locks analytics_outbox; do
    table_exists "$t" && staging_mysql -e "SET FOREIGN_KEY_CHECKS=0; TRUNCATE TABLE \`$t\`; SET FOREIGN_KEY_CHECKS=1;"
  done
  table_exists blog_ratings && staging_mysql -e "UPDATE blog_ratings SET visitor_id = UUID();"

  verify_anonymized
}

# Контроль: в колонках *email* / *phone* не осталось реальных значений (кроме почты админов).
verify_anonymized() {
  log "Проверка обезличивания"
  local rows bad=0 t c n
  rows="$(staging_mysql -N -e "SELECT table_name, column_name FROM information_schema.columns WHERE table_schema='${STAGING_DB}' AND (column_name LIKE '%email%' OR column_name LIKE '%phone%') AND data_type IN ('varchar','text','char','mediumtext','longtext')")"
  while read -r t c; do
    [[ -n "$t" ]] || continue
    case "$t.$c" in
      users.email) n="$(staging_mysql -N -e "SELECT COUNT(*) FROM users WHERE is_admin = 0 AND email NOT LIKE '%@staging.invalid'")" ;;
      orders.customer_phone) n="$(staging_mysql -N -e "SELECT COUNT(*) FROM orders WHERE customer_phone NOT LIKE '+7900%'")" ;;
      *) n="$(staging_mysql -N -e "SELECT COUNT(*) FROM \`$t\` WHERE \`$c\` IS NOT NULL AND \`$c\` <> '' AND \`$c\` NOT LIKE '%@staging.invalid'")" ;;
    esac
    echo "$t.$c: осталось $n"
    [[ "$n" == "0" ]] || bad=1
  done <<< "$rows"
  echo "Админов (почта сохранена): $(staging_mysql -N -e 'SELECT COUNT(*) FROM users WHERE is_admin = 1')"
  [[ "$bad" == "0" ]] || warn "есть необезличенные значения выше: проверить таблицы (settings с почтой info@ — нормально)"
}

setup_files_from_prod() {
  local free_gb
  free_gb="$(df -BG --output=avail / | tail -1 | tr -dc '0-9')"
  (( free_gb >= 5 )) || die "на диске меньше 5 ГБ свободно (${free_gb} ГБ)"
  log "Копирую storage/app/public и public/videos с прода (только чтение прода)"
  rsync -a --delete "$PROD_ROOT/storage/app/public/" "$STAGING_ROOT/storage/app/public/"
  mkdir -p "$STAGING_ROOT/public/videos"
  rsync -a "$PROD_ROOT/public/videos/" "$STAGING_ROOT/public/videos/"
  # Файлы в public, которых нет в git (постеры и т.п.); файлы из git потом перезапишет deploy.
  rsync -a --ignore-existing \
    --exclude 'build/' --exclude 'hot' --exclude 'storage' --exclude 'videos/' --exclude 'index.php' \
    "$PROD_ROOT/public/" "$STAGING_ROOT/public/"
  fix_permissions
}

setup_env() {
  local env="$STAGING_ROOT/.env"
  if [[ ! -f "$env" ]]; then
    log "Создаю $env из несекретных ключей прода + staging-блок"
    ( umask 027
      # Из прода берём только несекретные настройки (локаль, флаги doctors и т.п.).
      grep -E '^[A-Z0-9_]+=' "$PROD_ROOT/.env" \
        | grep -vE '^(APP_KEY|APP_ENV|APP_DEBUG|APP_URL|APP_NAME|APP_DOCTORS_HOST|DB_|MAIL_|SESSION_DOMAIN|SESSION_COOKIE|SESSION_SECURE_COOKIE|REDIS_|AWS_|POSTMARK|RESEND|SLACK|GA4_|METRIKA_|ANALYTICS_|LEADS_|YOOKASSA|MEDMIS|DNKOM|ALFALAB|PAYMENTS|ORDER_BRIDGE|INERTIA_SSR|CACHE_PREFIX)' \
        | grep -viE '(PASSWORD|SECRET|TOKEN|_KEY=|API_KEY|PRIVATE)' > "$env" || true
      echo "" >> "$env"
      echo "# ---- staging (scripts/staging/staging.sh) ----" >> "$env"
      sed -e "s|__STAGING_DOMAIN__|${STAGING_DOMAIN}|g" -e "s|__STAGING_DOC_DOMAIN__|${STAGING_DOC_DOMAIN}|g" \
        "$TOOLS_DIR/env.staging" >> "$env"
    )
    env_set APP_KEY "base64:$(openssl rand -base64 32)"
    env_set DB_PASSWORD "$(grep -E '^password=' "$DB_CNF" | cut -d= -f2-)"
    $SUDO chgrp www-data "$env"
    chmod 640 "$env"
  fi
  enforce_env
}

# Ключи безопасности, которые перезаписываются при каждом setup/deploy: staging не шлёт в боевые сервисы.
enforce_env() {
  local env="$STAGING_ROOT/.env"
  [[ -f "$env" ]] || die "нет $env, сначала setup"
  env_set APP_ENV staging
  env_set APP_DEBUG false
  env_set APP_URL "https://${STAGING_DOMAIN}"
  env_set APP_DOCTORS_HOST "${STAGING_DOC_DOMAIN}"
  env_set DB_DATABASE "$STAGING_DB"
  env_set DB_USERNAME "$STAGING_DB_USER"
  env_set SESSION_DOMAIN null
  env_set SESSION_COOKIE alex2_staging_session
  env_set MAIL_MAILER log
  env_set LEADS_MAIL_TO ""
  env_set ANALYTICS_CLIENT_ENABLED false
  env_set GA4_MEASUREMENT_ID ""
  env_set GA4_MP_API_SECRET ""
  env_set METRIKA_COUNTER_ID ""
  env_set METRIKA_OAUTH_TOKEN ""
  env_set ANALYTICS_EXPORT_DOCTOR_APPOINTMENT false
  env_set ANALYTICS_EXPORT_TEST_ORDER false
  env_set INERTIA_SSR_ENABLED false
  check_integration_keys
}

# Интеграции на staging — только тестовые: секретный ключ ЮKassa test_*, адреса и логины не как в проде.
check_integration_keys() {
  local env="$STAGING_ROOT/.env" k v pv
  v="$(env_get "$env" YOOKASSA_SECRET_KEY)"
  if [[ -n "$v" && "$v" != test_* ]]; then
    env_set YOOKASSA_SECRET_KEY ""
    warn "YOOKASSA_SECRET_KEY не тестовый (нет префикса test_) — очищен"
  fi
  for k in YOOKASSA_SHOP_ID YOOKASSA_SECRET_KEY MEDMIS_BASE_URL MEDMIS_LOGIN MEDMIS_PASSWORD DNKOM_URL DNKOM_PASSWORD DNKOM_SENDER_PAID DNKOM_SENDER_UNPAID; do
    v="$(env_get "$env" "$k")"
    pv="$(env_get "$PROD_ROOT/.env" "$k")"
    if [[ -n "$v" && "$v" == "$pv" ]]; then
      env_set "$k" ""
      warn "$k совпадает с продом — очищен"
    fi
  done
}

# Секреты тестовых стендов приходят из GitHub secrets STAGING_* через stdin строками KEY=VALUE.
apply_secrets() {
  guard_paths
  [[ -f "$STAGING_ROOT/.env" ]] || die "нет .env staging, сначала setup"
  local line key value n=0
  while IFS= read -r line || [[ -n "$line" ]]; do
    key="${line%%=*}"; value="${line#*=}"
    [[ -n "$value" ]] || continue
    case "$key" in
      YOOKASSA_SHOP_ID|YOOKASSA_SECRET_KEY|MEDMIS_*|DNKOM_*) env_set "$key" "$value"; n=$((n + 1)) ;;
      *) warn "ключ $key не из списка, пропущен" ;;
    esac
  done
  check_integration_keys
  log "Записано ключей тестовых стендов: $n"
}

setup_basic_auth() {
  if [[ ! -s "$BASIC_AUTH_FILE" ]] || ! $SUDO test -s "$HTPASSWD"; then
    local user="alex" pw hash tmp
    pw="$(random_secret 20)"
    ( umask 077; printf 'login: %s\npassword: %s\n' "$user" "$pw" > "$BASIC_AUTH_FILE" )
    hash="$(openssl passwd -apr1 -stdin <<< "$pw")"
    tmp="$(mktemp)"
    printf '%s:%s\n' "$user" "$hash" > "$tmp"
    $SUDO install -o root -g www-data -m 640 "$tmp" "$HTPASSWD"
    rm -f "$tmp"
    log "Basic Auth создан, логин и пароль: $BASIC_AUTH_FILE"
  else
    log "Basic Auth уже есть: $BASIC_AUTH_FILE"
  fi
}

setup_fpm_supervisor() {
  local ver pool_user tmp
  ver="$(php_version)"
  [[ -x "/usr/sbin/php-fpm${ver}" ]] || die "нет /usr/sbin/php-fpm${ver}"
  command -v supervisorctl >/dev/null || die "supervisor не установлен"
  pool_user="$(fpm_pool_user)"

  # Отдельный мастер php-fpm под supervisor: свой сокет, opcache сам видит новые файлы,
  # деплой staging не перезагружает php-fpm прода.
  tmp="$(mktemp)"
  sed -e "s|__POOL_USER__|${pool_user}|g" -e "s|__FPM_SOCK__|${FPM_SOCK}|g" "$TOOLS_DIR/php-fpm.conf" > "$tmp"
  $SUDO install -d -m 755 "$FPM_DIR"
  $SUDO install -m 644 "$tmp" "$FPM_CONF"
  $SUDO "/usr/sbin/php-fpm${ver}" -t -y "$FPM_CONF"

  sed -e "s|__PHP_FPM__|/usr/sbin/php-fpm${ver}|g" -e "s|__FPM_CONF__|${FPM_CONF}|g" \
      -e "s|__STAGING_ROOT__|${STAGING_ROOT}|g" -e "s|__POOL_USER__|${pool_user}|g" \
      "$TOOLS_DIR/supervisor.conf" > "$tmp"
  $SUDO install -m 644 "$tmp" "$SUPERVISOR_CONF"
  rm -f "$tmp"
  # reread/update трогают только изменённые группы: программы прода (SSR и т.д.) не перезапускаются.
  $SUDO supervisorctl reread
  $SUDO supervisorctl update alex-staging
  sleep 2
  $SUDO supervisorctl status 'alex-staging:*' || true
}

setup_cron() {
  local line="* * * * * cd ${STAGING_ROOT} && umask 002 && [ -f artisan ] && php artisan schedule:run >> /dev/null 2>&1 ${CRON_MARK}"
  if crontab -l 2>/dev/null | grep -qF "$CRON_MARK"; then
    log "cron staging уже есть"
  else
    ( crontab -l 2>/dev/null; echo "$line" ) | crontab -
    log "cron staging добавлен"
  fi
}

render_nginx() {
  local tmp body http_body ipv6="" ssl="no"
  grep -qsE 'listen\s+\[::\]' /etc/nginx/sites-enabled/* && ipv6="yes"
  $SUDO test -f "$CERT_DIR/fullchain.pem" && ssl="yes"

  body="$(sed -e "s|__STAGING_ROOT__|${STAGING_ROOT}|g" -e "s|__FPM_SOCK__|${FPM_SOCK}|g" -e "s|__HTPASSWD__|${HTPASSWD}|g" "$TOOLS_DIR/nginx-app.conf")"
  if [[ "$ssl" == "yes" ]]; then
    http_body='    location / { return 301 https://$host$request_uri; }'
  else
    http_body="$body"
  fi

  tmp="$(mktemp)"
  {
    echo "# Управляется scripts/staging/staging.sh — на сервере руками не править."
    echo "server {"
    echo "    listen 80;"
    [[ "$ipv6" == "yes" ]] && echo "    listen [::]:80;"
    echo "    server_name ${STAGING_DOMAIN} ${STAGING_DOC_DOMAIN};"
    echo "    location ^~ /.well-known/acme-challenge/ { auth_basic off; root ${ACME_ROOT}; default_type text/plain; }"
    echo "$http_body"
    echo "}"
    if [[ "$ssl" == "yes" ]]; then
      echo "server {"
      echo "    listen 443 ssl http2;"
      [[ "$ipv6" == "yes" ]] && echo "    listen [::]:443 ssl http2;"
      echo "    server_name ${STAGING_DOMAIN} ${STAGING_DOC_DOMAIN};"
      echo "    ssl_certificate ${CERT_DIR}/fullchain.pem;"
      echo "    ssl_certificate_key ${CERT_DIR}/privkey.pem;"
      echo "    ssl_protocols TLSv1.2 TLSv1.3;"
      echo "    location ^~ /.well-known/acme-challenge/ { auth_basic off; root ${ACME_ROOT}; default_type text/plain; }"
      echo "$body"
      echo "}"
    fi
  } > "$tmp"

  local backup=""
  if $SUDO test -f "$NGINX_SITE"; then
    backup="$(mktemp)"
    $SUDO cat "$NGINX_SITE" > "$backup"
  fi
  $SUDO install -m 644 "$tmp" "$NGINX_SITE"
  $SUDO ln -sfn "$NGINX_SITE" "$NGINX_LINK"
  rm -f "$tmp"
  if ! $SUDO nginx -t; then
    if [[ -n "$backup" ]]; then
      $SUDO install -m 644 "$backup" "$NGINX_SITE"
    else
      $SUDO rm -f "$NGINX_LINK" "$NGINX_SITE"
    fi
    die "nginx -t не прошёл, конфиг staging откатан, reload не делал"
  fi
  [[ -n "$backup" ]] && rm -f "$backup"
  $SUDO systemctl reload nginx
  log "nginx перезагружен (reload), staging: ssl=${ssl}"
}

setup() {
  guard_paths
  need_sudo
  setup_dirs
  setup_db
  if [[ "$(staging_mysql -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${STAGING_DB}'")" == "0" ]]; then
    refresh_db
  else
    log "БД ${STAGING_DB} уже с данными; пересоздать копию — режим refresh-db"
  fi
  setup_files_from_prod
  setup_env
  setup_basic_auth
  setup_fpm_supervisor
  setup_cron
  render_nginx
  log "setup готов. Пароли: $BASIC_AUTH_FILE (Basic Auth), $ADMIN_PASSWORD_FILE (вход в админку staging)"
}

# ---------------------------------------------------------------- certs
certs() {
  guard_paths
  need_sudo
  local d ip
  for d in "$STAGING_DOMAIN" "$STAGING_DOC_DOMAIN"; do
    ip="$(getent ahostsv4 "$d" | awk 'NR==1 {print $1}')"
    [[ "$ip" == "$SERVER_IP" ]] || die "$d резолвится в '${ip:-ничего}', ждём $SERVER_IP — DNS ещё не обновился"
  done
  command -v certbot >/dev/null || die "certbot не установлен"
  render_nginx
  $SUDO certbot certonly --webroot -w "$ACME_ROOT" \
    -d "$STAGING_DOMAIN" -d "$STAGING_DOC_DOMAIN" --cert-name "$STAGING_DOMAIN" \
    --non-interactive --agree-tos --keep-until-expiring \
    --deploy-hook "systemctl reload nginx"
  render_nginx
}

# ---------------------------------------------------------------- deploy
deploy() {
  guard_paths
  need_sudo
  cd "$STAGING_ROOT"
  [[ -f .env ]] || die "нет .env staging, сначала setup"
  [[ -f artisan ]] || die "код не залит"
  grep -q "client_enabled" resources/views/app.blade.php \
    || die "в этой ветке нет флага ANALYTICS_CLIENT_ENABLED — Метрика/GA загрузились бы на staging; деплойте PR как pull/N (merge с production)"
  enforce_env
  if [[ -n "${ORDER_BRIDGE_ENABLED:-}" ]]; then
    env_set ORDER_BRIDGE_ENABLED "$ORDER_BRIDGE_ENABLED"
  fi

  umask 002
  rm -f public/hot
  composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress
  if [[ -f package-lock.json ]]; then npm ci --legacy-peer-deps; else npm install --legacy-peer-deps; fi
  npm run build
  rm -f public/hot
  [[ -L public/storage ]] || php artisan storage:link
  php artisan migrate --force
  php artisan optimize:clear
  php artisan config:cache
  php artisan route:cache || php artisan route:clear
  php artisan view:cache
  fix_permissions
  php artisan queue:restart || true
  $SUDO supervisorctl restart 'alex-staging:*'

  printf 'ref=%s\nsha=%s\ndeployed_at=%s\norder_bridge=%s\n' "${STAGING_REF:-?}" "${STAGING_SHA:-?}" "$(date -Is)" \
    "$(env_get .env ORDER_BRIDGE_ENABLED)" > storage/app/staging-release.txt
  log "Выложено: ${STAGING_REF:-?} @ ${STAGING_SHA:-?}"
  smoke
}

smoke() {
  local auth scheme="http" port=80 d code html fail=0
  auth="$(awk '/^login:/ {u=$2} /^password:/ {p=$2} END {print u ":" p}' "$BASIC_AUTH_FILE")"
  $SUDO test -f "$CERT_DIR/fullchain.pem" && { scheme="https"; port=443; }
  for d in "$STAGING_DOMAIN" "$STAGING_DOC_DOMAIN"; do
    code="$(curl -s -o /dev/null -w '%{http_code}' --resolve "$d:$port:127.0.0.1" "$scheme://$d/")"
    echo "$d без пароля: $code (ждём 401)"; [[ "$code" == "401" ]] || fail=1
    html="$(curl -s -K - --resolve "$d:$port:127.0.0.1" "$scheme://$d/" <<< "user = \"$auth\"")"
    code="$(curl -s -o /dev/null -w '%{http_code}' -K - --resolve "$d:$port:127.0.0.1" "$scheme://$d/" <<< "user = \"$auth\"")"
    echo "$d с паролем: $code"; [[ "$code" == "200" ]] || fail=1
    if grep -q 'ANALYTICS_ENABLED = false' <<< "$html"; then echo "$d: аналитика выключена"; else echo "$d: НЕ найден ANALYTICS_ENABLED = false"; fail=1; fi
    curl -s --resolve "$d:$port:127.0.0.1" "$scheme://$d/robots.txt" | grep -qx 'Disallow: /' && echo "$d: robots Disallow: /" || { echo "$d: robots не закрыт"; fail=1; }
    curl -sI -K - --resolve "$d:$port:127.0.0.1" "$scheme://$d/" <<< "user = \"$auth\"" | grep -qi '^x-robots-tag: noindex' && echo "$d: X-Robots-Tag noindex" || { echo "$d: нет X-Robots-Tag"; fail=1; }
  done
  html="$(curl -s -K - --resolve "$STAGING_DOC_DOMAIN:$port:127.0.0.1" "$scheme://$STAGING_DOC_DOMAIN/" <<< "user = \"$auth\"")"
  grep -q '&quot;isDoctorsHost&quot;:true\|"isDoctorsHost":true' <<< "$html" && echo "doc: врачебный сайт" || { echo "doc: не определился как врачебный"; fail=1; }
  [[ "$fail" == "0" ]] || die "smoke staging не прошёл"
  log "smoke staging OK"
}

case "$MODE" in
  inspect) inspect ;;
  setup) setup ;;
  refresh-db) need_sudo; refresh_db; [[ -f "$STAGING_ROOT/artisan" ]] && (cd "$STAGING_ROOT" && php artisan migrate --force && php artisan optimize:clear && php artisan config:cache) || true ;;
  apply-secrets) apply_secrets ;;
  certs) certs ;;
  deploy) deploy ;;
  smoke) smoke ;;
  *) die "режим: inspect | setup | refresh-db | apply-secrets | certs | deploy | smoke" ;;
esac
