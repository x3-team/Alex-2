# Alex-2

Laravel 12 + Inertia/Vue site for [alexallergotest.ru](https://alexallergotest.ru).

## Doctors subdomain (phase 1)

See [DOCTORS_PHASE1.md](./DOCTORS_PHASE1.md) for merge steps, env flags, and Host-header test plan.

Branch `cursor/doctors-subdomain-phase1-6f93` adds:

- `DetectSite` (Host `doc.*` or path `/doctors`)
- Public doctors materials **Variant B** skeleton
- Blog `audience` (`patients`|`doctors`)
- Open doctor register/login (`is_doctor`)
- Feature flags `DOCTORS_PATH_PREVIEW` / `DOCTORS_SUBDOMAIN_REDIRECT`

# Alex-2