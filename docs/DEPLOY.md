# Deploy (Alex-2 / Immunotech VPS)

Рабочие PR → base **`production`** (это default branch). Правила: [AGENT_WORKFLOW.md](./AGENT_WORKFLOW.md).

`cursor/prod-baseline-20260916-2397` — то же содержимое после #27, не цель новых PR. `main` — пустой скелет, **не использовать**.

There is **no git checkout on the VPS**. Do not `git pull` or `git reset` on the server.

## Iron-аудит VPS ↔ git (2026-09-16)

- Полная сверка исходников VPS ↔ git `production` (нормализация CRLF): **403 файла совпали**.
- Единственный реальный path-diff: на VPS файл `database/migrations/2026_08_17_134746_change_value_column_in_settings_table.php` **испорчен** (лежит копия `DoctorAppointmentMail` — старая коллизия scp). Правильный Mail на месте: `app/Mail/DoctorAppointmentMail.php` (= git). В git по пути миграции — **правильная миграция**.
- Вывод: деплой из `production` **исправит** этот битый файл, не затрёт живой код. Не считать это «расхождением фич».
- `bootstrap/ssr/*` и sqlite на сервере могут отличаться (сборка/runtime) — ок.
- Вне скоупа: `.env`, `storage/`, `public/videos`, `public/build`.

## SITE_VERSION (жёстко)

**Каждый** деплой на прод (Actions Deploy или ручной scp+build) обязан поднять `resources/js/siteVersion.js` в том же релизе. Сейчас live **1.0.118** → следующий **1.0.119+**, даже для одной строки CSS или видео.

- Rebuild без bump **запрещён** как завершённый релиз.
- Скрипт на сервере **не** бампит версию сам (operator must bump in the release commit).
- Checklist перед merge/deploy: **SITE_VERSION bumped**.

## Current process (CD is off)

Use this until someone manually runs the GitHub `Deploy to VPS (manual)` workflow **and** types `I_CONFIRM_PRODUCTION_DEPLOY`.

Checklist перед merge/deploy:

- [ ] PR в base **`production`**
- [ ] **SITE_VERSION bumped** в том же релизе (`1.0.118` → `1.0.119+`)
- [ ] Нет правок `.env` / `storage/` / `public/videos`
- [ ] `migrate --force` только если явно просили

1. Merge the feature into **`production`** (not `main`, not the old baseline name).
2. **Обязательно** bump `resources/js/siteVersion.js` в том же релизе (следующий после live **1.0.118**). Без bump релиз не завершён.
3. On the VPS, copy only the files you changed (scp). **Never** full-tree rsync from a laptop without the checklist below.
4. Backup first:
   - `public/build` (whole directory)
   - `resources/js/siteVersion.js`
   - every file you are about to overwrite  
   Destination used on the server: `/var/backups/alexallergotest.ru/<UTC-stamp>/` (or `~/backups/…`).
5. On the VPS: `npm run build` (client + SSR), then `php artisan optimize:clear`.
6. Migrations only when the release contains schema changes (`php artisan migrate --force`). Default: skip.
7. Restart php-fpm / `php artisan inertia:start-ssr` (port 13714) only if the new SSR bundle must replace a running process.
8. Smoke: `https://alexallergotest.ru/up`, `https://doc.alexallergotest.ru/up`, patient home, doctor home, `/blog`. Check that the built `siteVersion-*.js` matches the bumped value.

**Forbidden without a written checklist:** full `rsync` of the repo, overwrite of `.env*`, `storage/`, `public/storage`, `public/videos`, blind `vendor/` or `node_modules/` replace, `git reset --hard` on the server.

## What GitHub Actions does today

| Workflow | When | Effect on VPS |
|----------|------|----------------|
| `CI` | pull requests; pushes to `production`, prod-baseline, sync-prod | **None** — tests + `npm run build` on GitHub runners |
| `Deploy to VPS (manual)` | **only** Actions → Run workflow | None unless confirm string is exact; still **no** `on: push` |

Feature PHPUnit (`tests/Feature`, stock Breeze) is **not** in CI (routes differ from this app). CI runs `--testsuite=Unit` only.

## Secrets (names only — values stay in GitHub / Cloud UI)

GitHub repository / environment **`production` secrets already exist** (same names as Cloud `ALEX_*` + healthchecks). GitHub Actions does not read Cloud Agent env; keep names in sync:

| GitHub secret | Same idea as Cloud env | Purpose |
|---------------|------------------------|---------|
| `ALEX_APP_ROOT` | `ALEX_APP_ROOT` | Absolute app root on the VPS |
| `ALEX_SSH_HOST` | `ALEX_SSH_HOST` | SSH host |
| `ALEX_SSH_USER` | *(create; Cloud `SSH_USER` is not `alexadmin`)* | Deploy SSH user |
| `ALEXADMIN_SSH_PRIVATE_KEY` | `ALEXADMIN_SSH_PRIVATE_KEY` | Deploy private key (PEM) |
| `HEALTHCHECK_URL_PATIENT` | — | e.g. `https://alexallergotest.ru/up` |
| `HEALTHCHECK_URL_DOCTOR` | — | e.g. `https://doc.alexallergotest.ru/up` |

Do not put `.env` production contents in git or in workflow logs.

When CD is first enabled: GitHub Environment **`production`** with required reviewers; `run_migrations` and `restart_services` stay **false** unless the release needs them.

## Later (not this change)

- Align `main` with the prod baseline **without** turning on `on: push` deploy.
- Only then consider auto-deploy, still behind environment protection.
