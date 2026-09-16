# Deploy (Alex-2 / Immunotech VPS)

**Предпочитать имя ветки `production`** (сейчас тот же SHA, что `cursor/prod-baseline-20260916-2397`). Правила для агентов: [AGENT_WORKFLOW.md](./AGENT_WORKFLOW.md).

Live site is the source of truth until `main` matches it. Default git branch: `cursor/prod-baseline-20260916-2397` (live **1.0.118** + post-14.09 UI). `main` is still an empty skeleton — **do not merge there to “enable deploy”.**

There is **no git checkout on the VPS**. Do not `git pull` or `git reset` on the server.

## Current process (CD is off)

Use this until someone manually runs the GitHub `Deploy to VPS (manual)` workflow **and** types `I_CONFIRM_PRODUCTION_DEPLOY`.

1. Merge the feature into **`production`** / prod baseline (not `main`).
2. Bump `resources/js/siteVersion.js` in that same release (next value after live **1.0.118**).
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
