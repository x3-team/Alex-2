<script setup>
defineProps({
  /**
   * 'mouse' reproduces the desktop hint. The rest are touch variants, where a
   * scroll wheel has no meaning.
   */
  glyph: {
    type: String,
    default: 'dot',
    validator: (v) => ['mouse', 'chevrons', 'dot', 'dot-chevrons'].includes(v),
  },
});
</script>

<template>
  <div class="hint" :class="`hint--${glyph}`">
    <!-- Wheel version: glyph on top, chevrons flowing down. -->
    <svg v-if="glyph === 'mouse'" class="hint__glyph" width="74" height="74" viewBox="0 0 74 74" fill="none">
      <path
        d="M37 18.5013V30.8346M37 6.16797C48.9201 6.16797 58.5833 15.8312 58.5833 27.7513V46.2513C58.5833 58.1714 48.9201 67.8346 37 67.8346C25.0798 67.8346 15.4166 58.1714 15.4166 46.2513V27.7513C15.4166 15.8312 25.0798 6.16797 37 6.16797Z"
        stroke="black"
        stroke-opacity="0.8"
        stroke-width="5"
        stroke-linecap="round"
        stroke-linejoin="round"
      />
    </svg>

    <!-- A dot that runs bottom to top leaving a fading tail: it does not
         describe the gesture, it performs it. -->
    <span v-if="glyph === 'dot' || glyph === 'dot-chevrons'" class="hint__swipe" aria-hidden="true">
      <span class="hint__trail" />
      <span class="hint__head" />
    </span>

    <div v-if="glyph !== 'dot'" class="hint__chevrons">
      <svg
        v-for="i in 3"
        :key="i"
        class="hint__chevron"
        :class="`hint__chevron--${i}`"
        width="32"
        height="13"
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
  </div>
</template>

<style scoped>
.hint {
  position: relative;
  height: 119px;
}

.hint--mouse {
  width: 74px;
}

/* Without a glyph there is nothing to centre the chevrons under, so the 21px
   indent the site uses would leave them hanging off the text edge. These
   variants are flush left instead, aligning with the headline below. */
.hint--chevrons,
.hint--dot,
.hint--dot-chevrons {
  width: 32px;
}

.hint__glyph {
  position: absolute;
  left: 0;
  top: 0;
  width: 74px;
  height: 74px;
}

.hint__chevrons {
  position: absolute;
  width: 32px;
  height: 43px;
}

.hint__chevron {
  position: absolute;
  left: 0;
  width: 32px;
  height: 13px;
}

/* Mouse: chevrons below the glyph, centred under it, cascading down. */
.hint--mouse .hint__chevrons {
  left: 21px;
  top: 76px;
}
.hint--mouse .hint__chevron--1 {
  top: 0;
}
.hint--mouse .hint__chevron--2 {
  top: 15px;
}
.hint--mouse .hint__chevron--3 {
  top: 30px;
}

/* Touch: chevrons at the top, rotated, cascading up. Chevron 1 stays the
   lowest, so the site's existing 1-2-3 pulse travels upward untouched. */
.hint--chevrons .hint__chevrons,
.hint--dot-chevrons .hint__chevrons {
  left: 0;
  top: 0;
}

.hint--chevrons .hint__chevron,
.hint--dot-chevrons .hint__chevron {
  transform: rotate(180deg);
}

.hint--chevrons .hint__chevron--1,
.hint--dot-chevrons .hint__chevron--1 {
  top: 30px;
}
.hint--chevrons .hint__chevron--2,
.hint--dot-chevrons .hint__chevron--2 {
  top: 15px;
}
.hint--chevrons .hint__chevron--3,
.hint--dot-chevrons .hint__chevron--3 {
  top: 0;
}

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

/* ---------------------------------------------------------------------------
   Swipe dot
   The head is a solid dot; the trail is a bar with a gradient that fades away
   from it, so the pair reads as one moving object rather than two elements.
   --------------------------------------------------------------------------- */

.hint__swipe {
  position: absolute;
  bottom: 0;
  width: 9px;
  height: 84px;
}

/* Dot only: flush left, so it lines up with the headline below. */
.hint--dot .hint__swipe {
  left: 0;
  width: 9px;
  height: 96px;
}

/* Alongside chevrons the dot sits under their centre. */
.hint--dot-chevrons .hint__swipe {
  left: 11px;
  height: 60px;
}

/* The trail is as wide as the head so the pair reads as one moving object;
   a narrower bar turns the pair into a pin in any still frame. */
.hint__trail {
  position: absolute;
  left: 0;
  bottom: 0;
  width: 9px;
  height: 72px;
  border-radius: 5px;
  background: linear-gradient(
    to top,
    rgba(0, 0, 0, 0) 0%,
    rgba(0, 0, 0, 0.12) 30%,
    rgba(0, 0, 0, 0.42) 100%
  );
  transform-origin: bottom center;
  animation: swipe-trail 2.4s cubic-bezier(0.33, 0, 0.2, 1) infinite;
}

.hint--dot-chevrons .hint__trail {
  height: 40px;
}

.hint__head {
  position: absolute;
  left: 0;
  bottom: 0;
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.8);
  animation: swipe-head 2.4s cubic-bezier(0.33, 0, 0.2, 1) infinite;
}

/* The head leads, the trail stretches to follow it, then both release at the
   top and the field rests before the next pass. */
@keyframes swipe-head {
  0% {
    transform: translateY(0);
    opacity: 0;
  }
  8% {
    transform: translateY(0);
    opacity: 1;
  }
  58% {
    transform: translateY(calc(-1 * var(--swipe-travel, 58px)));
    opacity: 1;
  }
  74% {
    transform: translateY(calc(-1 * var(--swipe-travel, 58px) - 14px));
    opacity: 0;
  }
  100% {
    transform: translateY(calc(-1 * var(--swipe-travel, 58px) - 14px));
    opacity: 0;
  }
}

@keyframes swipe-trail {
  0% {
    transform: scaleY(0);
    opacity: 0;
  }
  8% {
    transform: scaleY(0);
    opacity: 1;
  }
  58% {
    transform: scaleY(1);
    opacity: 1;
  }
  74% {
    transform: scaleY(1);
    opacity: 0;
  }
  100% {
    transform: scaleY(0);
    opacity: 0;
  }
}

.hint--dot .hint__head,
.hint--dot .hint__trail {
  --swipe-travel: 72px;
}

.hint--dot-chevrons .hint__head,
.hint--dot-chevrons .hint__trail {
  --swipe-travel: 40px;
}

@media (prefers-reduced-motion: reduce) {
  .hint__chevron,
  .hint__trail,
  .hint__head {
    animation: none;
  }

  .hint__chevron {
    opacity: 0.6;
  }

  .hint__trail {
    transform: scaleY(1);
    opacity: 1;
  }
}
</style>
