<script setup>
import { computed } from 'vue';

const props = defineProps({
  label: {
    type: String,
    default: 'Записаться на тест на аллергию',
  },
  /**
   * Which periodic accent to run. 'none' leaves the button static.
   */
  effect: {
    type: String,
    default: 'shine-nudge',
    validator: (v) => ['none', 'shine', 'glow', 'shine-nudge'].includes(v),
  },
  /** Seconds between two firings. The gesture itself always lasts ~1.6s. */
  period: {
    type: Number,
    default: 8,
  },
  /**
   * How many times to fire. Null runs indefinitely; a number is useful if the
   * accent should stop once the visitor has settled on the page.
   */
  cycles: {
    type: Number,
    default: null,
  },
  href: {
    type: String,
    default: '#',
  },
});

const style = computed(() => ({
  '--cta-period': `${props.period}s`,
  '--cta-cycles': props.cycles === null ? 'infinite' : String(props.cycles),
}));
</script>

<template>
  <a :href="href" class="cta" :class="`cta--${effect}`" :style="style">
    <span v-if="effect === 'glow' || effect === 'shine-nudge'" class="cta__glow" aria-hidden="true" />
    <span v-if="effect === 'shine' || effect === 'shine-nudge'" class="cta__shine" aria-hidden="true" />

    <span class="cta__label">{{ label }}</span>

    <span class="cta__arrow" aria-hidden="true">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none">
        <path
          d="m9 18 6-6-6-6"
          stroke="#121212"
          stroke-width="2.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </svg>
    </span>
  </a>
</template>

<style scoped>
/* Surface values come straight from the sidebar CTA in the comp: a 22% white
   glass fill on a 30% white hairline. */
.cta {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  height: 80px;
  padding: 0 24px;
  background: rgba(255, 255, 255, 0.22);
  border: 1px solid rgba(255, 255, 255, 0.3);
  color: #ffffff;
  font-size: 21px;
  text-decoration: none;
  overflow: hidden;
  isolation: isolate;
}

.cta__label {
  position: relative;
  z-index: 2;
  white-space: nowrap;
}

.cta__arrow {
  position: relative;
  z-index: 2;
  flex: none;
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #ffffff;
}

/* ---------------------------------------------------------------------------
   Shine: a wide, soft band of light crossing the surface.
   The band is deliberately wider than a highlight line and peaks at 14% white —
   past roughly 20% it stops reading as light and starts reading as a moving
   rectangle.
   --------------------------------------------------------------------------- */

.cta__shine {
  position: absolute;
  inset: 0;
  z-index: 1;
  overflow: hidden;
  pointer-events: none;
}

.cta__shine::before {
  content: '';
  position: absolute;
  top: -60%;
  bottom: -60%;
  left: 0;
  width: 45%;
  background: linear-gradient(
    100deg,
    rgba(255, 255, 255, 0) 0%,
    rgba(255, 255, 255, 0.06) 35%,
    rgba(255, 255, 255, 0.14) 50%,
    rgba(255, 255, 255, 0.06) 65%,
    rgba(255, 255, 255, 0) 100%
  );
  transform: translateX(-140%) skewX(-14deg);
  animation: cta-shine var(--cta-period) cubic-bezier(0.32, 0, 0.24, 1)
    var(--cta-cycles) both;
}

/* Travel occupies the first 18% of the period, then the band parks off-canvas
   until the next firing. */
@keyframes cta-shine {
  0% {
    transform: translateX(-140%) skewX(-14deg);
    opacity: 0;
  }
  2% {
    opacity: 1;
  }
  16% {
    opacity: 1;
  }
  19% {
    transform: translateX(320%) skewX(-14deg);
    opacity: 0;
  }
  100% {
    transform: translateX(320%) skewX(-14deg);
    opacity: 0;
  }
}

/* ---------------------------------------------------------------------------
   Glow: the whole button breathes once. Only opacity animates, so this stays
   cheap despite being a blurred shadow.
   --------------------------------------------------------------------------- */

.cta__glow {
  position: absolute;
  inset: -1px;
  z-index: 0;
  pointer-events: none;
  box-shadow: 0 0 34px 2px rgba(255, 255, 255, 0.3);
  opacity: 0;
  animation: cta-glow var(--cta-period) ease-in-out var(--cta-cycles) both;
}

/* The glow leads the shine slightly, so together they read as one gesture
   rather than two effects that happen to coincide. */
@keyframes cta-glow {
  0% {
    opacity: 0;
  }
  8% {
    opacity: 1;
  }
  24% {
    opacity: 0;
  }
  100% {
    opacity: 0;
  }
}

.cta--shine-nudge .cta__glow {
  box-shadow: 0 0 30px 1px rgba(255, 255, 255, 0.22);
}

/* ---------------------------------------------------------------------------
   Nudge: the arrow drifts as the light passes it, which is what makes the
   accent feel intentional instead of decorative.
   --------------------------------------------------------------------------- */

.cta--shine-nudge .cta__arrow {
  animation: cta-nudge var(--cta-period) cubic-bezier(0.34, 0, 0.24, 1)
    var(--cta-cycles) both;
}

@keyframes cta-nudge {
  0%,
  9% {
    transform: translateX(0);
  }
  14% {
    transform: translateX(3px);
  }
  22% {
    transform: translateX(0);
  }
  100% {
    transform: translateX(0);
  }
}

/* Hover is a separate, immediate response and must not fight the idle accent. */
.cta:hover {
  background: rgba(255, 255, 255, 0.3);
  border-color: rgba(255, 255, 255, 0.45);
}

.cta:focus-visible {
  outline: 2px solid rgba(255, 255, 255, 0.9);
  outline-offset: 3px;
}

@media (prefers-reduced-motion: reduce) {
  .cta__shine::before,
  .cta__glow,
  .cta--shine-nudge .cta__arrow {
    animation: none;
  }

  .cta__glow {
    opacity: 0;
  }
}
</style>
