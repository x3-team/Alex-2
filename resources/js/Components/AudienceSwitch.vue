<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useDoctorMode } from '@/composables/useDoctorMode'

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
</script>

<template>
  <div class="audience-switch" :class="`audience-switch--${variant}`" role="group" aria-label="Версия сайта">
    <a
        class="audience-switch__option"
        :class="{ 'is-active': !isDoctorMode }"
        :href="patientsHome"
        :aria-current="!isDoctorMode ? 'page' : undefined"
    >
      <span class="audience-switch__dot" aria-hidden="true"></span>Пациентам
    </a>
    <a
        class="audience-switch__option"
        :class="{ 'is-active': isDoctorMode }"
        :href="doctorsHome"
        :aria-current="isDoctorMode ? 'page' : undefined"
    >
      <span class="audience-switch__dot" aria-hidden="true"></span>Врачам
    </a>
  </div>
</template>
