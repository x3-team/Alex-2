# SiteSidebar.vue merge notes

Apply to the existing production `resources/js/Components/SiteSidebar.vue`:

1. Import site version from shared module:

```js
import { SITE_VERSION } from '@/siteVersion.js';
```

Replace hardcoded `const Y = "1.0.75"` with `SITE_VERSION`.

2. Import and use `doctorsUrl` from `useDoctorMode`:

```js
const { isDoctorMode, doctorsUrl } = useDoctorMode();
```

3. Doctor materials navigation should use doctors contour URL:

```js
const openMaterials = () => {
  router.visit(doctorsUrl('/materials'));
  emit('close');
};
```

4. Sidebar promo/header doctor theme (`#cba98e` under logo when doctors mode):

```css
.sidebar-container.doctor-theme .promo-card,
.site-sidebar.doctor-theme .promo-card {
  background: var(--doctor-theme-color, #cba98e);
}

.sidebar-container.doctor-theme,
.site-sidebar.doctor-theme {
  --doctor-theme-color: #cba98e;
}
```

5. Profile row on doctors contour: link to login/register instead of "раздел в разработке":

```js
const openProfile = () => {
  if (isDoctorMode.value) {
    router.visit(doctorsUrl(auth.user ? '/cabinet' : '/login'));
    return;
  }
  // existing patient modal
};
```

6. Keep `:class="{ 'doctor-theme': isDoctorMode }"` on `<aside>` (already present in production bundle).
