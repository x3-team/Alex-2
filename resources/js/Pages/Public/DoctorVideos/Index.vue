<script setup>
import { Head, router } from '@inertiajs/vue3'
import SiteSidebar from '@/Components/SiteSidebar.vue'
import DoctorTypeChips from '@/Components/DoctorTypeChips.vue'
import DoctorVideoCard from '@/Components/DoctorVideoCard.vue'
import PublicFooter from '@/Components/PublicFooter.vue'
import '../../../css/main.css'

defineProps({
  videos: { type: Array, default: () => [] },
  videosMeta: { type: Object, default: () => ({}) },
})

const goBack = () => {
  if (window.history.length > 1) {
    window.history.back()
    return
  }
  router.visit('/')
}
</script>

<template>
  <Head>
    <title>{{ videosMeta.title || 'Видеолекции для врачей — ALEX LAB' }}</title>
    <meta name="description" :content="videosMeta.description || 'Видеолекции лаборатории ALEX²'" />
  </Head>

  <main class="page-container site-sidebar-layout doctor-mode doctor-materials-page">
    <SiteSidebar class="doctor-materials-sidebar" :doctor-mode="true" />

    <section class="materials-shell">
      <header class="materials-back-bar">
        <button class="materials-back-button" type="button" aria-label="Назад" @click="goBack">
          <img src="/assets/figma-demo-back.svg" alt="" width="24" height="24" />
          <span>Назад</span>
        </button>
      </header>

      <div class="materials-content">
        <p class="materials-kicker">Материалы для врачей</p>
        <h1>Видеолекции</h1>
        <p class="materials-lead">
          Разборы профилей ALEX² и клинических случаев. До клика — своя обложка, плеер подключается только после нажатия.
        </p>

        <DoctorTypeChips active="videos" />

        <div v-if="videos.length" class="videos-grid">
          <DoctorVideoCard v-for="video in videos" :key="video.id" :video="video" />
        </div>
        <p v-else class="materials-empty">Пока нет опубликованных видеолекций.</p>
      </div>
      <PublicFooter />
    </section>
  </main>
</template>

<style scoped>
.doctor-materials-page { background: #f5f5f5; }
.materials-shell { min-width: 0; height: 100dvh; overflow-x: hidden; overflow-y: auto; background: #f5f5f5; }
.materials-back-bar { width: 100%; height: 72px; padding: 24px 32px; background: #fff; border: 1px solid #dfdfdf; }
.materials-back-button {
  display: flex; align-items: center; gap: 8px; padding: 0; border: 0; background: transparent;
  color: #000; cursor: pointer; font-family: Helvetica, Arial, sans-serif; font-size: 17px; line-height: 24px;
}
.materials-back-button img { display: block; width: 24px; height: 24px; }
.materials-content { width: min(1115px, 100%); padding: 64px; display: flex; flex-direction: column; gap: 32px; }
.materials-kicker { margin: 0; font-family: Roboto, Arial, sans-serif; font-size: 16px; color: rgba(0,0,0,0.4); }
.materials-content h1 { margin: 0; font-family: Roboto, Arial, sans-serif; font-size: 42px; font-weight: 400; line-height: 1.15; color: #000; }
.materials-lead { margin: 0; max-width: 700px; font-family: Roboto, Arial, sans-serif; font-size: 21px; line-height: 1.35; color: #000; }
.videos-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 33px; }
.materials-empty { padding: 24px 0; font-size: 18px; color: rgba(0,0,0,0.5); }

@media (max-width: 1024px) {
  .doctor-materials-sidebar { display: none !important; }
  .materials-content { width: 100%; padding: 32px 16px 48px; gap: 24px; }
  .materials-content h1 { font-size: 32px; }
  .videos-grid { grid-template-columns: 1fr; gap: 24px; }
  .materials-back-button span { display: none; }
}
</style>
