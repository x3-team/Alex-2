<script setup>
import { Link } from '@inertiajs/vue3'
import { useDoctorMode } from '@/composables/useDoctorMode'

defineProps({
  active: { type: String, required: true },
})

const { doctorsUrl } = useDoctorMode()

const chips = [
  { key: 'all', label: 'Все', path: '/materials' },
  { key: 'articles', label: 'Статьи', path: '/materials?type=articles' },
  { key: 'videos', label: 'Видео', path: '/materials?type=videos' },
  { key: 'documents', label: 'Документы', path: '/materials?type=documents' },
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

@media (max-width: 1024px) {
  .doctor-type-chips {
    flex-wrap: nowrap;
    max-width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
  }
  .doctor-type-chips::-webkit-scrollbar {
    display: none;
  }
  .doctor-type-chip {
    flex-shrink: 0;
  }
}

.doctor-type-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  height: 32px;
  padding: 0 12px;
  border: 1px solid rgba(0, 0, 0, 0.4);
  border-radius: 8px;
  background: transparent;
  color: #000;
  font-family: Roboto, Arial, sans-serif;
  font-size: 14px;
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
