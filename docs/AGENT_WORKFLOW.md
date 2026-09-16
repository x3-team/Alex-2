# Правила работы агента (Alex-2)

Зафиксировано 2026-09-16. **Git only.** Live на VPS не меняли в ходе настройки CI/CD.

## Source of truth в git

| Ветка | Роль |
|-------|------|
| **`production`** | **Default branch** репозитория и цель всех рабочих PR. Снимок live **1.0.118** + UI после 14.09. Branch protection: required check `test-and-build`. |
| `cursor/prod-baseline-20260916-2397` | Старое длинное имя того же tip (после merge #27). Не использовать как base новых PR. |
| `main` | Пустой исторический скелет. **Не мержить сюда. Не считать продом. Не открывать PR в `main`.** |

Новые правки: ветка `cursor/<имя>-2397` **от `production`** → PR с base **`production`**. Старый `main` не использовать.

## Iron-аудит VPS ↔ git (2026-09-16)

- Полная сверка исходников VPS ↔ git `production` (нормализация CRLF): **403 файла совпали**.
- Единственный реальный path-diff: на VPS файл `database/migrations/2026_08_17_134746_change_value_column_in_settings_table.php` **испорчен** (лежит копия `DoctorAppointmentMail` — старая коллизия scp). Правильный Mail на месте: `app/Mail/DoctorAppointmentMail.php` (= git). В git по пути миграции — **правильная миграция**.
- Вывод: деплой из `production` **исправит** этот битый файл, не затрёт живой код. Не считать это «расхождением фич».
- `bootstrap/ssr/*` и sqlite на сервере могут отличаться (сборка/runtime) — ок.
- Вне скоупа: `.env`, `storage/`, `public/videos`, `public/build`.

## Жёсткий порядок релиза

После #30/#31 агент задеплоил на VPS, а bump `SITE_VERSION` довёл в git отдельным PR — потому что прямой push в `production` заблокирован. **Так больше нельзя.**

1. Правки **и** bump `SITE_VERSION` — **в одном PR** в `production` (сейчас live **1.0.120** → следующий релиз **1.0.121+**).
2. Дождаться зелёного CI.
3. Merge в `production`.
4. **Только потом** деплой на VPS (по явной команде Виталия «залей»): scp/rsync + build + optimize:clear.
5. **Запрещено:** деплоить, а потом отдельным PR догонять `siteVersion.js`.
6. **Запрещено:** пытаться `git push` напрямую в `production` (branch protection) — версию всегда класть в feature-PR.
7. После деплоя smoke: live `siteVersion-*.js` == значение из смерженного PR.

Checklist:

- [ ] SITE_VERSION bumped in same PR as code
- [ ] CI green
- [ ] merged to production
- [ ] then deploy
- [ ] live chunk matches

## Деплой

- На VPS **нет git**. Не `git pull` / `git reset` на сервере.
- **Не деплоить** без явной команды Виталия («залей», «деплой», confirm).
- **Не запускать** workflow `Deploy to VPS (manual)` «на всякий случай».
- Не делать scp / rsync / `npm run build` на сервере без этой команды.
- CD: только `workflow_dispatch`. **Запрещено** включать `on: push` для Deploy.
- **Каждый** деплой на прод (Actions Deploy или ручной scp+build) → bump `SITE_VERSION` уже должен быть **в том же смерженном PR**, что и код (`resources/js/siteVersion.js`; live сейчас **1.0.120** → следующий **1.0.121+**), даже для одной строки CSS/видео. Скрипт **не** бампит сам.
- Rebuild без bump **запрещён** как завершённый релиз. Не считать `SITE_VERSION` правдой, если сборка была без bump.
- После OK Виталия: либо Actions Deploy + строка `I_CONFIRM_PRODUCTION_DEPLOY`, либо точечный scp + бэкап `public/build` + сборка **на VPS**.
- Перед выкладкой smoke: patient + `doc.*` + `/blog`; live `siteVersion-*.js` == значение из смерженного PR.

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
