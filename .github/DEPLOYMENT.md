# CI/CD (GitHub Actions)

## CI (`ci.yml`)

Runs on every pull request and on pushes to `main` and `cursor/unify-menu-fonts-425e`:

1. `composer install` + `php artisan test --testsuite=Unit` (PHPUnit, SQLite in memory)
2. `npm ci --legacy-peer-deps` + `npm run build` (Vite client + SSR)

## CD (`deploy.yml`)

Manual deploy via **Actions → Deploy to VPS → Run workflow**.

1. Checks out the chosen git ref
2. `rsync` to the VPS (does **not** overwrite `.env`, `storage/`, or `vendor/` on the server before install)
3. Runs `scripts/deploy-vps.sh` on the server: `composer install --no-dev`, `npm run build`, optional migrations, Laravel caches

Production still has no git checkout on the server; deploy is rsync-based, matching the previous manual process.

### Required repository secrets

| Secret | Description |
|--------|-------------|
| `SSH_PRIVATE_KEY` | Private key for deploy user (PEM, including `BEGIN`/`END` lines) |
| `SSH_HOST` | VPS hostname or IP |
| `SSH_USER` | SSH user (e.g. `alexadmin`) |
| `DEPLOY_PATH` | Absolute app root on the VPS (Immunotech web root) |
| `SSH_PORT` | Optional; default `22` |
| `HEALTHCHECK_URL` | Optional; e.g. `https://alexallergotest.ru/up` |

Add deploy user public key to `~/.ssh/authorized_keys` on the VPS. Restrict key to rsync + deploy commands if possible.

### GitHub environment

Workflow uses the **`production`** environment. In **Settings → Environments → production** you can require reviewers before deploy runs.

### After first automated deploy

- Confirm **queue workers** (`php artisan queue:work`) and **Inertia SSR** (`php artisan inertia:start-ssr`, port 13714) are still running (restart via your existing systemd/supervisor or manual process).
- Keep server `.env` in sync with new config keys when you add features.

### Optional: auto-deploy on merge to `main`

When `main` matches production, add to `deploy.yml`:

```yaml
on:
  push:
    branches: [main]
```

and remove or keep `workflow_dispatch` as a manual override.
