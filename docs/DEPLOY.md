# Deploy (Alex-2 / Immunotech VPS)

Рабочие PR → base **`production`**. Правила агента: [AGENT_WORKFLOW.md](./AGENT_WORKFLOW.md).

На VPS **нет git**. Не `git pull` / `git reset` на сервере.

## Кто деплоит

| Кто | Когда |
|-----|--------|
| **Cloud Agent (Alex-2 env)** | **Основной путь.** По команде Виталия «залей» после merge в `production`. SSH/rsync с VM агента. |
| **Человек в GitHub UI** | Запасной: workflow `Deploy to VPS (manual)` + `I_CONFIRM_PRODUCTION_DEPLOY`. |

Агент **не** должен полагаться на `workflow_dispatch` (часто **403** у integration token). Не просить жать Actions, если SSH с агента работает.

## Cloud Agent secrets (environment Alex-2)

Имена (значения только в Cursor / GitHub Secrets, не в git):

| Env | Назначение |
|-----|------------|
| `ALEXADMIN_SSH_PRIVATE_KEY` | OpenSSH ключ для `alexadmin` |
| `ALEX_SSH_HOST` | IP/host VPS |
| `ALEX_SSH_USER` | `alexadmin` |
| `ALEX_APP_ROOT` | абсолютный путь app root на VPS (см. секрет в Alex-2) |

Legacy `SSH_*` на VM могут указывать на `root` — **для деплоя использовать `alexadmin`**.

На VM агента: `~/.ssh/alexadmin`, `chmod 600`. Нужен `rsync` (`apt install rsync` если отсутствует).

## Жёсткий порядок релиза

1. Код **и** bump `resources/js/siteVersion.js` — **один PR** → `production`.
2. CI green → merge.
3. Только потом деплой по «залей».
4. Smoke: live `siteVersion-*.js` = версия из PR.

Checklist:

- [ ] SITE_VERSION bumped in same PR as code
- [ ] CI green
- [ ] merged to production
- [ ] deploy via agent SSH
- [ ] live chunk matches
- [ ] `.env` / `storage/` / `public/videos` не тронуты
- [ ] `migrate --force` только если в релизе есть миграции

## Agent deploy steps (canonical)

```bash
# 1) sync git locally
git fetch origin production && git checkout production && git pull origin production

# 2) SSH smoke (no key in logs)
ssh -i ~/.ssh/alexadmin -o BatchMode=yes alexadmin@$ALEX_SSH_HOST \
  'echo OK; test -d '"$ALEX_APP_ROOT"' && echo APP_OK'

# 3) backup on VPS
ssh ... "cd $ALEX_APP_ROOT && bash scripts/deploy-vps.sh backup"
# или: ~/backups/alexallergotest.ru/<UTC-stamp>/ (build, siteVersion.js, manifest.json)

# 4) rsync (from repo root on agent VM)
# Без --delete. Исключение public/storage без хвостового слэша.
# --filter 'P public/storage' дополнительно запрещает удалить симлинк.
rsync -az \
  --filter 'P public/storage' \
  --exclude '.git/' --exclude '.github/' --exclude 'node_modules/' --exclude 'vendor/' \
  --exclude '.env' --exclude '.env.*' --exclude 'storage/' --exclude 'bootstrap/cache/' \
  --exclude 'public/build/' --exclude 'public/hot/' --exclude 'public/storage' \
  --exclude 'public/videos/' --exclude 'tests/' --exclude 'phpunit.xml' \
  --exclude '.phpunit.cache/' --exclude 'docs/' \
  -e "ssh -i ~/.ssh/alexadmin -o BatchMode=yes" \
  ./ alexadmin@$ALEX_SSH_HOST:$ALEX_APP_ROOT/

# 5) build + migrate on VPS
ssh ... "cd $ALEX_APP_ROOT && RUN_MIGRATIONS=true bash scripts/deploy-vps.sh apply"
# при ошибке route:cache (duplicate route names): php artisan route:clear && config:cache && view:cache
```

`RESTART_SERVICES=true` — сигнал перезапустить php-fpm / SSR на VPS вручную или через ваш PM.

## Iron-аудит VPS ↔ git (2026-09-16)

- 403 файла совпали с `production`.
- Испорченный path на VPS: `database/migrations/2026_08_17_134746_change_value_column_in_settings_table.php` — деплой из git **исправляет**.
- Вне скоупа: `.env`, `storage/`, `public/videos`, `public/build` (до `npm run build` на сервере).

## GitHub Actions (fallback)

| Workflow | Когда | VPS |
|----------|--------|-----|
| `CI` | PR / push `production` | **None** |
| `Deploy to VPS (manual)` | Run workflow + confirm string | rsync + build on VPS |

Секреты GitHub environment **`production`**: те же имена `ALEX_*`, `HEALTHCHECK_URL_PATIENT`, `HEALTHCHECK_URL_DOCTOR`.

**Запрещено** включать `on: push` для Deploy.

## Запреты

- overwrite `.env*`, `storage/`, `public/videos`
- blind full-tree rsync без excludes
- `migrate --force` без нужды
- rebuild без bump `SITE_VERSION` в том же PR
