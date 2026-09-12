<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import SiteSidebar from '@/Components/SiteSidebar.vue'
import PublicFooter from '@/Components/PublicFooter.vue'
import { useDoctorMode } from '@/composables/useDoctorMode'
import '../../../css/main.css'

defineProps({
  category: { type: Object, required: true },
  materials: { type: Array, default: () => [] },
  otherCategories: { type: Array, default: () => [] },
  seoMeta: { type: Object, default: () => ({}) },
})

const { doctorsUrl } = useDoctorMode()

const goBack = () => {
  router.visit(doctorsUrl('/materials'))
}
</script>

<template>
  <Head>
    <title>{{ category.name }} — документы ALEX LAB</title>
    <meta name="description" :content="category.description || seoMeta.description || category.name" />
  </Head>

  <main class="page-container site-sidebar-layout doctor-mode doctor-materials-page">
    <SiteSidebar class="doctor-materials-sidebar" :doctor-mode="true" />

    <section class="materials-shell">
      <header class="materials-back-bar">
        <button class="materials-back-button" type="button" @click="goBack">
          <img src="/assets/figma-demo-back.svg" alt="" width="24" height="24" />
          <span>К документам</span>
        </button>
      </header>

      <div class="materials-content">
        <nav class="crumbs">
          <Link :href="doctorsUrl('/')">Главная</Link>
          <span>/</span>
          <Link :href="doctorsUrl('/materials')">Документы</Link>
          <span>/</span>
          <strong>{{ category.name }}</strong>
        </nav>

        <h1>{{ category.name }}</h1>
        <p class="materials-lead">
          {{ category.description || 'Документы категории.' }}
          <template v-if="category.count_label"> {{ category.count_label }}.</template>
        </p>

        <div class="materials-list">
          <article v-for="(material, index) in materials" :key="material.title + index" class="material-card">
            <div class="material-copy">
              <h2>{{ material.title }}</h2>
              <p v-if="material.date">{{ material.date }}</p>
              <p v-else-if="material.description">{{ material.description }}</p>
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
          <p v-if="materials.length === 0" class="materials-empty">В этой категории пока нет файлов.</p>
        </div>

        <section v-if="otherCategories.length" class="others">
          <h2>Другие категории</h2>
          <div class="category-grid">
            <Link
              v-for="item in otherCategories"
              :key="item.id"
              :href="doctorsUrl(`/materials/${item.slug}`)"
              class="category-card"
            >
              <div class="category-copy">
                <h3>{{ item.name }}</h3>
                <p v-if="item.description">{{ item.description }}</p>
              </div>
              <div class="category-meta">
                <span>{{ item.count_label }}</span>
                <img src="/assets/figma-arrow-right.svg" alt="" width="24" height="24" />
              </div>
            </Link>
          </div>
        </section>
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
.materials-back-button img { width: 24px; height: 24px; }
.materials-content { width: min(1115px, 100%); padding: 64px; display: flex; flex-direction: column; gap: 32px; }
.crumbs { display: flex; flex-wrap: wrap; gap: 8px; font-size: 16px; color: rgba(0,0,0,0.4); }
.crumbs a { color: rgba(0,0,0,0.4); text-decoration: none; }
.crumbs strong { color: #000; font-weight: 400; }
.materials-content h1 { margin: 0; font-family: Roboto, Arial, sans-serif; font-size: 42px; font-weight: 400; }
.materials-lead { margin: 0; max-width: 700px; font-size: 21px; line-height: 1.35; }
.materials-list { display: flex; flex-direction: column; gap: 6px; }
.material-card {
  display: flex; align-items: center; justify-content: space-between; padding: 24px;
  background: #fff; border: 1px solid #dfdfdf;
}
.material-copy { display: flex; flex-direction: column; gap: 12px; }
.material-copy h2 { margin: 0; font-size: 21px; font-weight: 400; }
.material-copy p { margin: 0; font-size: 16px; }
.material-download { width: 24px; height: 24px; }
.material-download img { width: 24px; height: 24px; }
.materials-empty { padding: 24px 0; color: rgba(0,0,0,0.5); }
.others h2 { margin: 0 0 32px; font-size: 32px; font-weight: 400; }
.category-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 33px; }
.category-card {
  display: flex; flex-direction: column; justify-content: space-between; min-height: 184px;
  padding: 32px; background: #fff; border: 1px solid #dfdfdf; color: inherit; text-decoration: none;
}
.category-copy h3 { margin: 0 0 12px; font-size: 24px; font-weight: 400; }
.category-copy p { margin: 0; font-size: 16px; color: rgba(0,0,0,0.6); }
.category-meta { display: flex; justify-content: space-between; align-items: center; margin-top: 24px; color: rgba(0,0,0,0.45); }
.category-meta img { width: 24px; height: 24px; }

@media (max-width: 1024px) {
  .doctor-materials-sidebar { display: none !important; }
  .materials-content { width: 100%; padding: 32px 16px 48px; }
  .materials-content h1 { font-size: 32px; }
  .category-grid { grid-template-columns: 1fr; }
}
</style>
