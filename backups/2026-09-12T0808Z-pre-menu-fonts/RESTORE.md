# Backup: live prod before sidebar menu typography (1.0.106)

**Created:** 2026-09-12 UTC  
**Live version:** `SITE_VERSION=1.0.105` (from `siteVersion-DYzAFIKx.js`)

## Contents

| Path | Description |
|------|-------------|
| `live-build/app-z0jQe8aM.css` | Compiled Vite CSS served on patient + doc hosts |
| `live-build/siteVersion-DYzAFIKx.js` | Version module on prod |
| `live-build/Welcome-dvv9hcKi.js` | Welcome page bundle |
| `live-build/patient-home.html` | Snapshot of `https://alexallergotest.ru/` |
| `live-build/doctor-home.html` | Snapshot of `https://doc.alexallergotest.ru/` |
| `source-main.css.v1.0.105` | `resources/css/main.css` from git `sync/prod-1.0.105` |
| `source-main.css.v1.0.106-draft` | Source after typography unification (pre-deploy) |
| `git-sync-prod-1.0.105.tar.gz` | Full tree of branch `sync/prod-1.0.105` |
| `SHA256SUMS` | Checksums |

## Rollback on VPS (`/var/www/alexallergotest.ru`)

1. Restore source: copy `source-main.css.v1.0.105` → `resources/css/main.css`
2. Set `resources/js/siteVersion.js` to `1.0.105`
3. Run `npm ci && npm run build` (or project’s usual frontend build)
4. **Or** fast rollback of compiled assets only (no rebuild):

   ```bash
   cp live-build/app-z0jQe8aM.css public/build/assets/app-z0jQe8aM.css
   cp live-build/siteVersion-DYzAFIKx.js public/build/assets/siteVersion-DYzAFIKx.js
   ```

   Adjust filenames if Vite manifest changed; match paths under `public/build/assets/`.

5. `php artisan optimize:clear` if applicable

Git tag on remote: `backup/live-prod-1.0.105-20260912` → commit `7df8bec`.
