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
7. После деплоя smoke входит в `scripts/deploy-vps.sh apply`: главные обоих доменов (пациентский `APP_URL` и `doc.`) отдают 200 и живую `SITE_VERSION`, плюс по одному реальному файлу из `storage/app/public/{allergens,blog,authors}` по `/storage/...` (200/206, не HTML). Если нет — скрипт делает `storage:link` и проверяет ещё раз, иначе падает.

Checklist:

- [ ] SITE_VERSION bumped in same PR as code
- [ ] CI green
- [ ] merged to production
- [ ] then deploy (по «залей»)
- [ ] live chunk matches
- [ ] apply smoke: главные 200 + версия, `/storage` 200/206 не-HTML на обоих доменах

## Деплой (основной путь — cloud-агент по SSH)

**Все production-деплои выполняет этот cloud-агент** после команды «залей». Не просить Виталия вручную жать GitHub Actions, если SSH с VM работает.

1. `git fetch` / sync с `origin/production`.
2. Проверка SSH: `alexadmin@$ALEX_SSH_HOST` (BatchMode), каталог `$ALEX_APP_ROOT`.
3. **Бэкап** на VPS: `bash scripts/deploy-vps.sh backup` (если есть) или `~/backups/alexallergotest.ru/<UTC-stamp>/` (`public/build`, `siteVersion.js`, `manifest.json`).
4. **Rsync/scp** с VM агента (excludes как в [DEPLOY.md](./DEPLOY.md)): не трогать `.env`, `storage/`, `public/videos`, `public/build` до сборки на сервере.
5. На VPS: `RUN_MIGRATIONS=true` (только если релиз с миграциями) и `bash scripts/deploy-vps.sh apply` → `composer install --no-dev`, `npm ci`, `npm run build`, `migrate --force` при флаге, `optimize:clear`, `config:cache`, `view:cache`. **`route:cache`** — только если нет дубликатов имён маршрутов; иначе `route:clear`.
6. **Restart** php-fpm / Inertia SSR — по необходимости (существующий process manager на VPS); флаг `RESTART_SERVICES=true` в скрипте — напоминание оператору.
7. Smoke делает сам `apply`: главные (200 + `SITE_VERSION`) и `/storage` (аллерген, блог, автор) на обоих доменах. Дополнительно при ручной проверке: `/admin/doctor-videos`.

**Запасной путь:** GitHub Actions `Deploy to VPS (manual)` — для человека в UI. Агент **не полагается** на `workflow_dispatch` (у integration token часто **403**).

Подробности: [DEPLOY.md](./DEPLOY.md).

## Staging (test.alexallergotest.ru, doc.test.alexallergotest.ru)

Всё новое с внешними интеграциями (ЮKassa, Medmis, ДНКОМ, АльфаЛАБ) сначала проверяется на staging. Подробности, пути и отличия от прода: [STAGING.md](./STAGING.md).

1. PR открыт в `production`, CI зелёный.
2. Выкладка PR на staging **до мержа**: Actions → «Staging (manual)», `action=deploy`, `ref=pull/<номер>`, `confirm=I_CONFIRM_STAGING`; флаги интеграций — через `order_bridge` и т.п. Команда «залей» для staging не нужна, это не прод.
3. Проверка на staging (Basic Auth, логин и пароль — в `~/staging/basic-auth.txt` на VPS), отчёт Виталию со ссылкой.
4. Дальше обычный порядок: merge → «залей» → прод.

Правила staging:
- На staging — только тестовые ключи и стенды (GitHub secrets `STAGING_*`). Боевые ключи ЮKassa, Medmis, ДНКОМ, Метрики и GA4 туда не попадают; скрипт сам очищает ключи, совпадающие с продом.
- Почта только в лог, аналитика выключена, поисковикам закрыто (Basic Auth, noindex, robots `Disallow: /`).
- БД staging — обезличенная копия прода (`action=refresh-db`). Реальные персональные данные туда не заносить.
- Workflow staging не трогает прод: свой каталог, БД, php-fpm и supervisor-группа `alex-staging`; общее — только `nginx reload`.
- Пароли staging в чат и репозиторий не писать, только путь к файлу на сервере.

## Никогда

- `.env` / `.env.production`
- `storage/`, `public/storage`
- `public/videos`
- полный слепой rsync без excludes
- `rsync --delete`. Если rsync всё же с `--delete`, обязательны `--exclude 'public/storage'` (без хвостового слэша) и `--filter 'P public/storage'`, иначе симлинк удаляется и `/storage/*` отдаёт 404
- `migrate --force` без явной просьбы / без миграций в релизе
- правки nginx прода (конфиг staging меняет только `scripts/staging/staging.sh`)
- ломать статьи, ссылки, SEO
- merge в `main`

## CI

- Required check на **`production`**: `test-and-build`.
- Feature PHPUnit (Breeze) в CI не гоняются.
