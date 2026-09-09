# SiteSidebar.vue — VPS merge only

**Do not copy `resources/js/Components/SiteSidebar.vue` from this branch over production.**
The live sidebar (modals, lab CTAs, icons, quiz row) already exists on the VPS.
Doctors pages use `DoctorsSidebar.vue` instead.

Merge these hunks into the existing production `SiteSidebar.vue`:

## 1. Shared version module

```js
import { SITE_VERSION } from '@/siteVersion.js';
```

Replace hardcoded `const Y = "1.0.75"` with `SITE_VERSION`.

## 2. Doctors URLs (path preview until DNS)

```js
const { isDoctorMode, doctorsUrl } = useDoctorMode();
```

Production already imports `useDoctorMode`. After replacing that composable,
`doctorsUrl` / `toggleAudienceMode` remain exported (cookie toggle is a no-op).

## 3. Materials row in doctor mode

```js
const openMaterials = () => {
  if (isDoctorMode.value) {
    router.visit(doctorsUrl('/materials'));
    emit('close');
    return;
  }
  router.visit('/quiz');
};
```

Keep the existing `/doctor-materials` fallback only if you have not yet deployed
`/doctors/materials`.

## 4. Profile row on doctors contour

Replace the “раздел в разработке” modal when `isDoctorMode`:

```js
const openProfile = () => {
  if (isDoctorMode.value) {
    const user = page.props.auth?.user;
    router.visit(doctorsUrl(user?.is_doctor ? '/cabinet' : '/login'));
    return;
  }
  // existing patient modal
};
```

## 5. Theme under the logo

Production already has `:class="{ 'doctor-theme': isDoctorMode }"`.
Import `doctors-theme.css` from `app.css` so `.promo-card` becomes `#cba98e`.
