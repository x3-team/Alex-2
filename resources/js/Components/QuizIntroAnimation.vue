<template>
  <div ref="animationContainer" class="quiz-intro-animation" :class="`quiz-intro-animation--${variant}`" aria-hidden="true">
    <template v-if="variant === 'pollen'">
      <i
          v-for="particle in pollenParticles"
          :key="particle.id"
          class="pollen-particle"
          :class="{ 'pollen-particle--dark': particle.dark }"
          :style="particle.style"
      />
    </template>

    <template v-else-if="variant === 'dots'">
      <div class="allergen-grid">
        <b
            v-for="dot in allergenDots"
            :key="dot.id"
            :class="{ 'allergen-dot--live': dot.live }"
            :style="dot.style"
        />
      </div>
      <span class="allergen-caption">более 300 аллергенов — один забор крови</span>
    </template>

    <template v-else-if="variant === 'photo'">
      <div class="photo-image" />
      <i
          v-for="particle in photoDust"
          :key="particle.id"
          class="photo-dust-particle"
          :style="particle.style"
      />
    </template>

    <svg v-else class="topographic-lines" viewBox="0 0 677 352" preserveAspectRatio="none" aria-hidden="true">
      <g v-for="group in topographicLineGroups" :key="group.id" :style="{ animationDelay: group.delay }">
        <path
            v-for="line in group.lines"
            :key="line.id"
            :d="line.path"
            :stroke="line.color"
            :stroke-width="line.width"
        />
      </g>
    </svg>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'

type AnimationVariant = 'pollen' | 'dots' | 'photo' | 'topo'
type PollenParticle = { id: number; dark: boolean; style: Record<string, string> }
type PhotoDustParticle = { id: number; style: Record<string, string> }

const props = defineProps<{ variant: AnimationVariant }>()
const animationContainer = ref<HTMLElement | null>(null)

const W = 760
const H = 352

const createPollenParticles = (width: number, height: number): PollenParticle[] => Array.from({ length: 64 }, (_, id) => {
  const size = 5 + Math.abs(Math.sin(id * 12.9898)) * 22
  const duration = 9 + (id % 7) * 1.6
  const phase = Math.abs(Math.sin(id * 17.13)) * duration
  const dark = id % 7 === 0

  return {
    id,
    dark,
    style: {
      width: `${size}px`,
      height: `${size}px`,
      left: `${Math.abs(Math.sin(id * 78.233)) * (width - 10) - size / 2}px`,
      top: `${Math.abs(Math.cos(id * 43.512)) * (height - 10) - size / 2}px`,
      filter: `blur(${id % 3 === 0 ? 8 : 0}px)`,
      '--particle-opacity': dark ? '0.6' : String(0.55 + Math.abs(Math.sin(id)) * 0.4),
      animationDuration: `${duration}s`,
      animationDelay: `-${phase.toFixed(2)}s`
    }
  }
})

const pollenParticles = ref<PollenParticle[]>([])

const photoDust: PhotoDustParticle[] = Array.from({ length: 30 }, (_, id) => {
  const near = id % 3 === 0
  const size = near ? 7 + Math.abs(Math.sin(id * 9.7)) * 11 : 2 + Math.abs(Math.sin(id * 5.3)) * 3
  const duration = (near ? 9 : 15) + (id % 5) * 1.7

  return {
    id,
    style: {
      width: `${size}px`,
      height: `${size}px`,
      left: `${Math.abs(Math.sin(id * 61.7)) * 660 / W * 100}%`,
      top: `${(60 + Math.abs(Math.cos(id * 27.3)) * 260) / H * 100}%`,
      filter: `blur(${near ? 4 : 0.6}px)`,
      '--particle-opacity': near ? '0.5' : '0.85',
      animationDuration: `${duration}s`,
      animationDelay: `-${(Math.abs(Math.sin(id * 13.9)) * duration).toFixed(2)}s`
    }
  }
})

onMounted(() => {
  if (props.variant !== 'pollen' || !animationContainer.value) return
  pollenParticles.value = createPollenParticles(animationContainer.value.clientWidth, animationContainer.value.clientHeight)
})

const allergenDots = Array.from({ length: 300 }, (_, id) => {
  const live = Math.abs(Math.sin(id * 7.13)) > 0.962
  return {
    id,
    live,
    style: live
        ? { animationDelay: `-${(Math.abs(Math.sin(id * 31.7)) * 7.5).toFixed(2)}s` }
        : undefined
  }
})

const topographicLines = Array.from({ length: 18 }, (_, id) => {
  const base = 24 + id * 20
  const points = Array.from({ length: 151 }, (_, point) => {
    const angle = (point / 150) * Math.PI * 2
    const wobble = 1 + 0.14 * Math.sin(3 * angle + id * 0.5) + 0.06 * Math.sin(5 * angle - id * 0.3) + 0.035 * Math.sin(8 * angle + id * 0.85)
    const radius = base * wobble
    const x = W * 0.4 + radius * Math.cos(angle) * 1.45
    const y = H * 0.46 + radius * Math.sin(angle)
    return `${point === 0 ? 'M' : 'L'}${x.toFixed(1)} ${y.toFixed(1)}`
  }).join(' ')

  return {
    id,
    path: `${points} Z`,
    color: id < 4 ? '#4F6B3C' : id < 10 ? '#7C9464' : '#B4C3A4',
    width: id < 4 ? 1.6 : 1.2,
    delay: `-${(9 - id * 0.34).toFixed(2)}s`
  }
})

const topographicLineGroups = Array.from({ length: 6 }, (_, id) => ({
  id,
  delay: `-${(9 - id * 3 * 0.34).toFixed(2)}s`,
  lines: topographicLines.slice(id * 3, id * 3 + 3)
}))
</script>

<style scoped>
.quiz-intro-animation {
  position: absolute;
  inset: 0 0 auto;
  height: min(352px, calc(100% - 168px));
  min-height: 176px;
  overflow: hidden;
  pointer-events: none;
}

.quiz-intro-animation--pollen {
  background: linear-gradient(180deg, #b9c9ac 0%, #fff 100%);
}

.pollen-particle {
  position: absolute;
  aspect-ratio: 1;
  border-radius: 50%;
  background: #fff;
  animation-timing-function: linear;
  animation-iteration-count: infinite;
}

.pollen-particle {
  animation-name: pollen-drift;
}

.pollen-particle--dark {
  background: #4f6b3c;
}

@keyframes pollen-drift {
  0% { margin-top: 44px; margin-left: 0; opacity: 0; }
  15% { opacity: var(--particle-opacity); }
  85% { opacity: var(--particle-opacity); }
  100% { margin-top: -120px; margin-left: 26px; opacity: 0; }
}

.quiz-intro-animation--dots {
  background: #f4f6f0;
}

.allergen-grid {
  position: absolute;
  top: 38px;
  left: 82px; /* 👈 Фиксированный отступ 82px слева, как в Figma */
  width: 517px; /* 👈 Фиксированная ширина сетки (25 колонок * 8px + 24 * 13px gap) */
  display: grid;
  grid-template-columns: repeat(25, 8px);
  grid-auto-rows: 8px;
  gap: 13px;
}

.allergen-grid b {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #c9d3be;
}

.allergen-grid .allergen-dot--live {
  animation: allergen-blip 7.5s cubic-bezier(.45, 0, .55, 1) infinite;
}

@keyframes allergen-blip {
  0%, 24% { background: #c9d3be; box-shadow: 0 0 0 0 rgb(79 107 60 / 0%); }
  44%, 60% { background: #4f6b3c; box-shadow: 0 0 0 6px rgb(79 107 60 / 20%); }
  100% { background: #c9d3be; box-shadow: 0 0 0 0 rgb(79 107 60 / 0%); }
}

.allergen-caption {
  position: absolute;
  bottom: 30px;
  left: 82px;
  color: #6b7a5e;
  font-size: 13px;
  letter-spacing: .26px;
}

.quiz-intro-animation--photo {
  background: #dce3d4;
}

.photo-image {
  position: absolute;
  inset: 0;
  background: url('/assets/quiz-animation-photo.webp') 62% 38% / 112% auto no-repeat;
}

.photo-dust-particle {
  position: absolute;
  border-radius: 50%;
  background: #fff;
  animation: photo-dust-drift linear infinite;
}

@keyframes photo-dust-drift {
  0% { margin-top: 60px; margin-left: 0; opacity: 0; }
  20% { opacity: var(--particle-opacity); }
  80% { opacity: var(--particle-opacity); }
  100% { margin-top: -150px; margin-left: 40px; opacity: 0; }
}

.quiz-intro-animation--topo {
  background: linear-gradient(90deg, #f7f8f4 0%, #e8ede2 100%);
}

.topographic-lines {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}

.topographic-lines g {
  stroke-opacity: .32;
  animation: topo-scan 9s linear infinite;
}

.topographic-lines path {
  fill: none;
}

@keyframes topo-scan {
  0%, 10% { stroke-opacity: .32; }
  17% { stroke-opacity: 1; }
  30%, 100% { stroke-opacity: .32; }
}

@media (max-width: 600px) {
  .allergen-grid {
    transform: translateX(-50%) scale(.62);
    transform-origin: top center;
    left: 24px;
  }

  .allergen-caption {
    bottom: 18px;
    left: 24px;
    font-size: 11px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .pollen-particle,
  .allergen-grid .allergen-dot--live,
  .photo-dust-particle,
  .topographic-lines g {
    animation: none !important;
  }
}
</style>