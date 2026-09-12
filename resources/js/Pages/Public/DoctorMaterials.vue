<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import DoctorPublicShell from '@/Components/DoctorPublicShell.vue'
import DoctorTypeChips from '@/Components/DoctorTypeChips.vue'
import DoctorFeedCard from '@/Components/DoctorFeedCard.vue'
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

    <div class="mb-6 xl:mb-8 pt-[1rem]" :style="{ borderTop: '1px solid rgba(0, 0, 0, 0.3)' }">
      <div class="text-[16px] xl:text-[18px] text-[rgba(0, 0, 0, 1)] mb-2 opacity-[0.5] font-400">Тип материала</div>
      <DoctorTypeChips :active="isHub ? 'all' : 'documents'" />
    </div>

    <template v-if="isHub">
      <div v-if="feed.length" class="pt-2">
        <DoctorFeedCard v-for="item in feed" :key="`${item.type}-${item.id}`" :item="item" />
      </div>
      <p v-else class="doctor-empty">Пока нет опубликованных статей и видео.</p>

      <section v-if="categories.length" class="doctor-hub-docs">
        <div class="doctor-hub-docs-head">
          <h2>Документы лаборатории</h2>
          <Link :href="doctorsUrl('/materials/documents')" class="doctor-hub-docs-all">
            Все документы
            <img src="/assets/figma-arrow-right.svg" alt="" width="24" height="24" />
          </Link>
        </div>
        <div class="doctor-doc-grid">
          <DoctorDocumentCategoryCard
            v-for="category in categories"
            :key="category.id"
            :category="category"
          />
        </div>
      </section>
    </template>

    <template v-else>
      <div v-if="categories.length" class="doctor-doc-grid">
        <DoctorDocumentCategoryCard
          v-for="category in categories"
          :key="category.id"
          :category="category"
        />
      </div>
      <p v-else class="doctor-empty">Документы скоро появятся.</p>
    </template>
  </DoctorPublicShell>
</template>

<style scoped>
.doctor-empty {
  padding: 24px 0;
  font-family: Roboto, Arial, sans-serif;
  font-size: 18px;
  color: rgba(0, 0, 0, 0.5);
}

.doctor-hub-docs {
  margin-top: 24px;
}

.doctor-hub-docs-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 40px;
}

.doctor-hub-docs-head h2 {
  margin: 0;
  font-family: Roboto, Arial, sans-serif;
  font-size: 32px;
  font-weight: 400;
  color: #000;
}

.doctor-hub-docs-all {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  color: #000;
  font-family: Roboto, Arial, sans-serif;
  font-size: 18px;
  text-decoration: none;
}

.doctor-hub-docs-all img {
  width: 24px;
  height: 24px;
}

.doctor-doc-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 33px;
}

@media (max-width: 1024px) {
  .doctor-hub-docs-head {
    flex-direction: column;
    align-items: flex-start;
    margin-bottom: 24px;
  }

  .doctor-hub-docs-head h2 {
    font-size: 24px;
  }

  .doctor-doc-grid {
    grid-template-columns: 1fr;
    gap: 16px;
  }
}
</style>
