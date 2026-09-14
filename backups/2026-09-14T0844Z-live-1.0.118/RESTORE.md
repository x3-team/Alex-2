# Backup: live prod **1.0.118** (2026-09-14 UTC)

## Live snapshot

| Host | Role | `SITE_VERSION` | Build assets |
|------|------|----------------|--------------|
| `https://alexallergotest.ru` | Patient | **1.0.118** | `siteVersion-zXYljG-m.js`, `Welcome-BL3uZIqU.js`, `app-CSekJR9p.css`, `app-Bwg6WrbX.js`, `useDoctorMode-Bl9ItZax.js` |
| `https://doc.alexallergotest.ru` | Doctor | **1.0.118** | Same hashed files as patient (byte-identical CSS/Welcome/siteVersion) |

Git anchor (partial repo slice): `origin/cursor/mobile-advantages-shift-2397` → see `git-ref-mobile-advantages.txt`.

## `doc.allergetest.ru`

From this Cloud Agent environment **`doc.allergetest.ru` did not resolve** (no DNS A record). If you added it recently, re-check from your network or VPS nginx `server_name`. When live, add another HTML snapshot here the same way as `doctor-home.html`.

Likely intent: alternate spelling **allerget** vs **alexallerg** — confirm TLS cert and redirect target (`doc.alexallergotest.ru` vs apex).

## Contents

| Path | Description |
|------|-------------|
| `live-build/` | Compiled Vite assets + `manifest.json` |
| `html/` | Home + `/blog` for patient and doc hosts |
| `source-main.css`, `source-siteVersion.js` | From git branch `cursor/mobile-advantages-shift-2397` |
| `SHA256SUMS` | Checksums |

## Fast rollback (compiled assets only)

On VPS under `public/build/assets/` (paths must match current manifest):

```bash
cp live-build/patient-app-CSekJR9p.css public/build/assets/app-CSekJR9p.css
cp live-build/siteVersion-zXYljG-m.js public/build/assets/siteVersion-zXYljG-m.js
cp live-build/patient-Welcome-BL3uZIqU.js public/build/assets/Welcome-BL3uZIqU.js
# …restore other manifest entries if needed
php artisan optimize:clear
```

Full rollback: server directory backup per `DEPLOY_VPS.md` (`cp -a` / `rsync` from `../alexallergotest.ru.bak-*`).
