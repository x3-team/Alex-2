<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'
import SiteSidebar from '@/Components/SiteSidebar.vue'
import { useDoctorMode } from '@/Composables/useDoctorMode'
import '../../../../css/main.css'
import PublicFooter from '@/Components/PublicFooter.vue'

const { blogBreadcrumbLabel } = useDoctorMode()

const props = defineProps({
  authors: Array,
  categories: Array,
  pageDescription: String,
  filters: Object,
  authorsMeta: { type: Object, default: () => ({ title: '', description: '', keywords: '' }) },
})

const selectedCategory = ref(props.filters?.category || null)

const applyCategoryFilter = (categorySlug = null) => {
  selectedCategory.value = categorySlug
  router.get('/blog/authors', { category: categorySlug }, { preserveState: true, preserveScroll: true })
}

const resetFilters = () => {
  selectedCategory.value = null
  router.get('/blog/authors', {}, { preserveState: true, preserveScroll: true })
}

const pluralizeAuthors = (count) => {
  const lastTwo = count % 100
  const lastOne = count % 10
  if (lastTwo >= 11 && lastTwo <= 14) return 'авторов'
  if (lastOne === 1) return 'автор'
  if (lastOne >= 2 && lastOne <= 4) return 'автора'
  return 'авторов'
}

const siteUrl = 'https://alexallergotest.ru'

// 🔹 Canonical URL
const canonicalUrl = computed(() => `${siteUrl}/blog/authors`)

// 🔹 OG Title
const ogTitle = computed(() => {
  if (props.authorsMeta?.title) return props.authorsMeta.title

  // Если выбрана категория — добавляем её в заголовок
  if (selectedCategory.value) {
    const category = props.categories?.find(c => c.slug === selectedCategory.value)
    if (category) {
      return `Авторы: ${category.name}`
    }
  }

  return ''
})

// 🔹 OG Description
const ogDescription = computed(() => {
  if (props.authorsMeta?.description) return props.authorsMeta.description
  return ''
})

// 🔹 OG Image — берём аватар первого автора или дефолтную картинку
const ogImage = computed(() => {
  const firstAuthorWithAvatar = props.authors?.find(author => author.avatar)

  if (firstAuthorWithAvatar?.avatar) {
    return `${siteUrl}/storage/${firstAuthorWithAvatar.avatar}`
  }

  return ``
})
</script>

<template>
  <Head>
    <script type="application/ld+json">
      {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
          {
            "@type": "ListItem",
            "position": 1,
            "name": "Главная",
            "item": "https://alexallergotest.ru/"
          },
          {
            "@type": "ListItem",
            "position": 2,
            "name": "Блог",
            "item": "https://alexallergotest.ru/blog"
          },
          {
            "@type": "ListItem",
            "position": 3,
            "name": "Авторы",
            "item": "https://alexallergotest.ru/blog/authors/"
          }
        ]
      }
    </script>
  </Head>

  <div class="page-container search-page-container site-sidebar-layout doctor-mode">

      <!-- Компонент Сайдбара -->
      <SiteSidebar />

      <div class="xl:hidden bg-white sticky top-0 z-10" style="height: 64px; border-bottom: 1px solid #DFDFDF;">
        <div class="relative h-full flex items-center justify-between px-4">
          <Link href="/blog" class="inline-flex items-center justify-center w-10 h-10">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </Link>
          <div class="w-10"></div>
        </div>
      </div>

      <div class="flex-1 flex flex-col bg-[#f7f7f7] w-full" style="overflow: auto">

        <header class="hidden xl:block bg-white border-b border-gray-200 sticky top-0 z-10">
          <div class="px-9 py-4 flex align-center height-[72px]">
            <Link href="/blog" class="inline-flex items-center gap-2 text-gray-700 hover:text-emerald-700 font-medium transition-colors w-full">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
              Обратно ко всем материалам
            </Link>
          </div>
        </header>

        <main class="main-content p-8 xl:p-16 flex-1">

          <div class="breadcrumbs flex items-center gap-2 xl:gap-3 mb-8 overflow-hidden">
            <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
              <rect x="6" y="6" width="36" height="36" rx="18" stroke="black" stroke-width="2"/>
              <path d="M27.6667 30.625V29.0417C27.6667 28.2018 27.3331 27.3964 26.7392 26.8025C26.1453 26.2086 25.3399 25.875 24.5 25.875H19.75C18.9102 25.875 18.1047 26.2086 17.5109 26.8025C16.917 27.3964 16.5834 28.2018 16.5834 29.0417V30.625" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M27.6666 16.4763C28.3457 16.6524 28.9471 17.0489 29.3764 17.6037C29.8057 18.1585 30.0386 18.8402 30.0386 19.5417C30.0386 20.2432 29.8057 20.9248 29.3764 21.4796C28.9471 22.0344 28.3457 22.431 27.6666 22.607" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M32.4166 30.625V29.0417C32.4161 28.34 32.1826 27.6584 31.7527 27.1039C31.3228 26.5494 30.721 26.1533 30.0416 25.9779" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M22.125 22.7083C23.8739 22.7083 25.2917 21.2906 25.2917 19.5417C25.2917 17.7928 23.8739 16.375 22.125 16.375C20.3761 16.375 18.9584 17.7928 18.9584 19.5417C18.9584 21.2906 20.3761 22.7083 22.125 22.7083Z" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>

            <Link href="/" class="flex-shrink-0 text-[14px] xl:text-[18px] text-black opacity-30">
              Главная
            </Link>
            <span class="text-black opacity-[0.3]">
              <svg width="6" height="9" viewBox="0 0 6 9" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0.75 8.25L4.5 4.5L0.75 0.75" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </span>
            <Link href="/blog" class="flex-shrink-0 text-[14px] xl:text-[18px] text-black opacity-30">
              {{ blogBreadcrumbLabel }}
            </Link>
            <span class="text-black opacity-[0.3]">
              <svg width="6" height="9" viewBox="0 0 6 9" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0.75 8.25L4.5 4.5L0.75 0.75" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </span>
            <span class="text-[14px] xl:text-[18px] text-black truncate">
              Авторы
            </span>
          </div>

          <div class="mb-4 max-w-full xl:max-w-[462px]">
            <h1 class="font-400 text-[28px] sm:text-[34px] xl:text-[42px]">Все авторы</h1>
            <p v-if="pageDescription" class="text-black-600 text-[21px] leading-[1.25] opacity-[0.5]">
              {{ pageDescription }}
            </p>
          </div>

          <div class="mb-6 xl:mb-8 pt-[1rem]" :style="{ borderTop: '1px solid rgba(0, 0, 0, 0.3)' }">
            <h3 class="text-[16px] font-400 xl:text-[18px] text-[rgba(0, 0, 0, 1)] mb-4 opacity-[0.5]">Выберите категорию</h3>
            <div class="flex gap-[5px] overflow-x-auto pb-2 xl:flex-wrap xl:overflow-visible xl:pb-0 scrollbar-hide">
              <button
                  @click="applyCategoryFilter(null)"
                  :class="selectedCategory === null ? 'bg-black text-white' : 'bg-[transparent] text-black border border-black'"
                  class="flex-shrink-0 px-4 xl:px-6 py-2 xl:py-3 rounded-xl font-[400] transition-all text-[16px] xl:text-[18px]"
                  style="min-width: 60px; height: 45px; display: flex; align-items: center; border: 1px solid rgba(0, 0, 0, 0.4);"
              >
                Все
              </button>
              <button
                  v-for="category in categories"
                  :key="category.id"
                  @click="applyCategoryFilter(category.slug)"
                  :class="selectedCategory === category.slug ? 'bg-black text-white' : 'bg-[transparent] text-black border border-black'"
                  class="flex-shrink-0 px-4 xl:px-6 py-2 xl:py-3 rounded-xl font-[400] transition-all text-[16px] xl:text-[18px]"
                  style="min-width: 60px; height: 45px; display: flex; align-items: center; border: 1px solid rgba(0, 0, 0, 0.4);"
              >
                {{ category.name }}
              </button>
            </div>
          </div>

          <div class="mb-4 pt-8 xl:pt-16">
            <h2 class="text-[24px] xl:text-[32px] font-[400] text-gray-900">
              Всего {{ authors?.length || 0 }} {{ pluralizeAuthors(authors?.length || 0) }}
            </h2>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            <Link
                v-for="(author, authorIdx) in authors"
                :key="author.id"
                class="flex-shrink-0"
                style="display: flex; flex-direction: column; justify-content: space-between; align-items: center; padding: 16px; gap: 15px; background: #FFFFFF; border-radius: 20px;"
                :href="`/blog/author/${author.id}`"
            >
              <div class="w-full max-w-[317px] aspect-square rounded-[20px] overflow-hidden bg-white flex-shrink-0 border border-gray-200">
                <img v-if="author.avatar"
                     :src="`/storage/${author.avatar}`"
                     :alt="author.name"
                     class="w-full h-full object-cover"
                     :loading="authorIdx === 0 ? 'eager' : 'lazy'"
                     decoding="async" />
                <div v-else class="w-full h-full bg-emerald-100 flex items-center justify-center text-6xl font-semibold text-emerald-700">
                  {{ author.name?.charAt(0) || '?' }}
                </div>
              </div>

              <div class="text-center" style="margin-top: -5px">
                <h3 class="text-[20px] xl:text-[24px] font-[400] text-gray-900" style="line-height: 1">
                  {{ author.name }}
                </h3>

                <p v-if="author.author_categories?.length"
                   class="text-[16px] xl:text-[21px] font-[400] text-gray-900 opacity-50"
                   style="font-family: 'Roboto'; line-height: 25px;"
                >
                  {{ author.author_categories[0].name }}
                </p>

                <p v-if="author.position" class="text-[14px] xl:text-[16px] font-[400] text-gray-600">
                  {{ author.position }}
                </p>
              </div>

              <div
                  class="inline-flex items-center justify-center gap-2"
                  style="
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    padding: 12px 20px;
                    gap: 10px;
                    min-width: 140px;
                    height: 45px;
                    background: #F5F5F5;
                    border-radius: 10px;
                    font-family: 'Roboto';
                    font-size: 16px;
                    font-weight: 400;
                    color: #000000;
                    transition: background 0.2s ease;
                  "
                  @mouseover="$event.target.style.background = '#E5E5E5'"
                  @mouseout="$event.target.style.background = '#F5F5F5'"
              >
                {{ author.articles_count }} материалов
              </div>
            </Link>
          </div>

          <div v-if="!authors?.length" class="text-center py-16 bg-gray-50 rounded-xl">
            <div class="text-gray-400 mb-4">
              <svg class="mx-auto h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
            </div>
            <h3 class="text-xl font-medium text-gray-900 mb-2">Авторы не найдены</h3>
            <p class="text-gray-500 mb-4">Попробуйте сбросить фильтры</p>
            <button @click="resetFilters" class="px-6 py-2 bg-emerald-700 text-white rounded-xl hover:bg-emerald-800 font-medium transition-colors">
              Сбросить фильтры
            </button>
          </div>

        </main>
        <PublicFooter />
      </div>
    </div>
  
</template>

<style scoped>
.author-card:hover {
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
  transform: translateY(-4px);
  transition: all 0.3s ease;
}

.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
.main-content {
  padding: 24px; /* По умолчанию 32px */
  background-color: rgb(247, 247, 247);
  /* Добавлено для гарантии отсутствия отступов у контента справа, если нужно */
  width: 100%;
  box-sizing: border-box;

}
@media (min-width: 1240px) {
  .main-content {
    padding: 64px;
    max-width: 1115px;
    margin: 0 auto;
  }

}

.mobile-menu-button {
  display: flex;
  flex-direction: row;
  justify-content: center;
  align-items: center;
  padding: 21px;
  gap: 10px;
  width: 100%;
  height: 66px;
  background: #FFFFFF;
  border: 1px solid #DFDFDF;
  backdrop-filter: blur(20px);
  cursor: pointer;
}

.mobile-menu-button-text {
  font-family: 'Helvetica', sans-serif;
  font-style: normal;
  font-weight: 400;
  font-size: 18px;
  line-height: 21px;
  color: #000000;
}


.mobile-menu-content {
  position: fixed;
  inset: 0;
  display: flex;
  flex-direction: column;
  z-index: 10000;
  background: #fff;
}

.mobile-menu-gradient {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
  align-items: flex-start;
  padding: 12px;
  background: linear-gradient(180deg,#7e8f7c,#97af96);
  text-align: left;
}

.mobile-menu-logo {
  margin-bottom: 24px;
}

.mobile-menu-description {
  font-family: 'Roboto', sans-serif;
  font-style: normal;
  font-weight: 400;
  font-size: 18px;
  line-height: 1.2;
  color: rgba(255, 255, 255, 0.9);
  max-width: 320px;
}

.mobile-menu-lab-button {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px;
  height: 60px;
  background: #FFFFFF;
  border: 1px solid #DFDFDF;
  font-family: 'Roboto', sans-serif;
  font-style: normal;
  font-weight: 500;
  font-size: 18px;
  color: #374151;
  text-decoration: none;
}

.mobile-menu-close-button {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 20px;
  height: 66px;
  background: #000000;
  color: #FFFFFF;
  font-family: 'Roboto', sans-serif;
  font-style: normal;
  font-weight: 400;
  font-size: 18px;
  border: none;
  cursor: pointer;
  width: 100%;
}


.mobile-menu-enter-active,
.mobile-menu-leave-active {
  transition: all 0.3s ease;
}

.mobile-menu-enter-from,
.mobile-menu-leave-to {
  opacity: 0;
  transform: translateY(100%);
}

.mobile-menu-enter-to,
.mobile-menu-leave-from {
  opacity: 1;
  transform: translateY(0);
}

.cust_width{
  width: 677px;
}
@media (max-width: 1720px) {
  .cust_width{
    width: 33%;
  }

}
</style>