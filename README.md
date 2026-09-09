# Alex-2

Laravel 12 + Inertia/Vue site for [alexallergotest.ru](https://alexallergotest.ru).

## Doctors subdomain (phase 1)

See [DOCTORS_PHASE1.md](./DOCTORS_PHASE1.md) and the production checklist in [DEPLOY_VPS.md](./DEPLOY_VPS.md).

Branch `cursor/doctors-subdomain-phase1-6f93` adds:

- `DetectSite` — Host `doc.*` **or** path `/doctors` (`DOCTORS_PATH_PREVIEW=true`)
- Public `/doctors/materials`, `/doctors/login`, `/doctors/register` (no patient `/login` gate)
- Materials Variant B (Figma) + `DoctorsSidebar`
- Blog `audience` (`patients`|`doctors`) and open doctor register (`is_doctor`)
- Flags `DOCTORS_PATH_PREVIEW` / `DOCTORS_SUBDOMAIN_REDIRECT=false`
- `SITE_VERSION` **1.0.77**

Helper checks (no Laravel): `php tests/run-doctors.php`
