<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
  active: {
    type: Boolean,
    default: false,
  },
  durationMs: {
    type: Number,
    default: 1100,
  },
})

const emit = defineEmits(['complete'])

const overlay = ref(null)
const prefersReducedMotion = ref(false)

let completedForActivation = false
let fallbackTimer = null
let motionPreferenceQuery = null
let previouslyFocusedElement = null

const normalizedDurationMs = computed(() => (
    Number.isFinite(props.durationMs) && props.durationMs > 0
        ? props.durationMs
        : 1100
))

const effectiveDurationMs = computed(() => (
    prefersReducedMotion.value
        ? Math.min(normalizedDurationMs.value, 180)
        : normalizedDurationMs.value
))

const overlayStyle = computed(() => ({
  '--doctor-blog-transition-duration': `${effectiveDurationMs.value}ms`,
}))

const clearFallbackTimer = () => {
  if (fallbackTimer === null || typeof window === 'undefined') {
    return
  }

  window.clearTimeout(fallbackTimer)
  fallbackTimer = null
}

const completeTransition = () => {
  if (!props.active || completedForActivation) {
    return
  }

  completedForActivation = true
  clearFallbackTimer()
  emit('complete')
}

const scheduleFallback = () => {
  clearFallbackTimer()

  if (typeof window === 'undefined') {
    return
  }

  const bufferMs = prefersReducedMotion.value ? 80 : 180
  fallbackTimer = window.setTimeout(
      completeTransition,
      effectiveDurationMs.value + bufferMs,
  )
}

const focusOverlay = async () => {
  if (typeof document === 'undefined') {
    return
  }

  previouslyFocusedElement = document.activeElement instanceof HTMLElement
      ? document.activeElement
      : null

  await nextTick()

  if (props.active) {
    overlay.value?.focus({ preventScroll: true })
  }
}

const restorePreviousFocus = async () => {
  const elementToRestore = previouslyFocusedElement
  previouslyFocusedElement = null

  if (!elementToRestore || typeof document === 'undefined') {
    return
  }

  await nextTick()

  if (!props.active && document.contains(elementToRestore)) {
    elementToRestore.focus({ preventScroll: true })
  }
}

const syncMotionPreference = (event) => {
  prefersReducedMotion.value = event.matches
}

watch(
    () => props.active,
    (isActive) => {
      clearFallbackTimer()

      if (isActive) {
        completedForActivation = false
        focusOverlay()
        scheduleFallback()
        return
      }

      completedForActivation = false
      restorePreviousFocus()
    },
    { immediate: true },
)

watch(effectiveDurationMs, () => {
  if (props.active && !completedForActivation) {
    scheduleFallback()
  }
})

onMounted(() => {
  motionPreferenceQuery = window.matchMedia('(prefers-reduced-motion: reduce)')
  prefersReducedMotion.value = motionPreferenceQuery.matches

  if (typeof motionPreferenceQuery.addEventListener === 'function') {
    motionPreferenceQuery.addEventListener('change', syncMotionPreference)
  } else {
    motionPreferenceQuery.addListener(syncMotionPreference)
  }
})

onBeforeUnmount(() => {
  clearFallbackTimer()

  if (!motionPreferenceQuery) {
    return
  }

  if (typeof motionPreferenceQuery.removeEventListener === 'function') {
    motionPreferenceQuery.removeEventListener('change', syncMotionPreference)
  } else {
    motionPreferenceQuery.removeListener(syncMotionPreference)
  }
})
</script>

<template>
  <Teleport to="body">
    <div
        v-if="active"
        ref="overlay"
        class="doctor-blog-transition"
        :style="overlayStyle"
        aria-busy="true"
        tabindex="-1"
        @animationend.self="completeTransition"
        @click.prevent.stop
        @contextmenu.prevent.stop
        @keydown.prevent.stop
        @pointerdown.stop
        @touchmove.prevent.stop
        @wheel.prevent.stop
    >
      <span class="doctor-blog-transition__status" role="status" aria-live="polite">
        Переходим в блог
      </span>

      <div
          class="doctor-blog-transition__wash"
          aria-hidden="true"
          @animationend.self="completeTransition"
      />

      <svg
          class="doctor-blog-transition__mark"
          viewBox="0 0 240 260"
          xmlns="http://www.w3.org/2000/svg"
          aria-hidden="true"
          focusable="false"
      >
        <defs>
          <clipPath id="doctor-blog-transition-flask-clip">
            <path d="M101 31h38v57l46 103c8 18-5 38-25 38H80c-20 0-33-20-25-38l46-103V31Z" />
          </clipPath>
        </defs>

        <g class="doctor-blog-transition__flask">
          <g clip-path="url(#doctor-blog-transition-flask-clip)">
            <rect
                class="doctor-blog-transition__liquid"
                x="48"
                y="143"
                width="144"
                height="92"
            />
            <path
                class="doctor-blog-transition__liquid-surface"
                d="M47 150c17-12 31 9 49 0s31-8 48 0 31-8 49 0v12H47v-12Z"
            />

            <circle class="doctor-blog-transition__bubble doctor-blog-transition__bubble--one" cx="91" cy="191" r="7" />
            <circle class="doctor-blog-transition__bubble doctor-blog-transition__bubble--two" cx="135" cy="204" r="5" />
            <circle class="doctor-blog-transition__bubble doctor-blog-transition__bubble--three" cx="158" cy="178" r="4" />
          </g>

          <path
              class="doctor-blog-transition__outline"
              d="M101 31h38v57l46 103c8 18-5 38-25 38H80c-20 0-33-20-25-38l46-103V31Z"
          />
          <path class="doctor-blog-transition__rim" d="M94 31h52" />
        </g>
      </svg>
    </div>
  </Teleport>
</template>

<style scoped>
.doctor-blog-transition {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: grid;
  place-items: center;
  width: 100vw;
  height: 100vh;
  height: 100dvh;
  overflow: hidden;
  overscroll-behavior: contain;
  touch-action: none;
  outline: none;
  isolation: isolate;
  background:
      linear-gradient(141.59deg, rgba(92, 61, 27, 0.7) 0%, rgba(120, 83, 46, 0.7) 100%),
      #ffffff;
  cursor: wait;
  animation: doctor-blog-transition-appear var(--doctor-blog-transition-duration) linear both;
}

.doctor-blog-transition__status {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  clip-path: inset(50%);
  white-space: nowrap;
  border: 0;
}

.doctor-blog-transition__wash {
  position: absolute;
  z-index: 0;
  inset: 0;
  background: linear-gradient(135deg, #a0ae9d 0%, #7b8b7a 100%);
  will-change: clip-path, opacity;
  animation: doctor-blog-transition-wash var(--doctor-blog-transition-duration) cubic-bezier(0.7, 0, 0.2, 1) both;
}

.doctor-blog-transition__mark {
  position: relative;
  z-index: 1;
  width: clamp(136px, 21vw, 224px);
  height: auto;
  overflow: visible;
  filter: drop-shadow(0 18px 30px rgb(34 20 8 / 0.2));
}

.doctor-blog-transition__flask {
  transform-box: fill-box;
  transform-origin: 50% 58%;
  will-change: transform, opacity;
  animation: doctor-blog-transition-flask var(--doctor-blog-transition-duration) cubic-bezier(0.4, 0, 0.2, 1) both;
}

.doctor-blog-transition__outline,
.doctor-blog-transition__rim {
  fill: none;
  stroke: #fff;
  stroke-width: 7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.doctor-blog-transition__liquid {
  fill: #78532e;
  animation: doctor-blog-transition-liquid var(--doctor-blog-transition-duration) ease-in-out both;
}

.doctor-blog-transition__liquid-surface {
  fill: rgb(255 255 255 / 0.32);
  transform-origin: center;
  animation: doctor-blog-transition-surface var(--doctor-blog-transition-duration) ease-in-out both;
}

.doctor-blog-transition__bubble {
  fill: rgb(255 255 255 / 0.72);
  transform-box: fill-box;
  transform-origin: center;
  will-change: transform, opacity;
}

.doctor-blog-transition__bubble--one {
  animation: doctor-blog-transition-bubble-one var(--doctor-blog-transition-duration) ease-in both;
}

.doctor-blog-transition__bubble--two {
  animation: doctor-blog-transition-bubble-two var(--doctor-blog-transition-duration) ease-in both;
}

.doctor-blog-transition__bubble--three {
  animation: doctor-blog-transition-bubble-three var(--doctor-blog-transition-duration) ease-in both;
}

@keyframes doctor-blog-transition-appear {
  0% {
    opacity: 0;
  }

  12%,
  100% {
    opacity: 1;
  }
}

@keyframes doctor-blog-transition-wash {
  0%,
  57% {
    opacity: 0;
    clip-path: circle(0 at 50% 53%);
  }

  58% {
    opacity: 1;
    clip-path: circle(0 at 50% 53%);
  }

  100% {
    opacity: 1;
    clip-path: circle(150vmax at 50% 53%);
  }
}

@keyframes doctor-blog-transition-flask {
  0% {
    opacity: 0;
    transform: translateY(18px) scale(0.9) rotate(-4deg);
  }

  14% {
    opacity: 1;
    transform: translateY(0) scale(1) rotate(-4deg);
  }

  32% {
    transform: translateY(-2px) scale(1) rotate(3deg);
  }

  50% {
    transform: translateY(1px) scale(1) rotate(-2deg);
  }

  68% {
    opacity: 1;
    transform: translateY(-1px) scale(1) rotate(1deg);
  }

  88%,
  100% {
    opacity: 0;
    transform: translateY(-4px) scale(1.04) rotate(0);
  }
}

@keyframes doctor-blog-transition-liquid {
  0%,
  32% {
    fill: #78532e;
  }

  68%,
  100% {
    fill: #7b8b7a;
  }
}

@keyframes doctor-blog-transition-surface {
  0%,
  100% {
    transform: translateX(-4px);
  }

  45% {
    transform: translateX(5px);
  }
}

@keyframes doctor-blog-transition-bubble-one {
  0%,
  12% {
    opacity: 0;
    transform: translateY(18px) scale(0.7);
  }

  48% {
    opacity: 0.85;
  }

  78%,
  100% {
    opacity: 0;
    transform: translateY(-52px) scale(1.08);
  }
}

@keyframes doctor-blog-transition-bubble-two {
  0%,
  24% {
    opacity: 0;
    transform: translateY(12px) scale(0.6);
  }

  54% {
    opacity: 0.75;
  }

  86%,
  100% {
    opacity: 0;
    transform: translateY(-48px) scale(1);
  }
}

@keyframes doctor-blog-transition-bubble-three {
  0%,
  8% {
    opacity: 0;
    transform: translateY(15px) scale(0.65);
  }

  38% {
    opacity: 0.7;
  }

  68%,
  100% {
    opacity: 0;
    transform: translateY(-38px) scale(1.1);
  }
}

@media (prefers-reduced-motion: reduce) {
  .doctor-blog-transition {
    animation: none;
  }

  .doctor-blog-transition__mark {
    display: none;
  }

  .doctor-blog-transition__wash {
    clip-path: none;
    animation-name: doctor-blog-transition-wash-reduced;
    animation-timing-function: ease;
  }
}

@keyframes doctor-blog-transition-wash-reduced {
  from {
    opacity: 0;
  }

  to {
    opacity: 1;
  }
}
</style>