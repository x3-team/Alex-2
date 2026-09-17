<script setup>
import { Head, Link } from '@inertiajs/vue3'
import DoctorPublicShell from '@/Components/DoctorPublicShell.vue'
import DoctorTypeChips from '@/Components/DoctorTypeChips.vue'
import DoctorBreadcrumbIcon from '@/Components/DoctorBreadcrumbIcon.vue'
import DoctorVideoCard from '@/Components/DoctorVideoCard.vue'
import { useDoctorMode } from '@/composables/useDoctorMode'

defineProps({
  videos: { type: Array, default: () => [] },
  videosMeta: { type: Object, default: () => ({}) },
})

const { doctorsUrl } = useDoctorMode()
</script>

<template>
  <Head>
    <title>{{ videosMeta.title || 'Видеолекции для врачей — ALEX LAB' }}</title>
    <meta name="description" :content="videosMeta.description || 'Видеолекции лаборатории ALEX²'" />
  </Head>

  <DoctorPublicShell :back-href="doctorsUrl('/')" back-label="На главную">
    <div
      class="breadcrumbs flex items-center gap-3 mb-6"
      style="display: flex; flex-direction: row; justify-content: flex-start; align-items: center; padding: 0px; gap: 12px; min-height: 48px;"
    >
      <DoctorBreadcrumbIcon />
      <Link :href="doctorsUrl('/')" class="flex-shrink-0 text-[14px] xl:text-[18px] text-black opacity-30">Главная</Link>
      <span class="text-black opacity-[0.3]">
        <svg width="6" height="9" viewBox="0 0 6 9" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M0.75 8.25L4.5 4.5L0.75 0.75" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </span>
      <Link :href="doctorsUrl('/blog')" class="flex-shrink-0 text-[14px] xl:text-[18px] text-black opacity-30">Материалы для врачей</Link>
      <span class="text-black opacity-[0.3]">
        <svg width="6" height="9" viewBox="0 0 6 9" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M0.75 8.25L4.5 4.5L0.75 0.75" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </span>
      <span class="text-[14px] xl:text-[18px] text-black">Видео</span>
    </div>

    <div class="mb-4 max-w-full xl:max-w-[700px]">
      <h1 class="font-400 text-[28px] sm:text-[34px] xl:text-[42px]">Видеолекции</h1>
      <p class="mt-3 text-[16px] xl:text-[21px] text-black" style="line-height: 1.35">
        Разборы профилей ALEX² и клинических случаев. До клика — своя обложка, плеер подключается только после нажатия.
      </p>
    </div>

    <div class="mb-6 xl:mb-8 pt-[1rem]" :style="{ borderTop: '1px solid rgba(0, 0, 0, 0.3)' }">
      <div class="text-[16px] xl:text-[18px] text-[rgba(0, 0, 0, 1)] mb-2 opacity-[0.5] font-400">Тип материала</div>
      <DoctorTypeChips active="videos" />
    </div>

    <div v-if="videos.length" class="videos-grid">
      <DoctorVideoCard v-for="video in videos" :key="video.id" :video="video" />
    </div>
    <p v-else class="materials-empty">Пока нет опубликованных видеолекций.</p>
  </DoctorPublicShell>
</template>

<style scoped>
.videos-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 33px; }
.materials-empty { padding: 24px 0; font-size: 18px; color: rgba(0, 0, 0, 0.5); }

@media (max-width: 1024px) {
  .videos-grid { grid-template-columns: 1fr; gap: 24px; }
}
</style>
