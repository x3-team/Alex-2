<script setup>
import { ref } from 'vue';

import arrowDark from '@/assets/icons/arrow-right-dark.svg';
import arrowLight from '@/assets/icons/arrow-right-light.svg';
import { useParticleField } from '@/composables/useParticleField.js';

const props = defineProps({
  title: {
    type: String,
    default: 'Ошибка 404',
  },
  subtitle: {
    type: String,
    default: 'Вы попали на сайт ALEX2, но мы не смогли найти именно то, что вы искали',
  },
  /**
   * Destinations shown as buttons. `primary` picks the dark treatment.
   */
  navItems: {
    type: Array,
    default: () => [
      { label: 'Вернуться на главную', to: '/', primary: true },
      { label: 'Блог', to: '/blog' },
      { label: 'Личный кабинет', to: '/account' },
      { label: 'Корзина', to: '/cart' },
    ],
  },
  /**
   * Element or component used for each button. Pass RouterLink to navigate
   * client-side; the default plain anchor keeps this view router-agnostic.
   */
  linkComponent: {
    type: [String, Object, Function],
    default: 'a',
  },
  /**
   * Tuning overrides forwarded to the particle engine. See ParticleField.
   */
  fieldOptions: {
    type: Object,
    default: () => ({}),
  },
});

const canvas = ref(null);

useParticleField(canvas, props.fieldOptions);

const linkProps = (item) =>
  props.linkComponent === 'a' ? { href: item.to } : { to: item.to };
</script>

<template>
  <div class="error-404">
    <div class="space" aria-hidden="true">
      <canvas ref="canvas" class="space__particles" />
    </div>
    <div class="space__scrim" aria-hidden="true" />

    <main class="error-page">
      <div class="error-card">
        <header class="error-head">
          <h1 class="error-title">{{ title }}</h1>
          <p class="error-subtitle">{{ subtitle }}</p>
        </header>

        <nav class="error-nav" aria-label="Основные разделы">
          <component
            :is="linkComponent"
            v-for="item in navItems"
            :key="item.to"
            class="nav-btn"
            :class="{ 'nav-btn--primary': item.primary }"
            v-bind="linkProps(item)"
          >
            <span class="nav-btn__label">{{ item.label }}</span>
            <img
              class="nav-btn__icon"
              :src="item.primary ? arrowLight : arrowDark"
              alt=""
              width="24"
              height="24"
            />
          </component>
        </nav>
      </div>
    </main>
  </div>
</template>

<style scoped>
/* Fixed to the viewport so the view is self-contained: dropping it into an
   existing layout does not require global body styles. */
.error-404 {
  position: fixed;
  inset: 0;
  overflow: hidden;
  font-family: var(--font-sans);
  color: var(--color-white);
  background-color: var(--space-deep);
}

/* ---------------------------------------------------------------------------
   Backdrop
   The comp used a flat bitmap for the nebula. These layers rebuild it with
   gradients so it stays crisp at any viewport and the particles above it can
   move independently.
   --------------------------------------------------------------------------- */

.space,
.space__scrim {
  position: absolute;
  inset: 0;
}

.space {
  background:
    radial-gradient(ellipse 62% 56% at 12% 6%, var(--space-glow-warm), transparent 72%),
    radial-gradient(ellipse 72% 62% at 86% 94%, var(--space-glow-cool), transparent 74%),
    linear-gradient(
      155deg,
      var(--space-warm) 0%,
      var(--space-mid) 42%,
      var(--space-cool) 74%,
      var(--space-deep) 100%
    );
}

.space__particles {
  display: block;
  width: 100%;
  height: 100%;
}

/* Radial darkening centred on the card: keeps the copy legible while letting
   the corners of the field stay luminous. */
.space__scrim {
  pointer-events: none;
  background:
    radial-gradient(
      ellipse 66% 58% at 50% 50%,
      rgba(6, 10, 18, 0.56) 0%,
      rgba(6, 10, 18, 0.2) 58%,
      rgba(6, 10, 18, 0) 100%
    ),
    linear-gradient(
      to bottom,
      rgba(6, 10, 18, 0.26) 0%,
      rgba(6, 10, 18, 0) 32%,
      rgba(6, 10, 18, 0) 68%,
      rgba(6, 10, 18, 0.34) 100%
    );
}

/* ---------------------------------------------------------------------------
   Layout
   --------------------------------------------------------------------------- */

.error-page {
  position: relative;
  z-index: 1;
  height: 100%;
  display: grid;
  place-items: center;
  padding: 40px 24px;
}

.error-card {
  width: 100%;
  max-width: 414px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 64px;
  animation: card-enter 720ms var(--ease-out-soft) both;
}

@keyframes card-enter {
  from {
    opacity: 0;
    transform: translateY(14px);
  }
  to {
    opacity: 1;
    transform: none;
  }
}

.error-head {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  text-align: center;
}

.error-title {
  margin: 0;
  font-size: var(--font-size-h1);
  font-weight: 400;
  line-height: 1.16;
  letter-spacing: -0.01em;
  text-shadow: 0 2px 18px rgba(6, 10, 18, 0.45);
}

.error-subtitle {
  margin: 0;
  max-width: 400px;
  font-size: var(--font-size-h4);
  font-weight: 400;
  line-height: 1.32;
  color: rgba(255, 255, 255, 0.88);
  text-shadow: 0 1px 12px rgba(6, 10, 18, 0.45);
}

.error-nav {
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

/* ---------------------------------------------------------------------------
   Navigation buttons
   --------------------------------------------------------------------------- */

/* Flat by design: the rest of the site never lifts, shades or gradients a
   button, so state is carried by the fill alone. */
.nav-btn {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  height: 72px;
  padding: 0 24px;
  border-radius: var(--radius-lg);
  border: 1px solid var(--btn-border);
  background: var(--btn-bg);
  color: var(--color-ink);
  font-size: var(--font-size-h4);
  text-decoration: none;
  overflow: hidden;
  transition:
    background-color var(--duration-base) var(--ease-out-soft),
    border-color var(--duration-base) var(--ease-out-soft);
}

.nav-btn__label {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.nav-btn__icon {
  flex: none;
  width: 24px;
  height: 24px;
  transition: transform var(--duration-base) var(--ease-out-soft);
}

.nav-btn:hover {
  background: var(--btn-bg-hover);
  border-color: var(--btn-border-hover);
}

/* The only movement on hover: a short nudge on the arrow, to hint direction
   without introducing elevation the design language does not use. */
.nav-btn:hover .nav-btn__icon {
  transform: translateX(3px);
}

.nav-btn:focus-visible {
  outline: 2px solid var(--color-white);
  outline-offset: 3px;
}

/* Primary — the solid dark CTA fill used across the site. */
.nav-btn--primary {
  background: var(--btn-primary-bg);
  border-color: var(--btn-primary-bg);
  color: var(--color-white);
}

.nav-btn--primary:hover {
  background: var(--btn-primary-bg-hover);
  border-color: var(--btn-primary-bg-hover);
}

/* ---------------------------------------------------------------------------
   Responsive — mirrors the 420px mobile frame
   --------------------------------------------------------------------------- */

@media (max-width: 560px) {
  .error-page {
    padding: 32px 16px;
  }

  .error-card {
    gap: 44px;
  }

  .error-title {
    font-size: 34px;
  }

  .error-subtitle {
    font-size: 18px;
  }

  .nav-btn {
    height: 66px;
    padding: 0 20px;
    font-size: 18px;
    border-radius: var(--radius-md);
  }
}

@media (max-height: 620px) {
  .error-card {
    gap: 32px;
  }
}

/* ---------------------------------------------------------------------------
   Reduced motion — the field renders one static frame and nothing animates.
   --------------------------------------------------------------------------- */

@media (prefers-reduced-motion: reduce) {
  .error-card {
    animation: none;
  }

  .nav-btn,
  .nav-btn__icon {
    transition: none;
  }

  .nav-btn:hover .nav-btn__icon {
    transform: none;
  }
}
</style>
