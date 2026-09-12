<script setup>
import { Head, Link } from '@inertiajs/vue3'
import DoctorPublicShell from '@/Components/DoctorPublicShell.vue'
import DoctorTypeChips from '@/Components/DoctorTypeChips.vue'
import DoctorDocumentCategoryCard from '@/Components/DoctorDocumentCategoryCard.vue'
import { useDoctorMode } from '@/composables/useDoctorMode'

defineProps({
  category: { type: Object, required: true },
  materials: { type: Array, default: () => [] },
  otherCategories: { type: Array, default: () => [] },
  seoMeta: { type: Object, default: () => ({}) },
})

const { doctorsUrl } = useDoctorMode()
</script>

<template>
  <Head>
    <title>{{ category.name }} — документы ALEX LAB</title>
    <meta name="description" :content="category.description || seoMeta.description || category.name" />
  </Head>

  <DoctorPublicShell :back-href="doctorsUrl('/materials/documents')" back-label="К документам">
    <div
      class="breadcrumbs flex items-center gap-3 mb-6"
      style="display: flex; flex-direction: row; justify-content: flex-start; align-items: center; padding: 0px; gap: 12px; min-height: 48px;"
    >
      <Link :href="doctorsUrl('/')" class="flex-shrink-0 text-[14px] xl:text-[18px] text-black opacity-30">Главная</Link>
      <span class="text-black opacity-[0.3]">
        <svg width="6" height="9" viewBox="0 0 6 9" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M0.75 8.25L4.5 4.5L0.75 0.75" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </span>
      <Link :href="doctorsUrl('/materials/documents')" class="flex-shrink-0 text-[14px] xl:text-[18px] text-black opacity-30">Документы</Link>
      <span class="text-black opacity-[0.3]">
        <svg width="6" height="9" viewBox="0 0 6 9" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M0.75 8.25L4.5 4.5L0.75 0.75" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </span>
      <span class="text-[14px] xl:text-[18px] text-black">{{ category.name }}</span>
    </div>

    <div class="mb-4 max-w-full xl:max-w-[700px]">
      <h1 class="font-400 text-[28px] sm:text-[34px] xl:text-[42px]">{{ category.name }}</h1>
      <p class="mt-3 text-[16px] xl:text-[21px] text-black" style="line-height: 1.35">
        {{ category.description || 'Документы категории.' }}
        <template v-if="category.count_label"> {{ category.count_label }}.</template>
      </p>
    </div>

    <div class="mb-6 xl:mb-8 pt-[1rem]" :style="{ borderTop: '1px solid rgba(0, 0, 0, 0.3)' }">
      <div class="text-[16px] xl:text-[18px] text-[rgba(0, 0, 0, 1)] mb-2 opacity-[0.5] font-400">Тип материала</div>
      <DoctorTypeChips active="documents" />
    </div>

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
      <div class="doctor-doc-grid">
        <DoctorDocumentCategoryCard
          v-for="item in otherCategories"
          :key="item.id"
          :category="item"
        />
      </div>
    </section>
  </DoctorPublicShell>
</template>

<style scoped>
.materials-list { display: flex; flex-direction: column; gap: 6px; }
.material-card {
  display: flex; align-items: center; justify-content: space-between; padding: 24px;
  background: #fff; border: 1px solid #dfdfdf;
}
.material-copy { display: flex; flex-direction: column; gap: 12px; }
.material-copy h2 { margin: 0; font-family: Roboto, Arial, sans-serif; font-size: 21px; font-weight: 400; }
.material-copy p { margin: 0; font-size: 16px; }
.material-download { width: 24px; height: 24px; }
.material-download img { width: 24px; height: 24px; }
.materials-empty { padding: 24px 0; color: rgba(0, 0, 0, 0.5); }
.others { margin-top: 48px; }
.others h2 { margin: 0 0 32px; font-family: Roboto, Arial, sans-serif; font-size: 32px; font-weight: 400; }
.doctor-doc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 33px; }

@media (max-width: 1024px) {
  .others h2 { font-size: 24px; }
  .doctor-doc-grid { grid-template-columns: 1fr; gap: 16px; }
}
</style>
