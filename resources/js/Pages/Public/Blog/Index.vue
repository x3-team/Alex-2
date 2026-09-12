<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import SiteSidebar from '@/Components/SiteSidebar.vue'
import { useDoctorMode } from '@/Composables/useDoctorMode'
import '../../../../css/main.css'
import PublicFooter from '@/Components/PublicFooter.vue'
const props = defineProps({
  blogs: { type: Object, default: () => ({ data: [], links: {}, meta: {} }) },
  authors: Array,
  categories: Array,
  tags: Array,
  pageDescription: String,
  blogIntroDescription: { type: String, default: '' },
  filters: Object,
  currentCategory: { type: Object, default: null },
  blogMeta: { type: Object, default: () => ({ title: '', description: '', keywords: '' }) },
})

const { isDoctorMode } = useDoctorMode()

const hasSelectedTags = computed(() => selectedTags.value.length > 0)

const searchQuery = ref(props.filters?.search || '')
const selectedAuthor = ref(props.filters?.author || '')
const selectedCategories = ref(
    props.filters?.category ? props.filters.category.split(',') : []
)
const selectedTags = ref(
    props.filters?.tags ? props.filters.tags.split(',') : []
)

const formatDate = (dateString) => {
  if (!dateString) return ''
  return new Date(dateString).toLocaleDateString('ru-RU', {
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  })
}

const excerpt = (html, words = 30) => {
  const raw = String(html || '')
  const text = (typeof document !== 'undefined')
    ? (() => {
        const tmp = document.createElement('DIV')
        tmp.innerHTML = raw
        return tmp.textContent || tmp.innerText || ''
      })()
    : raw.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim()
  const parts = text.split(/\s+/).filter(Boolean)
  return parts.slice(0, words).join(' ') + (parts.length > words ? '...' : '')
}

const toggleCategory = (categorySlug) => {
  if (categorySlug === null) {
    selectedCategories.value = []
  } else if (selectedCategories.value.length === 1 && selectedCategories.value[0] === categorySlug) {
    selectedCategories.value = []
  } else {
    selectedCategories.value = [categorySlug]
  }

  applySelectedTags()
}

const toggleTag = (tagSlug) => {
  const index = selectedTags.value.indexOf(tagSlug)
  if (index === -1) {
    selectedTags.value.push(tagSlug)
  } else {
    selectedTags.value.splice(index, 1)
  }
}

const listingPath = () => {
  if (selectedCategories.value.length === 1) {
    return `/blog/${selectedCategories.value[0]}`
  }
  return '/blog'
}

const applySelectedTags = () => {
  const params = {}
  if (selectedCategories.value.length > 1) {
    params.category = selectedCategories.value.join(',')
  }
  if (selectedTags.value.length) params.tags = selectedTags.value.join(',')
  router.get(listingPath(), params, { preserveState: true, preserveScroll: true })
  showTagDropdown.value = false
}

const resetFilters = () => {
  searchQuery.value = ''
  selectedAuthor.value = ''
  selectedCategories.value = []
  selectedTags.value = []
  router.get('/blog', {}, { preserveState: true, preserveScroll: true })
}

const buildPageUrl = (page) => {
  const params = new URLSearchParams()

  if (page > 1) {
    params.set('page', page)
  }

  if (selectedCategories.value.length > 1) {
    params.set('category', selectedCategories.value.join(','))
  }
  if (selectedTags.value.length) {
    params.set('tags', selectedTags.value.join(','))
  }

  const queryString = params.toString()
  const path = listingPath()
  return queryString ? `${path}?${queryString}` : path
}

const paginationRange = computed(() => {
  const total = props.blogs?.last_page || 1
  const current = props.blogs?.current_page || 1

  if (total <= 5) {
    return Array.from({ length: total }, (_, i) => i + 1)
  }

  if (current <= 3) {
    return [1, 2, 3, 4, '...', total]
  }

  if (current >= total - 2) {
    return [1, '...', total - 3, total - 2, total - 1, total]
  }

  return [1, '...', current - 1, current, current + 1, '...', total]
})

const selectedCategoryName = computed(() => {
  if (props.currentCategory?.name && selectedCategories.value.length <= 1) {
    return props.currentCategory.name
  }
  if (!selectedCategories.value.length) return null

  return selectedCategories.value.map(slug => {
    const cat = props.categories?.find(c => c.slug === slug)
    return cat?.name
  }).filter(Boolean).join(', ')
})

const pageHeading = computed(() => props.currentCategory?.name || 'Блог про аллергию')

const rememberedBlogIntro = ref(
  props.blogIntroDescription || (!props.currentCategory ? (props.pageDescription || '') : '')
)

watch(
  () => [props.blogIntroDescription, props.pageDescription, props.currentCategory],
  ([intro, description, category]) => {
    if (intro) {
      rememberedBlogIntro.value = intro
    } else if (!category && description) {
      rememberedBlogIntro.value = description
    }
  }
)

const leadSizerText = computed(() => rememberedBlogIntro.value || props.blogIntroDescription || '')

const selectedTagNames = computed(() => {
  return selectedTags.value
      .slice(0, 2)
      .map(slug => {
        const tag = props.tags?.find(t => t.slug === slug)
        return tag?.name || slug
      })
})

const allTagNames = computed(() => {
  return selectedTags.value.map(slug => {
    const tag = props.tags?.find(t => t.slug === slug)
    return tag?.name || slug
  })
})

const hiddenTagsCount = computed(() => Math.max(0, selectedTags.value.length - 1))

const firstTagName = computed(() => {
  if (!selectedTags.value.length) return ''
  return allTagNames.value[0]
})

const hasMoreThanOneTag = computed(() => selectedTags.value.length > 1)

const showTagDropdown = ref(false)

const closeDropdownIfOutside = (event) => {
  const dropdown = document.querySelector('.tag-dropdown')
  const button = event.target.closest('.filter-btn-tags')

  if (dropdown && !dropdown.contains(event.target) && !button) {
    showTagDropdown.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', closeDropdownIfOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', closeDropdownIfOutside)
})

const siteUrl = 'https://alexallergotest.ru'

const canonicalUrl = computed(() => {
  const params = new URLSearchParams()
  const currentPage = props.blogs?.current_page || 1

  if (currentPage > 1) {
    params.set('page', currentPage)
  }

  if (selectedCategories.value.length > 1) {
    params.set('category', selectedCategories.value.join(','))
  }
  if (selectedTags.value.length) {
    params.set('tags', selectedTags.value.join(','))
  }

  const queryString = params.toString()
  const path = listingPath()
  return queryString ? `${siteUrl}${path}?${queryString}` : `${siteUrl}${path}`
})

const metaTitle = computed(() => {
  const baseTitle = props.blogMeta?.title || 'Блог'
  const currentPage = props.blogs?.current_page || 1

  if (currentPage > 1) {
    return `${baseTitle} — страница ${currentPage}`
  }
  return baseTitle
})

const ogTitle = computed(() => {
  if (props.blogMeta?.title) return props.blogMeta.title

  if (selectedCategoryName.value || selectedTags.value.length) {
    const parts = []
    if (selectedCategoryName.value) parts.push(selectedCategoryName.value)
    if (selectedTagNames.value.length) parts.push(selectedTagNames.value.join(', '))
    return `${parts.join(' — ')}`
  }

  return ''
})

const ogDescription = computed(() => {
  if (props.blogMeta?.description) return props.blogMeta.description
  return ''
})

const ogImage = computed(() => {
  const firstBlogWithImage = props.blogs?.data?.find(blog => blog.preview_image)

  if (firstBlogWithImage?.preview_image) {
    return `${siteUrl}/storage/${firstBlogWithImage.preview_image}`
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
            "item": "https://alexallergotest.ru"
          },
          {
            "@type": "ListItem",
            "position": 2,
            "name": "Блог",
            "item": "https://alexallergotest.ru/blog"
          }
        ]
      }
    </script>
  </Head>

  <!-- Главная обёртка сайта -->
  <div
      class="page-container search-page-container site-sidebar-layout"
      :class="{ 'doctor-mode': isDoctorMode }"
  >
    <!-- Подключённый универсальный сайдбар -->
    <SiteSidebar />

    <div class="flex-1 flex flex-col" style="background-color: #f7f7f7; width: 100%; overflow: auto">
      <header class="hidden xl:block bg-white border-b border-gray-200 sticky top-0 z-10">
        <div class="px-9 py-4 flex align-center height-[72px]">
          <Link href="/" class="inline-flex items-center gap-2 text-gray-700 hover:text-emerald-700 font-medium transition-colors w-full">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            На главную
          </Link>
        </div>
      </header>

      <main class="main-content p-8 xl:p-16 flex-1">
        <div class="breadcrumbs flex items-center gap-3 mb-6" style="display: flex; flex-direction: row; justify-content: flex-start; align-items: center; padding: 0px; gap: 12px; min-height: 48px;">
          <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="6" y="6" width="36" height="36" rx="18" stroke="black" stroke-width="2"/>
            <path d="M31.625 18.9583H17.375" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M26.875 24.5H17.375" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M28.4583 30.0417H17.375" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>

          <Link href="/" class="flex-shrink-0 text-[14px] xl:text-[18px] text-black opacity-30">
            Главная
          </Link>

          <span class="text-black opacity-[0.3]">
            <svg width="6" height="9" viewBox="0 0 6 9" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M0.75 8.25L4.5 4.5L0.75 0.75" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>

          <Link
            v-if="currentCategory"
            href="/blog"
            class="flex-shrink-0 text-[14px] xl:text-[18px] text-black opacity-30"
          >
            Блог
          </Link>
          <span v-else class="text-[14px] xl:text-[18px] text-black">
            Блог
          </span>
          <template v-if="currentCategory">
            <span class="text-black opacity-[0.3]">
              <svg width="6" height="9" viewBox="0 0 6 9" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M0.75 8.25L4.5 4.5L0.75 0.75" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </span>
            <span class="text-[14px] xl:text-[18px] text-black">
              {{ currentCategory.name }}
            </span>
          </template>
        </div>

        <div class="relative">
          <div class="mb-4 max-w-full xl:max-w-[462px]">
            <h1 class="font-400 text-[28px] sm:text-[34px] xl:text-[42px]">{{ pageHeading }}</h1>
            <div class="blog-lead-slot">
              <p class="blog-lead-sizer" aria-hidden="true">{{ leadSizerText }}</p>
              <p v-if="pageDescription" class="blog-lead-visible text-black-600">
                {{ pageDescription }}
              </p>
            </div>
          </div>

          <div class="m_cust xl:absolute xl:bottom-0 xl:right-0 xl:mt-6 mt-4">
            <Link href="/blog/authors" class="inline-flex items-center gap-2 text-[18px] font-[400] text-gray-900 transition-colors">
              Все авторы
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M10 8L14 12L10 16" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </Link>
          </div>
        </div>

        <div class="mb-6 xl:mb-8 pt-[1rem]" :style="{ borderTop: '1px solid rgba(0, 0, 0, 0.3)' }">
          <div class="text-[16px] xl:text-[18px] text-[rgba(0, 0, 0, 1)] mb-2 opacity-[0.5] font-400">Выберите категорию</div>

          <div class="flex gap-[5px] overflow-x-auto pb-2 xl:flex-wrap xl:overflow-visible xl:pb-0 scrollbar-hide">
            <button
                @click="toggleCategory(null)"
                :class="!selectedCategories.length ? 'bg-black text-white' : 'bg-[transparent] text-black '"
                class="px-6 py-3 font-medium transition-all but_cust flex-shrink-0 whitespace-nowrap"
                style="border:1px solid rgba(0, 0, 0, 0.4); border-radius: 8px"
            >
              Все
            </button>

            <button
                v-for="category in categories"
                :key="category.id"
                @click="toggleCategory(category.slug)"
                :class="selectedCategories.includes(category.slug) ? 'bg-black text-white' : 'bg-[transparent] text-black'"
                class="px-6 py-3 font-medium transition-all but_cust flex-shrink-0 whitespace-nowrap"
                style="border: 1px solid rgba(0, 0, 0, 0.4); border-radius: 8px"
            >
              {{ category.name }}
            </button>
          </div>
        </div>

        <div class="grid pt-8 xl:pt-[9px]">
          <div class="text-[21px] mb-[8px] xl:text-[32px] font-[400] text-gray-900 break-words" style="line-height: 1">
            <span class="title-wrapper">
              <span v-if="selectedCategoryName" class="title-category">{{ selectedCategoryName }}</span>
              <span v-if="selectedCategoryName && selectedTags.length" class="title-separator">: </span>

              <span v-if="selectedTags.length" class="title-tags">
                <span v-for="(name, idx) in allTagNames" :key="idx" class="text-[#ACACAC]">
                  {{ name }}<span v-if="idx < allTagNames.length - 1" class="text-[#ACACAC]">, </span>
                </span>
              </span>
            </span>

            <span v-if="!selectedCategoryName && !selectedTags.length" class="title-empty">Последние публикации</span>
          </div>

          <div class="relative mb-[32px]">
            <button
                @click="showTagDropdown = !showTagDropdown"
                class="filter-btn-tags cust"
                :class="{ 'has-tags': selectedTags.length }"
            >
              <span v-if="!selectedTags.length" class="tag-placeholder">Выберите тег</span>
              <span v-else class="tag-text">
                {{ firstTagName }}<span v-if="hasMoreThanOneTag" class="tag-count"> +{{ hiddenTagsCount }}</span>
              </span>
              <svg class="w-4 h-4 flex-shrink-0 transition-transform" :class="{'rotate-180': showTagDropdown}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <div v-if="showTagDropdown" class="tag-dropdown absolute top-full left-0 mt-2 z-20">
              <div class="space-y-1 max-h-[200px] overflow-y-auto w-full p-2">
                <div
                    v-for="tag in tags"
                    :key="tag.id"
                    @click.prevent="toggleTag(tag.slug)"
                    class="flex items-center gap-3 px-[15px] py-[10px] rounded cursor-pointer hover:bg-[#F1F1F1] transition-colors select-none"
                >
                  <span class="custom-checkbox flex items-center justify-center" :class="{ 'checked': selectedTags.includes(tag.slug) }"></span>
                  <span class="text-[14px] xl:text-[18px] font-[400] text-gray-900">{{ tag.name }}</span>
                </div>
              </div>

              <div class="p-3 border-t border-gray-200 bg-white flex justify-between items-center w-full">
                <button
                    @click="applySelectedTags()"
                    :disabled="!hasSelectedTags"
                    class="w-[50%] text-[18px] transition-colors duration-200 flex items-center justify-center gap-[5px]"
                    :style="{ color: hasSelectedTags ? '#000000' : '#969696', cursor: hasSelectedTags ? 'pointer' : 'default' }"
                >
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" :stroke="hasSelectedTags ? '#000000' : '#969696'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M9 12L11 14L15 10" :stroke="hasSelectedTags ? '#000000' : '#969696'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                  Сохранить
                </button>

                <button
                    @click="selectedTags = []; applySelectedTags()"
                    :disabled="!hasSelectedTags"
                    class="w-[50%] text-[18px] transition-colors duration-200 flex items-center justify-center gap-[5px]"
                    :style="{ color: hasSelectedTags ? '#000000' : '#969696', cursor: hasSelectedTags ? 'pointer' : 'default' }"
                >
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 12C3 13.78 3.52784 15.5201 4.51677 17.0001C5.50571 18.4802 6.91131 19.6337 8.55585 20.3149C10.2004 20.9961 12.01 21.1743 13.7558 20.8271C15.5016 20.4798 17.1053 19.6226 18.364 18.364C19.6226 17.1053 20.4798 15.5016 20.8271 13.7558C21.1743 12.01 20.9961 10.2004 20.3149 8.55585C19.6337 6.91131 18.4802 5.50571 17.0001 4.51677C15.5201 3.52784 13.78 3 12 3C9.48395 3.00947 7.06897 3.99122 5.26 5.74L3 8" :stroke="hasSelectedTags ? '#000000' : '#969696'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M3 3V8H8" :stroke="hasSelectedTags ? '#000000' : '#969696'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                  Сбросить
                </button>
              </div>
            </div>
          </div>

          <article
              v-for="(blog, blogIdx) in blogs.data"
              :key="blog.id"
              class="bg-[transparent] overflow-hidden transition-all duration-300"
              style="height: auto;"
          >
            <Link
                :href="`/blog/${blog.slug}`"
                class="block h-[250px] sm:h-[350px] xl:h-[494px] overflow-hidden relative"
            >
              <img v-if="blog.preview_image"
                   :src="`/storage/${blog.preview_image}`"
                   :alt="blog.title"
                   class="w-full h-full object-cover" style="background-color: rgb(247, 247, 247);"
                   :loading="blogIdx === 0 ? 'eager' : 'lazy'"
                   decoding="async" />
              <div v-else class="w-full h-full bg-gradient-to-br from-gray-400 to-gray-500 flex items-center justify-center"></div>

              <div class="absolute top-2 left-2 xl:top-4 xl:left-4 flex flex-wrap gap-2">
                <div v-if="blog.published_at" class="bg-white h-[32px] xl:h-[45px] px-3 xl:px-4 flex items-center shadow-md" style="border-radius: 8px">
                  <span class="text-[14px] xl:text-[18px] font-[400] text-gray-900">
                    {{ formatDate(blog.published_at) }}
                  </span>
                </div>
                <div v-if="blog.duration" class="bg-white h-[32px] xl:h-[45px] px-3 xl:px-4 flex items-center shadow-md" style="border-radius: 8px">
                  <span class="text-[14px] xl:text-[18px] font-[400] text-gray-900">~{{ blog.duration }}</span>
                </div>
                <div v-if="blog.category" class="bg-white h-[32px] xl:h-[45px] px-3 xl:px-4 flex items-center shadow-md" style="border-radius: 8px">
                  <span class="text-[14px] xl:text-[18px] font-[400] text-gray-900">{{ blog.category.name }}</span>
                </div>
                <div v-if="blog.tags && blog.tags.length" class="bg-white h-[32px] xl:h-[45px] px-3 xl:px-4 flex items-center shadow-md" style="border-radius: 8px">
                  <span class="text-[14px] xl:text-[18px] font-[400] text-gray-900">{{ blog.tags[0].name }}</span>
                </div>
              </div>
            </Link>

            <div
                class="py-[2rem] space-y-3 xl:space-y-4" style="padding-bottom: 64px"
            >
              <component
                  :is="blog.author?.id ? Link : 'div'"
                  v-bind="blog.author?.id ? { href: `/blog/author/${blog.author.id}` } : {}"
                  class="flex items-center gap-3 xl:gap-4 text-sm text-gray-600"
              >
                <div class="flex items-center gap-2 xl:gap-3">
                  <div class="w-[45px] h-[45px] xl:w-[60px] xl:h-[60px] rounded-[8px] xl:rounded-[10px] overflow-hidden bg-white flex-shrink-0">
                    <img v-if="blog.author?.avatar"
                         :src="`/storage/${blog.author.avatar}`"
                         class="w-full h-full object-cover"
                         :alt="blog.author?.name"
                         loading="lazy"
                         decoding="async" />
                    <div v-else class="w-full h-full bg-emerald-100 flex items-center justify-center text-xl xl:text-xl font-semibold text-emerald-700">
                      {{ blog.author?.name?.charAt(0) || '?' }}
                    </div>
                  </div>
                  <div class="gap-[5px]" :style="{ display: 'flex', flexFlow: 'column' }">
                    <span class="font-[400] text-[18px] xl:text-[24px] text-gray-900 block">
                      {{ blog.author?.name || 'Аноним' }}
                    </span>
                    <span v-if="blog.author?.author_categories?.length" class="font-[400] text-[16px] xl:text-[18px] text-black opacity-50 block">
                      {{ blog.author.author_categories[0].name }}
                    </span>
                  </div>
                </div>
              </component>

              <Link :href="`/blog/${blog.slug}`" class="block">
              <h2 class="text-[22px] sm:text-[26px] xl:text-[32px] font-[400] text-gray-900 transition-colors line-clamp-2" style="line-height: 1">
                {{ blog.title }}
              </h2>
              <p v-if="blog.excerpt" class="text-black opacity-[0.6] text-[16px] xl:text-[21px] font-[400] truncate" style="margin-top: 8px;line-height: 1.2">
                {{ blog.excerpt }}
              </p>
              <p v-else class="text-gray-600 text-[16px] xl:text-[21px] font-[400]" style="line-height: 1.2">
                {{ excerpt(blog.content, 35) }}
              </p>
              </Link>
            </div>
          </article>

          <div v-if="!blogs.data?.length" class="text-center py-16 bg-gray-50 rounded-xl">
            <div class="text-gray-400 mb-4">
              <svg class="mx-auto h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <h3 class="text-xl font-medium text-gray-900 mb-2">Ничего не найдено</h3>
            <p class="text-gray-500 mb-4">Попробуйте изменить параметры поиска</p>
            <button
              @click="resetFilters"
              class="bg-black text-white px-6 py-3 font-medium transition-all but_cust"
              style="border: 1px solid rgba(0, 0, 0, 0.4); border-radius: 8px"
            >
              Сбросить фильтры
            </button>
          </div>
        </div>

        <div v-if="blogs.last_page > 1" class="mt-8 flex justify-center items-center gap-2">
          <Link
              v-if="blogs.current_page > 1"
              :href="buildPageUrl(blogs.current_page - 1)"
              class="w-[48px] h-[48px] flex items-center justify-center bg-[#EEEEEE]"
          >
            <svg width="8" height="14" viewBox="0 0 8 14" fill="none">
              <path d="M7 13L1 7L7 1" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </Link>
          <span v-else class="w-[48px] h-[48px] flex items-center justify-center bg-[#EEEEEE]">
            <svg width="8" height="14" viewBox="0 0 8 14" fill="none">
              <path d="M7 13L1 7L7 1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>

          <template v-for="page in paginationRange" :key="page">
            <span v-if="page === '...'" class="px-2 text-gray-500">...</span>
            <Link
                v-else
                :href="buildPageUrl(page)"
                :class="page === blogs.current_page ? 'text-black' : 'text-black opacity-[0.4] hover:bg-gray-100'"
                class="w-[48px] h-[48px] flex items-center justify-center rounded-xl"
            >
              {{ page }}
            </Link>
          </template>

          <Link
              v-if="blogs.current_page < blogs.last_page"
              :href="buildPageUrl(blogs.current_page + 1)"
              class="w-[48px] h-[48px] flex items-center justify-center bg-[#EEEEEE]"
          >
            <svg width="8" height="14" viewBox="0 0 8 14" fill="none">
              <path d="M1 13L7 7L1 1" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </Link>
          <span v-else class="w-[48px] h-[48px] flex items-center justify-center bg-[#EEEEEE]">
            <svg width="8" height="14" viewBox="0 0 8 14" fill="none">
              <path d="M1 13L7 7L1 1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
        </div>
      </main>
      <PublicFooter />
    </div>
  </div>
</template>



<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.line-clamp-3 {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.line-clamp-4 {
  display: -webkit-box;
  -webkit-line-clamp: 4;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.filter-btn {
  display: flex;
  flex-direction: row;
  justify-content: center;
  align-items: center;
  padding: 11px 24px;
  gap: 10px;
  width: 195px;
  height: 45px;
  border: 1px solid rgba(0, 0, 0, 0.4);
  border-radius: 10px;
  flex: none;
  order: 1;
  flex-grow: 0;
  background: white;
  transition: all 0.2s ease;
}

.custom-checkbox {
  display: inline-block;
  width: 18px;
  height: 18px;
  border: 2px solid rgba(0, 0, 0, 0.4);
  border-radius: 3px;
  flex-shrink: 0;
  transition: all 0.2s ease;
  box-sizing: border-box;
}

.custom-checkbox.checked {
  background-color: #000000;
  border-color: #000000;
}
.filter-btn:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}


.tag-dropdown {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  padding: 6px 0;
  width: 350px;
  max-height: 277px;
  background: #F5F5F5;
  box-shadow: -10px 0px 24px rgba(0, 0, 0, 0.06);
  border-radius: 12px;
  overflow: hidden;
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
  padding-bottom: 80px;
  box-sizing: border-box;
}
.m_cust{
  margin-bottom: 1rem;
}

.but_cust{
  height: 43px;
  padding: 0 1.5rem;;
}

.filter-btn-tags.cust {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 8px;
  padding: 0 20px;
  height: 45px;
  min-width: 195px;
  max-width: 100%;
  border: 1px solid rgba(0, 0, 0, 0.4);
  border-radius: 10px;
  background: transparent;
  font-size: 18px;
  font-weight: 400;
  color: #000;
  opacity: 0.5;
  cursor: pointer;
  transition: all 0.2s ease;
  overflow: hidden;
}


.filter-btn-tags:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}


.tag-placeholder {
  flex-shrink: 0;
  white-space: nowrap;
  font-size: 16px;
  line-height: 1;
}


.tag-list {
  display: flex;
  align-items: center;
  gap: 4px;
  flex: 1;
  min-width: 0;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  color: rgba(0, 0, 0, 0.5);
}

.tag-item {
  white-space: nowrap;
  flex-shrink: 0;
}

.tag-separator {
  margin-left: 2px;
  color: rgba(0, 0, 0, 0.3);
}

.tag-more {
  white-space: nowrap;
  flex-shrink: 0;
  color: rgba(0, 0, 0, 0.5);
  font-weight: 500;
}
@media (min-width: 1240px) {
  .main-content {
    padding: 64px; /* При ≥1024px = 64px */
    max-width: 1115px;
    margin: 0 auto;
  }
  .m_cust{
    margin-bottom: 0;
  }
  .but_cust{
    height: auto;
    padding: .75rem 1.5rem;
  }
  .tag-placeholder{
    font-size: 18px;
  }
}

.tag-text {
  white-space: nowrap;
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  color: rgba(0, 0, 0, 0.8);
}

.tag-count {
  margin-left: 4px;
  font-weight: 400;
}

.title-wrapper {
  display: inline;
  white-space: pre-wrap!important;
}


.title-category {
  color: #000;
}


.title-separator {
  color: #000;
}


.title-tags {
  color: rgba(0, 0, 0, 0.5);
  white-space: pre-wrap!important;
}

.title-tag {
  color: rgba(0, 0, 0, 0.5);
}

.title-tag-separator {
  color: rgba(0, 0, 0, 0.5);
}


.title-empty {
  color: #000;
}

.blog-lead-slot {
  position: relative;
}

.blog-lead-sizer,
.blog-lead-visible {
  font-size: 21px;
  line-height: 1.25;
  opacity: 0.5;
  margin: 0;
}

.blog-lead-sizer {
  visibility: hidden;
}

.blog-lead-visible {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
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

/* Оверлей */
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

/* Анимация появления/скрытия */
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
.filter-btn-tags:hover{
  box-shadow:unset!important;
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