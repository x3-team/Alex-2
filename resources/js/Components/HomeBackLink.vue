<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  stretched: {
    type: Boolean,
    default: false
  },
  showMobileLabel: {
    type: Boolean,
    default: false
  }
})
</script>

<template>
  <Link
      href="/"
      class="home-back-link"
      :class="{
      'home-back-link--stretched': stretched,
      'home-back-link--mobile-label': showMobileLabel
    }"
      aria-label="На главную"
  >
    <span class="home-back-link__icon" aria-hidden="true">
      <svg width="8" height="14" viewBox="0 0 8 14" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M7 13L1 7L7 1" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </span>
    <span class="home-back-link__label">На главную</span>
  </Link>
</template>

<style scoped>
.home-back-link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 0;
  border: 0;
  background: transparent;
  color: #000;
  font-family: 'Roboto', Arial, sans-serif;
  font-size: 18px;
  font-weight: 400;
  line-height: 24px;
  text-decoration: none;
}

.home-back-link:hover {
  opacity: 0.7;
}

:global(.home-back-area) {
  position: relative;
  cursor: pointer;
}

.home-back-link--stretched::after {
  content: '';
  position: absolute;
  inset: 0;
  z-index: 1;
}

.home-back-link__icon {
  display: inline-flex;
  width: 24px;
  height: 24px;
  flex: 0 0 24px;
  align-items: center;
  justify-content: center;
}

@media (max-width: 1024px) {
  .home-back-link__label {
    display: none;
  }

  .home-back-link--mobile-label .home-back-link__label {
    display: inline;
  }
}
</style>