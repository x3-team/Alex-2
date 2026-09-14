<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import SiteSidebar from '@/Components/SiteSidebar.vue'
import { useDoctorMode } from '@/Composables/useDoctorMode'
import '../../../../css/main.css'
import PublicFooter from '@/Components/PublicFooter.vue'
// 🔹 Принимаем готовые JSON-LD строки с бэкенда (это самый надежный способ для SEO)
const props = defineProps({
  blog: Object,
  relatedPosts: Array, // 🔹 Добавлено, так как используется ниже
  articleJsonLd: String,
  breadcrumbJsonLd: String,
})

const { blog } = props

// 🔹 Вспомогательные свойства только для обычных <meta> тегов (не для JSON-LD)
const siteUrl = 'https://alexallergotest.ru'
const articleDescription = computed(() => props.blog?.seo_description || props.blog?.excerpt || '')
const articleImage = computed(() => props.blog?.preview_image ? `${siteUrl}/storage/${props.blog.preview_image}` : '')

const transliterate = (text) => {
  const translitMap = {
    'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd', 'е': 'e', 'ё': 'yo',
    'ж': 'zh', 'з': 'z', 'и': 'i', 'й': 'y', 'к': 'k', 'л': 'l', 'м': 'm',
    'н': 'n', 'о': 'o', 'п': 'p', 'р': 'r', 'с': 's', 'т': 't', 'у': 'u',
    'ф': 'f', 'х': 'kh', 'ц': 'ts', 'ч': 'ch', 'ш': 'sh', 'щ': 'sch',
    'ъ': '', 'ы': 'y', 'ь': '', 'э': 'e', 'ю': 'yu', 'я': 'ya',
    'А': 'A', 'Б': 'B', 'В': 'V', 'Г': 'G', 'Д': 'D', 'Е': 'E', 'Ё': 'Yo',
    'Ж': 'Zh', 'З': 'Z', 'И': 'I', 'Й': 'Y', 'К': 'K', 'Л': 'L', 'М': 'M',
    'Н': 'N', 'О': 'O', 'П': 'P', 'Р': 'R', 'С': 'S', 'Т': 'T', 'У': 'U',
    'Ф': 'F', 'Х': 'Kh', 'Ц': 'Ts', 'Ч': 'Ch', 'Ш': 'Sh', 'Щ': 'Sch',
    'Ъ': '', 'Ы': 'Y', 'Ь': '', 'Э': 'E', 'Ю': 'Yu', 'Я': 'Ya'
  }
  return text
      .split('')
      .map(char => translitMap[char] || char)
      .join('')
      .toLowerCase()
      .replace(/[^a-z0-9\s-]/g, '')
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-')
      .replace(/^-|-$/g, '')
}

const mobileMenuOpen = ref(false)
const toggleMobileMenu = () => {
  mobileMenuOpen.value = !mobileMenuOpen.value
  document.body.style.overflow = mobileMenuOpen.value ? 'hidden' : ''
}

const closeMobileMenu = () => {
  mobileMenuOpen.value = false
  document.body.style.overflow = ''
}

const formatDate = (dateString) => {
  if (!dateString) return ''
  return new Date(dateString).toLocaleDateString('ru-RU', {
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  })
}

const parseTableOfContents = (text) => {
  if (!text) return []
  try {
    const parsed = JSON.parse(text)
    if (Array.isArray(parsed)) {
      return parsed
          .filter(item => item.level === 2)
          .map(item => ({ ...item, anchor: transliterate(item.text) }))
    }
  } catch (e) {
    console.warn('TOC is not valid JSON')
    return []
  }
  return []
}

const parseSources = (sources) => {
  if (!sources) return []
  if (Array.isArray(sources)) {
    return sources.map(item => typeof item === 'string' ? { title: item, url: '' } : { title: item.title || '', url: item.url || '' })
  }
  if (typeof sources === 'string') {
    try {
      const parsed = JSON.parse(sources)
      if (Array.isArray(parsed)) {
        return parsed.map(item => typeof item === 'string' ? { title: item, url: '' } : { title: item.title || '', url: item.url || '' })
      }
    } catch (e) {
      return sources.split('\n').filter(line => line.trim()).map(line => ({ title: line.trim(), url: '' }))
    }
  }
  return []
}

const scrollToAnchor = (anchorId) => {
  if (!anchorId) return

  const element = document.getElementById(anchorId)
  if (element) {
    element.scrollIntoView({
      behavior: 'smooth',
      block: 'start'
    })
    history.pushState(null, null, `#${anchorId}`)
  }
}
const wrapTables = () => {
  const contentEl = document.querySelector('.blog-content')
  if (!contentEl) return

  const tables = contentEl.querySelectorAll('table')
  tables.forEach(table => {
    // Проверяем, не обернута ли уже таблица в наш wrapper
    if (table.parentElement && table.parentElement.classList.contains('table-wrapper')) {
      return
    }

    // Создаем контейнер-обертку
    const wrapper = document.createElement('div')
    wrapper.className = 'table-wrapper'

    // Вставляем wrapper перед таблицей и перемещаем таблицу внутрь
    table.parentNode.insertBefore(wrapper, table)
    wrapper.appendChild(table)
  })
}
onMounted(() => {
  nextTick(() => {
    // Находим все заголовки внутри контента статьи
    const headings = document.querySelectorAll('.blog-content h2, .blog-content h3, .blog-content h4, .blog-content h5')

    headings.forEach(heading => {
      // Генерируем id из текста заголовка для всех элементов
      if (heading.textContent) {
        heading.id = transliterate(heading.textContent)
      }
    })

    // Скролл при прямой ссылке с хешем в URL
    if (window.location.hash) {
      const anchorId = decodeURIComponent(window.location.hash.substring(1))
      setTimeout(() => { scrollToAnchor(anchorId) }, 300)
    }

    wrapTables()
  })
})
const currentRating = ref(0)
const hoverRating = ref(0)
const ratingSubmitted = ref(false)
const visitorId = ref('')

onMounted(() => {
  let vid = getCookie('visitor_id')
  if (!vid) {
    vid = 'visitor_' + Math.random().toString(36).substring(2, 15) + Date.now().toString(36)
    setCookie('visitor_id', vid, 365)
  }
  visitorId.value = vid
})

const getCookie = (name) => {
  const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'))
  return match ? match[2] : null
}

const setCookie = (name, value, days) => {
  const d = new Date()
  d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000))
  document.cookie = name + '=' + value + ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax'
}

const submitRating = async (rating) => {
  currentRating.value = rating
  try {
    const response = await fetch(`/blog/${props.blog.id}/rate`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({ rating: rating, visitor_id: visitorId.value }),
    })
    if (response.ok) {
      const data = await response.json()
      if (props.blog) {
        props.blog.average_rating = data.average_rating
        props.blog.ratings_count = data.ratings_count
      }
      ratingSubmitted.value = true
      setTimeout(() => { ratingSubmitted.value = false }, 3000)
    }
  } catch (error) {
    console.error('Ошибка оценки:', error)
  }
}

const pluralizeRatings = (count) => {
  const lastTwo = count % 100
  const lastOne = count % 10
  if (lastTwo >= 11 && lastTwo <= 14) return 'оценок'
  if (lastOne === 1) return 'оценка'
  if (lastOne >= 2 && lastOne <= 4) return 'оценки'
  return 'оценок'
}

const parsedSources = computed(() => {
  if (!blog.sources) return []
  try {
    const parsed = JSON.parse(blog.sources)
    if (Array.isArray(parsed)) {
      return parsed.map(item => typeof item === 'string' ? { title: item, url: '' } : { title: item.title || '', url: item.url || '' })
    }
  } catch (e) {
    return [{ title: blog.sources, url: '' }]
  }
  return []
})

const relatedSource = computed(() => {
  const curated = props.blog?.related_posts
  if (Array.isArray(curated) && curated.length) return curated
  return Array.isArray(props.relatedPosts) ? props.relatedPosts : []
})
const filteredRelatedPosts = computed(() => {
  return relatedSource.value.filter(post => {
    if (post.is_active === false) return false
    if (!post.published_at) return false
    const publishDate = new Date(post.published_at)
    if (publishDate > new Date()) return false
    return true
  })
})
const showUpdatedAt = computed(() => {
  if (!blog?.published_at || !blog?.updated_at) return false
  return new Date(blog.updated_at).getTime() - new Date(blog.published_at).getTime() > 60000
})
const openFaqIndexes = ref([])

const toggleFaq = (index) => {
  if (openFaqIndexes.value.includes(index)) {
    openFaqIndexes.value = openFaqIndexes.value.filter(i => i !== index)
  } else {
    openFaqIndexes.value.push(index)
  }
}
</script>

<template>
  <div
      class="page-container search-page-container site-sidebar-layout"
      :class="{}"
  >

     <SiteSidebar />

      <div class="flex-1 flex flex-col bg-[#f7f7f7;] " style="background-color: #f7f7f7; width: 100%; overflow: auto">




        <header class="hidden xl:block bg-white border-b border-gray-200 sticky top-0 z-10">
          <div class="px-9 py-4 flex align-center height-[72px]">
            <Link href="/blog" class="inline-flex items-center gap-2 text-gray-700 font-medium transition-colors w-full">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
              Обратно ко всем материалам
            </Link>
          </div>
        </header>


        <main class="main-content p-8 xl:p-16 flex-1">



          <div class="breadcrumbs flex items-center gap-2 lg:gap-3 mb-8 w-full">
            <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" class="flex-shrink-0">
              <rect x="6" y="6" width="36" height="36" rx="18" stroke="black" stroke-width="2"/>
              <path d="M27.6667 30.625V29.0417C27.6667 28.2018 27.3331 27.3964 26.7392 26.8025C26.1453 26.2086 25.3399 25.875 24.5 25.875H19.75C18.9102 25.875 18.1047 26.2086 17.5109 26.8025C16.917 27.3964 16.5834 28.2018 16.5834 29.0417V30.625" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M27.6666 16.4763C28.3457 16.6524 28.9471 17.0489 29.3764 17.6037C29.8057 18.1585 30.0386 18.8402 30.0386 19.5417C30.0386 20.2432 29.8057 20.9248 29.3764 21.4796C28.9471 22.0344 28.3457 22.431 27.6666 22.607" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M32.4166 30.625V29.0417C32.4161 28.34 32.1826 27.6584 31.7527 27.1039C31.3228 26.5494 30.721 26.1533 30.0416 25.9779" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M22.125 22.7083C23.8739 22.7083 25.2917 21.2906 25.2917 19.5417C25.2917 17.7928 23.8739 16.375 22.125 16.375C20.3761 16.375 18.9584 17.7928 18.9584 19.5417C18.9584 21.2906 20.3761 22.7083 22.125 22.7083Z" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>


            <Link href="/" class="flex-shrink-0 text-[14px] lg:text-[18px] text-black opacity-30 whitespace-nowrap">
              Главная
            </Link>

            <span class="text-black opacity-[0.3] flex-shrink-0"><svg width="6" height="9" viewBox="0 0 6 9" fill="none" xmlns="http://www.w3.org/2000/svg">
    <path d="M0.75 8.25L4.5 4.5L0.75 0.75" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
  </svg></span>

            <Link href="/blog" class="flex-shrink-0 text-[14px] lg:text-[18px] text-black opacity-30 whitespace-nowrap">
              Блог
            </Link>

            <template v-if="blog?.category?.slug">
              <span class="text-black opacity-[0.3] flex-shrink-0"><svg width="6" height="9" viewBox="0 0 6 9" fill="none" xmlns="http://www.w3.org/2000/svg">
    <path d="M0.75 8.25L4.5 4.5L0.75 0.75" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
  </svg></span>
              <Link
                :href="`/blog/${blog.category.slug}`"
                class="flex-shrink-0 text-[14px] lg:text-[18px] text-black opacity-30 whitespace-nowrap"
              >
                {{ blog.category.name }}
              </Link>
            </template>

            <span class="text-black opacity-[0.3] flex-shrink-0"><svg width="6" height="9" viewBox="0 0 6 9" fill="none" xmlns="http://www.w3.org/2000/svg">
    <path d="M0.75 8.25L4.5 4.5L0.75 0.75" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
  </svg></span>

            <span class="flex-1 w-0 text-[14px] lg:text-[18px] text-black truncate min-w-0" :title="blog?.title">
  {{ blog?.title }}
</span>
          </div>


          <div class="flex items-center gap-3 xl:gap-4 mb-6">
            <Link
                v-if="blog?.author?.id"
                :href="`/blog/author/${blog.author.id}`"
                class="flex items-center gap-3 xl:gap-4"
            >
              <div class="w-[45px] h-[45px] xl:w-[60px] xl:h-[60px] rounded-[8px] xl:rounded-[10px] overflow-hidden bg-white flex-shrink-0">
                <img v-if="blog?.author?.avatar"
                     :src="`/storage/${blog.author.avatar}`"
                     class="w-full h-full object-cover"
                     :alt="blog.author?.name"
                     decoding="async" />
                <div v-else class="w-full h-full bg-emerald-100 flex items-center justify-center text-xl font-semibold text-emerald-700">
                  {{ blog?.author?.name?.charAt(0) || '?' }}
                </div>
              </div>
              <div :style="{ display: 'flex', flexFlow: 'column' }">
         <span class="font-[400] text-[18px] xl:text-[24px] text-gray-900 block" style="line-height: 1.2;">
          {{ blog.author?.name || 'Аноним' }}<template v-if="blog.author?.credentials">, {{ blog.author.credentials }}</template>
        </span>
                <span v-if="blog.author?.author_categories?.length" class="font-[400] text-[16px] xl:text-[18px] text-black opacity-50 block">
 {{ blog.author.author_categories.map(cat => cat.name).join(', ') }}
</span>
              </div>
            </Link>


            <div v-else class="flex items-center gap-3 xl:gap-4">
              <div class="w-[45px] h-[45px] xl:w-[60px] xl:h-[60px] rounded-[8px] xl:rounded-[10px] overflow-hidden bg-white flex-shrink-0">
                <div class="w-full h-full bg-emerald-100 flex items-center justify-center text-xl font-semibold text-emerald-700">?</div>
              </div>
              <span class="font-[400] text-[18px] xl:text-[24px] text-gray-900">Аноним</span>
            </div>
          </div>


          <h1 class="text-[28px] xl:text-[32px] font-[400] text-gray-900 mb-4 xl:mb-6 leading-tight">
            {{ blog?.title }}
          </h1>

          <div class="flex flex-wrap items-center gap-[5px] pb-4 mb-4" style="border-bottom: 1px solid rgba(0, 0, 0, 0.3);     padding-bottom: 32px;
    margin-bottom: 32px; ">
            <div v-if="blog?.duration" class="h-[36px] xl:h-[45px] px-3 xl:px-4 flex items-center " style="border-radius:8px;background-color: rgba(237, 237, 237, 1);">
              <span class="text-[14px] xl:text-[18px] font-[400] text-gray-900">~{{ blog.duration }}</span>
            </div>
            <Link
              v-if="blog?.category?.slug"
              :href="`/blog/${blog.category.slug}`"
              class="h-[36px] xl:h-[45px] px-3 xl:px-4 flex items-center "
              style="border-radius:8px;background-color: rgba(237, 237, 237, 1);"
            >
              <span class="text-[14px] xl:text-[18px] font-[400] text-gray-900">{{ blog.category.name }}</span>
            </Link>
            <div v-if="blog?.tags?.length" class="text-[18px] font-[400] h-[45px] px-4 flex items-center " style="border-radius: 8px; background-color: rgba(237, 237, 237, 1);">
              {{ blog.tags[0].name }}
            </div>
            <div
                v-if="blog?.published_at"
                class="text-[18px] font-[400] h-[45px] px-4 flex items-center"
                style="border-radius: 8px; color:rgba(171, 171, 171, 1); background-color: rgba(237, 237, 237, 0.6);"
            >
              Опубликовано {{ formatDate(blog.published_at) }}
            </div>

            <div
                v-if="showUpdatedAt"
                class="text-[18px] font-[400] h-[45px] px-4 flex items-center"
                style="border-radius: 8px; color:rgba(171, 171, 171, 1); background-color: rgba(237, 237, 237, 0.6);"
            >
              Обновлено {{ formatDate(blog.updated_at) }}
            </div>
          </div>

          <div v-if="blog?.table_of_contents" class="blog-toc mb-8">
            <h3 class="text-[18px] font-[400] text-gray-900 mb-3">Содержание</h3>
            <ul class="list-disc pl-5 space-y-1">

              <li v-for="(item, index) in parseTableOfContents(blog.table_of_contents)"
                  :key="index"
                  :style="{ marginLeft: (item.level - 2) * 16 + 'px' }"
                  style="color: rgb(171, 171, 171);"
                  class="text-[16px] font-[400]">
                <a
                    :href="'#' + item.anchor"
                    class="hover:text-emerald-700 transition-colors cursor-pointer"
                    @click.prevent="scrollToAnchor(item.anchor)"
                >
                  {{ item.text }}
                </a>
              </li>
            </ul>
          </div>

          <div class="blog-content" v-html="blog?.content"></div>
          <div v-if="parsedSources.length" class="blog-sources">
            <h3 class="text-[18px] font-[400] text-gray-900 mb-3">Источники</h3>
            <p class="text_sources">Отбираем лучшие материалы, чтобы быть уверенными в достоверности наших статей.</p>
            <ol class="list-decimal pl-5 space-y-2 w-full">
              <li
                  v-for="(source, index) in parsedSources"
                  :key="index"
                  class="text-[16px] font-[400] text-gray-700 break-words overflow-wrap-anywhere"
              >
                <a
                    v-if="source.url"
                    :href="source.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-[#0073FF] hover:underline"
                >
                  {{ source.title }}
                </a>
                <span v-else>{{ source.title }}</span>
              </li>
            </ol>
          </div>

          <div class="blog-rating-block">
            <div class="blog-rating-title">Нажмите, чтобы оценить материал</div>


            <div class="blog-rating-stars">
              <button
                  v-for="star in 5"
                  :key="star"
                  @click="submitRating(star)"
                  class="blog-rating-star"
                  :class="{ 'active': star <= currentRating, 'hovered': star <= hoverRating }"
                  @mouseenter="hoverRating = star"
                  @mouseleave="hoverRating = 0"
              >
                <svg width="64" height="64" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path
                      d="M30.7333 6.11988C30.8502 5.88377 31.0307 5.68503 31.2545 5.54608C31.4783 5.40713 31.7365 5.3335 32 5.3335C32.2634 5.3335 32.5216 5.40713 32.7454 5.54608C32.9692 5.68503 33.1498 5.88377 33.2666 6.11988L39.4266 18.5972C39.8324 19.4185 40.4315 20.129 41.1723 20.6678C41.9131 21.2065 42.7736 21.5575 43.68 21.6905L57.456 23.7065C57.717 23.7444 57.9622 23.8545 58.1639 24.0244C58.3656 24.1944 58.5158 24.4173 58.5973 24.6682C58.6789 24.919 58.6887 25.1876 58.6255 25.4437C58.5624 25.6998 58.4288 25.9331 58.24 26.1172L48.2773 35.8185C47.6203 36.4588 47.1287 37.2492 46.8448 38.1216C46.561 38.9939 46.4934 39.9223 46.648 40.8265L49 54.5332C49.046 54.7941 49.0178 55.0627 48.9186 55.3084C48.8194 55.554 48.6531 55.7668 48.4387 55.9225C48.2244 55.7649 47.9706 56.1503 47.7063 56.1872C47.442 56.2073 47.1778 56.1511 46.944 56.0265L34.6293 49.5519C33.8179 49.1258 32.9151 48.9032 31.9986 48.9032C31.0821 48.9032 30.1794 49.1258 29.368 49.5519L17.056 56.0265C16.8222 56.1503 16.5583 56.206 16.2945 56.1872C16.0306 56.1685 15.7773 56.076 15.5634 55.9205C15.3495 55.7649 15.1835 55.5524 15.0844 55.3071C14.9852 55.0619 14.9569 54.7938 15.0026 54.5332L17.352 40.8292C17.5071 39.9245 17.4399 38.9956 17.1561 38.1227C16.8722 37.2497 16.3802 36.459 15.7226 35.8185L5.75996 26.1199C5.56954 25.936 5.43461 25.7023 5.37052 25.4454C5.30644 25.1886 5.31579 24.9189 5.3975 24.6671C5.47921 24.4153 5.63 24.1915 5.8327 24.0212C6.03539 23.8509 6.28183 23.7409 6.54396 23.7039L20.3173 21.6905C21.2246 21.5585 22.0863 21.208 22.8282 20.6692C23.57 20.1303 24.1698 19.4193 24.576 18.5972L30.7333 6.11988Z"
                      :fill="(star <= currentRating || star <= hoverRating) ? '#000' : 'none'"
                      stroke="black"
                      stroke-width="1.5"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                  />
                </svg>
              </button>
            </div>


            <p v-if="blog?.ratings_count > 0" class="blog-rating-average">
              Средняя оценка: {{ blog.average_rating }} из 5 ({{ blog.ratings_count }} {{ pluralizeRatings(blog.ratings_count) }})
            </p>


            <p class="blog-rating-text">
              Это поможет нам создавать более интересный контент и радовать вас.
            </p>


            <p v-if="ratingSubmitted" class="blog-rating-success">
              ✓ Спасибо за вашу оценку!
            </p>
          </div>


          <!-- 🔹 Блок FAQ -->
          <div v-if="blog?.faqs && blog.faqs.length" class="blog-faq-block">
            <h3 class="text-[24px] xl:text-[32px] font-[400] text-gray-900 mb-6">
              Часто задаваемые вопросы(FAQ)
            </h3>

            <div class="faq-list">
              <div
                  v-for="(faq, index) in blog.faqs"
                  :key="index"
                  class="faq-item"
              >
                <div
                    class="faq-question flex items-center justify-between py-4 cursor-pointer select-none"
                    @click="toggleFaq(index)"
                >
                  <span class="text-[18px] xl:text-[20px] font-[500] text-gray-900 pr-4">
                    {{ faq.question }}
                  </span>

                  <!-- Стрелочка вниз с плавной анимацией поворота -->
                  <svg
                      class="w-6 h-6 flex-shrink-0 transition-transform duration-300 transform"
                      :class="{ 'rotate-180': openFaqIndexes.includes(index) }"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                      xmlns="http://www.w3.org/2000/svg"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </div>

                <!-- Ответ на вопрос -->
                <Transition name="faq-fade">
                  <div
                      v-show="openFaqIndexes.includes(index)"
                      class="faq-answer pb-4 text-[16px] xl:text-[18px] text-gray-700 leading-relaxed"
                      v-html="faq.answer"
                  ></div>
                </Transition>

                <!-- Светло-серая полоса под всеми вопросами, кроме последнего -->
                <div
                    v-if="index !== blog.faqs.length - 1"
                    class="border-b border-[#E5E7EB]"
                ></div>
              </div>
            </div>
          </div>

          <div v-if="filteredRelatedPosts.length" class="mt-16 pt-8">
            <h4 class="text-[24px] xl:text-[32px] font-[400] text-gray-900 mb-4">
              Также рекомендуем
            </h4>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <article
                  v-for="related in filteredRelatedPosts"
                  :key="related.id"
                  @click="$inertia.visit(`/blog/${related.slug}`)"
                  class="bg-[transparent] overflow-hidden transition-all duration-300 cursor-pointer"
              >

                <div class="block overflow-hidden relative" style="max-height: 494px;     aspect-ratio: 16 / 9;">
                  <img v-if="related.preview_image"
                       :src="`/storage/${related.preview_image}`"
                       :alt="related.title"
                       class="w-full h-full object-cover transition-transform duration-500"
                       style="background-color: rgb(247, 247, 247);"
                       loading="lazy"
                       decoding="async" />
                  <div v-else class="w-full h-full bg-gradient-to-br from-gray-400 to-gray-500 flex items-center justify-center">
                    <span class="text-gray-300">Нет изображения</span>
                  </div>


                  <div class="absolute top-3 left-3 flex gap-2">
                    <div v-if="related.duration" class="bg-white h-[36px] px-3 flex items-center rounded-xl shadow-md">
                      <span class="text-[14px] font-[400] text-gray-900">~{{ related.duration }}</span>
                    </div>
                    <div v-if="related.category" class="bg-white h-[36px] px-3 flex items-center rounded-xl shadow-md">
                      <span class="text-[14px] font-[400] text-gray-900">{{ related.category.name }}</span>
                    </div>
                  </div>
                </div>


                <div class="py-5 space-y-3">

                  <div class="flex items-center gap-3 cursor-pointer" @click.stop="$inertia.visit(`/blog/author/${related.author?.id}`)">
                    <div class="w-[40px] h-[40px] rounded-[8px] overflow-hidden bg-white flex-shrink-0">
                      <img v-if="related.author?.avatar"
                           :src="`/storage/${related.author.avatar}`"
                           class="w-full h-full object-cover"
                           :alt="related.author?.name"
                           loading="lazy"
                           decoding="async" />
                      <div v-else class="w-full h-full bg-emerald-100 flex items-center justify-center text-xl font-semibold text-emerald-700">
                        {{ related.author?.name?.charAt(0) || '?' }}
                      </div>
                    </div>
                    <div style="display: flex; flex-flow: column;">
                      <div class="text-[24px] font-[400] text-black" style="line-height: 1;">
                        {{ related.author?.name || 'Аноним' }}
                      </div>
                      <span v-if="related.author?.author_categories?.length"  style="line-height: 1" class="font-[400] text-[16px] xl:text-[18px] text-black opacity-50 block">
          {{ related.author.author_categories[0].name }}
        </span>
                    </div>
                  </div>


                  <div class="text-[32px] font-[400] text-black line-clamp-2 hover:text-emerald-700 transition-colors" style="line-height: 1">
                    {{ related.title }}
                  </div>


                  <p class="text-gray-600 text-[16px] font-[400] line-clamp-3" style="margin-top: 8px">
                    {{ related.excerpt || (related.content ? excerpt(related.content, 25) : '') }}
                  </p>
                </div>
              </article>
            </div>
          </div>
        </main>
        <PublicFooter />
      </div>
    </div>

    <div v-if="!mobileMenuOpen" class="xl:hidden fixed bottom-0 left-0 right-0 z-50">
      <button
          @click="toggleMobileMenu"
          class="mobile-menu-button"
      >
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M4 5H20" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M4 12H20" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M4 19H20" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="mobile-menu-button-text">Меню</span>
      </button>
    </div>


    <Transition name="mobile-menu">
      <div v-if="mobileMenuOpen" class="xl:hidden fixed inset-0 z-[9999]">

        <div class="absolute inset-0 bg-black/40" @click="closeMobileMenu"></div>


        <div class="mobile-menu-content">

          <div class="mobile-menu-gradient">
            <div class="mobile-menu-logo">
              <svg width="332" height="120" viewBox="0 0 332 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M26.5356 13.0822L44.3736 13.0997C45.9726 13.0963 52.3093 12.9632 53.5942 13.3145C54.9183 15.9957 55.3306 17.7293 56.2668 20.4805L58.7924 27.7077L66.8783 50.9292L75.0641 74.2776C76.0204 76.9284 76.9407 79.4015 77.7939 82.109C78.4313 84.1315 79.464 86.1484 79.8747 88.2809C71.2988 88.6215 61.9777 88.0197 53.2356 88.3908C52.6279 85.7234 51.5709 82.4397 50.7738 79.7576C50.5286 79.1777 50.3624 78.2775 50.2214 77.6424C49.2132 77.7162 48.1206 77.717 47.1061 77.6989C41.0559 77.5908 34.8609 77.9065 28.8249 77.6627C28.0038 80.6389 26.8126 85.5244 25.8959 88.3029C23.5458 88.423 19.9864 88.2948 17.5331 88.2956L0.185547 88.3045C1.2604 84.8386 2.91941 80.5255 4.12642 77.0369L14.5921 47.0445L22.8166 23.4891C24.0227 20.0864 25.1126 16.403 26.5356 13.0822ZM34.0761 58.8508C35.9313 58.8238 37.7868 58.8197 39.6421 58.8385L45.0989 58.827C43.4149 52.365 41.3979 45.6776 39.6011 39.235C38.2583 43.1166 37.3429 46.6803 36.331 50.6332C35.6148 53.4304 34.6373 55.9352 34.0761 58.8508Z" fill="#FEFEFE"/>
                <path d="M211.59 13.0161L211.824 13.0381L212.026 13.3321C212.135 17.052 211.997 21.0473 212.057 24.8074C212.08 26.3067 212.151 31.1819 211.922 32.3035C210.76 33.024 185.076 32.6628 181.488 32.6211C181.482 34.7929 181.58 38.6761 181.334 40.7093C190.574 40.8254 199.853 40.6813 209.1 40.7583C209.251 45.8434 209.099 50.967 209.163 56.0562C209.18 57.3733 209.139 58.6956 209.04 60.0094C204.791 60.2788 198.681 60.0308 194.27 60.0681C190.764 60.0979 184.67 60.2441 181.415 59.9929C181.522 61.73 181.529 67.0721 181.352 68.7138C187.28 68.7594 193.207 68.7746 199.135 68.7595C203.34 68.76 207.935 68.8422 212.104 68.6455C211.845 73.0055 212.042 79.7462 212.047 84.2963C212.055 85.6323 212.052 86.9683 212.036 88.3043C206.972 88.4932 200.473 88.2924 195.305 88.2908L156.953 88.3441L156.935 42.6139C156.935 33.067 156.73 22.6876 157.009 13.1994C158.325 13.0543 160.611 13.1091 161.972 13.1088L170.424 13.1093L197.051 13.1166C201.355 13.1179 207.456 13.3025 211.59 13.0161Z" fill="#FEFEFE"/>
                <path d="M91.762 13.1351C99.8158 12.9625 108.22 13.3368 116.234 13.0392C116.394 24.4613 116.25 36.1553 116.258 47.599L116.266 60.7063C116.267 62.9794 116.35 66.0098 116.195 68.2264C118.353 68.1033 120.833 68.254 123.023 68.2003C130.217 68.0234 138.293 68.5194 145.38 68.0732C145.272 74.696 145.564 81.6785 145.332 88.2339C144.444 88.3943 141.382 88.3154 140.345 88.3099L130.506 88.2812L91.6223 88.3338L91.6007 33.4953L91.5848 20.1723C91.5835 18.9153 91.4903 14.1066 91.762 13.1351Z" fill="#FEFEFE"/>
                <path d="M134.555 102.922C138.262 102.637 141.043 104.265 141.016 108.218C140.982 113.2 141.741 115.855 136.044 117.094C134.369 117.476 132.423 116.583 130.898 115.941C129.396 114.012 129.591 111.674 129.577 109.375C129.549 105.118 130.072 103.402 134.555 102.922ZM131.143 113.267C132.316 115.225 133.563 115.697 135.783 115.677C136.846 115.415 137.261 115.256 138.267 114.815C139.491 112.726 139.267 111.61 139.3 109.293C139.344 106.13 138.539 104.324 135.045 104.377C129.76 104.97 131.185 109.15 131.143 113.267Z" fill="#FEFEFE"/>
                <path d="M144.441 102.963C145.361 103.002 146.279 102.981 147.197 103.011C150.387 103.117 152.524 102.479 154.472 105.504C154.872 109.217 154.203 110.695 150.352 111.181C151.217 112.421 154.129 115.707 154.285 116.711L154.084 116.959C153.61 117.07 153.149 116.885 152.677 116.748C151.761 115.888 149.037 112.219 148.427 111.124L146.225 111.117C146.259 112.761 146.419 115.154 146.02 116.701C145.635 117.045 145.737 116.941 145.107 116.97C145.036 116.91 145.038 116.914 144.965 116.838C143.875 115.711 144.414 105.226 144.441 102.963ZM146.143 109.677C147.776 109.685 151.149 110.049 152.193 109.021C152.481 108.626 152.805 108.196 152.917 107.718C153.824 103.864 148.309 104.689 146.223 104.58C146.244 106.274 146.305 107.993 146.143 109.677Z" fill="#FEFEFE"/>
                <path d="M49.9599 103C52.4744 103.136 56.6084 102.563 58.4751 103.935C59.307 104.538 59.8579 105.453 60.0013 106.469C60.4653 109.555 58.2873 110.827 55.5963 111.206C56.1848 111.921 59.8838 116.462 59.9161 116.871C59.5521 117.069 58.827 116.899 58.3794 116.839C57.0777 115.962 54.6016 112.475 53.5513 111.106L51.4243 111.129C51.4306 112.789 51.4651 114.472 51.3267 116.127C51.2803 116.683 51.2805 116.795 50.7937 117.001L50.3691 116.82C49.7071 116.086 49.8982 113.531 49.8888 112.448C49.8649 109.298 49.8886 106.149 49.9599 103ZM51.3638 109.678C53.0575 109.693 56.3822 109.998 57.658 109.08C58.449 108.068 58.9124 106.164 57.5912 105.35C55.7152 104.193 53.4813 104.741 51.4097 104.565C51.4133 106.06 51.4778 108.24 51.3638 109.678Z" fill="#FEFEFE"/>
                <path d="M170.292 102.969C172.761 103.047 176.748 102.586 178.9 103.944C179.657 104.484 180.307 105.443 180.384 106.38C180.648 109.565 178.85 110.851 176.015 111.191C176.569 111.98 180.055 116.221 180.228 116.788C179.834 116.963 179.233 116.849 178.784 116.806C177.13 115.596 175.319 112.818 173.888 111.08C173.233 111.126 172.499 111.125 171.837 111.14C171.892 112.248 171.969 116.151 171.231 116.809C170.74 116.783 170.892 116.86 170.6 116.577C169.983 113.799 170.257 106.268 170.292 102.969ZM171.829 109.657C173.797 109.694 176.375 109.954 178.104 109.157C180.278 103.613 175.331 104.744 171.85 104.589C171.85 106.259 171.873 107.993 171.829 109.657Z" fill="#FEFEFE"/>
                <path d="M106.051 102.934C108.693 103.17 112.551 102.537 114.458 104.027C115.348 104.714 115.921 105.731 116.046 106.846C116.184 108.015 115.841 109.189 115.098 110.102C113.972 111.498 112.784 111.709 111.128 111.896C109.85 111.926 108.895 111.94 107.609 111.852C107.62 113.187 107.717 115.484 107.138 116.617C106.689 116.956 106.891 116.902 106.344 116.844C105.701 115.473 105.973 104.985 106.051 102.934ZM107.515 110.451C109.449 110.363 111.745 110.662 113.343 109.76C114.415 108.622 115.258 106.855 113.783 105.663C112.033 104.248 109.659 104.618 107.57 104.582C107.597 106.272 107.637 108.803 107.515 110.451Z" fill="#FEFEFE"/>
                <path d="M241.534 64.2396C243.024 64.1364 244.266 64.1065 245.527 65.012C248.667 67.2655 248.719 73.8142 244.178 74.5236C236.825 75.8453 235.089 65.3829 241.534 64.2396Z" fill="white"/>
                <path d="M248.9 75.5974C253.889 75.0776 257.759 77.628 255.724 83.4291C255.356 84.4787 254.024 84.9883 252.988 85.2646C251.229 85.4912 249.704 85.0935 248.042 84.6085C245.143 81.5016 244.711 77.8025 248.9 75.5974Z" fill="white"/>
                <path d="M295.115 75.2419C303.116 74.8725 301.917 84.0525 296.217 84.8966C294.738 84.9597 294.014 84.9006 292.556 84.6571C289.827 82.8107 290.075 78.6107 292.326 76.5487C293.221 75.7294 293.967 75.5411 295.115 75.2419Z" fill="white"/>
                <path d="M257.767 86.4028C259.571 86.2638 261.45 86.1957 262.992 87.2753C266.056 89.4199 265.667 94.9189 261.523 95.5439C256.726 96.1863 253.283 93.8023 255.095 88.3697C255.442 87.3306 256.731 86.7231 257.767 86.4028Z" fill="white"/>
                <path d="M302.784 64.2497C303.867 64.1907 305.455 64.0557 306.336 64.6864C310.032 67.3369 308.897 72.9949 304.624 74.2222C297.183 75.1456 297.172 65.4101 302.784 64.2497Z" fill="white"/>
                <path d="M257.068 64.8908C258.697 64.799 259.984 64.7239 261.453 65.658C263.557 66.992 264.24 70.4024 262.849 72.449C262.017 73.6733 260.962 74.1582 259.56 74.4431C258.449 74.5568 257.417 74.5432 256.374 74.0851C255.136 73.5452 254.173 72.5239 253.708 71.2579C252.547 68.174 254.09 65.9056 257.068 64.8908Z" fill="white"/>
                <path d="M286.84 86.2469C293.506 85.0552 294.493 93.9823 288.432 95.4104C287.034 95.4517 284.883 95.6608 283.674 94.8696C281.754 93.6137 281.933 89.916 283.132 88.1964C284.053 86.8759 285.314 86.4785 286.84 86.2469Z" fill="white"/>
                <path d="M249.272 53.6019C250.75 53.5887 251.953 53.522 253.197 54.5015C255.187 56.0692 255.293 59.4931 253.787 61.4179C252.835 62.6328 251.838 62.982 250.347 63.1613C250.165 63.173 249.985 63.1787 249.804 63.1782C247.891 63.1668 246.978 62.4358 245.717 61.1584C244.668 57.7326 244.833 54.032 249.272 53.6019Z" fill="white"/>
                <path d="M265.997 76.2028C268.429 75.9799 271.43 76.5427 272.003 79.2589C272.688 82.4927 271.635 84.4918 268.443 85.256C266.584 85.3884 265.887 85.2655 264.078 84.7777C262.385 83.0208 261.416 80.3832 262.825 78.0539C263.603 76.7662 264.619 76.5075 265.997 76.2028Z" fill="white"/>
                <path d="M278.948 76.2056C281.181 76.0057 282.122 76.256 284.063 77.2791C286.099 80.3459 285.164 84.2839 281.232 85.037C279.765 85.1184 278.224 85.3123 276.875 84.5127C274.797 83.2812 274.44 80.1889 275.675 78.2399C276.539 76.8751 277.463 76.551 278.948 76.2056Z" fill="white"/>
                <path d="M287.442 64.8278C289.219 64.3922 291.091 64.9887 292.286 66.3713C293.482 67.7538 293.798 69.6887 293.105 71.3783C292.518 72.809 291.282 73.8751 289.778 74.2489C287.17 74.8977 284.527 73.3104 283.881 70.7058C283.235 68.1012 284.831 65.4679 287.442 64.8278Z" fill="white"/>
                <path d="M243.207 85.7709C247.911 85.3194 249.509 88.5246 249.279 92.6434C248.25 94.3461 247.878 94.7361 245.909 95.014C242.67 94.9839 240.062 93.7071 239.604 90.1668C239.249 87.4131 240.486 86.0777 243.207 85.7709Z" fill="white"/>
                <path d="M235.216 74.946C235.91 74.8346 237.523 75.1685 238.194 75.4887C240.644 76.6575 241.232 80.0004 240.506 82.374C240.401 82.7188 240.073 83.3288 239.893 83.6856C239.062 84.1855 238.45 84.4902 237.496 84.6487C235.918 84.6611 234.946 84.5659 233.602 83.5677C231.528 82.0292 230.867 78.2232 232.423 76.1478C233.146 75.1811 234.078 75.0521 235.216 74.946Z" fill="white"/>
                <path d="M242.998 31.0903C245.145 30.9517 246.249 30.9677 247.836 32.572C248.392 34.3198 248.239 35.725 248.014 37.5184C246.891 39.2713 246.005 39.8399 244.054 40.3064C239.698 40.6981 237.585 38.0475 239.255 33.9315C239.936 32.2512 241.38 31.6617 242.998 31.0903Z" fill="white"/>
                <path d="M248.759 41.2986C249.385 41.2396 250.012 41.2047 250.64 41.1942C252.577 41.178 253.34 41.8346 254.639 43.114C255.401 46.611 255.023 48.9413 251.335 50.2396C250.053 50.4101 249.182 50.5695 247.958 50.0591C246.926 49.6401 246.109 48.8213 245.694 47.7903C244.578 45.0411 246.075 42.3395 248.759 41.2986Z" fill="white"/>
                <path d="M295.648 53.7075C302.721 53.4586 303.277 61.1827 298.258 63.0396C296.045 63.3945 294.993 63.0749 293.221 61.8277C291.879 59.1922 291.905 56.4464 293.957 54.2077L295.648 53.7075Z" fill="white"/>
                <path d="M258.197 30.621C265.064 29.9455 265.92 38.0253 259.614 39.3453C252.342 40.0748 252.327 31.5781 258.197 30.621Z" fill="white"/>
                <path d="M252.066 21.3795C253.664 21.2463 254.664 21.5304 256.033 22.3072C258.132 26.4913 256.311 29.441 251.795 30.2026C247.66 30.238 245.753 27.235 247.879 23.6055C248.78 22.0654 250.435 21.719 252.066 21.3795Z" fill="white"/>
                <path d="M301.343 31.7219C302.937 31.6855 303.905 32.1301 305.386 32.6654C307.486 35.2199 308.875 39.8976 304.487 40.9099C302.992 40.9611 301.707 40.8626 300.381 40.0285C298.323 38.7327 297.325 35.2781 298.609 33.1669C299.28 32.0617 300.155 31.9676 301.343 31.7219Z" fill="white"/>
                <path d="M301.978 85.0961C303.566 84.9814 303.843 85.1071 305.299 85.7595C305.771 86.36 306.183 86.9962 306.374 87.7397C306.703 89.0354 306.502 90.4088 305.813 91.5554C304.852 93.1712 303.508 93.8099 301.768 94.2551C295.574 94.9633 296.168 87.6507 300.262 85.6044C300.734 85.3683 301.452 85.2193 301.978 85.0961Z" fill="white"/>
                <path d="M5.41124 102.993C6.03787 103.006 6.57468 102.969 7.14041 103.245C8.25553 104.384 11.2171 114.301 12.0772 116.332C12.1908 116.601 12.0075 116.768 11.8441 116.993C10.4548 117.537 9.43711 113.531 8.95041 112.462L7.39007 112.525L3.52962 112.545C2.84073 114.19 2.74982 117.095 0.64635 116.89L0.462891 116.664C0.695412 115.166 4.70313 104.399 5.41124 102.993ZM4.13213 110.888C4.8096 110.88 5.75082 110.841 6.4055 110.871L8.45191 110.913C7.93115 109.571 6.90978 105.554 6.22645 105.015C5.56748 106.654 4.73083 109.719 4.13213 110.888Z" fill="#FEFEFE"/>
                <path d="M286.832 30.9417C292.214 29.9559 295.477 37.6612 289.659 39.6174C281.611 40.034 281.65 31.4889 286.832 30.9417Z" fill="white"/>
                <path d="M292.155 22.0623C293.75 21.87 295.89 22.1155 297.221 23.0706C299.043 24.3791 299.865 27.5931 298.495 29.4751C297.89 30.3059 297.135 30.3007 296.2 30.4194C292.881 30.4883 290.45 29.7845 289.628 26.1966C289.151 24.1134 290.025 22.4916 292.155 22.0623Z" fill="white"/>
                <path d="M295.895 41.623C298.063 41.7704 298.364 41.9125 300.141 43.2411C301.49 46.5408 301.619 49.875 297.335 50.6774C293.234 50.4446 290.904 47.7488 292.546 43.5123C292.799 42.8586 293.373 42.3589 293.897 41.8937L295.895 41.623Z" fill="white"/>
                <path d="M250.643 96.2544C255.441 95.8094 257.96 97.1043 257.787 102.216C256.886 103.388 256.467 103.769 255.073 104.199C248.513 104.85 244.766 98.5424 250.643 96.2544Z" fill="white"/>
                <path d="M272.202 66.693C273.705 66.4575 275.522 66.6828 276.916 67.3318C278.03 69.077 278.618 71.0546 277.681 73.0866C277.047 74.4631 276.212 74.6709 274.872 75.1066C268.229 76.3997 267.063 68.0377 272.202 66.693Z" fill="white"/>
                <path d="M309.597 74.5888C310.138 74.5411 311.117 74.56 311.695 74.5544C311.748 74.5861 311.801 74.6181 311.855 74.6504C314.749 76.3875 314.415 80.6359 312.15 82.781C311.322 83.565 310.758 83.6668 309.681 83.9136C308.512 83.9315 308.024 83.8624 306.89 83.6236C304.153 80.393 305.162 75.5619 309.597 74.5888Z" fill="white"/>
                <path d="M293.529 95.9369C297.266 95.5567 299.894 97.0234 298.085 101.263C297.417 102.831 295.695 103.505 294.124 103.963C289.974 104.689 287.672 102.273 289.764 98.285C290.529 96.8251 291.989 96.3155 293.529 95.9369Z" fill="white"/>
                <path d="M238.985 22.5294C243.341 22.3263 243.19 27.0897 240.891 29.3882C239.682 30.5961 238.549 30.8201 236.939 31.2262C235.879 31.1982 235.368 30.9933 234.352 30.6627C231.366 26.9178 234.679 22.9961 238.985 22.5294Z" fill="white"/>
                <path d="M228.068 84.8235C229.133 84.7008 230.342 84.6393 231.367 84.9671C232.713 85.3836 233.828 86.3298 234.458 87.5867C235.428 89.5207 235.593 92.2166 233.33 93.2984C232.643 93.4587 232.356 93.4761 231.66 93.4223C227.014 93.0621 226.337 89.934 226.523 86.0703C227.006 85.3984 227.313 85.1306 228.068 84.8235Z" fill="white"/>
                <path d="M260.626 12.8392C262.602 12.7238 263.546 13.1557 264.937 14.4636C265.629 16.9419 265.029 18.0828 263.291 19.7946C262.067 20.0471 261.306 20.1669 260.064 20.2825C259.174 20.4412 257.259 20.1889 256.536 19.603C255.046 18.3947 255.215 15.9014 256.343 14.505C257.451 13.1348 259.015 12.9822 260.626 12.8392Z" fill="white"/>
                <path d="M265.646 21.2458C266.045 21.2127 266.481 21.22 266.867 21.1833C271.882 20.7036 274.546 26.8009 268.899 28.8223C266.62 29.2687 263.788 28.9642 262.802 26.4992C262.438 25.5786 262.451 24.5515 262.84 23.6405C263.44 22.2548 264.344 21.7726 265.646 21.2458Z" fill="white"/>
                <path d="M278.758 21.3823C280.089 21.2964 281.767 21.9236 282.944 22.4856C284.21 24.4371 284.291 24.7282 284.067 27.0325C282.66 28.7301 282.328 28.8792 280.162 29.2411C279.089 29.3455 277.399 28.8431 276.498 28.2486C274.263 26.7758 274.075 23.3159 276.511 21.9418C277.012 21.6591 278.155 21.4913 278.758 21.3823Z" fill="white"/>
                <path d="M67.4323 102.919C68.3434 102.837 69.4806 103.043 70.3819 103.221C71.4374 103.43 72.868 104.795 72.5184 105.94C71.4389 106.3 70.2378 104.607 68.6279 104.39C64.7502 103.865 64.0217 106.689 64.3033 109.876C64.4122 111.108 63.8786 113.381 65.0503 114.505C66.5214 115.916 69.4942 116.067 71.0015 114.618C71.8172 113.82 71.6566 112.364 71.6496 111.287L67.7969 111.241C67.618 110.696 67.6478 110.476 67.95 110.032C69.3439 109.329 71.683 109.776 73.551 109.51C73.5327 111.067 73.6804 113.714 72.7703 114.976C71.181 117.475 66.4577 117.726 64.2735 116.055C62.0513 114.355 62.1938 106.545 63.8155 104.302C64.5803 103.245 66.2463 103.079 67.4323 102.919Z" fill="#FEFEFE"/>
                <path d="M283.794 13.1762C286.782 12.796 288.512 13.2979 290.687 15.3146C290.76 15.6127 290.827 15.9122 290.887 16.2129C291.316 18.3935 291.227 19.9153 288.757 20.3396C286.335 20.6561 285.407 20.3631 283.157 19.6014C281.184 17.5387 280.275 14.3003 283.794 13.1762Z" fill="white"/>
                <path d="M37.3594 102.985C38.8375 103.013 45.7741 102.661 46.1591 103.842L45.8928 104.17C44.8389 104.73 40.5108 104.642 39.1777 104.569C39.215 105.798 39.2914 107.875 39.0359 109.023C41.1655 109.139 43.2815 109.005 45.3784 109.167C45.7605 109.197 45.7317 109.247 45.9163 109.508C45.8438 109.866 45.828 110.209 45.5039 110.442C44.458 111.192 40.7473 110.861 39.4229 110.825C39.1056 111.017 39.2013 114.479 39.0155 115.355C40.26 115.318 45.2157 115.199 46.1436 115.654C46.3647 116.084 46.3511 115.906 46.228 116.326C45.0996 117.252 39.2596 116.908 37.3751 116.976C37.1533 112.736 37.3075 107.313 37.3594 102.985Z" fill="#FEFEFE"/>
                <path d="M272.551 31.096C277.03 30.8007 278.755 33.829 277.27 37.8529C276.232 38.6481 275.598 38.8166 274.358 39.1349C269.626 39.4105 268.801 37.1842 269.313 33.0516C270.372 31.8167 271.027 31.5052 272.551 31.096Z" fill="white"/>
                <path d="M157.83 102.965C159.299 103.035 166.398 102.62 166.626 103.888C165.978 104.876 160.715 104.636 159.478 104.542C159.505 106.001 159.582 107.641 159.418 109.078C161.117 109.092 162.81 109.097 164.509 109.077C165.096 109.07 165.877 108.993 166.228 109.47C166.216 109.838 166.247 110.195 166.031 110.49C164.908 111.06 161.267 110.875 159.857 110.827L159.639 110.859C159.444 111.241 159.525 114.345 159.395 115.359C160.588 115.322 166.25 115.125 166.805 115.79C166.557 117.528 159.46 116.965 157.774 117.01C157.606 113.038 157.653 106.93 157.83 102.965Z" fill="#FEFEFE"/>
                <path d="M236.935 94.9707C238.985 94.6874 241.649 95.6472 242.573 97.5663C243.176 98.8219 243.184 99.4036 243.283 100.781C243.194 101.019 243.095 101.253 242.986 101.483C242.474 102.538 242.095 102.731 241.064 103.075C239.336 103.064 238.044 102.987 236.555 101.967C235.335 101.137 234.503 99.8487 234.249 98.3962C233.843 96.1314 234.941 95.3005 236.935 94.9707Z" fill="white"/>
                <path d="M247.438 13.9668C250.781 14.2712 251.875 16.1782 249.967 19.1345C248.974 20.6753 246.754 21.2767 245 21.5383C244.379 21.482 243.64 21.368 243.011 21.2871L242.938 21.2339C241.103 19.8786 240.639 18.1331 242.145 16.2421C243.591 14.4251 245.281 14.2048 247.438 13.9668Z" fill="white"/>
                <path d="M314.686 83.9607C315.443 83.9052 316.331 83.8981 317.012 84.2696C317.619 84.6038 318.058 85.1733 318.228 85.8428C318.569 87.135 318.094 89.1794 317.385 90.2877C316.409 91.8158 315.571 92.2594 313.911 92.6634C309.914 93.3276 310.051 89.0671 311.296 86.585C312.099 84.9886 313.057 84.485 314.686 83.9607Z" fill="white"/>
                <path d="M305.605 23.6542C311.112 23.4304 314.453 30.1268 310.06 31.8734C304.642 32.6533 301.007 25.321 305.605 23.6542Z" fill="white"/>
                <path d="M244.91 104.357C246.231 104.149 248.41 104.655 249.559 105.406C250.624 106.102 251.368 107.194 251.623 108.438C251.992 110.296 250.769 111.198 249.11 111.522C247.264 111.66 244.654 111.206 243.393 109.696C242.287 108.371 242.394 107.174 242.537 105.588C243.428 104.63 243.642 104.563 244.91 104.357Z" fill="white"/>
                <path d="M296.692 14.7532C300.286 14.4757 301.801 15.3195 304.017 17.9728C304.31 20.0741 304.8 21.1835 302.656 22.145C296.968 23.1109 292.857 16.9956 296.692 14.7532Z" fill="white"/>
                <path d="M97.5706 108.613C98.3976 107.161 100.859 102.228 102.791 103.065C102.866 104.118 99.1649 108.869 98.337 110.04C100.237 112.403 101.486 114.661 103.282 116.937C103.002 116.966 101.996 116.995 101.781 116.901C100.225 116.226 98.2636 112.588 97.5587 111.225C96.7002 112.577 94.002 117.683 92.1903 116.913C91.9561 115.875 95.562 111.46 96.381 110.008C95.0585 107.49 93.3949 105.832 91.959 102.976C92.9462 103.01 93.4647 102.977 94.1724 103.777C95.4954 105.272 96.3333 107.005 97.5706 108.613Z" fill="#FEFEFE"/>
                <path d="M264.387 56.1527C267.898 55.7424 270.98 56.8053 269.857 61.3376C269.606 62.3524 268.532 63.0598 267.584 63.4712C266.18 63.6092 265.188 63.8114 263.881 63.152C262.985 62.7008 262.312 61.9058 262.016 60.9495C261.409 59.0235 262.372 56.7183 264.387 56.1527Z" fill="white"/>
                <path d="M280.278 56.1625C284.718 55.6306 286.912 57.8572 285.417 62.1415C284.66 62.8537 284.302 63.1388 283.347 63.4964C281.988 63.6436 280.926 63.7676 279.677 63.1081C278.775 62.6417 278.104 61.8271 277.82 60.854C277.229 58.8352 278.213 56.7481 280.278 56.1625Z" fill="white"/>
                <path d="M307.811 93.9758C309.021 93.8632 309.361 94.0979 310.444 94.6034C312.016 98.5888 309.825 100.879 306.091 101.949C302.499 102.18 301.904 99.5739 303.335 96.9015C304.373 94.9629 305.864 94.552 307.811 93.9758Z" fill="white"/>
                <path d="M264.618 41.7624C266.918 41.6173 268.022 41.5478 269.652 43.3402C270.324 46.2828 270.383 48.0496 267.055 48.942C265.01 49.0711 264.796 49.0661 262.908 48.3106C261.089 45.5025 261.587 43.2986 264.618 41.7624Z" fill="white"/>
                <path d="M280.515 41.7777C284.088 41.4379 286.755 43.7889 285.2 47.5902C284.772 48.6343 283.672 48.8973 282.65 49.1903C276.487 49.9563 276.288 42.5708 280.515 41.7777Z" fill="white"/>
                <path d="M299.565 104.345C302.725 104.009 304.957 105.199 302.919 108.765C302.139 110.131 300.784 110.774 299.251 111.072C294.635 112.011 294.011 109.37 296.055 105.831C297.393 104.999 298.041 104.719 299.565 104.345Z" fill="white"/>
                <path d="M254.768 6.52648C255.772 6.29248 257.537 6.84073 258.474 7.1786C258.513 7.39803 258.549 7.61801 258.581 7.83847C259.147 11.7216 257.002 12.3242 253.817 12.7749C251.537 12.8442 250.299 12.7416 249.608 10.4776C250.531 7.04828 251.513 6.91274 254.768 6.52648Z" fill="white"/>
                <path d="M234.018 15.8929C237.319 15.4571 238.024 17.881 236.528 20.3505C235.341 22.3084 234.179 22.6425 232.114 23.1841C226.757 23.7574 228.445 16.9516 234.018 15.8929Z" fill="white"/>
                <path d="M289.827 6.67612C290.175 6.62978 290.525 6.60987 290.876 6.61659C292.949 6.64403 294.471 7.13045 295.919 8.61281C296.241 10.4424 297.48 12.3811 294.755 12.9131C292.994 12.9871 290.803 12.844 289.323 11.813C287.089 10.2554 286.325 7.23546 289.827 6.67612Z" fill="white"/>
                <path d="M223.95 92.9941C224.702 93.0193 225.389 93.066 226.114 93.2793C227.597 93.7097 228.845 94.7192 229.575 96.0793C230.502 97.7845 230.87 99.8019 228.855 100.851C223.763 101.381 220.584 95.2538 223.95 92.9941Z" fill="white"/>
                <path d="M232.067 102.409C237.454 102.474 240.469 108.568 236.481 109.676C233.866 109.366 228.966 107.645 230.107 103.552C230.292 102.89 231.409 102.568 232.067 102.409Z" fill="white"/>
                <path d="M243.109 7.99641C245.56 7.80127 245.438 9.23796 245.259 11.1538C243.654 13.6358 242.251 14.0119 239.504 14.6113C234.802 14.8351 236.206 8.39281 243.109 7.99641Z" fill="white"/>
                <path d="M319.449 92.0467C322.557 92.0777 322.156 94.9365 320.908 96.8116C319.704 98.6174 318.724 99.164 316.659 99.6359C313.49 100.327 314.333 96.3503 315.323 94.7653C316.473 92.9298 317.432 92.5647 319.449 92.0467Z" fill="white"/>
                <path d="M308.892 17.1585C309.785 17.1132 310.528 17.0693 311.4 17.3047C312.844 17.6868 314.071 18.6379 314.798 19.9396C315.61 21.3834 316.161 23.4062 314.358 24.3382C313.783 24.4556 313.277 24.4115 312.706 24.2904C311.119 23.9682 309.73 23.0218 308.851 21.6649C308.03 20.3748 307.282 18.1542 308.892 17.1585Z" fill="white"/>
                <path d="M312.265 101.275C316.532 100.305 314.647 107.383 309.736 108.37C309.224 108.37 308.102 108.427 307.695 108.152C306.212 107.149 307.24 104.903 308.06 103.788C309.187 102.255 310.411 101.586 312.265 101.275Z" fill="white"/>
                <path d="M80.454 109.813C81.082 108.798 84.3872 101.227 85.794 103.248C85.7737 104.02 83.8552 107.124 83.3444 107.947C82.7849 108.736 81.9814 110.21 81.6602 111.215C81.1108 112.934 81.7538 114.999 81.083 116.682C81.0136 116.855 80.6589 116.895 80.4511 116.945C79.4713 116.44 79.6423 114.834 79.6865 113.778C79.8224 110.531 76.534 106.165 74.8281 103.007C76.0272 103.016 76.4888 102.856 77.1646 103.953C78.3402 105.862 79.4238 107.822 80.454 109.813Z" fill="#FEFEFE"/>
                <path d="M238.883 110.991C241.6 110.685 244.529 111.725 246.046 114.067C246.857 115.32 246.275 116.362 245.106 117.005C240.912 117.584 234.938 113.191 238.883 110.991Z" fill="white"/>
                <path d="M301.545 9.24337C305.683 8.9887 310.574 13.5127 306.546 15.7425C302.076 15.6874 297.21 10.317 301.545 9.24337Z" fill="white"/>
                <path d="M250.297 1.53195C251.551 1.49366 253.187 1.49321 253.654 2.8788C253.442 5.42241 249.862 6.65133 247.74 6.93789C246.998 7.01822 245.916 6.95541 245.331 6.46074C245.026 6.204 244.84 5.83504 244.812 5.43821C244.618 2.5865 248.119 1.65352 250.297 1.53195Z" fill="white"/>
                <path d="M304.944 110.971C307.509 110.573 308.161 112.26 306.911 114.258C305.799 116.034 304.217 116.648 302.266 117.114C299.985 117.263 298.711 116.353 300.05 114.138C301.292 112.081 302.745 111.539 304.944 110.971Z" fill="white"/>
                <path d="M232.003 10.2432C232.174 10.2574 232.367 10.2798 232.523 10.3621C232.836 10.5319 233.069 10.8184 233.173 11.1594C233.525 12.2974 232.465 14.36 231.639 15.1574C230.581 16.181 228.668 16.7429 227.275 17.1478C223.377 16.3737 227.326 10.6773 232.003 10.2432Z" fill="white"/>
                <path d="M220.372 100.142C222.476 100.007 223.83 100.642 225.224 102.23C226.383 103.548 227.456 105.706 225.821 107.091C222.678 107.266 217.625 102.721 220.372 100.142Z" fill="white"/>
                <path d="M119.531 102.927C119.787 103.016 120.087 103.153 120.341 103.26C120.971 104.095 120.756 113.737 120.685 115.346C122.74 115.323 124.853 115.268 126.91 115.329C127.386 115.343 127.332 115.309 127.646 115.63C127.732 116.202 127.686 116.309 127.484 116.86C125.209 117.035 121.603 116.948 119.186 117.012C119.113 114.601 118.887 104.904 119.531 102.927Z" fill="#FEFEFE"/>
                <path d="M26.5199 102.963C26.9603 103.015 27.4252 103.01 27.7242 103.319C27.9609 104.795 27.9613 113.769 27.8104 115.353C29.1418 115.337 33.3868 115.202 34.394 115.54C34.6276 115.96 34.5234 116.226 34.4289 116.702C32.7791 117.038 28.1585 116.936 26.2804 116.926C26.2496 112.869 26.0605 106.835 26.5199 102.963Z" fill="#FEFEFE"/>
                <path d="M293.445 1.65093C296.256 1.43892 298.694 1.89058 300.593 4.24694C301.306 5.13134 301.042 6.22415 300.114 6.8487C297.665 7.70045 290.879 5.03024 293.445 1.65093Z" fill="white"/>
                <path d="M15.3049 102.947C15.8227 103 16.2268 102.959 16.5165 103.373C16.64 105.132 16.7616 113.877 16.4969 115.358C18.0935 115.303 21.822 115.23 23.2012 115.571C23.4911 116.033 23.3849 116.127 23.3687 116.749C20.6385 116.951 17.8109 116.954 15.0676 116.996C14.9777 114.347 14.9397 105.189 15.3049 102.947Z" fill="#FEFEFE"/>
                <path d="M272.739 49.0173C277.087 48.8176 278.696 53.2476 274.422 54.832C270.558 55.7553 268.496 50.6862 272.739 49.0173Z" fill="white"/>
                <path d="M227.689 108.37C229.972 108.568 232.086 109.652 233.577 111.389C234.558 112.553 234.757 113.584 233.556 114.589C230.254 114.941 224.63 110.662 227.689 108.37Z" fill="white"/>
                <path d="M239.576 3.60145C240.359 3.69199 240.781 3.85533 241.147 4.58555C241.395 7.31206 236.603 9.00946 234.418 9.21883C232.667 8.78539 233.099 7.43267 233.881 6.30336C235.352 4.18306 237.236 3.97161 239.576 3.60145Z" fill="white"/>
                <path d="M322.773 98.4969C325.858 98.6409 322.487 104.843 318.59 105.755L318.042 105.653C316.723 104.653 317.498 103.149 318.18 101.952C319.274 100.036 320.652 99.0638 322.773 98.4969Z" fill="white"/>
                <path d="M258.339 49.2386L260.14 49.3134C262.245 50.6499 262.385 52.7533 260.701 54.5325C260.19 54.6866 259.564 54.8289 259.037 54.9638C256.721 54.68 254.654 53.393 256.064 50.5139C256.435 49.7564 257.507 49.3891 258.339 49.2386Z" fill="white"/>
                <path d="M311.646 11.9844C315.081 11.6324 319.641 15.6996 317.568 18.3413C314.948 19.0947 309.867 14.6838 311.646 11.9844Z" fill="white"/>
                <path d="M286.766 49.3507C288.707 49.0955 289.336 49.2527 290.74 50.5803C291.139 52.8543 291.438 53.9265 289.085 54.8257C285.029 55.6251 283.409 50.9767 286.766 49.3507Z" fill="white"/>
                <path d="M315.099 107.1C316.08 106.964 316.633 106.881 317.231 107.72C317.562 110.065 314.106 112.395 312.141 113.063C311.332 113.335 310.779 113.401 310.226 112.738C310.066 112.082 310.204 111.52 310.486 110.925C311.76 108.238 312.608 108.088 315.099 107.1Z" fill="white"/>
                <path d="M304.163 4.83947C306.707 4.81744 312.664 7.76514 310.419 10.3352C307.736 10.7237 302.146 7.89203 304.163 4.83947Z" fill="white"/>
                <path d="M229.771 6.49318L230.026 6.5399C230.573 7.00489 230.421 7.36683 230.15 7.98915C229.076 10.4724 226.774 11.7406 224.224 12.1837C223.168 11.618 223.51 10.374 224.136 9.60499C225.679 7.71045 227.403 6.84399 229.771 6.49318Z" fill="white"/>
                <path d="M217.903 105.612C218.253 105.592 218.603 105.593 218.953 105.615C221.166 105.757 222.364 107.381 223.724 108.927C223.901 109.666 224.105 110.821 223.924 111.554C221.322 111.338 216.976 108.407 217.903 105.612Z" fill="white"/>
                <path d="M313.764 8.10409C315.643 8.55086 321.255 11.6974 319.229 13.8908C317.305 13.6332 312.21 10.2535 313.764 8.10409Z" fill="white"/>
                <path d="M324.329 103.828C324.811 103.705 325.138 103.829 325.43 104.185C325.433 104.367 325.409 104.523 325.333 104.689C324.126 107.264 322.538 108.734 320.064 109.863L319.743 109.899L319.442 109.547C319.378 109.086 319.32 109.128 319.467 108.734C320.576 105.781 321.573 105.099 324.329 103.828Z" fill="white"/>
              </svg>
            </div>
            <p class="mobile-menu-description">
              Многокомпонентный анализ крови, который за один забор крови проверяет более 300 аллергенов, выявляя истинные причины реакции.
            </p>
          </div>


          <Link
              href="/alex-lab"
              @click="closeMobileMenu"
              class="mobile-menu-lab-button"
          >
            <span>Лаборатория ALEX LAB</span>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M10 8L14 12L10 16" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </Link>


          <button @click="closeMobileMenu" class="mobile-menu-close-button">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M18 6L6 18" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M6 6L18 18" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>Закрыть</span>
          </button>
        </div>
      </div>
    </Transition>

</template>


<style>
.faq-fade-enter-active,
.faq-fade-leave-active {
  transition: all 0.25s ease-in-out;
  max-height: 500px;
  opacity: 1;
  overflow: hidden;
}

.faq-fade-enter-from,
.faq-fade-leave-to {
  max-height: 0;
  opacity: 0;
  padding-bottom: 0;
  overflow: hidden;
}
.blog-toc {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  padding: 24px;
  gap: 21px;
  max-width: 100%;
  background: #F0F0F0;
  border-radius: 12px;
  margin-bottom: 2rem;
  box-sizing: border-box;
}

.text_sources {
  max-width: 418px;
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 400;
  font-size: 18px;
  line-height: 21px;
  color: #000000;
  opacity: 0.5;
  margin-bottom: 10px;
}

.blog-toc h3 {
  font-family: 'Roboto', sans-serif;
  font-style: normal;
  font-weight: 400;
  font-size: 18px;
  line-height: 21px;
  color: #000000;
  margin: 0;
}

.blog-toc ul {
  list-style: disc;
  padding-left: 1.5rem;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.blog-toc li {
  font-family: 'Roboto', sans-serif;
  font-style: normal;
  font-weight: 400;
  font-size: 18px;
  line-height: 19px;
  color: #000000;
}


.blog-content > * {
  margin-top: 0;
  margin-bottom: 0;
}



.blog-content h2 {
  margin-top: 48px;
  margin-bottom: 0;
  scroll-margin-top: 100px;
}


.blog-content h3 {
  margin-top: 32px;
  margin-bottom: 8px;
}

.blog-content h4 {
  margin-top: 21px;
  margin-bottom: 6px;
}

.blog-content h5 {
  margin-top: 16px;
  margin-bottom: 6px;
}


.blog-content p {
  margin-top: 12px;
  margin-bottom: 12px;
}
.blog-content li p{
  margin: 0;
  line-height: 1.2;
}

.blog-content blockquote {
  margin-top: 32px;
  margin-bottom: 32px;
}


.blog-content .blog-image {
  margin-top: 24px;
  margin-bottom: 48px;
}
.blog-content .blog-image:has(+ .blog-image-caption) {
  margin-bottom: 12px;
}
.blog-content .blog-image-caption {
  margin-top: 0;
  margin-bottom: 48px;
}


.blog-content table {
  margin-top: 32px;
  margin-bottom: 48px;
}


.blog-content ul:not([data-type="taskList"]),
.blog-content ol {
  margin-top: 12px;
  margin-bottom: 12px;
}
.blog-content ul:not([data-type="taskList"]) li,
.blog-content ol li {
  margin-top: 0;
  margin-bottom: 12px;
}
.blog-content ul:not([data-type="taskList"]) li:last-child,
.blog-content ol li:last-child {
  margin-bottom: 0;
}




.blog-content h2 {
  max-width: 695px;
  min-height: 17px;
  font-family: 'Roboto', sans-serif;
  font-style: normal;
  font-weight: 700;
  font-size: 32px;
  line-height: 28px;
  color: #000000;
}


.blog-content h3 {
  width: 695px;
  max-width: 100%;
  height: auto;
  min-height: 17px;
  font-family: 'Roboto', sans-serif;
  font-style: normal;
  font-weight: 700;
  font-size: 24px;
  line-height: 28px;
  color: #000000!important;
}


.blog-content h4 {
  width: 695px;
  max-width: 100%;
  height: auto;
  min-height: 15px;
  font-family: 'Roboto', sans-serif;
  font-style: normal;
  font-weight: 700;
  font-size: 21px;
  line-height: 25px;
  color: #000000;
}


.blog-content h5 {
  width: 695px;
  max-width: 100%;
  height: auto;
  min-height: 14px;
  font-family: 'Roboto', sans-serif;
  font-style: normal;
  font-weight: 700;
  font-size: 20px;
  line-height: 23px;
  color: #000000;
}


.blog-content p {
  font-family: 'Roboto', sans-serif;
  font-weight: 400;
  max-width: 695px;
  font-size: 18px;
  line-height: 1.5;
  color: #000000;
}


.blog-content p:empty:not(.blog-indent) {
  display: none;
}

.blog-content p.blog-indent {
  min-height: 24px;
}


.blog-content a {
  color: #0073FF;
  text-decoration: none;
}
.blog-content a:hover {
  text-decoration: underline;
}


.blog-content ol {
  list-style: decimal;
  padding-left: 1.5rem;
}
.blog-content ol li {
  list-style: decimal;
  display: list-item;
  font-family: 'Roboto', sans-serif;
  font-size: 18px;
  line-height: 1.2;
  color: #000;
}


.blog-content ul:not([data-type="taskList"]) {
  list-style: disc;
  padding-left: 1.5rem;
}
.blog-content ul:not([data-type="taskList"]) li {
  list-style: disc;
  display: list-item;
  font-family: 'Roboto', sans-serif;
  font-size: 18px;
  line-height: 1.2;
  color: #000;
}


.blog-content blockquote {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  padding: 32px;
  gap: 12px;
  max-width: 695px;
  background: #F0F0F0;
  border-radius: 12px;
  box-sizing: border-box;
  border: none;
  quotes: none;
  position: relative;
}


.blog-content blockquote::before {
  content: '';
  position: absolute;
  top: 32px;
  left: 32px;
  width: 23px;
  height: 11px;
  background-image: url("data:image/svg+xml,%3Csvg width='23' height='11' viewBox='0 0 23 11' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M5.54785 0.0380859L2.23438 5.1416L0 5.12891V4.96387L3.74512 0.0380859H5.54785ZM2.23438 5.02734L5.54785 10.1436H3.74512L0 5.20508V5.04004L2.23438 5.02734ZM9.75 0.0380859L6.43652 5.1416L4.20215 5.12891V4.96387L7.94727 0.0380859H9.75ZM6.43652 5.02734L9.75 10.1436H7.94727L4.20215 5.20508V5.04004L6.43652 5.02734ZM12.2383 10.1055L15.5518 5.00195L17.7861 5.01465V5.17969L14.041 10.1055H12.2383ZM12.2383 0H14.041L17.7861 4.93848V5.10352L15.5518 5.11621L12.2383 0ZM16.6689 10.1055L19.9824 5.00195L22.2168 5.01465V5.17969L18.4717 10.1055H16.6689ZM16.6689 0H18.4717L22.2168 4.93848V5.10352L19.9824 5.11621L16.6689 0Z' fill='%230073FF'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-size: contain;
  pointer-events: none;
}


.blog-content blockquote p:first-child {
  max-width: 631px;
  font-family: 'Roboto', sans-serif;
  font-style: normal;
  font-weight: 400;
  font-size: 18px;
  line-height: 21px;
  color: #000000;
  margin: 20px 0 0 0;
}

.blog-content blockquote p:last-child:not(:first-child) {
  font-family: 'Roboto', sans-serif;
  font-weight: 400;
  font-size: 18px;
  line-height: 21px;
  color: #000000;
  opacity: 0.3;
  width: 100%;
  max-width: 631px;
  margin: 0;
}
.blog-content blockquote p:last-child:not(:first-child)::before {
  content: '— ';
}

.blog-content .blog-image {
  display: block;
  max-width: 100%;
  height: max-content;
  width: 100%;
  object-fit: contain;
  background-color: #B3C3DE4D;
}

.blog-content .blog-image-caption {
  margin-top: 0 !important;
  margin-bottom: 48px;
  font-family: 'Roboto', sans-serif;
  font-size: 16px;
  line-height: 19px;
  color: #000000;
  opacity: 0.47;
  display: block;
  text-align: left;
  max-width: 100%;
}



.blog-content table {
  max-width: 1115px;
  width: 100%;
  background: #FFFFFF;
  border-collapse: collapse;
  margin-top: 32px;
  margin-bottom: 48px;
}
.blog-content table:has(+ .blog-image-caption) {
  margin-bottom: 12px;
}
.blog-content table th,
.blog-content table td {
  border: 1px solid #d1d5db;
  padding: 8px 12px;
  text-align: left;
  vertical-align: top;
}

.blog-content table th {
  background-color: #f9fafb;
  font-weight: 600;
}

/* Убираем margin у параграфов внутри таблиц */
.blog-content table p {
  margin-top: 0;
  margin-bottom: 0;
}


.blog-content .cta-block {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  padding: 32px;
  width: 695px;
  max-width: 100%;
  background: #FFFFFF;
  border: 1px solid #DFDFDF;
  backdrop-filter: blur(25px);
  border-radius: 12px;
  margin-top: 32px;
  margin-bottom: 48px;
}

.blog-content .cta-block .cta-icon {
  margin-bottom: 12px;
}

.blog-content .cta-block .cta-title {
  font-family: 'Roboto', sans-serif;
  font-style: normal;
  font-weight: 400;
  font-size: 24px;
  line-height: 28px;
  color: #000000;
  margin: 0 0 8px 0;
}

.blog-content .cta-block .cta-description {
  font-family: 'Roboto', sans-serif;
  font-style: normal;
  font-weight: 400;
  font-size: 18px;
  line-height: 21px;
  color: #000000;
  opacity: 0.5;
  margin: 0 0 16px 0;
}

.blog-content .cta-block .cta-button {
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  align-items: center;
  padding: 24px 32px;
  gap: 24px;
  width: 631px;
  max-width: 100%;
  height: 72px;
  background: #000000;
  backdrop-filter: blur(2px);
  color: #fff;
  text-decoration: none;
  font-family: 'Roboto', sans-serif;
  font-size: 18px;
  font-weight: 400;
  border-radius: 0;
  box-sizing: border-box;
}

.blog-content .cta-block .cta-button:hover {
  background: #222;
}


.blog-content .internal-link-block {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  padding: 24px;
  width: 695px;
  max-width: 100%;
  background: #000000;
  backdrop-filter: blur(2px);
  border-radius: 12px;
  margin-top: 32px;
  margin-bottom: 48px;
}

.blog-content .internal-link-block .internal-link-text {
  font-family: 'Roboto', sans-serif;
  font-style: normal;
  font-weight: 400;
  font-size: 18px;
  line-height: 21px;
  color: #ffffff;
  margin: 0 0 16px 0;
}

.blog-content .internal-link-block .internal-link-button {
  display: inline-flex;
  align-items: center;
  padding: 12px 24px;
  background: #fff;
  color: #000;
  text-decoration: none;
  font-family: 'Roboto', sans-serif;
  font-size: 16px;
  font-weight: 400;
  border-radius: 8px;
}

.blog-content .internal-link-block .internal-link-button:hover {
  background: #f0f0f0;
}


.blog-content hr {
  border: none;
  border-top: 2px solid #e5e7eb;
  margin-top: 32px;
  margin-bottom: 32px;
}


.blog-content sub {
  font-size: 0.75em;
  vertical-align: sub;
}

.blog-content sup {
  font-size: 0.75em;
  vertical-align: super;
}


.blog-sources {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  padding: 24px 32px;
  gap: 10px;
  max-width: 100%;
  background: #F0F0F0;
  border-radius: 12px;
  margin-top: 1.5rem;
  margin-bottom: 0;
  box-sizing: border-box;
}

.blog-sources h3 {
  font-family: 'Roboto', sans-serif;
  font-style: normal;
  font-weight: 400;
  font-size: 24px;
  line-height: 28px;
  color: #0073FF;
  margin: 0;
}

.blog-sources .sources-description {
  font-family: 'Roboto', sans-serif;
  font-style: normal;
  font-weight: 400;
  font-size: 18px;
  line-height: 21px;
  color: #000000;
  opacity: 0.5;
  margin: 0;
}

.blog-sources ol {
  list-style: decimal;
  padding-left: 1.5rem;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 13px;
}

.blog-sources li {
  font-family: 'Roboto', sans-serif;
  font-style: normal;
  font-weight: 400;
  font-size: 18px;
  line-height: 21px;
  color: #000000;
}

.blog-sources a.source-link {
  color: inherit;
  text-decoration: none;
  cursor: pointer;
}

.blog-sources a.source-link:hover {
  color: #0073FF;
}

@media (max-width:1500px){
  .blog-content table {
    display: table;
    width: 100%;
    max-width: 100%;
  }
  .blog-content table th,
  .blog-content table td {
    min-width: 0;
    overflow-wrap: anywhere;
    word-break: break-word;
  }
}
@media (max-width: 1240px) {

  .blog-content .cta-block {
    width: 100%;
    max-width: 100%;
  }
  .blog-content .cta-block .cta-button {
    width: 100%;
    padding: 16px 20px;
    height: auto;
  }
  .cta-wrap{
    max-width: 100%;
  }
  .blog-content .internal-link-block {
    width: 100%;
    padding: 16px;
  }
}

@media (max-width: 640px) {
  .blog-content table th,
  .blog-content table td {
    min-width: 120px;
    padding: 10px 8px;
    font-size: 13px;
  }
}

.main-content {
  padding: 24px; /* По умолчанию 32px */
  background-color: rgb(247, 247, 247);
  width: 100%;
  box-sizing: border-box;
}
@media (min-width: 1280px) {
  .main-content {
    padding: 64px; /* При ≥1024px = 64px */
    max-width: 1115px;
    margin: 0 auto;
  }
}


.blog-content h2,
.blog-content h3,
.blog-content h4,
.blog-content h5 {
  scroll-margin-top: 100px;
  scroll-snap-margin-top: 100px;
}

.blog-content h2:target,
.blog-content h3:target,
.blog-content h4:target,
.blog-content h5:target {
  animation: highlight 2s ease-out;
}

@keyframes highlight {
  0% { background-color: rgba(16, 185, 129, 0.2); }
  100% { background-color: transparent; }
}
/* === БЛОК ОЦЕНКИ === */
.blog-rating-block {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  padding: 32px;
  gap: 24px;
  width: 100%;
  max-width: 1115px;
  background: #F0F0F0;
  border-radius: 12px;
  margin: 1.5rem auto;
  box-sizing: border-box;
}

.blog-rating-title {
  width: 530px;
  max-width: 100%;
  font-family: 'Roboto', sans-serif;
  font-style: normal;
  font-weight: 400;
  font-size: 32px;
  line-height: 38px;
  color: #000000;
  text-align: center;
  margin: 0;
}

.blog-rating-stars {
  display: flex;
  gap: 16px;
  justify-content: center;
}

.blog-rating-star {
  cursor: pointer;
  transition: transform 0.2s ease;
  background: none;
  border: none;
  padding: 0;
}

.blog-rating-star:hover {
  transform: scale(1.15);
}

.blog-rating-star svg {
  width: 64px;
  height: 64px;
  transition: all 0.2s ease;
}

.blog-rating-star:hover svg path {
  fill: rgba(0, 0, 0, 0.3);
}

.blog-rating-star.active svg path {
  fill: #000;
}

.blog-rating-average {
  font-family: 'Roboto', sans-serif;
  font-size: 18px;
  font-weight: 400;
  color: #000;
  opacity: 0.6;
  text-align: center;
  margin: 0;
}

.blog-rating-text {
  width: 359px;
  max-width: 100%;
  font-family: 'Roboto', sans-serif;
  font-style: normal;
  font-weight: 400;
  font-size: 18px;
  line-height: 21px;
  text-align: center;
  color: #000000;
  opacity: 0.4;
  margin: 0;
}

.blog-rating-success {
  font-family: 'Roboto', sans-serif;
  font-size: 18px;
  font-weight: 500;
  color: #10b981;
  text-align: center;
  margin: 0;
  animation: fadeIn 0.3s ease;
}

@media (max-width: 640px) {
  .blog-rating-block {
    padding: 32px 16px;
    gap: 24px;
  }

  .blog-rating-title {
    font-size: 24px;
    line-height: 30px;
  }

  .blog-rating-star svg {
    width: 48px;
    height: 48px;
  }
}



.blog-content .key-insight {
  display: flex;
  width: 656px;
  max-width: 100%;
  padding: 32px;
  flex-direction: column;
  align-items: flex-start;
  gap: 21px;
  border-radius: 12px;
  background: #6E826D;
  box-sizing: border-box;
  margin-top: 32px;
  margin-bottom: 32px;
}


.blog-content .key-insight h4 {
  align-self: stretch;
  color: #F5F5F5;
  font-family: 'Roboto', sans-serif;
  font-size: 21px;
  font-style: normal;
  font-weight: 400;
  line-height: normal;
  margin: 0;
  width: 100%;
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
@media (max-width: 1240px) {
  .blog-content .key-insight {
    width: 100%;
    padding: 24px;
  }
  .blog-content table{
    max-width: 100%;
  }
  .blog-content table tbody{
    max-width: 100%;
    overflow: auto;
  }
}
.cust_width{
  width: 677px;
}
@media (max-width: 1720px) {
  .cust_width{
    width: 33%;
  }

}

.blog-faq-block {
  margin-top: 1.5rem;
  margin-bottom: 0;
}
.table-wrapper{
  display: block;
  width: 100%;
  max-width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  margin-top: 32px;
  margin-bottom: 48px;
}
.table-wrapper > table {
  margin-top: 0;
  margin-bottom: 0;
  width: 100%;
  max-width: 100%;
  border-collapse: collapse;
  table-layout: auto;
}
.blog-content table {
  width: 100%;
  max-width: 100%;
  border-collapse: collapse;
}
.blog-content table th,
.blog-content table td {
  overflow-wrap: anywhere;
  word-break: break-word;
}
@media (max-width: 640px) {
  .table-wrapper {
    margin-left: 0;
    margin-right: 0;
  }
  .blog-content table {
    width: 100% !important;
    min-width: 100%;
  }
}
</style>