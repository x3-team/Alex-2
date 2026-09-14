<script setup>
import { computed, ref } from 'vue'
import { router, Head } from '@inertiajs/vue3'
import SiteSidebar from '@/Components/SiteSidebar.vue'
import HomeBackLink from '@/Components/HomeBackLink.vue'
import reportData from '@/Components/demo-report.json'
import PublicFooter from '@/Components/PublicFooter.vue'
import '../../../css/main.css'
// Принимаем SEO и ссылку на PDF из Laravel / Inertia props
const props = defineProps({
  meta: { type: Object, default: () => ({}) },
  pdfUrl: { type: String, default: null }
})

const seoTitle = computed(() => props.meta?.title || 'Демо-результат ALEX² — ALEX LAB')
const seoDescription = computed(() => props.meta?.description || 'Интерактивный пример результата аллергологического исследования ALEX².')
const seoKeywords = computed(() => props.meta?.keywords || '')

const searchQuery = ref('')
const activeCategory = ref('all')
const categoryFilters = ref(null)

const patient = Object.freeze({
  name: 'Елагин М.С.',
  birthDate: '22.12.2023',
  sample: 'елагин',
  qr: '02CSU126',
  method: 'ALEX²',
  testedAt: '16.12.2025'
})

const sensitivityScale = Object.freeze([
  { level: '0', label: 'Отрицательный или неопределённый', range: '< 0.3', tone: 'neutral' },
  { level: '1', label: 'Низкий', range: '0.3–1', tone: 'low' },
  { level: '2', label: 'Умеренный', range: '1–5', tone: 'medium' },
  { level: '3', label: 'Высокий', range: '5–15', tone: 'high' },
  { level: '4', label: 'Очень высокий', range: '> 15', tone: 'very-high' }
])

const summaryLevelDetails = Object.freeze({
  '0': { label: 'Уровень 0', range: '< 0,3 kUA/L' },
  '1': { label: 'Уровень 1', range: '0,3–1 kUA/L' },
  '2': { label: 'Уровень 2', range: '1–5 kUA/L' },
  '3': { label: 'Уровень 3', range: '5–15 kUA/L' },
  '4': { label: 'Уровень 4', range: '> 15 kUA/L' }
})

const getResultLevel = (value) => {
  const numericValue = Number.parseFloat(String(value).replace(',', '.').replace(/[^0-9.]/g, ''))
  if (!Number.isFinite(numericValue) || numericValue < 0.3) return '0'
  if (numericValue < 1) return '1'
  if (numericValue < 5) return '2'
  if (numericValue <= 15) return '3'
  return '4'
}

const getSummaryLevelText = (value) => {
  const level = summaryLevelDetails[value] || summaryLevelDetails['0']
  return `${level.label} · ${level.range}`
}

const normalizeSearchValue = (value = '') =>
    String(value)
        .toLocaleLowerCase('ru-RU')
        .replace(/\s+/g, ' ')
        .trim()

const normalizedQuery = computed(() => normalizeSearchValue(searchQuery.value))

const categories = computed(() => {
  const counts = new Map()

  for (const item of reportData.items) {
    counts.set(item.category, (counts.get(item.category) || 0) + 1)
  }

  return [...counts].map(([name, count]) => ({ name, count }))
})

const filteredItems = computed(() => reportData.items.filter((item) => {
  const categoryMatches = activeCategory.value === 'all' || item.category === activeCategory.value
  if (!categoryMatches) return false
  if (!normalizedQuery.value) return true

  return [item.designation, item.allergen, item.protein, item.category, item.section]
      .map(normalizeSearchValue)
      .some((value) => value.includes(normalizedQuery.value))
}))

const groupedCategories = computed(() => {
  const categoryMap = new Map()

  for (const item of filteredItems.value) {
    if (!categoryMap.has(item.category)) {
      categoryMap.set(item.category, new Map())
    }

    const sectionMap = categoryMap.get(item.category)
    if (!sectionMap.has(item.section)) {
      sectionMap.set(item.section, [])
    }
    sectionMap.get(item.section).push(item)
  }

  return [...categoryMap].map(([name, sectionMap]) => ({
    name,
    count: [...sectionMap.values()].reduce((total, items) => total + items.length, 0),
    sections: [...sectionMap].map(([name, items]) => ({ name, items }))
  }))
})

const selectCategory = (category) => {
  activeCategory.value = category
}

const clearFilters = () => {
  searchQuery.value = ''
  activeCategory.value = 'all'
}

const scrollCategories = (direction) => {
  const slider = categoryFilters.value
  if (!slider) return

  slider.scrollBy({
    left: direction * Math.max(280, Math.round(slider.clientWidth * 0.7)),
    behavior: 'smooth'
  })
}

// Если ссылка на PDF передана из бэкенда — качаем по ней, иначе fallback на стандартную
const downloadPDF = () => {
  if (props.pdfUrl) {
    window.open(props.pdfUrl, '_blank')
    return
  }
  const link = document.createElement('a')
  link.href = '/documents/elagin-demo-result-clean.pdf'
  link.download = 'elagin-demo-result.pdf'
  link.click()
}
const isRegisterModalOpen = ref(false)
const isSheetDragging = ref(false)
const sheetDragOffset = ref(0)
let startY = 0

const labs = [
  { name: 'Гемотест', logo: '/assets/figma-lab-gemotest.webp', logoClass: 'test-location-modal__logo--gemotest', href: 'https://gemotest.ru/moskva/catalog/allergii/pervichnye-testy/obshchaya-allergodiagnostika/allergochip-alex-2/allergochip-alex-2-300-allergokomponentov-allergy-explorer-2-19ddaf/' },
  { name: 'ДНКОМ', logo: '/assets/figma-lab-dncom.webp', logoClass: 'test-location-modal__logo--dncom', href: 'https://dnkom.ru/analizy-i-tseny/allergochip-alex2-allergy-explorer/allergochip-alex2-allergy-explorer-2-do-300-allergokomponentov-i-obshchiy-ige/' },
  { name: 'Ситилаб', logo: '/assets/figma-lab-citilab.webp', logoClass: 'test-location-modal__logo--citilab', href: '#' },
  { name: 'KDL', logo: '/assets/figma-lab-kdl.webp', logoClass: 'test-location-modal__logo--kdl', href: 'https://kdl.ru/analizy-i-tseny/msk/allergochip-alex2-300-komponentov' },
  { name: 'CMD', logo: '/assets/figma-lab-cmd.webp', logoClass: 'test-location-modal__logo--cmd', href: 'https://www.cmd-online.ru/analizy-i-tseny/katalog-analizov/msk/allergochip_alex_2_300_allergokomponentov_i_ig_e_obshhij_ig_e/' },
  { name: 'CHROMOLAB', logo: '/assets/figma-lab-chromolab.webp', logoClass: 'test-location-modal__logo--chromolab', href: 'https://www.chromolab.ru/issledovaniya/al866_allergochip_alex2_300_komponentov_vklyuchaet_opredelenie_obshchego_ige/' }
]

const openCart = () => {
  isRegisterModalOpen.value = true
}

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
  if (delta > 0) {
    sheetDragOffset.value = delta
  }
}

const finishSheetDrag = () => {
  if (!isSheetDragging.value) return
  isSheetDragging.value = false
  if (sheetDragOffset.value > 120) {
    closeRegisterModal()
  }
  sheetDragOffset.value = 0
}
</script>

<template>
  <Head>
    <title>{{ seoTitle }}</title>
    <meta name="description" :content="seoDescription" />
    <meta v-if="seoKeywords" name="keywords" :content="seoKeywords" />
  </Head>

  <div class="page-container site-sidebar-layout">
    <SiteSidebar
        @register="openCart"
        @about="router.push('/')"
    />

    <main class="content-container results-page-content">
      <div class="mobile-top-bar home-back-area">
        <HomeBackLink stretched />
      </div>

      <div class="back-link-wrapper desktop-only home-back-area">
        <HomeBackLink stretched />
      </div>

      <div class="results-shell">
        <header class="report-header">
          <div class="report-heading">
            <p class="eyebrow">Пример лабораторного отчёта</p>
            <h1>Демо-результат ALEX²</h1>
            <p class="report-lead">
              Все показатели из лабораторного отчёта — в понятном и удобном формате.
              Используйте поиск или выберите группу аллергенов.
            </p>
          </div>

          <button class="download-pdf-btn" type="button" @click="downloadPDF">
            <span>Скачать PDF</span>
            <img src="/assets/figma-demo-download.svg" alt="" />
          </button>
        </header>

        <div class="report-brandbar" aria-label="Бренды лабораторного отчёта">
          <img
              class="report-brand-logo report-brand-logo-mad"
              src="/assets/report-brand/mad-logo.jpg"
              alt="MAD — Macro Array Diagnostics"
          />
          <div class="report-brand-caption" aria-hidden="true">
            <span>Лабораторный отчёт</span>
            <strong>ALEX²</strong>
          </div>
          <img
              class="report-brand-logo report-brand-logo-alex"
              src="/assets/report-brand/alex-allergy-xplorer.png"
              alt="ALEX — Allergy Xplorer"
          />
        </div>

        <section class="patient-panel" aria-labelledby="patient-title">
          <div class="panel-heading">
            <div>
              <p class="section-kicker">Пациент</p>
              <h2 id="patient-title">{{ patient.name }}</h2>
            </div>
            <span class="quality-badge"><i aria-hidden="true"></i> Контроль качества пройден</span>
          </div>

          <dl class="patient-data">
            <div>
              <dt>Дата рождения</dt>
              <dd>{{ patient.birthDate }}</dd>
            </div>
            <div>
              <dt>Номер образца</dt>
              <dd>{{ patient.sample }}</dd>
            </div>
            <div>
              <dt>QR-код</dt>
              <dd>{{ patient.qr }}</dd>
            </div>
            <div>
              <dt>Метод</dt>
              <dd>{{ patient.method }}</dd>
            </div>
            <div>
              <dt>Дата исследования</dt>
              <dd>{{ patient.testedAt }}</dd>
            </div>
          </dl>
        </section>

        <section class="report-summary" aria-labelledby="summary-title">
          <header class="report-summary-heading">
            <p class="section-kicker">Лабораторный отчёт</p>
            <h2 id="summary-title">Краткое изложение информации об исследуемой сенсибилизации</h2>
          </header>

          <div class="report-summary-columns">
            <section class="report-summary-panel" aria-labelledby="allergen-groups-title">
              <h3 id="allergen-groups-title">Группы аллергенов</h3>
              <dl class="report-summary-list">
                <div v-for="item in reportData.summary.allergenGroups" :key="item.label">
                  <dt>
                    <span class="summary-group">{{ item.group }}</span>
                    <span>{{ item.label }}</span>
                  </dt>
                  <dd class="summary-level" :class="`result-level-${item.value}`">{{ getSummaryLevelText(item.value) }}</dd>
                </div>
              </dl>
            </section>

            <section class="report-summary-panel" aria-labelledby="families-title">
              <h3 id="families-title">Семейства перекрёстно-реагирующих аллергенов</h3>
              <dl class="report-summary-list">
                <div v-for="item in reportData.summary.crossReactiveFamilies" :key="item.label">
                  <dt>{{ item.label }}</dt>
                  <dd class="summary-level" :class="`result-level-${item.value}`">{{ getSummaryLevelText(item.value) }}</dd>
                </div>
                <div class="summary-total-ige">
                  <dt>Общий IgE (kU/L)</dt>
                  <dd>{{ reportData.summary.totalIge }}</dd>
                </div>
              </dl>
            </section>
          </div>
        </section>

        <section class="interpretation-section" aria-labelledby="interpretation-title">
          <div class="interpretation-copy">
            <p class="section-kicker">Коротко о результате</p>
            <h2 id="interpretation-title">Как понимать показатели</h2>
            <p>
              Уровень специфического IgE показывает выраженность реакции на конкретный аллерген.
              Значения ниже 0,3 kUA/L считаются отрицательными или неопределёнными.
            </p>
            <p class="legend-note">E — экстракт аллергена, M — молекулярный аллерген</p>
          </div>

          <div class="total-ige-card" aria-label="Общий иммуноглобулин E">
            <span>Общий IgE</span>
            <strong>{{ reportData.summary.totalIge }}</strong>
            <small>kU/L</small>
          </div>

          <div class="sensitivity-scale" aria-label="Шкала чувствительности">
            <div
                v-for="item in sensitivityScale"
                :key="item.level"
                class="scale-item"
                :class="`scale-${item.tone}`"
            >
              <div class="scale-color" aria-hidden="true"></div>
              <div class="scale-value">
                <span>{{ item.level }}</span>
                <strong>{{ item.range }}</strong>
                <small>kUA/L</small>
              </div>
              <p>{{ item.label }}</p>
            </div>
          </div>

          <p class="medical-note">
            Интерпретация результата — инструмент для врача. Диагноз устанавливается с учётом
            симптомов, анамнеза и клинической картины.
          </p>
        </section>

        <section class="allergens-section" aria-labelledby="allergens-title">
          <div class="allergens-heading">
            <div>
              <p class="section-kicker">Полная панель</p>
              <h2 id="allergens-title">Результаты по аллергенам</h2>
              <p>{{ filteredItems.length }} из {{ reportData.items.length }} показателей</p>
            </div>

            <label class="allergen-search">
              <span class="visually-hidden">Поиск по аллергенам</span>
              <img src="/assets/figma-materials-search.svg" alt="" />
              <input
                  v-model="searchQuery"
                  type="search"
                  autocomplete="off"
                  placeholder="Название, код или белок"
                  @keydown.esc="searchQuery = ''"
              />
              <button
                  v-if="searchQuery"
                  type="button"
                  aria-label="Очистить поиск"
                  @click="searchQuery = ''"
              >
                ×
              </button>
            </label>
          </div>

          <div class="category-slider">
            <button
                class="category-slider-arrow category-slider-arrow-prev"
                type="button"
                aria-label="Показать предыдущие группы аллергенов"
                @click="scrollCategories(-1)"
            >
              <span aria-hidden="true">←</span>
            </button>

            <div ref="categoryFilters" class="category-filters" aria-label="Фильтр по группам аллергенов">
              <button
                  type="button"
                  :class="{ active: activeCategory === 'all' }"
                  @click="selectCategory('all')"
              >
                Все <span>{{ reportData.items.length }}</span>
              </button>
              <button
                  v-for="category in categories"
                  :key="category.name"
                  type="button"
                  :class="{ active: activeCategory === category.name }"
                  @click="selectCategory(category.name)"
              >
                {{ category.name }} <span>{{ category.count }}</span>
              </button>
            </div>

            <button
                class="category-slider-arrow category-slider-arrow-next"
                type="button"
                aria-label="Показать следующие группы аллергенов"
                @click="scrollCategories(1)"
            >
              <span aria-hidden="true">→</span>
            </button>
          </div>
          <p class="slider-hint">Листайте в сторону, чтобы увидеть все группы <span aria-hidden="true">→</span></p>

          <div v-if="groupedCategories.length" class="result-groups" aria-live="polite">
            <article
                v-for="category in groupedCategories"
                :key="category.name"
                class="result-category"
            >
              <header class="category-header">
                <h3>{{ category.name }}</h3>
                <span>{{ category.count }} показателей</span>
              </header>

              <section
                  v-for="section in category.sections"
                  :key="`${category.name}-${section.name}`"
                  class="result-section"
              >
                <div class="subsection-heading">
                  <h4>{{ section.name }}</h4>
                  <span>{{ section.items.length }}</span>
                </div>

                <div class="table-scroll">
                  <table class="report-table">
                    <thead>
                    <tr>
                      <th>Обозначение</th>
                      <th>Аллерген</th>
                      <th>E/M</th>
                      <th>Семейство белков</th>
                      <th class="cell-right">kUA/L</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="(item, index) in section.items" :key="`${item.allergen}-${index}`">
                      <td data-label="Обозначение">{{ item.designation }}</td>
                      <td data-label="Аллерген"><strong>{{ item.allergen }}</strong></td>
                      <td data-label="Тип"><span class="type-badge">{{ item.type }}</span></td>
                      <td data-label="Семейство белков">{{ item.protein || '—' }}</td>
                      <td data-label="kUA/L" class="cell-right">
                        <span class="value-badge" :class="`result-level-${getResultLevel(item.value)}`">{{ item.value }}</span>
                      </td>
                    </tr>
                    </tbody>
                  </table>
                </div>
              </section>
            </article>
          </div>

          <div v-else class="empty-search-result" aria-live="polite">
            <span class="empty-icon" aria-hidden="true">0</span>
            <h3>Ничего не найдено</h3>
            <p>Попробуйте другое название аллергена, его код или семейство белка.</p>
            <button type="button" @click="clearFilters">Сбросить фильтры</button>
          </div>
        </section>

        <footer class="report-footer">
          <div>
            <p class="section-kicker">Нужна помощь?</p>
            <h2>Объясним результат человеческим языком</h2>
          </div>
          <a href="tel:+7999439494">+7 999 43 94 94</a>
        </footer>
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
                <img :src="lab.logo" :class="lab.logoClass" :alt="`Логотип ${lab.name}`" />
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
.results-page-content {
  box-sizing: border-box;
  height: 100dvh !important;
  padding: 0 !important;
  display: flex !important;
  flex-direction: column;
  align-items: stretch;
  background: #fff;
  overflow-x: hidden !important;
  overflow-y: auto !important;
}

.back-link-wrapper {
  box-sizing: border-box;
  width: 100%;
  height: 72px;
  flex: 0 0 72px;
  padding: 24px 32px;
  display: flex;
  align-items: center;
  background: #fff;
  border-bottom: 1px solid #e4e4e4;
}

.results-shell {
  width: 100%;
  padding: clamp(40px, 5.2vw, 80px) clamp(32px, 5vw, 72px) 96px;
  box-sizing: border-box;
}

.report-brandbar {
  min-height: 104px;
  padding: 28px 0 24px;
  display: grid;
  grid-template-columns: minmax(170px, 230px) minmax(120px, 1fr) minmax(190px, 260px);
  align-items: center;
  gap: clamp(24px, 4vw, 64px);
  border-bottom: 3px solid #7b8b7a;
}

.report-brand-logo {
  display: block;
  width: 100%;
  object-fit: contain;
}

.report-brand-logo-mad {
  max-width: 230px;
  max-height: 92px;
  object-position: left center;
}

.report-brand-logo-alex {
  max-width: 260px;
  max-height: 96px;
  justify-self: end;
  object-position: right center;
}

.report-brand-caption {
  min-height: 52px;
  padding: 0 clamp(20px, 3vw, 44px);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  border-right: 1px solid #dedede;
  border-left: 1px solid #dedede;
  color: #737873;
  text-align: center;
  font: 400 12px/16px Roboto, Arial, sans-serif;
  letter-spacing: 0.075em;
  text-transform: uppercase;
}

.report-brand-caption strong {
  color: #303a3d;
  font-size: 14px;
  font-weight: 700;
}

.report-header {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: end;
  gap: 40px;
  padding: 0 0 32px;
  border-bottom: 0;
}

.report-heading {
  max-width: 760px;
}

.eyebrow,
.section-kicker {
  margin: 0 0 12px;
  color: #6e7370;
  font: 500 13px/18px Roboto, Arial, sans-serif;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.eyebrow::before,
.section-kicker::before {
  content: '';
  width: 16px;
  height: 3px;
  margin-right: 8px;
  display: inline-block;
  background: #7b8b7a;
  vertical-align: 3px;
}

.report-heading h1 {
  margin: 0;
  color: #101310;
  font: 500 clamp(40px, 4vw, 64px)/0.98 Roboto, Arial, sans-serif;
  letter-spacing: -0.045em;
}

.report-lead {
  max-width: 660px;
  margin: 22px 0 0;
  color: #646864;
  font: 400 18px/1.45 Roboto, Arial, sans-serif;
}

.download-pdf-btn {
  box-sizing: border-box;
  min-width: 190px;
  height: 56px;
  padding: 0 20px 0 24px;
  display: inline-flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  border: 1px solid #1c1f1c;
  border-radius: 0;
  background: #fff;
  color: #101310;
  font: 500 16px/20px Roboto, Arial, sans-serif;
  cursor: pointer;
  transition: background 160ms ease, color 160ms ease;
}

.download-pdf-btn:hover,
.download-pdf-btn:focus-visible {
  background: #1c1f1c;
  color: #fff;
  outline: none;
}

.download-pdf-btn img {
  width: 22px;
  height: 22px;
}

.download-pdf-btn:hover img,
.download-pdf-btn:focus-visible img {
  filter: invert(1);
}

.patient-panel {
  margin-top: 0;
  padding: 32px;
  border-top: 3px solid #7b8b7a;
  background: #f4f5f3;
}

.panel-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
}

.panel-heading h2,
.interpretation-copy h2,
.allergens-heading h2,
.report-footer h2 {
  margin: 0;
  color: #111411;
  font: 500 clamp(28px, 2.3vw, 40px)/1.08 Roboto, Arial, sans-serif;
  letter-spacing: -0.03em;
}

.quality-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 12px;
  background: rgba(255, 255, 255, 0.72);
  color: #536052;
  font: 500 13px/18px Roboto, Arial, sans-serif;
}

.quality-badge i {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #7b8b7a;
}

.patient-data {
  margin: 32px 0 0;
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  border-top: 1px solid rgba(73, 85, 71, 0.18);
}

.patient-data div {
  min-width: 0;
  padding: 22px 20px 0 0;
}

.patient-data dt {
  margin-bottom: 7px;
  color: #7a8278;
  font: 400 12px/16px Roboto, Arial, sans-serif;
}

.patient-data dd {
  margin: 0;
  color: #181b18;
  font: 500 16px/20px Roboto, Arial, sans-serif;
  overflow-wrap: anywhere;
}

.report-summary {
  padding: 56px 0;
  border-bottom: 1px solid #dedede;
}

.report-summary-heading {
  max-width: 820px;
}

.report-summary-heading h2 {
  margin: 0;
  color: #111411;
  font: 500 clamp(26px, 2vw, 36px)/1.15 Roboto, Arial, sans-serif;
  letter-spacing: -0.025em;
}

.report-summary-columns {
  display: grid;
  grid-template-columns: minmax(0, 1.12fr) minmax(0, 0.88fr);
  gap: 24px;
  margin-top: 32px;
}

.report-summary-panel {
  min-width: 0;
  padding: 24px;
  border: 1px solid #dfe2de;
  background: #f7f8f6;
}

.report-summary-panel h3 {
  margin: 0 0 16px;
  color: #303a3d;
  font: 500 15px/20px Roboto, Arial, sans-serif;
}

.report-summary-list {
  margin: 0;
  border-top: 1px solid #dfe2de;
}

.report-summary-list > div {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(126px, auto);
  gap: 12px;
  align-items: start;
  padding: 9px 0;
  border-bottom: 1px solid #dfe2de;
}

.report-summary-list dt,
.report-summary-list dd {
  min-width: 0;
  margin: 0;
}

.report-summary-list .summary-level {
  padding: 3px 6px;
  align-self: center;
  color: #4d554b;
  background: #f1f3f0;
  font-size: 11px;
  line-height: 14px;
  white-space: nowrap;
}

.report-summary-list dt {
  display: grid;
  gap: 2px;
  color: #404540;
  font: 400 13px/18px Roboto, Arial, sans-serif;
  overflow-wrap: anywhere;
}

.report-summary-list dd {
  color: #151815;
  font: 500 13px/18px Roboto, Arial, sans-serif;
  text-align: right;
}

.summary-group {
  color: #7a8278;
  font-size: 11px;
  line-height: 14px;
}

.report-summary-list .summary-total-ige {
  margin-top: 10px;
  padding: 11px 10px;
  border-color: #83c8ec;
  background: #dff2fb;
}

.summary-total-ige dt,
.summary-total-ige dd {
  color: #182326;
  font-weight: 600;
}

.interpretation-section {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 180px;
  gap: 40px;
  padding: 72px 0;
  border-bottom: 1px solid #dedede;
}

.interpretation-copy {
  max-width: 720px;
}

.interpretation-copy > p:not(.section-kicker):not(.legend-note) {
  max-width: 680px;
  margin: 18px 0 0;
  color: #626662;
  font: 400 16px/1.5 Roboto, Arial, sans-serif;
}

.legend-note {
  margin: 12px 0 0;
  color: #8c8f8c;
  font: 400 13px/18px Roboto, Arial, sans-serif;
}

.total-ige-card {
  min-height: 150px;
  padding: 22px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  background: #83c8ec;
  color: #182326;
}

.total-ige-card span {
  font: 500 14px/18px Roboto, Arial, sans-serif;
}

.total-ige-card strong {
  margin-top: auto;
  font: 500 44px/48px Roboto, Arial, sans-serif;
  letter-spacing: -0.04em;
}

.total-ige-card small {
  font: 400 13px/16px Roboto, Arial, sans-serif;
  opacity: 0.66;
}

.sensitivity-scale {
  grid-column: 1 / -1;
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 2px;
}

.scale-item {
  min-width: 0;
}

.scale-color {
  height: 10px;
  margin-bottom: 14px;
  background: #dce1dc;
}

.scale-low .scale-color { background: #c9d8c7; }
.scale-medium .scale-color { background: #f4b36e; }
.scale-high .scale-color { background: #ef815f; }
.scale-very-high .scale-color { background: #dc5554; }

.scale-value {
  display: flex;
  align-items: baseline;
  gap: 7px;
}

.scale-value span {
  width: 24px;
  height: 24px;
  display: grid;
  place-items: center;
  background: #f0f1f0;
  color: #4e524e;
  font: 500 12px/24px Roboto, Arial, sans-serif;
}

.scale-value strong {
  font: 500 16px/20px Roboto, Arial, sans-serif;
}

.scale-value small {
  color: #999d99;
  font: 400 11px/14px Roboto, Arial, sans-serif;
}

.scale-item p {
  margin: 10px 0 0 31px;
  color: #656965;
  font: 400 13px/18px Roboto, Arial, sans-serif;
}

.medical-note {
  grid-column: 1 / -1;
  margin: 0;
  padding: 16px 18px;
  border-left: 3px solid #7b8b7a;
  background: #f5f6f4;
  color: #676b67;
  font: 400 13px/19px Roboto, Arial, sans-serif;
}

.allergens-section {
  padding-top: 72px;
}

.allergens-heading {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(300px, 420px);
  align-items: end;
  gap: 40px;
}

.allergens-heading > div > p:last-child {
  margin: 10px 0 0;
  color: #8a8d8a;
  font: 400 14px/20px Roboto, Arial, sans-serif;
}

.allergen-search {
  position: relative;
  height: 56px;
  background: #fff;
}

.allergen-search > img {
  position: absolute;
  z-index: 1;
  top: 50%;
  left: 18px;
  width: 21px;
  height: 21px;
  transform: translateY(-50%);
  pointer-events: none;
}

.allergen-search input {
  box-sizing: border-box;
  width: 100%;
  height: 100%;
  padding: 0 50px 0 52px;
  border: 1px solid #c9ccc9;
  border-radius: 0;
  outline: none;
  background: #fff;
  color: #151815;
  font: 400 16px/22px Roboto, Arial, sans-serif;
  appearance: none;
}

.allergen-search input:focus {
  border-color: #111411;
  box-shadow: inset 0 0 0 1px #111411;
}

.allergen-search input::-webkit-search-cancel-button {
  display: none;
}

.allergen-search button {
  position: absolute;
  top: 50%;
  right: 11px;
  width: 34px;
  height: 34px;
  padding: 0;
  transform: translateY(-50%);
  border: 0;
  background: transparent;
  color: #737773;
  font: 300 26px/32px Arial, sans-serif;
  cursor: pointer;
}

.category-slider {
  position: relative;
  display: grid;
  grid-template-columns: 48px minmax(0, 1fr) 48px;
  align-items: center;
  gap: 10px;
  margin: 32px 0 40px;
}

.category-filters {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  scroll-snap-type: x proximity;
  scroll-behavior: smooth;
  scrollbar-width: none;
  -webkit-overflow-scrolling: touch;
  touch-action: pan-x;
}

.category-filters::-webkit-scrollbar {
  display: none;
}

.category-filters button {
  flex: 0 0 auto;
  min-height: 42px;
  padding: 10px 14px;
  border: 1px solid #d5d7d5;
  background: #fff;
  color: #4d514d;
  font: 400 13px/18px Roboto, Arial, sans-serif;
  white-space: nowrap;
  cursor: pointer;
  scroll-snap-align: start;
  transition: background 140ms ease, color 140ms ease, border-color 140ms ease;
}

.category-slider-arrow {
  width: 48px;
  height: 42px;
  padding: 0;
  display: grid;
  place-items: center;
  border: 1px solid #d5d7d5;
  background: #fff;
  color: #30383a;
  font: 400 22px/1 Arial, sans-serif;
  cursor: pointer;
  transition: background 140ms ease, color 140ms ease, border-color 140ms ease;
}

.category-slider-arrow:hover,
.category-slider-arrow:focus-visible {
  border-color: #7b8b7a;
  background: #7b8b7a;
  color: #fff;
  outline: none;
}

.slider-hint {
  display: none;
}

.category-filters button span {
  margin-left: 6px;
  color: #929692;
}

.category-filters button:hover,
.category-filters button:focus-visible,
.category-filters button.active {
  border-color: #7b8b7a;
  background: #7b8b7a;
  color: #fff;
  outline: none;
}

.category-filters button.active span,
.category-filters button:hover span,
.category-filters button:focus-visible span {
  color: rgba(255, 255, 255, 0.72);
}

.result-groups {
  display: grid;
  gap: 64px;
}

.result-category {
  min-width: 0;
}

.category-header {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 24px;
  padding-bottom: 18px;
  border-bottom: 2px solid #303a3d;
}

.category-header h3 {
  margin: 0;
  font: 500 26px/32px Roboto, Arial, sans-serif;
  letter-spacing: -0.025em;
}

.category-header span {
  color: #8a8e8a;
  font: 400 13px/18px Roboto, Arial, sans-serif;
}

.result-section + .result-section {
  margin-top: 28px;
}

.subsection-heading {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 18px 0 12px;
}

.subsection-heading h4 {
  margin: 0;
  font: 500 15px/20px Roboto, Arial, sans-serif;
}

.subsection-heading h4::before {
  content: '';
  width: 8px;
  height: 8px;
  margin-right: 9px;
  display: inline-block;
  background: #7b8b7a;
}

.subsection-heading span {
  min-width: 24px;
  height: 20px;
  padding: 0 6px;
  box-sizing: border-box;
  display: grid;
  place-items: center;
  background: #eef0ed;
  color: #767a76;
  font: 500 11px/20px Roboto, Arial, sans-serif;
}

.table-scroll {
  width: 100%;
  overflow-x: auto;
}

.report-table {
  width: 100%;
  min-width: 760px;
  table-layout: fixed;
  border-collapse: collapse;
  color: #202320;
  font-family: Roboto, Arial, sans-serif;
  font-size: 14px;
  line-height: 19px;
}

.report-table th:nth-child(1) { width: 31%; }
.report-table th:nth-child(2) { width: 18%; }
.report-table th:nth-child(3) { width: 8%; }
.report-table th:nth-child(4) { width: 29%; }
.report-table th:nth-child(5) { width: 14%; }

.report-table th,
.report-table td {
  padding: 12px 12px;
  border-bottom: 1px solid #e3e4e3;
  text-align: left;
  vertical-align: middle;
  overflow-wrap: anywhere;
}

.report-table th:first-child,
.report-table td:first-child {
  padding-left: 0;
}

.report-table th:last-child,
.report-table td:last-child {
  padding-right: 0;
}

.report-table thead th {
  color: #8a8e8a;
  font-size: 11px;
  font-weight: 500;
  line-height: 16px;
  letter-spacing: 0.045em;
  text-transform: uppercase;
}

.report-table tbody tr:hover {
  background: #f8f9f7;
}

.report-table td strong {
  font-weight: 500;
}

.cell-right {
  text-align: right !important;
  white-space: nowrap;
}

.type-badge {
  width: 26px;
  height: 26px;
  display: inline-grid;
  place-items: center;
  background: #eef0ed;
  color: #5b625a;
  font: 500 11px/26px Roboto, Arial, sans-serif;
}

.value-badge {
  display: inline-block;
  min-width: 60px;
  padding: 4px 8px;
  box-sizing: border-box;
  background: #f1f3f0;
  color: #4d554b;
  text-align: center;
  font: 500 12px/18px Roboto, Arial, sans-serif;
}

.result-level-1 {
  background: #f5efb7 !important;
  color: #584f14 !important;
}

.result-level-2 {
  background: #f4b36e !important;
  color: #47260c !important;
}

.result-level-3 {
  background: #ef815f !important;
  color: #fff !important;
}

.result-level-4 {
  background: #dc5554 !important;
  color: #fff !important;
}

.empty-search-result {
  min-height: 360px;
  padding: 48px 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  border-top: 1px solid #dedede;
  text-align: center;
}

.empty-icon {
  width: 52px;
  height: 52px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: #eef0ed;
  color: #758574;
  font: 500 18px/52px Roboto, Arial, sans-serif;
}

.empty-search-result h3 {
  margin: 18px 0 8px;
  font: 500 24px/30px Roboto, Arial, sans-serif;
}

.empty-search-result p {
  max-width: 460px;
  margin: 0;
  color: #777b77;
  font: 400 15px/22px Roboto, Arial, sans-serif;
}

.empty-search-result button {
  margin-top: 20px;
  padding: 10px 16px;
  border: 1px solid #758574;
  background: #758574;
  color: #fff;
  font: 500 14px/20px Roboto, Arial, sans-serif;
  cursor: pointer;
}

.report-footer {
  margin-top: 88px;
  padding: 38px 0 0;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 32px;
  border-top: 1px solid #dedede;
}

.report-footer h2 {
  max-width: 640px;
}

.report-footer a {
  color: #151815;
  font: 500 20px/26px Roboto, Arial, sans-serif;
  text-decoration: none;
  white-space: nowrap;
}

.report-footer a:hover,
.report-footer a:focus-visible {
  text-decoration: underline;
}

.visually-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.desktop-only {
  display: block;
}

@media (max-width: 1240px) {
  .patient-data {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    row-gap: 22px;
  }

  .report-summary {
    padding: 44px 0;
  }

  .report-summary-columns {
    grid-template-columns: 1fr;
    gap: 16px;
    margin-top: 24px;
  }

  .report-summary-panel {
    padding: 20px 16px;
  }

  .report-summary-list > div {
    grid-template-columns: minmax(0, 1fr) minmax(112px, auto);
  }

  .report-summary-list .summary-level {
    font-size: 10px;
  }

  .patient-data div {
    padding-top: 18px;
  }

  .sensitivity-scale {
    grid-template-columns: repeat(5, minmax(130px, 1fr));
    overflow-x: auto;
  }
}

@media (max-width: 1024px) {
  .desktop-only {
    display: none !important;
  }

  .results-page-content {
    height: 100dvh !important;
    max-height: 100dvh !important;
    padding: 0 !important;
    -webkit-overflow-scrolling: touch;
  }

  .mobile-top-bar {
    box-sizing: border-box;
    width: 100%;
    height: 72px;
    flex: 0 0 72px;
    margin: 0;
    padding: 24px 32px;
    position: sticky;
    top: 0;
    z-index: 100;
    background: rgba(255, 255, 255, 0.94);
    border-bottom: 1px solid #e4e4e4;
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
  }

  .results-shell {
    padding: 40px 32px 72px;
  }
}

@media (max-width: 720px) {
  .results-shell {
    padding: 32px 16px 56px;
  }

  .report-brandbar {
    min-height: 72px;
    padding: 24px 0 20px;
    grid-template-columns: minmax(110px, 0.9fr) minmax(140px, 1.1fr);
    gap: 24px;
    border-bottom-width: 2px;
  }

  .report-brand-caption {
    display: none;
  }

  .report-brand-logo-mad {
    max-height: 58px;
  }

  .report-brand-logo-alex {
    max-height: 62px;
  }

  .report-header {
    grid-template-columns: 1fr;
    gap: 28px;
    padding: 0 0 28px;
  }

  .report-heading h1 {
    font-size: clamp(36px, 12vw, 50px);
  }

  .report-lead {
    margin-top: 18px;
    font-size: 16px;
  }

  .download-pdf-btn {
    width: 100%;
  }

  .patient-panel {
    margin-top: 0;
    padding: 24px 20px;
  }

  .panel-heading {
    display: block;
  }

  .quality-badge {
    margin-top: 20px;
  }

  .patient-data {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    margin-top: 24px;
  }

  .interpretation-section {
    grid-template-columns: 1fr;
    gap: 28px;
    padding: 52px 0;
  }

  .total-ige-card {
    min-height: 126px;
  }

  .sensitivity-scale {
    grid-column: auto;
    margin-right: -16px;
    padding-right: 16px;
    grid-template-columns: repeat(5, 152px);
  }

  .medical-note {
    grid-column: auto;
  }

  .allergens-section {
    padding-top: 52px;
  }

  .allergens-heading {
    grid-template-columns: 1fr;
    gap: 24px;
  }

  .category-filters {
    margin: 0 -16px;
    padding: 0 16px;
  }

  .category-slider {
    display: block;
    margin: 24px 0 0;
  }

  .category-slider-arrow {
    display: none;
  }

  .slider-hint {
    display: block;
    margin: 10px 0 36px;
    color: #858985;
    font: 400 12px/16px Roboto, Arial, sans-serif;
  }

  .slider-hint span {
    margin-left: 5px;
    color: #758574;
    font-size: 16px;
    vertical-align: -1px;
  }

  .result-groups {
    gap: 52px;
  }

  .category-header {
    display: block;
  }

  .category-header span {
    display: block;
    margin-top: 6px;
  }

  .table-scroll {
    overflow: visible;
  }

  .report-table,
  .report-table tbody,
  .report-table tr,
  .report-table td {
    display: block;
    width: 100%;
  }

  .report-table {
    min-width: 0;
  }

  .report-table thead {
    display: none;
  }

  .report-table tbody {
    display: grid;
    gap: 8px;
  }

  .report-table tbody tr {
    padding: 15px 16px 12px;
    box-sizing: border-box;
    display: grid;
    //grid-template-columns: minmax(0, 1fr) auto;
    column-gap: 16px;
    background: #f6f7f5;
  }

  .report-table tbody tr:hover {
    background: #f1f3f0;
  }

  .report-table td {
    padding: 5px 0;
    border: 0;
  }

  .report-table td::before {
    content: attr(data-label);
    display: block;
    margin-bottom: 2px;
    color: #929692;
    font: 400 10px/13px Roboto, Arial, sans-serif;
    letter-spacing: 0.035em;
    text-transform: uppercase;
  }

  .report-table td:nth-child(1),
  .report-table td:nth-child(4) {
    grid-column: 1 / -1;
  }

  .report-table td:nth-child(2) {
    grid-column: 1;
  }

  .report-table td:nth-child(3) {
    grid-column: 2;
    grid-row: 2;
    text-align: right;
  }

  .report-table td:nth-child(5) {
    grid-column: 2;
    grid-row: 1;
    text-align: right !important;
  }

  .report-table td:nth-child(3)::before,
  .report-table td:nth-child(5)::before {
    text-align: right;
  }

  .value-badge {
    min-width: 58px;
  }

  .report-footer {
    margin-top: 64px;
    display: block;
  }

  .report-footer a {
    display: inline-block;
    margin-top: 24px;
  }
}

@media (max-width: 430px) {
  .mobile-top-bar {
    padding-inline: 24px;
  }

  .patient-data {
    grid-template-columns: 1fr 1fr;
    column-gap: 14px;
  }

  .patient-data div:last-child {
    grid-column: 1 / -1;
  }
}

@media print {
  .site-sidebar,
  .mobile-top-bar,
  .back-link-wrapper,
  .download-pdf-btn,
  .allergen-search,
  .category-filters,
  .report-footer {
    display: none !important;
  }

  .results-page-content,
  .results-shell {
    width: 100% !important;
    height: auto !important;
    padding: 0 !important;
    overflow: visible !important;
    background: #fff !important;
  }

  .report-table tbody tr {
    break-inside: avoid;
  }
}


@media (max-width: 1024px) {
.content-container.results-page-content .mobile-home-dock{
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
</style>