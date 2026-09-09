# Production VPS deploy (`/var/www/alexallergotest.ru`)

GitHub `main` is behind this VPS. Do **not** `git reset --hard` onto production.
Copy new files, merge patches into live models/routes, then migrate and build.

## 0. Safety

```bash
cd /var/www/alexallergotest.ru
php artisan down
cp -a . ../alexallergotest.ru.bak-$(date +%Y%m%d%H%M)
```

Confirm current patient site still serves `/`, `/blog`, `/search`, `/login`.

## 1. Copy only new files (do not overwrite live UI)

From this branch, copy these as **new** paths:

```text
app/Services/DetectSite.php
app/Support/DoctorsRedirect.php
app/Support/DoctorsCopy.php
app/Providers/DoctorsRouteServiceProvider.php
app/Http/Middleware/DetectSiteMiddleware.php
app/Http/Middleware/EnsureDoctor.php
app/Http/Middleware/RedirectDoctorFromPatientArea.php
app/Http/Middleware/Concerns/SharesDoctorsSite.php
app/Http/Controllers/Doctors/
app/Http/Controllers/Concerns/ResolvesDoctorsRoutes.php
app/Models/Concerns/HasBlogAudience.php
app/Models/Concerns/HasDoctorFlag.php
config/doctors.php
routes/doctors.php
database/migrations/2026_09_09_000001_add_audience_to_blogs_table.php
database/migrations/2026_09_09_000002_add_is_doctor_to_users_table.php
resources/js/Pages/Doctors/
resources/js/Components/DoctorsSidebar.vue
resources/js/siteVersion.js
resources/css/doctors-theme.css
resources/js/Pages/Admin/Blog/AudienceField.patch.vue
```

**Do not overwrite** (merge the matching `*.patch*` instead):

```text
app/Http/Middleware/HandleInertiaRequests.php
app/Models/Blog.php
app/Models/User.php
app/Models/Author.php
app/Models/BlogCategory.php
app/Models/BlogTag.php
app/Models/DoctorMaterial.php
resources/js/Components/SiteSidebar.vue
routes/web.php
bootstrap/app.php
resources/css/app.css
resources/js/composables/useDoctorMode.js   # replace only after checking toggleAudienceMode callers
```

`useDoctorMode.js` from this branch is safe to replace: it keeps `audienceCookie` / `toggleAudienceMode` exports so existing sidebar code does not crash. Mode is Host/`/doctors`, not the old cookie.

## 2. Merge patches on the live tree

1. `bootstrap/app.php.patch` — aliases `detect.site`, `doctor`, `doctor.not-patient`; prepend `DetectSiteMiddleware` to `web`; register `DoctorsRouteServiceProvider`.
2. `HandleInertiaRequests` — `use SharesDoctorsSite` and merge `$this->doctorsSiteShare($request)` into existing `share()`. Keep Ziggy / flash / auth.
3. `User` — `use HasDoctorFlag`.
4. `Blog` — `use HasBlogAudience` and add `audience` to `$fillable`.
5. Public `BlogController` — `->forCurrentSite()` on list/show/sitemap (`BlogController.patch.php`).
6. Admin blog form — audience validation + `AudienceField.patch.vue`.
7. `resources/css/app.css` — `@import './doctors-theme.css';`
8. `SiteSidebar.patch.md` — `SITE_VERSION`, `doctorsUrl('/materials')`, doctor profile → `/doctors/login` or `/cabinet`.
9. `routes/web.php.patch` — **remove the `/doctors` → `/login` gate** (`home.doctors` in Ziggy). Do not wrap `/doctors/*` in `auth` or `guest`.

## 3. Env

```env
APP_DOCTORS_HOST=doc.alexallergotest.ru
DOCTORS_PATH_PREVIEW=true
DOCTORS_SUBDOMAIN_REDIRECT=false
```

Leave `DOCTORS_SUBDOMAIN_REDIRECT=false` until `doc.alexallergotest.ru` DNS/TLS exists. This PR does not change nginx.

## 4. Migrate, build, cache

```bash
cd /var/www/alexallergotest.ru
php artisan migrate --force
php artisan config:cache
php artisan route:cache
npm ci
npm run build
php artisan up
```

If Inertia SSR is enabled, rebuild the SSR bundle too (`npm run build:ssr` if that script exists).

## 5. Verify with curl (Host header)

Patient apex must stay unchanged:

```bash
curl -sI -H 'Host: alexallergotest.ru' https://127.0.0.1/
curl -sI -H 'Host: alexallergotest.ru' https://127.0.0.1/blog
curl -sI -H 'Host: alexallergotest.ru' https://127.0.0.1/login
```

Expect **200**. No doctors theme on `/` or `/blog`.

Public doctors path (must **not** 302 to `/login`):

```bash
curl -sI -H 'Host: alexallergotest.ru' https://127.0.0.1/doctors
curl -sI -H 'Host: alexallergotest.ru' https://127.0.0.1/doctors/materials
curl -sI -H 'Host: alexallergotest.ru' https://127.0.0.1/doctors/login
curl -sI -H 'Host: alexallergotest.ru' https://127.0.0.1/doctors/register
```

Expect `/doctors` → **302** `/doctors/materials`. The other three → **200**.

Inertia JSON (doctors flag):

```bash
curl -s -H 'Host: alexallergotest.ru' -H 'X-Inertia: true' -H 'X-Requested-With: XMLHttpRequest' \
  https://127.0.0.1/doctors/materials | head
```

Expect `site.isDoctorsSite: true` and component `Doctors/Materials/Index`.

Cabinet is private:

```bash
curl -sI -H 'Host: alexallergotest.ru' https://127.0.0.1/doctors/cabinet
```

Expect **302** to `/doctors/login` (not `/login`).

Optional Host-only check (no DNS required):

```bash
curl -sI -H 'Host: doc.alexallergotest.ru' https://127.0.0.1/materials
```

Expect **200** only after the doctors domain is in nginx `server_name`. Until then, use `/doctors/*` on the apex.

## 6. Rollback

```bash
php artisan down
rsync -a --delete /var/www/alexallergotest.ru.bak-YYYYMMDDHHMM/ /var/www/alexallergotest.ru/
php artisan up
```
