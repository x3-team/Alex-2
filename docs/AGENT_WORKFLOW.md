# Правила работы агента (Alex-2)

Зафиксировано 2026-09-17. **Git only** на VPS; выкладка — **SSH с VM cloud-агента**.

## Source of truth в git

| Ветка | Роль |
|-------|------|
| **`production`** | **Default branch** и цель всех рабочих PR. Branch protection: required check `test-and-build`. |
| `cursor/prod-baseline-20260916-2397` | Старое имя baseline. Не использовать как base новых PR. |
| `main` | Пустой исторический скелет. **Не мержить сюда. Не открывать PR в `main`.** |

Новые правки: ветка `cursor/<имя>-2397` **от `production`** → PR с base **`production`**.

## Cloud Agent environment (обязательно)

- Агент **всегда** работает в linked environment **Alex-2** (`7677cce2-a0c2-11f1-b532-320a589b8025`). Без него не подхватятся секреты деплоя.
- Секреты (имена): `ALEXADMIN_SSH_PRIVATE_KEY`, `ALEX_SSH_HOST`, `ALEX_SSH_USER` (`alexadmin`), `ALEX_APP_ROOT` (абсолютный корень приложения на VPS). Legacy `SSH_*` — не использовать для деплоя (часто `root`).
- Ключ нормализовать на VM: `~/.ssh/alexadmin`, `chmod 600`, newline в конце. **Не логировать ключ.**

## Жёсткий порядок релиза

1. Правки **и** bump `SITE_VERSION` — **в одном PR** в `production`.
2. Дождаться зелёного CI.
3. Merge в `production`.
4. **Только потом** деплой на VPS по явной команде Виталия («залей», «деплой»).
5. **Запрещено:** деплоить, а потом отдельным PR догонять `siteVersion.js`.
6. **Запрещено:** `git push` напрямую в `production`.
7. После деплоя smoke: live `siteVersion-*.js` == значение из смерженного PR.

Checklist:

- [ ] SITE_VERSION bumped in same PR as code
- [ ] CI green
- [ ] merged to production
- [ ] then deploy (по «залей»)
- [ ] live chunk matches

## Деплой (основной путь — cloud-агент по SSH)

**Все production-деплои выполняет этот cloud-агент** после команды «залей». Не просить Виталия вручную жать GitHub Actions, если SSH с VM работает.

1. `git fetch` / sync с `origin/production`.
2. Проверка SSH: `alexadmin@$ALEX_SSH_HOST` (BatchMode), каталог `$ALEX_APP_ROOT`.
3. **Бэкап** на VPS: `bash scripts/deploy-vps.sh backup` (если есть) или `~/backups/alexallergotest.ru/<UTC-stamp>/` (`public/build`, `siteVersion.js`, `manifest.json`).
4. **Rsync/scp** с VM агента (excludes как в [DEPLOY.md](./DEPLOY.md)): не трогать `.env`, `storage/`, `public/videos`, `public/build` до сборки на сервере.
5. На VPS: `RUN_MIGRATIONS=true` (только если релиз с миграциями) и `bash scripts/deploy-vps.sh apply` → `composer install --no-dev`, `npm ci`, `npm run build`, `migrate --force` при флаге, `optimize:clear`, `config:cache`, `view:cache`. **`route:cache`** — только если нет дубликатов имён маршрутов; иначе `route:clear`.
6. **Restart** php-fpm / Inertia SSR — по необходимости (существующий process manager на VPS); флаг `RESTART_SERVICES=true` в скрипте — напоминание оператору.
7. Smoke: patient + doctor (`/up` или главные), `/admin/doctor-videos`, live chunk `SITE_VERSION`.

**Запасной путь:** GitHub Actions `Deploy to VPS (manual)` — для человека в UI. Агент **не полагается** на `workflow_dispatch` (у integration token часто **403**).

Подробности: [DEPLOY.md](./DEPLOY.md).

## Никогда

- `.env` / `.env.production`
- `storage/`, `public/storage`
- `public/videos`
- полный слепой rsync без excludes
- `migrate --force` без явной просьбы / без миграций в релизе
- правки nginx
- ломать статьи, ссылки, SEO
- merge в `main`

## CI

- Required check на **`production`**: `test-and-build`.
- Feature PHPUnit (Breeze) в CI не гоняются.
