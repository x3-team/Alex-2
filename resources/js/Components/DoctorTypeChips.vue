<script setup>
import { Link } from '@inertiajs/vue3'
import { useDoctorMode } from '@/composables/useDoctorMode'

defineProps({
  active: { type: String, required: true },
})

const { doctorsUrl } = useDoctorMode()

const chips = [
  { key: 'all', label: 'Все', path: '/blog' },
  { key: 'articles', label: 'Статьи', path: '/blog?type=articles' },
  { key: 'videos', label: 'Видео', path: '/blog?type=videos' },
  { key: 'documents', label: 'Документы', path: '/materials/documents' },
]
</script>

<template>
  <div class="doctor-type-chips" role="navigation" aria-label="Тип материала">
    <Link
      v-for="chip in chips"
      :key="chip.key"
      :href="doctorsUrl(chip.path)"
      class="doctor-type-chip"
      :class="{ 'is-active': active === chip.key }"
    >
      {{ chip.label }}
    </Link>
  </div>
</template>

<style scoped>
.doctor-type-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
}

.doctor-type-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  height: 43px;
  padding: 0 24px;
  border: 1px solid rgba(0, 0, 0, 0.4);
  border-radius: 8px;
  background: transparent;
  color: #000;
  font-family: Roboto, Arial, sans-serif;
  font-size: 16px;
  font-weight: 500;
  line-height: 1;
  text-decoration: none;
  white-space: nowrap;
}

.doctor-type-chip.is-active {
  background: #000;
  color: #f5f5f5;
}
</style>
