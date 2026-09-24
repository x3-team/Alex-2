<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useDoctorMode } from '@/composables/useDoctorMode'
import { AUDIENCE_COLOR, SWITCH_FLAG, hideAudienceVeil, showAudienceVeil } from '@/audienceTheme'

defineProps({
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

const doctorsHome = computed(
  () => (doctorsOrigin.value ? `${doctorsOrigin.value}/` : doctorsUrl('/'))
)

const patientsHome = computed(() => {
  const origin = doctorsOrigin.value
  return origin ? `${origin.replace(/\/\/doc\./i, '//')}/` : '/'
})

const current = computed(() => (isDoctorMode.value ? 'doctor' : 'patient'))

const root = ref(null)
// До монтирования подсветку рисует сама половина: разметка из SSR должна
// выглядеть правильно и без JS. Бегунок включается только после замера.
const animated = ref(false)
const highlighted = ref(null)
const thumb = ref({ left: 0, width: 0 })
const leaving = ref(false)

const shown = computed(() => highlighted.value || current.value)
const thumbStyle = computed(() => ({ left: `${thumb.value.left}px`, width: `${thumb.value.width}px` }))

const prefersReducedMotion = () =>
  typeof window !== 'undefined'
  && window.matchMedia('(prefers-reduced-motion: reduce)').matches

const measure = () => {
  const el = root.value?.querySelector(`[data-audience="${shown.value}"]`)
  if (el) {
    thumb.value = { left: el.offsetLeft, width: el.offsetWidth }
  }
}

const onResize = () => measure()
let resizeObserver = null

// Возврат по «назад» отдаёт страницу из bfcache вместе с залитым экраном.
const onPageShow = (event) => {
  if (!event.persisted) return
  leaving.value = false
  highlighted.value = current.value
  hideAudienceVeil()
  nextTick(measure)
}

onMounted(async () => {
  highlighted.value = current.value
  animated.value = true
  await nextTick()
  measure()
  window.addEventListener('resize', onResize)
  window.addEventListener('pageshow', onPageShow)
  // Строка в меню до открытия имеет нулевую ширину: замеряем, когда её покажут.
  if (typeof ResizeObserver !== 'undefined' && root.value) {
    resizeObserver = new ResizeObserver(() => measure())
    resizeObserver.observe(root.value)
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', onResize)
  window.removeEventListener('pageshow', onPageShow)
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
  nextTick(measure)
  showAudienceVeil(AUDIENCE_COLOR[audience])
  window.setTimeout(() => window.location.assign(withFlag(href)), 340)
}
</script>

<template>
  <div
      ref="root"
      class="audience-switch"
      :class="[`audience-switch--${variant}`, { 'is-animated': animated }]"
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
