<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { router } from '@inertiajs/vue3'
import MiniSearch from 'minisearch'
import SiteSidebar from '@/Components/SiteSidebar.vue'
import HomeBackLink from '@/Components/HomeBackLink.vue'
import SearchParticleBackground from '@/Components/SearchParticleBackground.vue'
import '../../../css/main.css'
import PublicFooter from '@/Components/PublicFooter.vue'
import { useDoctorMode } from '@/Composables/useDoctorMode'

// 🔹 Принимаем данные аллергенов из БД через контроллер
const { isDoctorMode } = useDoctorMode()

const props = defineProps({
  allergensData: {
    type: Array,
    default: () => []
  },
  blogsData: {
    type: Array,
    default: () => []
  }
})

const searchQuery = ref('')
const selectedAllergen = ref(null)
const detailHeaderRef = ref(null)
const searchMainRef = ref(null)

const scrollDetailIntoView = async () => {
  await nextTick()
  // Double rAF: wait for detail DOM + layout before scrolling .search-main
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      const el = detailHeaderRef.value
      if (!el) return
      const scroller = searchMainRef.value || el.closest('.search-main')
      if (scroller) {
        const stickyOffset = 12
        const top =
          el.getBoundingClientRect().top -
          scroller.getBoundingClientRect().top +
          scroller.scrollTop -
          stickyOffset
        scroller.scrollTo({ top: Math.max(0, top), behavior: 'smooth' })
      } else {
        el.scrollIntoView({ block: 'start', behavior: 'smooth' })
      }
    })
  })
}

// 🔹 Основные топ-25 клинических аллергенов для показа по умолчанию
const TOP_PRIMARY_ALLERGENS = [
  'Береза', 'Клещ домашней пыли', 'Кошка', 'Собака', 'Молоко',
  'Яйцо', 'Арахис', 'Полынь', 'Амброзия', 'Тимофеевка',
  'Яблоко', 'Пшеница', 'Соя', 'Орех фундук', 'Грецкий орех',
  'Альтернария', 'Треска', 'Креветка', 'Оса', 'Пчела',
  'Персик', 'Томат', 'Морковь', 'Лошадь', 'Плесень'
]

const foldYo = (value) => String(value ?? '').toLowerCase().replace(/ё/g, 'е')

const allergenIndex = new MiniSearch({
  fields: ['name', 'code', 'description', 'category'],
  storeFields: ['id'],
  processTerm: (term) => {
    const folded = foldYo(term)
    return folded || null
  },
  searchOptions: {
    boost: { name: 3, code: 2 },
    prefix: true,
    combineWith: 'AND',
  },
})

allergenIndex.addAll(
  props.allergensData.map((item) => ({
    id: item.id,
    name: item.name ?? '',
    code: item.code ?? '',
    description: item.description ?? '',
    category: item.category ?? '',
  }))
)

const blogIndex = new MiniSearch({
  fields: ['title', 'excerpt'],
  storeFields: ['id'],
  processTerm: (term) => {
    const folded = foldYo(term)
    return folded || null
  },
  searchOptions: {
    boost: { title: 3, excerpt: 1 },
    prefix: true,
    combineWith: 'AND',
  },
})
blogIndex.addAll(
  (props.blogsData || []).map((item) => ({
    id: 'blog-' + item.id,
    title: item.title ?? '',
    excerpt: item.excerpt ?? '',
  }))
)
const blogsByKey = new Map((props.blogsData || []).map((item) => ['blog-' + item.id, item]))
const filteredBlogs = computed(() => {
  const query = searchQuery.value.trim()
  if (!query) return []
  return blogIndex.search(query, { fuzzy: query.length > 3 ? 0.2 : false })
    .map((hit) => blogsByKey.get(hit.id))
    .filter(Boolean)
})

const allergensById = new Map(props.allergensData.map((item) => [item.id, item]))

// 🔹 Список основных объектов аллергенов для начального экрана
const primaryAllergens = computed(() => {
  const primary = props.allergensData.filter(item =>
      TOP_PRIMARY_ALLERGENS.some(top => foldYo(item.name).includes(foldYo(top)))
  )

  if (primary.length < 20) {
    const remaining = props.allergensData.filter(item => !primary.includes(item))
    return [...primary, ...remaining].slice(0, 25)
  }

  return primary.slice(0, 30)
})

// 🔹 Результаты поиска: если запрос пуст — выводим основные, иначе MiniSearch
const filteredAllergens = computed(() => {
  const query = searchQuery.value.trim()
  if (!query) {
    return primaryAllergens.value
  }
  const hits = allergenIndex.search(query, {
    fuzzy: query.length > 3 ? 0.2 : false,
  })
  return hits.map((hit) => allergensById.get(hit.id)).filter(Boolean)
})

// 🔹 Выбор аллергена
const selectAllergen = (item) => {
  selectedAllergen.value = item
  searchQuery.value = item.name
  scrollDetailIntoView()
}

const selectAllergenByName = (name) => {
  const found = props.allergensData.find(a => a.name.toLowerCase() === name.toLowerCase())
  if (found) {
    selectAllergen(found)
  } else {
    selectedAllergen.value = {
      name: name,
      category: 'Другое',
      description: 'Компонент входит в панель теста ALEX²',
      included: true,
      icon_url: null,
      related: []
    }
    searchQuery.value = name
  }
  scrollDetailIntoView()
}

const goBackToSearch = () => {
  selectedAllergen.value = null
  searchQuery.value = ''
}

const clearSearch = () => {
  searchQuery.value = ''
  selectedAllergen.value = null
}

watch(searchQuery, (newVal) => {
  if (selectedAllergen.value && newVal !== selectedAllergen.value.name) {
    selectedAllergen.value = null
  }
})

const mobileMenuOpen = ref(false)
const isRegisterModalOpen = ref(false)
const isSheetDragging = ref(false)
const sheetDragOffset = ref(0)
let startY = 0

const labs = [
  { name: 'Гемотест', logo: '/assets/figma-lab-gemotest.webp', logoClass: 'test-location-modal__logo--gemotest', href: 'https://gemotest.ru/moskva/catalog/allergii/pervichnye-testy/obshchaya-allergodiagnostika/allergochip-alex-2/allergochip-alex-2-300-allergokomponentov-allergy-explorer-2-19ddaf/' },
  { name: 'ДНКОМ', logo: '/assets/figma-lab-dncom.webp', logoClass: 'test-location-modal__logo--dncom', href: 'https://dncom.ru/analizy-i-tseny/allergochip-alex2-allergy-explorer/allergochip-alex2-allergy-explorer-2-do-300-allergokomponentov-i-obshchiy-ige/' },
  { name: 'Ситилаб', logo: '/assets/figma-lab-citilab.webp', logoClass: 'test-location-modal__logo--citilab', href: '#' },
  { name: 'KDL', logo: '/assets/figma-lab-kdl.webp', logoClass: 'test-location-modal__logo--kdl', href: 'https://kdl.ru/analizy-i-tseny/msk/allergochip-alex2-300-komponentov' },
  { name: 'CMD', logo: '/assets/figma-lab-cmd.webp', logoClass: 'test-location-modal__logo--cmd', href: 'https://www.cmd-online.ru/analizy-i-tseny/katalog-analizov/msk/allergochip_alex_2_300_allergokomponentov_i_ig_e_obshhij_ig_e/' },
  { name: 'CHROMOLAB', logo: '/assets/figma-lab-chromolab.webp', logoClass: 'test-location-modal__logo--chromolab', href: 'https://www.chromolab.ru/issledovaniya/al866_allergochip_alex2_300_komponentov_vklyuchaet_opredelenie_obshchego_ige/' }
]

const openCart = () => { isRegisterModalOpen.value = true }
const closeRegisterModal = () => {
  isRegisterModalOpen.value = false
  sheetDragOffset.value = 0
}

const withClinicUtm = (url) => {
  if (!url || url === '#') return url
  if (url.includes('utm_source=')) return url
  return url + (url.includes('?') ? '&' : '?') + 'utm_source=site&utm_medium=cta&utm_campaign=clinic'
}

const handleLabClick = (lab) => {
  closeRegisterModal()
  if (lab.href && lab.href !== '#') {
    window.open(withClinicUtm(lab.href), '_blank')
  }
}

const startSheetDrag = (e) => {
  isSheetDragging.value = true
  startY = e.clientY || e.touches?.[0]?.clientY || 0
}

const moveSheetDrag = (e) => {
  if (!isSheetDragging.value) return
  const currentY = e.clientY || e.touches?.[0]?.clientY || 0
  const delta = currentY - startY
  if (delta > 0) sheetDragOffset.value = delta
}

const finishSheetDrag = () => {
  if (!isSheetDragging.value) return
  isSheetDragging.value = false
  if (sheetDragOffset.value > 120) closeRegisterModal()
  sheetDragOffset.value = 0
}
</script>

<template>
  <div class="search-page-container site-sidebar-layout"
       :class="{
         'mobile-menu-open': mobileMenuOpen,
         'doctor-mode': isDoctorMode
       }"
       :data-audience="isDoctorMode ? 'doctors' : 'patients'"
  >
    <SiteSidebar
        @register="openCart"
        @about="router.visit('/')"
    />

    <main ref="searchMainRef" class="search-main">
      <div class="search-back-row home-back-area">
        <HomeBackLink stretched />
      </div>

      <div class="search-center" :class="{ 'search-center--detail': selectedAllergen, 'search-center--filtered': !selectedAllergen && !!searchQuery.trim() }">
        <section class="search-hero">
          <SearchParticleBackground />

          <div class="search-hero-content">
            <h1 class="search-heading">Поиск аллергенов,<br> которые покажет ALEX²</h1>
            <p class="search-subheading">Более 300 аллергенов в одном тесте —<br>убедитесь сами</p>

            <div class="search-input-wrapper">
              <input
                  id="allergen-search-input"
                  v-model="searchQuery"
                  type="text"
                  class="search-input"
                  placeholder="Введите продукт, растение или животное"
                  autocomplete="off"
              />
              <button
                  v-if="searchQuery"
                  type="button"
                  class="search-clear-btn"
                  aria-label="Очистить"
                  @click="clearSearch"
              >
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                  <path d="M3 3L11 11M11 3L3 11" stroke="#000" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
              </button>
              <button class="search-icon-btn" type="button" aria-label="Поиск">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <circle cx="11" cy="11" r="7" stroke="#000" stroke-width="1.8"/>
                  <path d="M16.5 16.5L21 21" stroke="#000" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
              </button>
            </div>
          </div>
        </section>

        <div class="search-divider"></div>

        <div class="search-bottom-content">
          <!-- Режим детального просмотра -->
          <div v-if="selectedAllergen" class="search-details-grid">
            <div class="details-left-col">
              <div ref="detailHeaderRef" class="details-left-main detail-header-anchor">
                <div class="allergen-icon-box">
                  <img
                      v-if="selectedAllergen.icon_url"
                      :src="selectedAllergen.icon_url"
                      :alt="selectedAllergen.name"
                      class="allergen-box-icon"
                      decoding="async"
                  />
                  <img v-else src="/assets/Alergen.svg" alt="" aria-hidden="true" class="allergen-box-icon" />
                </div>

                <div class="detail-copy">
                  <div v-if="selectedAllergen.included" class="test-badge">
                    Входит в тест ALEX²
                  </div>

                  <div class="detail-text-group">
                    <h2 class="detail-name">{{ selectedAllergen.name }}</h2>
                    <p class="detail-desc">{{ selectedAllergen.description }}</p>
                    <p v-if="selectedAllergen.category" class="detail-desc-cat">
                      Категория: {{ selectedAllergen.category }}
                    </p>
                  </div>
                </div>
              </div>

              <div class="details-left-footer" @click="goBackToSearch">
                <div class="back-arrow-circle">
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22Z" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M14 8L10 12L14 16" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </div>
                <span>Назад ко всем аллергенам</span>
              </div>
            </div>

            <!-- Правая колонка (Связанные аллергены) -->
            <div class="details-right-col">
              <h3 class="related-title">Связанные аллергены:</h3>
              <div class="related-tags-container">
                <button
                    v-for="(rel, index) in selectedAllergen.related"
                    :key="index"
                    class="related-tag-btn"
                    @click="selectAllergenByName(typeof rel === 'object' ? rel.name : rel)"
                >
                  <img
                      v-if="typeof rel === 'object' && rel.icon_url"
                      :src="rel.icon_url"
                      alt=""
                      class="tag-admin-icon"
                      loading="lazy"
                      decoding="async"
                  />
                  <span>{{ typeof rel === 'object' ? rel.name : rel }}</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Режим обычного поиска -->
          <div v-else class="search-results">
            <button
                v-for="(allergen, index) in filteredAllergens"
                :key="`${allergen.id}-${index}`"
                class="allergen-tag"
                @click="selectAllergen(allergen)"
            >
              <img
                  v-if="allergen.icon_url"
                  :src="allergen.icon_url"
                  alt=""
                  class="tag-admin-icon"
                  loading="lazy"
                  decoding="async"
              />
              <span>{{ allergen.name }}</span>
            </button>

            <a
                v-for="post in filteredBlogs"
                :key="'blog-'+post.id"
                class="allergen-tag blog-search-tag"
                :href="post.url"
            >
              <span>{{ post.title }}</span>
            </a>
            <div v-if="filteredAllergens.length === 0 && filteredBlogs.length === 0" class="search-empty-state">
              Ничего не найдено. Попробуйте изменить запрос.
            </div>
          </div>
        </div>

      </div>
      <PublicFooter />
      <nav class="mobile-home-dock" aria-label="Быстрые действия">
        <button class="mobile-home-register" type="button" @click="openCart">
          <span>Записаться на тест на аллергию</span>
          <img src="/assets/figma-about-icon.svg" alt="" width="24" height="24" />
        </button>
      </nav>
    </main>
  </div>

  <Teleport to="body">
    <Transition name="test-location-modal-fade">
      <section
          v-if="isRegisterModalOpen"
          class="test-location-modal"
          aria-label="Выбор лаборатории для сдачи теста"
          role="dialog"
          aria-modal="true"
          @click.self="closeRegisterModal"
      >
        <div
            class="test-location-modal__sheet"
            :class="{ 'test-location-modal__sheet--dragging': isSheetDragging }"
            :style="{ '--sheet-drag-offset': `${sheetDragOffset}px` }"
        >
          <div
              class="test-location-modal__grabber-area"
              role="button"
              tabindex="0"
              aria-label="Потяните вниз, чтобы закрыть"
              @pointerdown="startSheetDrag"
              @pointermove="moveSheetDrag"
              @pointerup="finishSheetDrag"
              @pointercancel="finishSheetDrag"
              @keydown.enter.prevent="closeRegisterModal"
              @keydown.space.prevent="closeRegisterModal"
          >
            <div class="test-location-modal__grabber" aria-hidden="true"></div>
          </div>

          <header class="test-location-modal__header">
            <h2>Где сдать тест?</h2>
            <button class="test-location-modal__close" type="button" aria-label="Закрыть" @click="closeRegisterModal">✕</button>
          </header>

          <div class="test-location-modal__labs">
            <article v-for="lab in labs" :key="lab.name" class="test-location-modal__lab-card">
              <div class="test-location-modal__logo-box">
                <img :src="lab.logo" :class="lab.logoClass" :alt="`Логотип ${lab.name}`" loading="lazy" decoding="async" />
              </div>
              <button
                  class="test-location-modal__lab-action"
                  type="button"
                  :aria-label="`Записаться на тест в ${lab.name}`"
                  @click="handleLabClick(lab)"
              >
                <span>Записаться на тест</span>
                <span class="test-location-modal__arrow" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                      <path d="M9 5L15 12L9 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </span>
              </button>
            </article>
          </div>
        </div>
      </section>
    </Transition>
  </Teleport>
</template>

<style scoped>
.active-card {
  background-color: #fafafa !important;
}

/* ========================================
   ОБЩАЯ СЕТКА СТРАНИЦЫ ПОИСКА
   ======================================= */
.search-page-container {
  display: grid;
  grid-template-columns: 677px 1fr;
  gap: 0;
  max-width: none;
  margin: 0;
  padding: 0;
  width: 100vw;
  height: 100dvh;
  min-height: 0;
  overflow: hidden;
  background: #f5f5f5;
}

/* ========================================
   ПРАВАЯ ПАНЕЛЬ ПОИСКА
   ======================================== */
.search-main {
  flex: 1;
  background: #f5f5f5;
  display: flex;
  flex-direction: column;
  height: 100dvh;
  min-height: 0;
  position: relative;
  overflow-y: auto;
  scrollbar-width: none;
}

.search-main::-webkit-scrollbar {
  display: none;
}

.search-back-row {
  height: 72px;
  background: #ffffff;
  border-bottom: 1px solid #dfdfdf;
  padding: 24px 32px;
  display: flex;
  align-items: center;
  flex: 0 0 72px;
}

/* Центрированный контент с поддержкой Hero части */
.search-center {
  --search-hero-top-offset: 218px;
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
  padding: 218px 64px 48px;
}

.search-center--filtered,
.search-center--detail {
  --search-hero-top-offset: 128px;
  padding-top: 128px;
}

.search-hero {
  position: relative;
  width: 100%;
  margin-top: calc(var(--search-hero-top-offset) * -1);
  padding-top: var(--search-hero-top-offset);
  overflow: hidden;
}

.search-hero-content {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.search-heading {
  font-size: 42px;
  font-weight: 400;
  font-family: 'Roboto', sans-serif;
  color: #000;
  text-align: center;
  line-height: 49px;
  margin: 0 0 16px;
}

.search-subheading {
  font-size: 21px;
  font-weight: 400;
  font-family: 'Roboto', sans-serif;
  color: rgba(0, 0, 0, 0.5);
  text-align: center;
  line-height: 20px;
  margin: 0 0 32px;
}

/* Поисковая строка */
.search-input-wrapper {
  display: flex;
  align-items: center;
  width: 100%;
  max-width: 534px;
  height: 61px;
  background: #f8f8f8;
  border: 1px solid #dfdfdf;
  border-radius: 0;
  overflow: hidden;
}

.search-input {
  flex: 1;
  border: none;
  outline: none;
  min-width: 0;
  height: 100%;
  padding: 0 24px;
  font-size: 18px;
  font-family: inherit;
  color: #000;
  background: transparent;
}

.search-input::placeholder {
  color: rgba(0, 0, 0, 0.16);
  opacity: 1;
}

.search-clear-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 100%;
  background: none;
  border: none;
  cursor: pointer;
  flex-shrink: 0;
  opacity: 0.55;
}

.search-clear-btn:hover {
  opacity: 1;
}

.search-icon-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 62px;
  height: 100%;
  background: none;
  border-left: 1px solid #dfdfdf;
  border-top: none;
  border-bottom: none;
  border-right: none;
  cursor: pointer;
  flex-shrink: 0;
}

.search-icon-btn:hover {
  background: #f0f0f0;
}

/* Разделитель */
.search-divider {
  width: 100%;
  max-width: 1115px;
  height: 0;
  border-top: 1px solid rgba(0, 0, 0, 0.2);
  margin: 64px 0 63px;
}

.search-bottom-content {
  width: 100%;
  max-width: 1115px;
  display: flex;
  justify-content: center;
}

/* Сетка с результатами поиска */
.search-results {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  justify-content: center;
  width: 100%;
}

.allergen-tag {
  min-height: 61px;
  padding: 0 23px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: #f8f8f8;
  border: 1px solid #dfdfdf;
  border-radius: 0;
  font-size: 18px;
  font-family: 'Roboto', sans-serif;
  line-height: 1;
  color: #000;
  cursor: pointer;
  transition: background 0.15s;
}

.allergen-tag:hover {
  background: #f0f0f0;
}

.search-empty-state {
  width: 100%;
  text-align: center;
  padding: 40px;
  color: rgba(0, 0, 0, 0.5);
  font-size: 18px;
}

/* ========================================
   ДЕТАЛЬНЫЙ ПРОСМОТР АЛЛЕРГЕНА
   ======================================== */
.search-details-grid {
  display: flex;
  width: 100%;
  background: transparent;
}

.details-left-col {
  width: 50%;
  display: flex;
  flex-direction: column;
}

.detail-header-anchor {
  scroll-margin-top: 12px;
}

.details-left-main {
  background: #ffffff;
  border: 1px solid #dfdfdf;
  border-right: none;
  padding: 32px;
  flex: 1 1 auto;
  min-height: 312px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  justify-content: space-between;
}

.allergen-icon-box {
  margin-bottom: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  padding-bottom: 10px;
}

.allergen-icon-box img {
  display: block;
  width: 42px;
  height: 42px;
}

.detail-copy {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 12px;
}

.detail-text-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.test-badge {
  background: #f1fff3;
  color: #0f5e1a;
  font-size: 14px;
  font-family: inherit;
  height: 40px;
  padding: 0 24px;
  margin: 0;
  border-radius: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.detail-name {
  font-size: 32px;
  font-weight: 400;
  color: #000;
  line-height: 38px;
  margin: 0;
}

.detail-desc {
  font-size: 18px;
  color: rgba(0, 0, 0, 0.5);
  margin: 0;
  line-height: 1.3;
}

.detail-desc-cat {
  margin-top: 4px;
  font-size: 14px;
  color: rgba(0, 0, 0, 0.5);
}

.details-left-footer {
  background: #ffffff;
  border: 1px solid #dfdfdf;
  border-right: none;
  border-top: none;
  padding: 0 24px;
  height: 72px;
  flex: 0 0 72px;
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  user-select: none;
}

.details-left-footer:hover {
  background: #fcfcfc;
}

.details-left-footer span {
  font-size: 18px;
  color: #000;
}

.back-arrow-circle {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
}

.details-right-col {
  width: 50%;
  background: transparent;
  border: 1px solid #dfdfdf;
  padding: 32px;
  min-height: 384px;
  display: flex;
  flex-direction: column;
}

.related-title {
  font-size: 21px;
  font-weight: 400;
  color: #000;
  margin: 0 0 24px;
}

.related-tags-container {
  width: 100%;
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  justify-content: center;
  align-content: flex-start;
}

.related-tag-btn {
  background: #f8f8f8;
  border: 1px solid #dfdfdf;
  color: #000;
  min-height: 61px;
  padding: 0 23px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  font-family: inherit;
  cursor: pointer;
  transition: all 0.15s;
}

.related-tag-btn:hover {
  background: #eee;
  border-color: #ccc;
}

/* ========================================
   АДАПТИВНОСТЬ
   ======================================== */
@media (max-width: 1024px) {
  .search-page-container {
    grid-template-columns: 1fr;
    gap: 0;
  }

  .sidebar-container {
    display: none;
  }

  .search-back-row {
    padding: 24px 32px;
    height: 72px;
    border-bottom: 1px solid #dfdfdf;
    background: #ffffff;
  }

  .search-center {
    --search-hero-top-offset: 102px;
    padding: 102px 16px 32px;
  }

  .search-center--filtered,
  .search-center--detail {
    --search-hero-top-offset: 102px;
    padding-top: 102px;
  }

  .search-heading {
    width: 100%;
    font-size: 32px;
    line-height: 38px;
  }

  .search-subheading {
    width: 100%;
    font-size: 18px;
    line-height: 1.2;
    margin-bottom: 32px;
  }

  .search-input-wrapper {
    max-width: 100%;
  }

  .search-divider {
    max-width: 100%;
    margin: 32px 0 31px;
  }

  .search-bottom-content {
    max-width: 100%;
  }

  .search-results {
    max-width: 100%;
  }

  .search-details-grid {
    flex-direction: column;
    gap: 0;
  }

  .details-left-col,
  .details-right-col {
    width: 100%;
  }

  .details-left-main {
    border: 1px solid #dfdfdf;
    height: fit-content;
    min-height: 266px;
  }

  .test-badge {
    height: 32px;
    padding: 0 12px;
  }

  .details-left-footer {
    order: -1;
    border: 1px solid #dfdfdf;
    height: 66px;
    flex: 0 0 66px;
    margin-bottom: 0;
  }

  .details-right-col {
    border: 1px solid #dfdfdf;
    padding: 32px;
    height: auto;
    min-height: 394px;
    background: transparent;
  }

  .related-title {
    line-height: 1.2;
  }

  .related-tags-container {
    width: 100%;
    height: auto;
    gap: 4px;
    justify-content: flex-start;
    align-content: flex-start;
  }

  .related-tag-btn {
    min-height: 55px;
    padding: 0 13px;
  }
  .search-main .mobile-home-dock{
    --mobile-unit: min(calc(100vw / 420), 1px);
    --mobile-dock-height: 67px;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 120;
    grid-template-rows: var(--mobile-dock-height);
    align-items: stretch;
    gap: 0;
    height: var(--mobile-dock-height);
    padding-bottom: env(safe-area-inset-bottom);
    box-sizing: content-box;
    background: #fff;
    display: grid;
  }
}

.test-location-modal {
  align-items: center;
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  background: rgb(78 98 77 / 63%);
  display: flex;
  inset: 0;
  justify-content: center;
  padding: 24px;
  position: fixed;
  z-index: 2000;
}

.test-location-modal__sheet {
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 32px 85.333px rgb(0 0 0 / 24%);
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 32px;
  max-height: calc(100vh - 48px);
  overflow: auto;
  padding: 42.667px;
  width: min(1226.667px, 100%);
}

.test-location-modal__grabber {
  display: none;
}

.test-location-modal__grabber-area {
  display: none;
}

.test-location-modal__header {
  align-items: center;
  display: flex;
  justify-content: space-between;
  min-height: 53.333px;
}

.test-location-modal__header h2 {
  color: #0f0f0f;
  font-family: Inter, Arial, sans-serif;
  font-size: 37.333px;
  font-weight: 600;
  letter-spacing: -0.02em;
  line-height: 45.333px;
  margin: 0;
}

.test-location-modal__close {
  align-items: center;
  background: #f4f4f2;
  border: 0;
  border-radius: 10.667px;
  color: #0f0f0f;
  cursor: pointer;
  display: flex;
  flex: 0 0 53.333px;
  font-family: Inter, Arial, sans-serif;
  font-size: 21.333px;
  font-weight: 500;
  height: 53.333px;
  justify-content: center;
  line-height: 1;
  padding: 0;
}

.test-location-modal__close:focus-visible,
.test-location-modal__lab-card:focus-within {
  outline: 2px solid #0f0f0f;
  outline-offset: 0;
}

.test-location-modal__labs {
  display: grid;
  gap: 21.333px;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.test-location-modal__lab-card {
  background: #f5f5f3;
  border: 1.333px solid #e7e7e5;
  border-radius: 13.333px;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 21.333px;
  min-width: 0;
  padding: 21.333px;
  transition: border-color 160ms ease, box-shadow 160ms ease;
}

.test-location-modal__lab-card:hover {
  border-color: #0f0f0f;
  box-shadow: 0 6px 16px rgb(0 0 0 / 10%);
}

.test-location-modal__logo-box {
  align-items: center;
  background: #fff;
  border-radius: 10.667px;
  display: flex;
  height: 117.333px;
  justify-content: center;
  overflow: hidden;
}

.test-location-modal__logo-box img {
  display: block;
  max-height: 58px;
  max-width: calc(100% - 32px);
  object-fit: contain;
}

.test-location-modal__logo--gemotest,
.test-location-modal__logo--chromolab { width: min(268px, calc(100% - 32px)); }
.test-location-modal__logo--dncom { width: min(156px, calc(100% - 32px)); }
.test-location-modal__logo--citilab,
.test-location-modal__logo--kdl { width: min(196px, calc(100% - 32px)); }
.test-location-modal__logo--cmd { width: min(152px, calc(100% - 32px)); }

.test-location-modal__lab-action {
  align-items: center;
  background: #0f0f0f;
  border: 0;
  border-radius: 10.667px;
  color: #fff;
  cursor: pointer;
  display: flex;
  font-family: Inter, Arial, sans-serif;
  font-size: 18.667px;
  font-weight: 600;
  justify-content: space-between;
  letter-spacing: -0.01em;
  line-height: 26.667px;
  min-height: 64px;
  padding: 16px 16px 16px 21.333px;
  text-align: left;
}

.test-location-modal__arrow {
  align-items: center;
  border: 1.333px solid rgb(255 255 255 / 50%);
  border-radius: 50%;
  display: flex;
  flex: 0 0 29.333px;
  height: 29.333px;
  justify-content: center;
  margin-left: 12px;
}

.test-location-modal__arrow svg {
  height: 16px;
  width: 16px;
}

.test-location-modal-fade-enter-active,
.test-location-modal-fade-leave-active { transition: opacity 180ms ease; }
.test-location-modal-fade-enter-from,
.test-location-modal-fade-leave-to { opacity: 0; }

@media (max-width: 1024px) {
  .test-location-modal {
    align-items: flex-end;
    padding: 0;
  }

  .test-location-modal__sheet {
    border-radius: 21.538px 21.538px 0 0;
    gap: 17.231px;
    max-height: min(100dvh, 620.308px);
    padding: 12.923px 21.538px 36.615px;
    transform: translateY(var(--sheet-drag-offset, 0));
    transition: transform 340ms cubic-bezier(0.22, 1, 0.36, 1);
    width: min(420px, 100vw);
  }

  .test-location-modal__sheet--dragging {
    transition: none;
  }

  .test-location-modal-fade-enter-active .test-location-modal__sheet,
  .test-location-modal-fade-leave-active .test-location-modal__sheet {
    transition: transform 340ms cubic-bezier(0.22, 1, 0.36, 1);
  }

  .test-location-modal-fade-enter-from .test-location-modal__sheet,
  .test-location-modal-fade-leave-to .test-location-modal__sheet {
    transform: translateY(100%);
  }

  .test-location-modal__grabber-area {
    align-items: center;
    cursor: grab;
    display: flex;
    justify-content: center;
    margin: -12.923px -21.538px -8px;
    min-height: 30px;
    touch-action: none;
  }

  .test-location-modal__grabber-area:active {
    cursor: grabbing;
  }

  .test-location-modal__grabber-area:focus-visible {
    outline: 2px solid #0f0f0f;
    outline-offset: -2px;
  }

  .test-location-modal__grabber {
    background: #d9d9d6;
    border-radius: 2.154px;
    display: block;
    height: 4.308px;
    margin: 0 auto;
    width: 43.077px;
  }

  .test-location-modal__header {
    min-height: 38.769px;
  }

  .test-location-modal__header h2 {
    font-size: 23.692px;
    line-height: 30.154px;
  }

  .test-location-modal__close {
    border-radius: 8.615px;
    flex-basis: 38.769px;
    font-size: 15.077px;
    height: 38.769px;
  }

  .test-location-modal__labs {
    display: flex;
    flex-direction: column;
    gap: 10.769px;
  }

  .test-location-modal__lab-card {
    align-items: center;
    border-width: 1.077px;
    border-radius: 10.769px;
    flex-direction: row;
    gap: 10.769px;
    min-height: 73.231px;
    padding: 10.769px;
  }

  .test-location-modal__logo-box {
    border-radius: 8.615px;
    flex: 0 0 133.538px;
    height: 51.692px;
  }

  .test-location-modal__logo-box img {
    max-height: 34px;
    max-width: calc(100% - 18px);
  }

  .test-location-modal__logo--gemotest,
  .test-location-modal__logo--chromolab { width: 111px; }
  .test-location-modal__logo--dncom { width: 72px; }
  .test-location-modal__logo--citilab,
  .test-location-modal__logo--kdl { width: 90px; }
  .test-location-modal__logo--cmd { width: 70px; }

  .test-location-modal__lab-action {
    border-radius: 8.615px;
    flex: 1 1 auto;
    font-size: 15.077px;
    line-height: 20px;
    min-height: 47.385px;
    padding: 11.846px 10.769px 11.846px 15.077px;
  }

  .test-location-modal__arrow {
    border-width: 1.077px;
    flex-basis: 21.538px;
    height: 21.538px;
    margin-left: 8px;
  }

  .test-location-modal__arrow svg {
    height: 12px;
    width: 12px;
  }
}

@media (max-width: 370px) {
  .test-location-modal__sheet { padding-left: 14px; padding-right: 14px; }
  .test-location-modal__logo-box { flex-basis: 110px; }
  .test-location-modal__lab-action { font-size: 13px; padding-left: 10px; }
}
/* Иконки в тегах (allergen-tag и related-tag-btn) */
.allergen-tag,
.related-tag-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.tag-admin-icon {
  width: 18px;
  height: 18px;
  object-fit: contain;
  stroke-width: 1px;
  stroke: #93A587;
  object-fit: contain;
  filter: invert(72%) sepia(8%) saturate(1008%) hue-rotate(50deg) brightness(91%) contrast(85%)
}

/* Иконка в блоке детального просмотра allergen-icon-box */
.allergen-icon-box {
  display: flex;
  align-items: center;
  justify-content: center;
}

.allergen-box-icon {
  width: 36px;
  height: 36px;
  object-fit: contain;
  stroke-width: 1.75px;
  stroke: #93A587;
  object-fit: contain;
  filter: invert(72%) sepia(8%) saturate(1008%) hue-rotate(50deg) brightness(91%) contrast(85%)
}
</style>