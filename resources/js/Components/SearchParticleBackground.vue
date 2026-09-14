<template>
  <canvas ref="canvas" class="search-particle-background" aria-hidden="true" />
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'

type Particle = {
  x: number; y: number; vx: number; vy: number; r: number; depth: number
  tier: number; speed: number; warm: boolean; wobble: number; phase: number; glow: number
}

const canvas = ref<HTMLCanvasElement | null>(null)
const DESKTOP_COUNT = 351
const MOBILE_COUNT = 351
const MOBILE_BREAKPOINT = 768
const PUSH_RADIUS = 210
const PUSH_FORCE = 1.05
const SWIRL = 0.85
const WIND = { x: .62, y: .36 }
const MARGIN = 40

// 🟢 Зона чтения текста / строки поиска (координаты внутри Canvas)
const READ_ZONE = { x0: 200, x1: 840, y0: 210, y1: 480 }

let host: HTMLElement | null = null
let context: CanvasRenderingContext2D | null = null
let observer: IntersectionObserver | null = null
let sizeObserver: ResizeObserver | null = null
let frameId: number | null = null
let width = 1243
let height = 525
let time = 0
let lastFrame = 0
let isIntersecting = true
let mouse = { x: null as number | null, y: null as number | null }
let particles: Particle[] = []
let sprites: Record<string, HTMLCanvasElement[]> | null = null
let reducedMotion: MediaQueryList | null = null
let finePointer: MediaQueryList | null = null

const random = (index: number, salt: number) => Math.abs(Math.sin(index * 12.9898 + salt * 78.233) * 43758.5453) % 1

const createSprite = (rgb: string, blur: number, coreStop: number) => {
  const size = 351
  const sprite = document.createElement('canvas')
  sprite.width = sprite.height = size
  const spriteContext = sprite.getContext('2d')
  if (!spriteContext) return sprite
  const gradient = spriteContext.createRadialGradient(size / 2, size / 2, 0, size / 2, size / 2, size / 2)
  gradient.addColorStop(0, `rgba(${rgb}, 1)`)
  gradient.addColorStop(coreStop, `rgba(${rgb}, .55)`)
  gradient.addColorStop(1, `rgba(${rgb}, 0)`)
  spriteContext.filter = blur ? `blur(${blur}px)` : 'none'
  spriteContext.fillStyle = gradient
  spriteContext.beginPath()
  spriteContext.arc(size / 2, size / 2, size / 2 - blur * 2, 0, Math.PI * 2)
  spriteContext.fill()
  return sprite
}

const createSprites = () => ({
  sage: [createSprite('150, 170, 134', 0, .42), createSprite('150, 170, 134', 4, .34), createSprite('150, 170, 134', 10, .28)],
  deep: [createSprite('108, 132, 84', 0, .42), createSprite('108, 132, 84', 4, .34), createSprite('108, 132, 84', 10, .28)]
})

const particleCount = () => width < MOBILE_BREAKPOINT ? MOBILE_COUNT : DESKTOP_COUNT

const createParticles = () => {
  particles = Array.from({ length: particleCount() }, (_, index) => {
    const depth = random(index, 3)
    return {
      x: random(index, 1) * width, y: random(index, 2) * height, vx: 0, vy: 0,
      r: 1.2 + depth * 5.2, depth, tier: depth < .45 ? 0 : depth < .82 ? 1 : 2,
      speed: .22 + depth * .58, warm: random(index, 8) > .8,
      wobble: .35 + random(index, 9) * .5, phase: random(index, 10) * Math.PI * 2, glow: 0
    }
  })
}

const edgeFade = (x: number, y: number) => Math.max(0, Math.min(1, Math.min(x, y, width - x, height - y) / 60))

// 🟢 Новая функция: приглушение частиц за заголовком и строкой поиска
const textDim = (x: number, y: number) => {
  // На мобилках или респонсиве, если ширина сильно отличается от эталона (1243px),
  // масштабируем координаты области чтения
  const scaleX = width / 1243
  const scaleY = height / 525

  const x0 = READ_ZONE.x0 * scaleX
  const x1 = READ_ZONE.x1 * scaleX
  const y0 = READ_ZONE.y0 * scaleY
  const y1 = READ_ZONE.y1 * scaleY

  const fx = Math.min(x - (x0 - 50), (x1 + 50) - x)
  const fy = Math.min(y - (y0 - 40), (y1 + 40) - y)
  if (fx <= 0 || fy <= 0) return 1
  const soft = Math.min(1, Math.min(fx / 50, fy / 40))
  return 1 - 0.82 * soft
}

// 🟢 Обновлённый метод отрисовки
const paint = () => {
  if (!context || !sprites) return
  context.clearRect(0, 0, width, height)
  for (const particle of particles) {
    // Учитываем и края, и маску текста
    const maskModifier = textDim(particle.x, particle.y) * edgeFade(particle.x, particle.y)
    if (maskModifier <= 0.01) continue

    const baseAlpha = 0.15 + particle.depth * 0.2
    const alpha = Math.min(0.55, (baseAlpha + particle.glow * 0.22) * maskModifier)
    if (alpha < 0.012) continue

    const radius = particle.r * (2.6 + particle.tier * 0.9) * (1 + particle.glow * 0.16)
    context.globalAlpha = alpha
    context.drawImage(
        sprites[particle.warm ? 'deep' : 'sage'][particle.tier],
        particle.x - radius,
        particle.y - radius,
        radius * 2,
        radius * 2
    )
  }
  context.globalAlpha = 1
}

const step = (delta: number) => {
  const mouseIsActive = finePointer?.matches && mouse.x !== null && mouse.y !== null
  for (const particle of particles) {
    const targetX = (WIND.x + Math.sin(time * particle.wobble + particle.phase) * .22) * particle.speed
    const targetY = (WIND.y + Math.cos(time * particle.wobble * .7 + particle.phase) * .16) * particle.speed
    particle.vx += (targetX - particle.vx) * .035
    particle.vy += (targetY - particle.vy) * .035
    let near = 0
    if (mouseIsActive) {
      const dx = particle.x - mouse.x!
      const dy = particle.y - mouse.y!
      const distance = Math.hypot(dx, dy)
      if (distance < PUSH_RADIUS && distance > .001) {
        const falloff = 1 - distance / PUSH_RADIUS
        near = falloff
        const force = PUSH_FORCE * falloff ** 3 * (.4 + particle.depth)
        const swirl = SWIRL * falloff ** 3
        particle.vx += (dx / distance) * force + (-dy / distance) * swirl
        particle.vy += (dy / distance) * force + (dx / distance) * swirl
      }
    }
    particle.glow += (near - particle.glow) * .07
    particle.x += particle.vx * delta * 60
    particle.y += particle.vy * delta * 60
    const wrapWidth = width + MARGIN * 2
    const wrapHeight = height + MARGIN * 2
    particle.x = ((particle.x + MARGIN) % wrapWidth + wrapWidth) % wrapWidth - MARGIN
    particle.y = ((particle.y + MARGIN) % wrapHeight + wrapHeight) % wrapHeight - MARGIN
  }
}

const canAnimate = () => !reducedMotion?.matches && isIntersecting && !document.hidden && width > 0 && height > 0

const stop = () => {
  if (frameId !== null) cancelAnimationFrame(frameId)
  frameId = null
  lastFrame = 0
}

const animate = (now: number) => {
  if (!canAnimate()) return stop()
  const delta = Math.min((now - (lastFrame || now)) / 1000, .05)
  lastFrame = now
  time += delta
  step(delta)
  paint()
  frameId = requestAnimationFrame(animate)
}

const syncAnimation = () => {
  stop()
  paint()
  if (canAnimate()) frameId = requestAnimationFrame(animate)
}

const resize = () => {
  if (!canvas.value || !host || !context) return
  const bounds = host.getBoundingClientRect()
  const oldCount = particles.length
  width = Math.max(0, bounds.width)
  height = Math.max(0, bounds.height)
  const dpr = Math.min(window.devicePixelRatio || 1, 2)
  canvas.value.width = Math.round(width * dpr)
  canvas.value.height = Math.round(height * dpr)
  context.setTransform(dpr, 0, 0, dpr, 0, 0)
  if (!oldCount || oldCount !== particleCount()) createParticles()
  paint()
}

const onPointerMove = (event: PointerEvent) => {
  if (!canvas.value || !finePointer?.matches) return
  const bounds = canvas.value.getBoundingClientRect()
  mouse = { x: event.clientX - bounds.left, y: event.clientY - bounds.top }
}
const onPointerLeave = () => { mouse = { x: null, y: null } }
const onVisibilityChange = () => syncAnimation()
const onMotionChange = () => syncAnimation()

onMounted(() => {
  if (!canvas.value) return
  host = canvas.value.parentElement
  context = canvas.value.getContext('2d')
  if (!host || !context) return
  reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)')
  finePointer = window.matchMedia('(hover: hover) and (pointer: fine)')
  sprites = createSprites()
  resize()
  host.addEventListener('pointermove', onPointerMove)
  host.addEventListener('pointerleave', onPointerLeave)
  window.addEventListener('resize', resize)
  document.addEventListener('visibilitychange', onVisibilityChange)
  reducedMotion.addEventListener('change', onMotionChange)
  observer = new IntersectionObserver(([entry]) => {
    isIntersecting = entry.isIntersecting
    syncAnimation()
  })
  observer.observe(host)
  sizeObserver = new ResizeObserver(resize)
  sizeObserver.observe(host)
  syncAnimation()
})

onBeforeUnmount(() => {
  stop()
  observer?.disconnect()
  sizeObserver?.disconnect()
  window.removeEventListener('resize', resize)
  document.removeEventListener('visibilitychange', onVisibilityChange)
  reducedMotion?.removeEventListener('change', onMotionChange)
  host?.removeEventListener('pointermove', onPointerMove)
  host?.removeEventListener('pointerleave', onPointerLeave)
})
</script>

<style scoped>
.search-particle-background {
  position: absolute;
  inset: 0;
  display: block;
  width: 100%;
  height: 100%;
  pointer-events: none;
}
</style>