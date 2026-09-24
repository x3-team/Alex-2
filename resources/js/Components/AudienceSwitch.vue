<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useDoctorMode } from '@/composables/useDoctorMode'
import { AUDIENCE_COLOR, SWITCH_FLAG, hideAudienceVeil, showAudienceVeil } from '@/audienceTheme'

const props = defineProps({
  // floating — плашка поверх первого экрана, row — строка в мобильном меню
  variant: { type: String, default: 'floating' },
})

const page = usePage()
const { isDoctorMode, doctorsUrl } = useDoctorMode()

// Сервер отдаёт origin врачебного сайта; пациентский — тот же адрес без «doc.».
// На превью по пути /doctors origin пустой, и ссылки остаются относительными.
const doctorsOrigin = computed(
  () => String(page.props.site?.doctorsOrigin || '').replace(/\/+$/, '')
)

const switchTargets = computed(() => page.props.site?.switch || null)

const doctorsHome = computed(() => {
  if (switchTargets.value?.doctor) return switchTargets.value.doctor
  return doctorsOrigin.value ? `${doctorsOrigin.value}/` : doctorsUrl('/')
})

const patientsHome = computed(() => {
  if (switchTargets.value?.patient) return switchTargets.value.patient
  const origin = doctorsOrigin.value
  return origin ? `${origin.replace(/\/\/doc\./i, '//')}/` : '/'
})

const current = computed(() => (isDoctorMode.value ? 'doctor' : 'patient'))

const root = ref(null)
// До монтирования подсветку рисует сама половина: разметка из SSR должна
// выглядеть правильно и без JS. Бегунок вставляется уже на замеренное место.
const animated = ref(false)
// Переход left/width только после первого кадра и только по клику.
const ready = ref(false)
const highlighted = ref(null)
const thumb = ref({ left: 0, width: 0 })
const leaving = ref(false)

const shown = computed(() => highlighted.value || current.value)
const thumbStyle = computed(() => ({ left: `${thumb.value.left}px`, width: `${thumb.value.width}px` }))

const prefersReducedMotion = () =>
  typeof window !== 'undefined'
  && window.matchMedia('(prefers-reduced-motion: reduce)').matches

let motionFrame = 0

const armMotion = () => {
  window.cancelAnimationFrame(motionFrame)
  motionFrame = window.requestAnimationFrame(() => {
    motionFrame = window.requestAnimationFrame(() => {
      ready.value = true
    })
  })
}

// animate=false ставит бегунок сразу, без проезда. Так гидрация и ресайз
// не повторяют анимацию клика.
const measure = (animate = false) => {
  const el = root.value?.querySelector(`[data-audience="${shown.value}"]`)
  if (!el || el.offsetWidth <= 0) return
  const next = { left: el.offsetLeft, width: el.offsetWidth }
  if (next.left === thumb.value.left && next.width === thumb.value.width && animated.value) return
  if (!animate) {
    ready.value = false
    thumb.value = next
    animated.value = true
    armMotion()
    return
  }
  thumb.value = next
}

const onResize = () => measure(false)
let resizeObserver = null
// На десктопе длинная страница часто крутится не окном, а колонкой
// (overflow: auto). absolute от документа тогда стоит на месте.
// Сдвигаем плашку на тот же scrollTop, чтобы она уходила вместе с текстом
// и в покое оставалась там, где была.
let paneScroller = null

const syncPaneScroll = () => {
  if (!root.value || !paneScroller) return
  const y = paneScroller.scrollTop
  root.value.style.transform = y > 0 ? `translateY(${-y}px)` : ''
}

const bindPaneScroll = () => {
  if (props.variant !== 'floating') return
  if (document.documentElement.scrollHeight > window.innerHeight + 8) return
  const panes = [...document.querySelectorAll('body *')].filter((el) => {
    if (el.closest('.home-page-container, .site-sidebar')) return false
    const style = getComputedStyle(el)
    if (style.overflowY !== 'auto' && style.overflowY !== 'scroll') return false
    const rect = el.getBoundingClientRect()
    return rect.height > window.innerHeight * 0.5 && rect.width > 320
  })
  panes.sort((a, b) => (b.scrollHeight - b.clientHeight) - (a.scrollHeight - a.clientHeight))
  paneScroller = panes[0] || null
  if (!paneScroller) return
  paneScroller.addEventListener('scroll', syncPaneScroll, { passive: true })
}

// Возврат по «назад» отдаёт страницу из bfcache вместе с залитым экраном.
const onPageShow = (event) => {
  if (!event.persisted) return
  leaving.value = false
  highlighted.value = current.value
  hideAudienceVeil()
  nextTick(() => measure(false))
}

onMounted(async () => {
  highlighted.value = current.value
  await nextTick()
  measure(false)
  window.addEventListener('resize', onResize)
  window.addEventListener('pageshow', onPageShow)
  // Строка в меню до открытия имеет нулевую ширину: замеряем, когда её покажут.
  if (typeof ResizeObserver !== 'undefined' && root.value) {
    resizeObserver = new ResizeObserver(() => measure(false))
    resizeObserver.observe(root.value)
  }
  bindPaneScroll()
})

onBeforeUnmount(() => {
  window.cancelAnimationFrame(motionFrame)
  window.removeEventListener('resize', onResize)
  window.removeEventListener('pageshow', onPageShow)
  paneScroller?.removeEventListener('scroll', syncPaneScroll)
  resizeObserver?.disconnect()
})

const withFlag = (href) => href + (href.includes('?') ? '&' : '?') + SWITCH_FLAG

const navigate = (event, audience, href) => {
  // Cmd/Ctrl/Shift/Alt и средняя кнопка — обычное поведение ссылки.
  if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
    return
  }
  event.preventDefault()
  if (audience === current.value || leaving.value) {
    return
  }

  if (prefersReducedMotion()) {
    window.location.assign(withFlag(href))
    return
  }

  leaving.value = true
  highlighted.value = audience
  ready.value = true
  nextTick(() => measure(true))
  showAudienceVeil(AUDIENCE_COLOR[audience])
  window.setTimeout(() => window.location.assign(withFlag(href)), 340)
}
</script>

<template>
  <div
      ref="root"
      class="audience-switch"
      :class="[`audience-switch--${variant}`, { 'is-animated': animated, 'is-ready': ready }]"
      role="group"
      aria-label="Версия сайта"
  >
    <span
        v-if="animated"
        class="audience-switch__thumb"
        :class="shown === 'doctor' ? 'is-right' : 'is-left'"
        :style="thumbStyle"
        aria-hidden="true"
    ></span>

    <a
        data-audience="patient"
        class="audience-switch__option"
        :class="{ 'is-active': shown === 'patient' }"
        :href="patientsHome"
        :aria-current="current === 'patient' ? 'page' : undefined"
        @click="navigate($event, 'patient', patientsHome)"
    >
      <span class="audience-switch__dot" aria-hidden="true"></span>Пациентам
    </a>
    <a
        data-audience="doctor"
        class="audience-switch__option"
        :class="{ 'is-active': shown === 'doctor' }"
        :href="doctorsHome"
        :aria-current="current === 'doctor' ? 'page' : undefined"
        @click="navigate($event, 'doctor', doctorsHome)"
    >
      <span class="audience-switch__dot" aria-hidden="true"></span>Врачам
    </a>
  </div>
</template>
