# Правила работы агента (Alex-2)

Зафиксировано 2026-09-16. **Git only.** Live на VPS не меняли в ходе настройки CI/CD.

## Source of truth в git

| Ветка | Роль |
|-------|------|
| **`production`** | **Default branch** репозитория и цель всех рабочих PR. Снимок live **1.0.118** + UI после 14.09. Branch protection: required check `test-and-build`. |
| `cursor/prod-baseline-20260916-2397` | Старое длинное имя того же tip (после merge #27). Не использовать как base новых PR. |
| `main` | Пустой исторический скелет. **Не мержить сюда. Не считать продом. Не открывать PR в `main`.** |

Новые правки: ветка `cursor/<имя>-2397` **от `production`** → PR с base **`production`**. Старый `main` не использовать.

## Деплой

- На VPS **нет git**. Не `git pull` / `git reset` на сервере.
- **Не деплоить** без явной команды Виталия («залей», «деплой», confirm).
- **Не запускать** workflow `Deploy to VPS (manual)` «на всякий случай».
- Не делать scp / rsync / `npm run build` на сервере без этой команды.
- CD: только `workflow_dispatch`. **Запрещено** включать `on: push` для Deploy.
- После OK Виталия: либо Actions Deploy + строка `I_CONFIRM_PRODUCTION_DEPLOY`, либо точечный scp + бэкап `public/build` + сборка **на VPS** + **обязательный bump `SITE_VERSION`** (live сейчас **1.0.118** → следующий **1.0.119+**).
- Rebuild без bump не делает `SITE_VERSION` правдой (на live уже был такой случай).
- Перед выкладкой smoke: patient + `doc.*` + `/blog` (`/up` на обоих хостах).

Подробности ручного процесса и секретов: [DEPLOY.md](./DEPLOY.md).

## Никогда

- `.env` / `.env.production`
- `storage/`, `public/storage`
- `public/videos`
- полный слепой rsync
- `migrate --force` без явной просьбы
- правки nginx
- ломать статьи, ссылки, SEO
- merge в старый `main`, чтобы «включить деплой»

## CI

- Required check на **`production`**: `test-and-build` (Unit + `npm run build` на GitHub).
- `ci.yml` `on.push.branches`: `production`, плюс baseline и `sync-prod` (наследие).
- Feature-тесты Breeze в CI не гоняются.
- Secrets и environment `production` уже заведены (имена `ALEX_*`, healthchecks). Значения в git не писать.

## Cloud Agent

Окружение запуска: **Alex-2** (`ALEX_APP_ROOT`, `ALEX_SSH_HOST`, `ALEXADMIN_SSH_PRIVATE_KEY`). Ключи не логировать и не коммитить.
