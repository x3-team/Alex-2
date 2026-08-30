<script setup>
defineProps({
  /**
   * 'mouse' reproduces what is on the site today. The rest are swipe glyphs for
   * touch, where a scroll wheel has no meaning.
   */
  glyph: {
    type: String,
    default: 'phone',
    validator: (v) => ['mouse', 'phone', 'hand', 'none'].includes(v),
  },
});
</script>

<template>
  <!-- Chevrons sit above the glyph and pulse upward, because the gesture that
       advances the story is a swipe up. On the wheel version they sit below and
       pulse down. -->
  <div class="hint" :class="glyph === 'mouse' ? 'hint--down' : 'hint--up'">
    <div class="hint__chevrons">
      <svg
        v-for="i in 3"
        :key="i"
        class="hint__chevron"
        :class="`hint__chevron--${i}`"
        width="36"
        height="17"
        viewBox="-3 -3 36 17"
        fill="none"
      >
        <path
          d="M0 0L15 11L30 0"
          stroke="black"
          stroke-width="5"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </svg>
    </div>

    <svg class="hint__glyph" width="74" height="74" viewBox="0 0 74 74" fill="none">
      <!-- Existing site asset, kept verbatim for comparison -->
      <path
        v-if="glyph === 'mouse'"
        d="M37 18.5013V30.8346M37 6.16797C48.9201 6.16797 58.5833 15.8312 58.5833 27.7513V46.2513C58.5833 58.1714 48.9201 67.8346 37 67.8346C25.0798 67.8346 15.4166 58.1714 15.4166 46.2513V27.7513C15.4166 15.8312 25.0798 6.16797 37 6.16797Z"
        stroke="black"
        stroke-opacity="0.8"
        stroke-width="5"
        stroke-linecap="round"
        stroke-linejoin="round"
      />

      <!-- Phone outline with an arrow inside: mirrors how the mouse is built,
           a recognisable device plus an inner cue. -->
      <template v-else-if="glyph === 'phone'">
        <rect
          x="21"
          y="7"
          width="32"
          height="60"
          rx="8"
          stroke="black"
          stroke-opacity="0.8"
          stroke-width="5"
        />
        <path
          d="M37 51V29M30 36L37 29L44 36"
          stroke="black"
          stroke-opacity="0.8"
          stroke-width="5"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </template>

      <!-- Hand silhouette with the index finger raised. -->
      <template v-else-if="glyph === 'hand'">
        <path
          d="M28 44V23C28 19.134 31.134 16 35 16C38.866 16 42 19.134 42 23V44"
          stroke="black"
          stroke-opacity="0.8"
          stroke-width="5"
          stroke-linecap="round"
        />
        <path
          d="M24 44H48C54.0751 44 59 48.9249 59 55V57C59 63.0751 54.0751 68 48 68H35C28.9249 68 24 63.0751 24 57V44Z"
          stroke="black"
          stroke-opacity="0.8"
          stroke-width="5"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </template>
    </svg>
  </div>
</template>

<style scoped>
.hint {
  position: relative;
  width: 74px;
  height: 119px;
}

.hint__glyph {
  position: absolute;
  left: 0;
  width: 74px;
  height: 74px;
}

.hint__chevrons {
  position: absolute;
  left: 21px;
  width: 32px;
  height: 45px;
}

.hint__chevron {
  position: absolute;
  left: 0;
  width: 32px;
  height: 13px;
}

/* Wheel version, as on the site today: glyph on top, chevrons flowing down. */
.hint--down .hint__glyph {
  top: 0;
}

.hint--down .hint__chevrons {
  top: 76px;
}

.hint--down .hint__chevron--1 {
  top: 0;
}
.hint--down .hint__chevron--2 {
  top: 15px;
}
.hint--down .hint__chevron--3 {
  top: 30px;
}

/* Swipe version: glyph at the bottom where the thumb starts, chevrons above it
   flowing up. Chevron 1 is the lowest, so the site's existing 1-2-3 cascade
   travels upward without touching the keyframes. */
.hint--up .hint__chevrons {
  top: 0;
}

.hint--up .hint__glyph {
  top: 45px;
}

.hint--up .hint__chevron--1 {
  top: 30px;
}
.hint--up .hint__chevron--2 {
  top: 15px;
}
.hint--up .hint__chevron--3 {
  top: 0;
}

.hint--up .hint__chevron {
  transform: rotate(180deg);
}

/* Keyframes copied from the site so the rhythm is identical. */
.hint__chevron--1 {
  animation: chevron-pulse-1 2.4s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}
.hint__chevron--2 {
  animation: chevron-pulse-2 2.4s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}
.hint__chevron--3 {
  animation: chevron-pulse-3 2.4s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

@keyframes chevron-pulse-1 {
  0%,
  100% {
    opacity: 1;
  }
  33% {
    opacity: 0.2;
  }
  66% {
    opacity: 0.6;
  }
}

@keyframes chevron-pulse-2 {
  0%,
  100% {
    opacity: 0.6;
  }
  33% {
    opacity: 1;
  }
  66% {
    opacity: 0.2;
  }
}

@keyframes chevron-pulse-3 {
  0%,
  100% {
    opacity: 0.2;
  }
  33% {
    opacity: 0.6;
  }
  66% {
    opacity: 1;
  }
}

@media (prefers-reduced-motion: reduce) {
  .hint__chevron {
    animation: none;
  }
  .hint--up .hint__chevron--1,
  .hint--up .hint__chevron--2,
  .hint--up .hint__chevron--3 {
    opacity: 0.6;
  }
}
</style>
