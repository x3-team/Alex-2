# Deploy state snapshot — 2026-09-14 (UTC)

Captured after live checks on VPS `vm-684133` and GitHub `x3-team/Alex-2`.

## Production (single Laravel app)

| Item | Value |
|------|--------|
| App root | Standard Immunotech VPS web root for alexallergotest (see RESTORE / deploy notes) |
| Git on server | **none** (deploy by scp + `npm run build`) |
| PHP | 8.2.32, `php8.2-fpm` |
| Inertia SSR | `php artisan inertia:start-ssr` → port **13714** |
| Last `manifest.json` mtime (VPS) | **2026-09-13 13:13 +0300** (~10:13 UTC) |

### `SITE_VERSION` (patient + doctor)

| Check | Result |
|-------|--------|
| `resources/js/siteVersion.js` on VPS | **1.0.118** |
| Live chunk (both hosts) | `siteVersion-zXYljG-m.js` → `const S="1.0.118"` |
| `https://alexallergotest.ru/` | 200, `Welcome-BL3uZIqU.js`, `app-Bwg6WrbX.js` |
| `https://doc.alexallergotest.ru/` | 200, same hashed bundles as patient |

Doctor and patient share one build; doctor mode is host-driven (`doc.*`).

### Doctor-specific live UI (on prod, not all merged to one Git branch)

| Feature | On prod VPS sources |
|---------|---------------------|
| Mobile advantages overlay | `main.css`: `--mobile-copy-shift` / `--mobile-advantages-drop` **40px**; doctor `slide-5` copy **76px** |
| Doctor blog feed | `Blog/Index.vue`: compact `blog-filter-chip` (32px), type chips |
| Doctor video show | `DoctorVideos/Show.vue`: crumbs **Главная → Блог → Видео → title** |
| Doctor cabinet | `SiteSidebar` / `DoctorsSidebar` + `CabinetDevModal` (in-dev modal, not `/login`) |

### Key Vite outputs (live)

| Source | Hashed file |
|--------|-------------|
| Welcome | `Welcome-BL3uZIqU.js` |
| Blog index | `Index-Cc_UpwcH.js`, `Index-DnHtJJNn.css` |
| Doctor video show | `Show-C980nPNv.js` |
| Doctor type chips | `DoctorTypeChips-D1Sqt_QA.js` |

### Mob doctor webm (on disk, not in git)

Under `public/videos/rezak previs for doc/mob/main/` — e.g. `s9-pan-v2.webm` updated **2026-09-13 00:14** (left-pan 768→460).

---

## GitHub (open PR stack)

`main` @ `9be7ace` — still behind prod.

| PR | Branch → base | Head SHA | Notes |
|----|----------------|----------|--------|
| [#13](https://github.com/x3-team/Alex-2/pull/13) | `unify-menu-fonts-425e` → `main` | `0521739` | Menu typography baseline |
| [#17](https://github.com/x3-team/Alex-2/pull/17) | `mobile-advantages-shift-2397` → `unify-menu-fonts-425e` | `5e49568` | **1.0.118** + mobile advantages CSS |
| [#16](https://github.com/x3-team/Alex-2/pull/16) draft | `doctor-videos-documents-2397` → `unify-menu-fonts-425e` | `e2029ec` | Doctor blog; branch `siteVersion` **1.0.126** (not prod) |
| [#18](https://github.com/x3-team/Alex-2/pull/18) | `compact-blog-chips-2397` → `#16` | `805a0bb` | Compact chips (in prod) |
| [#19](https://github.com/x3-team/Alex-2/pull/19) | `video-blog-breadcrumbs-2397` → `#16` | `3bcf244` | Video crumbs + chips (in prod) |

**Tag:** `backup/live-prod-1.0.118-20260914` → commit `68e19c3` on `cursor/backup-live-118-425e`.

**Prod truth:** VPS files + this repo folder `backups/2026-09-14T0844Z-live-1.0.118/` and VPS-only copy  
`~/backups/alex-live-1.0.118-20260914T0849Z/` (sources + manifest + key chunks + HTML + SHA256SUMS).

---

## Restore pointers

- Git repo backup: `backups/2026-09-14T0844Z-live-1.0.118/RESTORE.md`
- VPS extended backup: `~/backups/alex-live-1.0.118-20260914T0849Z/RESTORE.md` on `alex-immunotech`
