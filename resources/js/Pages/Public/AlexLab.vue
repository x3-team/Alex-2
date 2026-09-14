<script setup>
import '../../../css/main.css';
import { useDoctorMode } from '@/Composables/useDoctorMode'
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import HomeBackLink from '@/Components/HomeBackLink.vue'
import SiteSidebar from '@/Components/SiteSidebar.vue'
import PublicFooter from '@/Components/PublicFooter.vue'
const { isDoctorMode } = useDoctorMode()
const props = defineProps({
  about: Object,
  licenses: Array,
  contacts: Object,
  doctors: Array,
  privacy_policy: Object,
  consent: Object,
  activeSection: { type: String, default: 'about' },
  pageTitle: { type: String, default: '' },
  meta: { type: Object, default: () => ({}) },
})
const activeSection = ref(props.activeSection)


watch(() => props.activeSection, (newValue) => {
  if (newValue) {
    activeSection.value = newValue
  }
})

const resolvedPageTitle = computed(() => props.pageTitle || props.meta?.title || '')
watch(resolvedPageTitle, (title) => {
  if (title && typeof document !== 'undefined') {
    document.title = title
  }
}, { immediate: true })
const licenseSearchQuery = ref('')


const processedAboutContent = computed(() => {
  if (!props.about?.content) return { html: '', toc: [] }

  // SSR-safe: DOMParser недоступен в Node — иначе Inertia SSR отдаёт пустой #app
  if (typeof DOMParser === 'undefined') {
    const toc = []
    let idx = 0
    const html = String(props.about.content).replace(
      /<(h[2-4])(\s[^>]*)?>([\s\S]*?)<\/\1>/gi,
      (match, tag, attrs = '', inner) => {
        const attrStr = attrs || ''
        const idMatch = attrStr.match(/\bid\s*=\s*["']([^"']+)["']/i)
        const id = idMatch ? idMatch[1] : `section-${idx}`
        const text = String(inner).replace(/<[^>]+>/g, '').trim()
        if (text) {
          toc.push({
            text,
            anchor: id,
            level: parseInt(tag.charAt(1), 10),
          })
        }
        idx += 1
        if (idMatch) return match
        return `<${tag}${attrStr} id="${id}">${inner}</${tag}>`
      }
    )
    return { html, toc }
  }

  const parser = new DOMParser()
  const doc = parser.parseFromString(props.about.content, 'text/html')
  const headings = doc.querySelectorAll('h2, h3, h4')
  const toc = []

  Array.from(headings).forEach((heading, index) => {
    // Если у заголовка уже есть ID — оставляем, иначе генерируем
    const id = heading.id || `section-${index}`
    if (!heading.id) {
      heading.id = id
    }

    const text = heading.textContent.trim()
    if (text) {
      toc.push({
        text,
        anchor: id,
        level: parseInt(heading.tagName.charAt(1)), // 2, 3 или 4
      })
    }
  })

  // 🔹 Возвращаем HTML с уже проставленными ID
  return {
    html: doc.body.innerHTML,
    toc,
  }
})

const tableOfContents = computed(() => processedAboutContent.value.toc)

// Фильтрация лицензий
const filteredLicenses = computed(() => {
  if (!props.licenses) return []
  const query = licenseSearchQuery.value.toLowerCase()
  return props.licenses.filter(license =>
      license.title && license.title.toLowerCase().includes(query)
  )
})

const sections = {
  about: {
    title: 'О нас',
  },
  licenses: {
    title: 'Лицензии',
  },
  doctors: {
    title: 'Врачи и эксперты',
  },
  contacts: {
    title: 'Контакты',
  },
  privacy: {
    title: 'Политика конфиденциальности',
  },
  consent: {
    title: 'Согласие на обработку ПД',
  },
}

const formatDate = (dateString) => {
  if (!dateString) return ''
  try {
    const date = new Date(dateString)
    return date.toLocaleDateString('ru-RU', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    })
  } catch (e) {
    return dateString
  }
}

const scrollToAnchor = (anchorId) => {
  // Обновляем URL без перезагрузки (только в браузере)
  if (typeof history !== 'undefined' && history.pushState) {
    history.pushState(null, '', '#' + anchorId)
  }

  // Ждём следующего тика, чтобы DOM обновился
  nextTick(() => {
    const element = document.getElementById(anchorId)
    if (element) {
      const headerOffset = 120 // отступ под sticky-хедер
      const elementPosition = element.getBoundingClientRect().top + window.scrollY
      const offsetPosition = elementPosition - headerOffset

      window.scrollTo({
        top: offsetPosition,
        behavior: 'smooth',
      })
    }
  })
}

// 🔹 Обработка хэша при первом заходе (например, по внешней ссылке)
onMounted(() => {
  // Ждём рендеринга v-html
  setTimeout(() => {
    const hash = window.location.hash.replace(/^#/, '')
    if (hash) {
      scrollToAnchor(hash)
    }
  }, 300)
})
const pluralize = (count, forms) => {
  const lastTwo = count % 100
  const lastOne = count % 10
  if (lastTwo >= 11 && lastTwo <= 14) return forms[2]
  if (lastOne === 1) return forms[0]
  if (lastOne >= 2 && lastOne <= 4) return forms[1]
  return forms[2]
}

const getYandexMapUrl = (coords) => {
  if (!coords) return ''

  // Парсим координаты "55.7558, 37.6173" → [lat, lon]
  const [lat, lon] = coords.split(',').map(c => c.trim())

  if (!lat || !lon) return ''

  // Для Яндекс.Карт нужен порядок: долгота, широта (lon, lat)
  // Параметр pt — точка на карте, ll — центр карты, z — зум
  return `https://yandex.ru/map-widget/v1/?ll=${lon}%2C${lat}&z=17&pt=${lon}%2C${lat}&l=map`
}

// 🔹 Формируем ссылку для открытия Яндекс.Карт в новой вкладке
const getYandexMapLink = (coords) => {
  if (!coords) return ''

  const [lat, lon] = coords.split(',').map(c => c.trim())
  if (!lat || !lon) return ''

  return `https://yandex.ru/maps/?pt=${lon}%2C${lat}&z=17&l=map`
}
// 🔹 Разделяем строку режима работы на дни и часы
const parseWorkHours = (line) => {
  if (!line) return { days: '', hours: '' }
  const parts = line.split(' ')
  if (parts.length >= 2) {
    return {
      days: parts[0],
      hours: parts.slice(1).join(' ')
    }
  }
  return { days: line, hours: '' }
}

const handleSectionClick = (key) => {
  // Все секции переходят по отдельному URL
  router.get(route('alex-lab.section', { section: key }), {}, {
    preserveState: true,
    preserveScroll: false, // Сбрасываем скролл при переходе
    replace: true
  })
}
</script>

<template>
  <Head />
  <div
      class="page-container search-page-container site-sidebar-layout"
      :class="{ 'doctor-mode': isDoctorMode }"
  >

    <SiteSidebar />





      <div class="flex-1 flex flex-col" style="background-color: #f7f7f7;overflow: auto">
        <header class="hidden xl:block bg-white border-b border-gray-200 sticky top-0 z-10">
          <div class="px-9 py-4 flex align-center height-[72px]">
            <Link href="/" class="inline-flex items-center gap-2 text-gray-700 hover:text-emerald-700 font-medium transition-colors w-full">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
              Назад
            </Link>
          </div>
        </header>
        <main class="main-content p-8 2xl:p-16 flex-1">

          <h1 class="text-[42px] font-[400] text-gray-900 mb-[24px]">
            {{
              activeSection === 'licenses' ? 'Лицензии' :
              activeSection === 'contacts' ? 'Контакты' :
              activeSection === 'privacy' ? 'Политика конфиденциальности' :
              activeSection === 'consent' ? 'Согласие на обработку персональных данных' :
              activeSection === 'doctors' ? 'Врачи и эксперты' :
              'Лаборатория ALEX LAB'
            }}
          </h1>

          <!-- Кнопки переключения секций -->
          <div class="relative">
            <div class="flex gap-[5px] overflow-x-auto pb-2 2xl:flex-wrap 2xl:overflow-visible 2xl:pb-0 scrollbar-hide" style="flex-flow: wrap">
              <button
                  v-for="(section, key) in sections"
                  :key="key"
                  @click="handleSectionClick(key)"
                  :class="activeSection === key ? 'bg-black text-white' : 'bg-[transparent] text-black border border-black'"
                  class="px-6 py-3 rounded-[8px] font-medium transition-all flex-shrink-0 whitespace-nowrap"
                  style="max-width:fit-content;height: 45px; font-size: 18px; font-weight: 400; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(0, 0, 0, 0.4);"
              >
                {{ section.title }}
              </button>
            </div>
          </div>

          <!-- Контейнер для динамического контента -->
          <div class="min-h-[400px] mt-[48px]">

            <!-- === О НАС === -->
            <template v-if="activeSection === 'about'">

              <!-- Содержание (TOC) -->
              <!--              <div v-if="tableOfContents.length" class="blog-toc mb-[48px] w-[753px]">-->
              <!--                <h3 class="text-[18px] font-[400] text-gray-900 mb-3">Содержание</h3>-->
              <!--                <ul class="list-disc pl-5 space-y-1">-->
              <!--                  <li-->
              <!--                      v-for="(item, index) in tableOfContents"-->
              <!--                      :key="index"-->
              <!--                      class="text-[16px] font-[400]"-->
              <!--                      style="margin-left: 0px; color: rgb(171, 171, 171);"-->
              <!--                  >-->
              <!--                    <a-->
              <!--                        :href="'#' + item.anchor"-->
              <!--                        class="hover:text-emerald-700 transition-colors cursor-pointer"-->
              <!--                        @click.prevent="scrollToAnchor(item.anchor)"-->
              <!--                    >-->
              <!--                      {{ item.text }}-->
              <!--                    </a>-->
              <!--                  </li>-->
              <!--                </ul>-->
              <!--              </div>-->

              <!-- HTML Контент -->
              <div class="blog-content max-w-none" v-html="processedAboutContent.html"></div>

              <div v-if="!about?.content" class="text-center text-gray-400 py-10">
                Информация о лаборатории еще не добавлена.
              </div>
            </template>

            <!-- === ЛИЦЕНЗИИ === -->
            <template v-if="activeSection === 'licenses'">


              <!-- Поиск -->
              <div class="mb-[21px] w-[753px]" style="display: flex;border-bottom: 1px solid #656565; flex-direction: column; align-items: flex-start; gap: 21px; align-self: stretch;">
                <div class="relative  w-full opacity-[0.3]" >

                  <input
                      v-model="licenseSearchQuery"
                      type="text"
                      placeholder="Поиск"
                      class="w-full"
                      style="color: #000; font-family: Roboto; font-size: 18px; font-style: normal; font-weight: 400; line-height: normal;padding: 24px;background: unset;border: unset"
                  />
                  <span class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none opacity-50">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <g opacity="1">
                        <path d="M20.9999 21L16.6599 16.66" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M11 19C15.4183 19 19 15.4183 19 11C19 6.58172 15.4183 3 11 3C6.58172 3 3 6.58172 3 11C3 15.4183 6.58172 19 11 19Z" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                      </g>
                    </svg>
                  </span>
                </div>
              </div>

              <!-- Список лицензий -->
              <div class="flex flex-col gap-[6px] w-[753px] custom">
                <div
                    v-for="license in filteredLicenses"
                    :key="license.id"
                    class="flex flex-col sm:flex-row sm:items-center justify-between p-6 border border-[#DFDFDF] bg-white backdrop-blur-[2px] transition-shadow custom_adaptive"
                >
                  <div class="mb-4 sm:mb-0 cust_one">
                    <h4 class="font-[400] text-[21px] text-black mb-2" style="font-family: Roboto; line-height: normal;">
                      {{ license.title }}
                    </h4>
<!--                    <span class="text-[16px] text-black font-[400]" style="font-family: Roboto; line-height: normal;">-->
<!--                      от {{ formatDate(license.created_at) }}-->
<!--                    </span>-->
                  </div>

                  <a
                      v-if="license.file_path"
                      :href="license.file_path"
                      target="_blank"
                      class="flex items-center justify-center w-[56px] h-[56px] rounded-full hover: transition-colors shrink-0 cust_two"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                      <path d="M12 15V3" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M21 15V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V15" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M7 10L12 15L17 10" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </a>
                </div>

                <div v-if="!filteredLicenses.length" class="text-center py-8 text-gray-500">
                  Лицензии не найдены
                </div>
              </div>
            </template>

            <!-- === ВРАЧИ === -->
            <template v-if="activeSection === 'doctors'">

              <!-- 🔹 Заголовок с количеством специалистов -->
              <div v-if="doctors?.length" class="mb-6 pt-8 2xl:pt-0">
                <h2 class="text-[24px] 2xl:text-[32px] font-[400] text-gray-900">
                  Всего {{ doctors.length }} {{ pluralize(doctors.length, ['специалист', 'специалиста', 'специалистов']) }}
                </h2>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-3 gap-6">
                <Link
                    v-for="(doctor, doctorIdx) in doctors"
                    :key="doctor.id"
                    :href="`/blog/author/${doctor.id}`"
                    class="flex-shrink-0"
                    style="display: flex; flex-direction: column; justify-content: space-between; align-items: center; padding: 16px; gap: 15px; background: #FFFFFF; border-radius: 20px;"
                >
                  <div class="w-full max-w-[317px] aspect-square rounded-[20px] overflow-hidden bg-white flex-shrink-0 border border-gray-200">
                    <img
                        v-if="doctor.avatar"
                        :src="`/storage/${doctor.avatar}`"
                        :alt="doctor.name"
                        class="w-full h-full object-cover"
                        :loading="doctorIdx === 0 ? 'eager' : 'lazy'"
                        decoding="async" />
                    <div v-else class="w-full h-full bg-emerald-100 flex items-center justify-center text-6xl font-semibold text-emerald-700">
                      {{ doctor.name?.charAt(0) || '?' }}
                    </div>
                  </div>

                  <div class="text-center" style="margin-top: -5px">
                    <h3 class="text-[20px] 2xl:text-[24px] font-[400] text-gray-900" style="line-height: 1">
                      {{ doctor.name }}
                    </h3>
                    <p v-if="doctor.categories?.length" class="text-[16px] 2xl:text-[21px] font-[400] text-gray-900 opacity-50"
                       style="font-family: 'Roboto'; line-height: 25px;">
                      {{ doctor.categories[0].name }}
                    </p>
                    <p v-if="doctor.position" class="text-[14px] 2xl:text-[16px] font-[400] text-gray-600">
                      {{ doctor.position }}
                    </p>
                  </div>

                  <!-- 🔹 Счётчик материалов -->
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
                  >
                    {{ doctor.articles_count || 0 }} материалов
                  </div>
                </Link>
              </div>

              <div v-if="!doctors?.length" class="text-center py-12 text-gray-400">
                Список врачей пуст
              </div>
            </template>

            <!-- === КОНТАКТЫ === -->
            <template v-if="activeSection === 'contacts'">

              <div class="flex flex-col gap-8">
                <!-- Карта и инфо -->
                <div class="relative w-full bg-white overflow-hidden" style="width: 1115px; max-width: 100%; height: 517px;">

                  <!-- 🔹 Яндекс.Карта -->
                  <iframe
                      v-if="contacts?.map_coords"
                      :src="getYandexMapUrl(contacts.map_coords)"
                      width="100%"
                      height="100%"
                      style="border:0;"
                      allowfullscreen=""
                      loading="lazy"
                  ></iframe>
                  <div v-else class="w-full h-full bg-gray-100 flex items-center justify-center text-gray-400">
                    Координаты карты не заданы
                  </div>

                  <!-- Блок информации поверх карты -->
                  <div class="absolute top-8 left-8 bg-white p-8 max-w-[472px] h-[85%] cart_cust">

                    <h2 class="text-[32px] font-[400] text-black mb-[16px]" style="font-family: Roboto; line-height: normal;">
                      {{ contacts?.address || 'ALEX LAB' }}
                    </h2>

                    <!-- 🔹 Телефон -->
                    <a
                        v-if="contacts?.phone"
                        :href="`tel:${contacts.phone.replace(/\s/g, '')}`"
                        class="block text-[21px] font-[400] text-black mb-[12px]"
                        style="line-height: 1"
                    >
                      {{ contacts.phone }}
                    </a>

                    <!-- 🔹 Email -->
                    <a
                        v-if="contacts?.email"
                        :href="`mailto:${contacts.email}`"
                        class="block text-[21px] font-[400] text-black mb-[33px]"
                        style="line-height: 1"
                    >
                      {{ contacts.email }}
                    </a>

                    <!-- 🔹 Режим работы (массив строк) -->
                    <div v-if="contacts?.work_hours?.length" class="mb-6 flex flex-col gap-[4px]">
                      <div
                          v-for="(line, idx) in contacts.work_hours"
                          :key="idx"
                          class="flex items-center gap-[27px]"
                          style="font-family: Roboto; line-height: normal;"
                      >
    <span class="text-[16px] font-[400] text-[#929292]">
      {{ parseWorkHours(line).days }}
    </span>
                        <span class="text-[16px] font-[400] text-black">
      {{ parseWorkHours(line).hours }}
    </span>
                      </div>
                    </div>

                    <!-- 🔹 Кнопка "Открыть на Яндекс.Картах" -->
                    <a
                        v-if="contacts?.map_coords"
                        :href="getYandexMapLink(contacts.map_coords)"
                        target="_blank"
                        class="flex gap-2 w-full px-8 py-6 bg-black text-white mt-[80px] but_custom"
                        style="width: 401px; max-width: 100%; flex-flow: wrap; align-items: center; justify-content: space-between;"
                    >
                      <span class="text-[18px] font-[400]">Открыть на картах</span>

                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M11.998 21.998C17.5209 21.998 21.998 17.5209 21.998 11.998C21.998 6.4752 17.5209 1.99805 11.998 1.99805C6.4752 1.99805 1.99805 6.4752 1.99805 11.998C1.99805 17.5209 6.4752 21.998 11.998 21.998Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M10.002 7.99805L14.002 11.998L10.002 15.998" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                      </svg>
                    </a>
                  </div>
                </div>

                <!-- Реквизиты -->
                <div v-if="contacts?.inn || contacts?.ogrn || contacts?.kpp" class="w-full mt-[48px]">
                  <div class="flex gap-[21px]" style="flex-direction: column">
                    <div v-if="contacts.inn" class="w-full flex gap-2">
                      <div class="text-[24px] font-[400] text-[#C0C0C0] mb-0" style="font-family: Roboto; line-height: 1;">ИНН</div>
                      <div class="text-[24px] font-[400] text-black" style="font-family: Roboto; line-height: 1;">{{ contacts.inn }}</div>
                    </div>
                    <div v-if="contacts.ogrn"  class="w-full flex gap-2">
                      <div class="text-[24px] font-[400] text-[#C0C0C0] mb-0" style="font-family: Roboto; line-height: 1;">ОГРН</div>
                      <div class="text-[24px] font-[400] text-black" style="font-family: Roboto; line-height: 1;">{{ contacts.ogrn }}</div>
                    </div>
                    <div v-if="contacts.kpp"  class="w-full flex gap-2">
                      <div class="text-[24px] font-[400] text-[#C0C0C0] mb-0" style="font-family: Roboto; line-height: 1;">КПП</div>
                      <div class="text-[24px] font-[400] text-black" style="font-family: Roboto; line-height: 1;">{{ contacts.kpp }}</div>
                    </div>
                  </div>
                </div>

                <!-- Соцсети (оставляем как было) -->
                <div v-if="contacts?.socials?.length" class="flex gap-4">
                  <a
                      v-for="(social, idx) in contacts.socials"
                      :key="idx"
                      :href="social.url"
                      target="_blank"
                      class="hover:opacity-80 transition-opacity"
                  >
                    <!-- Telegram Icon -->
                    <svg v-if="social.name.toLowerCase().includes('telegram') || social.url.includes('t.me')"  width="56" height="56" viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <g clip-path="url(#clip0_1805_296)">
                        <path d="M28 56C43.464 56 56 43.464 56 28C56 12.536 43.464 0 28 0C12.536 0 0 12.536 0 28C0 43.464 12.536 56 28 56Z" fill="black"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M12.6724 27.7046C20.835 24.1483 26.278 21.8037 29.0014 20.671C36.7773 17.4367 38.3931 16.8749 39.4462 16.8563C39.6778 16.8523 40.1957 16.9097 40.5312 17.1819C40.8145 17.4117 40.8924 17.7222 40.9297 17.9402C40.967 18.1581 41.0134 18.6545 40.9765 19.0424C40.5551 23.4699 38.7318 34.2142 37.8042 39.173C37.4117 41.2713 36.6389 41.9748 35.8907 42.0436C34.2647 42.1933 33.03 40.9691 31.4551 39.9367C28.9907 38.3213 27.5985 37.3157 25.2064 35.7393C22.442 33.9176 24.2341 32.9163 25.8095 31.28C26.2218 30.8517 33.3861 24.3353 33.5247 23.7442C33.5421 23.6702 33.5582 23.3946 33.3945 23.2491C33.2307 23.1036 32.9891 23.1534 32.8147 23.1929C32.5676 23.249 28.6307 25.8512 21.0042 30.9993C19.8867 31.7666 18.8745 32.1405 17.9677 32.1209C16.9679 32.0993 15.0448 31.5556 13.6152 31.0909C11.8617 30.5209 10.468 30.2196 10.5894 29.2515C10.6526 28.7473 11.3469 28.2317 12.6724 27.7046Z" fill="white"/>
                      </g>
                      <defs>
                        <clipPath id="clip0_1805_296">
                          <rect width="56" height="56" fill="white"/>
                        </clipPath>
                      </defs>
                    </svg>

                    <!-- VK Icon -->
                    <svg v-else-if="social.name.toLowerCase().includes('vk') || social.url.includes('vk.com')" width="57" height="56" viewBox="0 0 57 56" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <g clip-path="url(#clip0_1805_300)">
                        <mask id="mask0_1805_300" style="mask-type:luminance" maskUnits="userSpaceOnUse" x="0" y="0" width="57" height="56">
                          <path d="M56.7188 0H0.283203V56H56.7188V0Z" fill="white"/>
                        </mask>
                        <g mask="url(#mask0_1805_300)">
                          <path d="M0.283203 26.88C0.283203 14.2087 0.283203 7.87298 4.25031 3.93649C8.21743 0 14.6024 0 27.3723 0H29.6297C42.3997 0 48.7846 0 52.7518 3.93649C56.7188 7.87298 56.7188 14.2087 56.7188 26.88V29.12C56.7188 41.7913 56.7188 48.127 52.7518 52.0635C48.7846 56 42.3997 56 29.6297 56H27.3723C14.6024 56 8.21743 56 4.25031 52.0635C0.283203 48.127 0.283203 41.7913 0.283203 29.12V26.88Z" fill="black"/>
                          <path d="M30.3155 40.3434C17.4528 40.3434 10.1163 31.5934 9.81055 17.0334H16.2536C16.4653 27.7201 21.2152 32.2467 24.9775 33.1801V17.0334H31.0446V26.25C34.7599 25.8534 38.6629 21.6534 39.9797 17.0334H46.0467C45.0356 22.7268 40.8029 26.9267 37.793 28.6534C40.8029 30.0534 45.6237 33.7167 47.4579 40.3434H40.7794C39.345 35.9101 35.7711 32.4801 31.0446 32.0134V40.3434H30.3155Z" fill="white"/>
                        </g>
                      </g>
                      <defs>
                        <clipPath id="clip0_1805_300">
                          <rect width="57" height="56" fill="white"/>
                        </clipPath>
                      </defs>
                    </svg>

                    <!-- YouTube Icon -->
                    <svg v-else-if="social.name.toLowerCase().includes('youtube') || social.url.includes('youtube.com')" width="76" height="56" viewBox="0 0 76 56" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <g clip-path="url(#clip0_1805_307)">
                        <path d="M74.4116 8.744C73.5376 5.304 70.9612 2.592 67.6932 1.672C61.7652 0 38 0 38 0C38 0 14.2348 0 8.3068 1.672C5.0388 2.592 2.4624 5.304 1.5884 8.744C0 14.984 0 28 0 28C0 28 0 41.016 1.5884 47.256C2.4624 50.696 5.0388 53.408 8.3068 54.328C14.2348 56 38 56 38 56C38 56 61.7652 56 67.6932 54.328C70.9612 53.408 73.5376 50.696 74.4116 47.256C76 41.016 76 28 76 28C76 28 76 14.984 74.4116 8.744Z" fill="black"/>
                        <path d="M30.3984 39.9999L50.1432 27.9999L30.3984 15.9999V39.9999Z" fill="white"/>
                      </g>
                      <defs>
                        <clipPath id="clip0_1805_307">
                          <rect width="76" height="56" fill="white"/>
                        </clipPath>
                      </defs>
                    </svg>
                  </a>
                </div>



              </div>
            </template>
            <!-- === ПОЛИТИКА КОНФИДЕНЦИАЛЬНОСТИ === -->
            <template v-if="activeSection === 'privacy'">
              <div class="blog-content max-w-none" v-html="privacy_policy?.content || ''"></div>

              <div v-if="!privacy_policy?.content" class="text-center text-gray-400 py-10">
                Политика конфиденциальности еще не добавлена.
              </div>
            </template>

            <!-- === СОГЛАСИЕ НА ОБРАБОТКУ ПД === -->
            <template v-if="activeSection === 'consent'">
              <div class="blog-content max-w-none" v-html="consent?.content || ''"></div>

              <div v-if="!consent?.content" class="text-center text-gray-400 py-10">
                Согласие на обработку персональных данных еще не добавлено.
              </div>
            </template>
          </div>

        </main>
        <PublicFooter />
      </div>
    </div>
  
</template>

<style scoped>
/* Scoped стили для компонентов Vue */
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.main-content {
  padding: 24px; /* По умолчанию 32px */
  background-color: rgb(247, 247, 247);
  /* Добавлено для гарантии отсутствия отступов у контента справа, если нужно */
  width: 100%;
  padding-bottom: 80px;
  box-sizing: border-box;
}
@media (min-width: 1440px) {
  .main-content {
    padding: 64px; /* При ≥1024px = 64px */
    max-width: 1115px;
    margin: 0 auto;
  }
}
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
</style>

<!-- ВАЖНО: Стили без scoped, чтобы применяться к v-html -->
<style>
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
.blog-toc li a {
  color: rgb(171, 171, 171);
  text-decoration: none;
}
.blog-toc li a:hover {
  color: #059669; /* emerald-700 */
}

/* Стили для контента из редактора */
.blog-content h2 {
  max-width: 695px;
  min-height: 17px;
  font-family: 'Roboto', sans-serif;
  font-style: normal;
  font-weight: 700;
  font-size: 24px;
  line-height: 28px;
  color: #000000;
  margin-top: 48px;
  margin-bottom: 1rem;
}
.blog-content h3 {
  width: 695px;
  max-width: 100%;
  font-family: 'Roboto', sans-serif;
  font-weight: 700;
  font-size: 24px;
  line-height: 28px;
  color: #000000;
  margin-top: 32px;
  margin-bottom: 8px;
}
.blog-content h4 {
  width: 695px;
  max-width: 100%;
  font-family: 'Roboto', sans-serif;
  font-weight: 700;
  font-size: 21px;
  line-height: 25px;
  color: #000000;
  margin-top: 21px;
  margin-bottom: 6px;
}
.blog-content p {
  font-family: 'Roboto', sans-serif;
  font-weight: 400;
  max-width: 695px;
  font-size: 18px;
  line-height: 1.5;
  color: #000000;
  margin-top: 12px;
  margin-bottom: 12px;
}
.blog-content ul, .blog-content ol {
  padding-left: 1.5rem;
  margin: 1rem 0;
}
.blog-content li {
  font-family: 'Roboto', sans-serif;
  font-size: 18px;
  line-height: 1.5;
  margin-bottom: 0.5rem;
}
.blog-content a {
  color: #0073FF;
  text-decoration: none;
}
.blog-content a:hover {
  text-decoration: underline;
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
  margin: 32px 0;
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
}
.blog-content blockquote p:first-child {
  max-width: 631px;
  font-family: 'Roboto', sans-serif;
  font-size: 18px;
  line-height: 21px;
  color: #000000;
  margin: 20px 0 0 0;
}
.blog-content blockquote p:last-child:not(:first-child) {
  font-family: 'Roboto', sans-serif;
  font-size: 18px;
  line-height: 21px;
  color: #000000;
  opacity: 0.3;
  margin: 0;
}
.blog-content blockquote p:last-child:not(:first-child)::before {
  content: '— ';
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

.cust_width{
  width: 677px;
}
@media (max-width: 1720px) {
  .cust_width{
    width: 33%;
  }

}
@media (max-width: 1024px) {
  .custom {
    width: 100%!important;
  }
  .custom_adaptive{
    flex-wrap: wrap;
    flex-direction: row;
    align-items: flex-start
  }
  .custom_adaptive .cust_one{
    width: 80%;
  }
  .custom_adaptive .cust_two{
    width: 20%;
    align-items: flex-start;
  }

}
@media (max-width: 600px) {
  .flex .cart_cust {
    max-width: 80%;
    padding: 1rem;
  }

  .but_custom {
    width: 100%;
    margin: 0;
    padding: 10px;
  }
}
</style>