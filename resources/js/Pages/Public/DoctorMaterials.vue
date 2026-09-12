<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import SiteSidebar from '@/Components/SiteSidebar.vue'
import DoctorTypeChips from '@/Components/DoctorTypeChips.vue'
import PublicFooter from '@/Components/PublicFooter.vue'
import { useDoctorMode } from '@/composables/useDoctorMode'
import '../../../css/main.css'

defineProps({
  categories: { type: Array, default: () => [] },
  materials: { type: Array, default: () => [] },
  seoMeta: { type: Object, default: () => ({ title: '', description: '', keywords: '' }) },
})

const { doctorsUrl } = useDoctorMode()

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
    <title>{{ seoMeta?.title || 'Документы для врачей — ALEX LAB' }}</title>
    <meta name="description" :content="seoMeta?.description || 'Документы лаборатории для специалистов'" />
    <meta name="keywords" :content="seoMeta?.keywords || 'документы для врачей, ALEX2'" />
  </Head>

  <main class="page-container site-sidebar-layout doctor-mode doctor-materials-page">
    <SiteSidebar class="doctor-materials-sidebar" :doctor-mode="true" />

    <section class="materials-shell" aria-labelledby="materials-title">
      <header class="materials-back-bar">
        <button class="materials-back-button" type="button" aria-label="Назад" @click="goBack">
          <img src="/assets/figma-demo-back.svg" alt="" width="24" height="24" />
          <span>Назад</span>
        </button>
      </header>

      <div class="materials-content">
        <p class="materials-kicker">Материалы для врачей</p>
        <h1 id="materials-title">Документы лаборатории</h1>
        <p class="materials-lead">
          Регистрационные документы, инструкции, бланки и результаты контроля качества — всё в одном месте.
        </p>

        <DoctorTypeChips active="documents" />

        <div v-if="categories.length" class="category-grid">
          <Link
            v-for="category in categories"
            :key="category.id"
            :href="doctorsUrl(`/materials/${category.slug}`)"
            class="category-card"
          >
            <div class="category-copy">
              <h2>{{ category.name }}</h2>
              <p v-if="category.description">{{ category.description }}</p>
            </div>
            <div class="category-meta">
              <span>{{ category.count_label }}</span>
              <img src="/assets/figma-arrow-right.svg" alt="" width="24" height="24" />
            </div>
          </Link>
        </div>

        <div v-else class="materials-list">
          <article v-for="(material, index) in materials" :key="material.title + index" class="material-card">
            <div class="material-copy">
              <h2>{{ material.title }}</h2>
              <p v-if="material.date">{{ material.date }}</p>
            </div>
            <a
              v-if="material.file_path"
              class="material-download"
              :href="material.file_path"
              download
              target="_blank"
              :aria-label="`Скачать: ${material.title}`"
            >
              <img src="/assets/figma-materials-download.svg" alt="" width="24" height="24" />
            </a>
          </article>
          <p v-if="materials.length === 0" class="materials-empty">Документы пока не загружены.</p>
        </div>
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
  color: #000; cursor: pointer; font-family: Helvetica, Arial, sans-serif; font-size: 17px;
}
.materials-back-button img { display: block; width: 24px; height: 24px; }
.materials-content { width: min(1115px, 100%); padding: 64px; display: flex; flex-direction: column; gap: 32px; }
.materials-kicker { margin: 0; font-family: Roboto, Arial, sans-serif; font-size: 16px; color: rgba(0,0,0,0.4); }
.materials-content h1 { margin: 0; font-family: Roboto, Arial, sans-serif; font-size: 42px; font-weight: 400; line-height: 1.15; }
.materials-lead { margin: 0; max-width: 700px; font-family: Roboto, Arial, sans-serif; font-size: 21px; line-height: 1.35; }
.category-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 33px; }
.category-card {
  display: flex; flex-direction: column; justify-content: space-between; min-height: 184px;
  padding: 32px; background: #fff; border: 1px solid #dfdfdf; color: inherit; text-decoration: none;
}
.category-copy { display: flex; flex-direction: column; gap: 12px; }
.category-copy h2 { margin: 0; font-family: Roboto, Arial, sans-serif; font-size: 24px; font-weight: 400; }
.category-copy p { margin: 0; font-size: 16px; color: rgba(0,0,0,0.6); }
.category-meta { display: flex; align-items: center; justify-content: space-between; margin-top: 24px; color: rgba(0,0,0,0.45); font-size: 16px; }
.category-meta img { display: block; width: 24px; height: 24px; }
.materials-list { display: flex; flex-direction: column; gap: 6px; }
.material-card {
  display: flex; align-items: center; justify-content: space-between; padding: 24px;
  background: #fff; border: 1px solid #dfdfdf;
}
.material-copy { display: flex; flex-direction: column; gap: 12px; }
.material-copy h2 { margin: 0; font-size: 21px; font-weight: 400; }
.material-copy p { margin: 0; font-size: 16px; color: rgba(0,0,0,0.6); }
.material-download { display: flex; width: 24px; height: 24px; }
.material-download img { width: 24px; height: 24px; }
.materials-empty { padding: 24px 0; font-size: 18px; color: rgba(0,0,0,0.5); }

@media (max-width: 1024px) {
  .doctor-materials-sidebar { display: none !important; }
  .materials-content { width: 100%; padding: 32px 16px 48px; }
  .materials-content h1 { font-size: 32px; }
  .category-grid { grid-template-columns: 1fr; gap: 16px; }
  .materials-back-button span { display: none; }
}
</style>
