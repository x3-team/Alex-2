# Doctors subdomain — phase 1

Prepares **doc.alexallergotest.ru** and temporary apex preview **`/doctors/*`** in the same Laravel codebase and DB.

> GitHub `main` is behind the production VPS. This branch is an overlay: new doctors files plus merge notes for live models. See [DEPLOY_VPS.md](./DEPLOY_VPS.md) for the exact production checklist.

## Locked decisions

| Decision | Implementation |
|----------|----------------|
| Subdomain `doc.alexallergotest.ru` | `config/doctors.php`, domain route group (skipped if host equals apex) |
| Site mode from Host **doc.*** **or** `/doctors` | `App\Services\DetectSite` |
| No sticky audience cookie | `useDoctorMode.js` reads Inertia `site.isDoctorsSite` (`toggleAudienceMode` is a no-op for old callers) |
| Doctor theme `#cba98e` under logo | `doctors-theme.css`, `DoctorsSidebar.vue` |
| Shared `/admin`, blog audience XOR | migration `audience`, `HasBlogAudience`, admin field patch |
| Public doctors materials (no admin/login gate) | `routes/doctors.php` without `auth` / `guest` |
| Open doctor register/login | `DoctorAuthController`, `is_doctor` + `HasDoctorFlag` |
| Materials Variant B | `Doctors/Materials/Index.vue` + `DoctorsSidebar.vue` |
| `DOCTORS_SUBDOMAIN_REDIRECT=false` | `DoctorsRedirect` |
| `DOCTORS_PATH_PREVIEW=true` | path route group |
| `SITE_VERSION` | `resources/js/siteVersion.js` → **1.0.77** |

## Review notes (phase-1 follow-up)

Fixed on this revision:

- `guest` middleware on `/doctors/login|register` would send logged-in patients to `/dashboard`. Removed.
- `Hash::make` on Laravel 11+ `password => hashed` would double-hash and break the next login.
- `DetectSite` now matches configured host **or** `doc.*` (not `docs.*`).
- Domain routes are not registered when `APP_DOCTORS_HOST` equals the apex host.
- `HandleInertiaRequests` / `Blog` / `SiteSidebar` stubs must not overwrite VPS files.
- Materials default tab is **Материалы** (all), plus Статьи / Видео and Figma filter chips.
- Doctor sessions are steered off the patient dashboard via `RedirectDoctorFromPatientArea` (not `/admin`).

Files that **only exist on the VPS** stay as patches:

- `bootstrap/app.php.patch`
- `routes/web.php.patch`
- `app/Http/Controllers/Public/BlogController.patch.php`
- `app/Http/Controllers/Admin/BlogController.patch.php`
- `app/Models/User.is_doctor.patch.php`
- `resources/js/Components/SiteSidebar.patch.md`
- `resources/css/app.css.patch`

## Merge checklist (VPS)

Follow [DEPLOY_VPS.md](./DEPLOY_VPS.md). Short form:

1. Copy **new** files. Do not replace live `SiteSidebar.vue`, `HandleInertiaRequests`, `Blog`, `User`.
2. Register middleware + `DoctorsRouteServiceProvider`.
3. Env from `.env.example.patch`.
4. `HasBlogAudience` + `HasDoctorFlag` on live models.
5. `->forCurrentSite()` on public blog + sitemap.
6. **Remove** legacy `/doctors` → `/login` (`home.doctors`).
7. `php artisan migrate --force` and `npm run build`.

## Test plan (Host header)

### Apex patient (must stay unchanged)

```bash
curl -sI -H 'Host: alexallergotest.ru' https://127.0.0.1/
curl -sI -H 'Host: alexallergotest.ru' https://127.0.0.1/blog
curl -sI -H 'Host: alexallergotest.ru' https://127.0.0.1/search
```

Expect **200**, patient theme (not the doctors shell), patient-only blog posts.

### Apex doctors path preview

```bash
curl -sI -H 'Host: alexallergotest.ru' https://127.0.0.1/doctors
curl -sI -H 'Host: alexallergotest.ru' https://127.0.0.1/doctors/materials
curl -sI -H 'Host: alexallergotest.ru' https://127.0.0.1/doctors/login
curl -sI -H 'Host: alexallergotest.ru' https://127.0.0.1/doctors/register
```

Expect `/doctors` → 302 `/doctors/materials`. The rest **200** (not 302 to `/login`).

### Subdomain doctors host (local / staging)

```bash
# /etc/hosts: 127.0.0.1 doc.local.test
APP_DOCTORS_HOST=doc.local.test

curl -sI -H 'Host: doc.local.test' https://127.0.0.1/materials
curl -sI -H 'Host: doc.local.test' https://127.0.0.1/register
```

Expect doctors theme, routes **without** `/doctors` prefix. Needs nginx `server_name` — not part of this PR.

### Audience toggle

1. Admin → create blog post, audience **Врачи**.
2. Open `/doctors/materials` → post visible.
3. Open `/blog` on apex → post **hidden**.
4. Existing posts default **patients** after migration.

### Doctor auth

1. `/doctors/register` → `is_doctor=1`.
2. `/doctors/cabinet` requires a doctor session; patients are sent to `/doctors/login`.
3. Patient `/dashboard` and `/admin` stay on their own middleware. Doctors hitting `/dashboard` go to `/doctors/cabinet`.
