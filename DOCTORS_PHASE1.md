# Doctors subdomain — phase 1

Prepares **doc.alexallergotest.ru** and temporary apex preview **`/doctors/*`** in the same Laravel codebase and DB.

> **Repo note:** GitHub `main` is behind the production VPS. This PR adds merge-ready Laravel + Inertia files. Apply on the full VPS checkout, resolve overlaps with existing `Blog`, `SiteSidebar`, and route files, then run migrations and build assets.

## Locked decisions implemented

| Decision | Implementation |
|----------|----------------|
| Subdomain `doc.alexallergotest.ru` | `config/doctors.php`, domain route group |
| Site mode from Host **or** `/doctors` path | `App\Services\DetectSite` |
| No sticky audience cookie | `useDoctorMode.js` reads Inertia `site.isDoctorsSite` only |
| Doctor theme `#cba98e` under logo | `resources/css/doctors-theme.css` + sidebar `.doctor-theme` |
| Shared `/admin`, blog audience XOR | migration `audience`, admin field patch |
| Public doctors materials (no admin gate) | `routes/doctors.php` without auth |
| Open doctor register/login on doctors contour | `DoctorAuthController`, `is_doctor` migration |
| Materials Variant B skeleton | `Doctors/Materials/Index.vue` |
| `DOCTORS_SUBDOMAIN_REDIRECT=false` default | `DoctorsRedirect` helper |
| `DOCTORS_PATH_PREVIEW=true` default | path route group |
| `SITE_VERSION` bump | `resources/js/siteVersion.js` → `1.0.76` |

## Merge checklist (VPS)

1. Copy new files and merge patches (`*.patch`, `*.patch.php`, `*.patch.md`).
2. Register middleware + provider (`bootstrap/app.php.patch`).
3. Add env vars from `.env.example.patch`.
4. Merge `HasBlogAudience` into existing `Blog` model (or replace stub).
5. Add `->forCurrentSite()` to public blog queries + sitemap.
6. **Remove** legacy auth redirect on `/doctors` (production currently 302 → `/login`).
7. `php artisan migrate`
8. `npm run build`

## Test plan (Host header)

### Apex patient (must stay unchanged)

```bash
curl -sI -H 'Host: alexallergotest.ru' https://127.0.0.1/
curl -sI -H 'Host: alexallergotest.ru' https://127.0.0.1/blog
curl -sI -H 'Host: alexallergotest.ru' https://127.0.0.1/search
```

Expect **200**, patient theme (not `#cba98e` shell), patient-only blog posts.

### Apex doctors path preview

```bash
curl -sI -H 'Host: alexallergotest.ru' https://127.0.0.1/doctors
curl -sI -H 'Host: alexallergotest.ru' https://127.0.0.1/doctors/materials
curl -sI -H 'Host: alexallergotest.ru' https://127.0.0.1/doctors/login
curl -sI -H 'Host: alexallergotest.ru' https://127.0.0.1/doctors/register
```

Expect **200** (not 302 to `/login`), Inertia `Doctors/Materials/Index` or auth pages, `site.isDoctorsSite: true` in page JSON.

### Subdomain doctors host (local / staging)

```bash
# /etc/hosts: 127.0.0.1 doc.local.test
APP_DOCTORS_HOST=doc.local.test

curl -sI -H 'Host: doc.local.test' https://127.0.0.1/materials
curl -sI -H 'Host: doc.local.test' https://127.0.0.1/register
```

Expect doctors theme, routes **without** `/doctors` prefix.

### Audience toggle

1. Admin → create blog post, audience **Врачи**.
2. Open `/doctors/materials` → post visible.
3. Open `/blog` on apex → post **hidden**.
4. Existing posts default **patients** after migration.

### Doctor auth

1. `/doctors/register` → creates user with `is_doctor=1`.
2. `/doctors/cabinet` requires login.
3. Patient `/dashboard` and `/admin` unchanged.

### Redirect flag (do not enable in prod yet)

```env
DOCTORS_SUBDOMAIN_REDIRECT=true
```

Request `https://alexallergotest.ru/doctors/materials` → **301** to `https://doc.alexallergotest.ru/materials`.
