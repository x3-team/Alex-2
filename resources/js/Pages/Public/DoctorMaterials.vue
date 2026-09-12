<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import DoctorPublicShell from '@/Components/DoctorPublicShell.vue'
import DoctorTypeChips from '@/Components/DoctorTypeChips.vue'
import DoctorDocumentCategoryCard from '@/Components/DoctorDocumentCategoryCard.vue'
import { useDoctorMode } from '@/composables/useDoctorMode'

const props = defineProps({
  view: { type: String, default: 'all' },
  feed: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  materials: { type: Array, default: () => [] },
  seoMeta: { type: Object, default: () => ({}) },
})

const { doctorsUrl } = useDoctorMode()

const isHub = computed(() => props.view !== 'documents')
const pageTitle = computed(() => (
  isHub.value
    ? (props.seoMeta.title || 'Материалы для врачей — ALEX LAB')
    : (props.seoMeta.title || 'Документы для врачей — ALEX LAB')
))
const pageDescription = computed(() => (
  isHub.value
    ? (props.seoMeta.description || 'Статьи, видеолекции и документы лаборатории.')
    : (props.seoMeta.description || 'Регистрационные документы, инструкции и бланки лаборатории.')
))
</script>

<template>
  <Head>
    <title>{{ pageTitle }}</title>
    <meta name="description" :content="pageDescription" />
  </Head>

  <DoctorPublicShell>
    <div
      class="breadcrumbs flex items-center gap-3 mb-6"
      style="display: flex; flex-direction: row; justify-content: flex-start; align-items: center; padding: 0px; gap: 12px; min-height: 48px;"
    >
      <Link :href="doctorsUrl('/')" class="flex-shrink-0 text-[14px] xl:text-[18px] text-black opacity-30">
        Главная
      </Link>
      <span class="text-black opacity-[0.3]">
        <svg width="6" height="9" viewBox="0 0 6 9" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M0.75 8.25L4.5 4.5L0.75 0.75" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </span>
      <span class="text-[14px] xl:text-[18px] text-black">
        {{ isHub ? 'Материалы для врачей' : 'Документы' }}
      </span>
    </div>

    <div class="mb-4 max-w-full xl:max-w-[700px]">
      <h1 class="font-400 text-[28px] sm:text-[34px] xl:text-[42px]">
        {{ isHub ? 'Материалы для врачей' : 'Документы' }}
      </h1>
      <p class="mt-3 text-[16px] xl:text-[21px] text-black" style="line-height: 1.35">
        {{ isHub
          ? 'Статьи, видеолекции и документы лаборатории — для специалистов. Всё о молекулярной аллергодиагностике ALEX².'
          : 'Регистрационные документы, инструкции и бланки лаборатории.' }}
      </p>
    </div>

    <div class="documents-toolbar">
      <DoctorTypeChips :active="isHub ? 'all' : 'documents'" />
    </div>

    <div v-if="categories.length" class="doctor-doc-grid">
      <DoctorDocumentCategoryCard
        v-for="category in categories"
        :key="category.id"
        :category="category"
      />
    </div>
    <p v-else class="doctor-empty">Документы скоро появятся.</p>
  </DoctorPublicShell>
</template>

<style scoped>
.doctor-empty {
  padding: 24px 0;
  font-family: Roboto, Arial, sans-serif;
  font-size: 18px;
  color: rgba(0, 0, 0, 0.5);
}

.documents-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  margin: 8px 0 32px;
}

.doctor-doc-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 33px;
}

@media (max-width: 1024px) {
  .doctor-doc-grid {
    grid-template-columns: 1fr;
    gap: 16px;
  }
}
</style>
