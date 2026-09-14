<script setup>
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { useDoctorMode } from '@/Composables/useDoctorMode'
import { SITE_VERSION } from '@/siteVersion'

const props = defineProps({
  doctorMode: {
    type: Boolean,
    default: null
  }
})

const emit = defineEmits(['register', 'about', 'close', 'materials', 'home'])

// 1. Глобальный / Локальный режим врача
const { isDoctorMode: globalDoctorMode, doctorsUrl } = useDoctorMode()
const isDoctor = computed(() => {
  if (props.doctorMode !== null) {
    return props.doctorMode
  }
  return globalDoctorMode.value
})

const page = usePage()
const isAuthenticated = computed(() => !!page.props.auth?.user)

// 2. Логика модальных окон
const isRegisterModalOpen = ref(false)
const isDevModalOpen = ref(false) // 🟡 Реактивное состояние для модалки разработки

const previouslyFocusedElement = ref(null)
const sheetDragOffset = ref(0)
const isSheetDragging = ref(false)
const dragStartY = ref(0)
let previousBodyOverflow = ''

const labs = Object.freeze([
  { name: 'Гемотест', logo: '/assets/figma-lab-gemotest.webp', logoClass: 'test-location-modal__logo--gemotest', href: 'https://gemotest.ru/moskva/catalog/allergii/pervichnye-testy/obshchaya-allergodiagnostika/allergochip-alex-2/allergochip-alex-2-300-allergokomponentov-allergy-explorer-2-19ddaf/' },
  { name: 'ДНКОМ', logo: '/assets/figma-lab-dncom.webp', logoClass: 'test-location-modal__logo--dncom', href: 'https://dnkom.ru/analizy-i-tseny/allergochip-alex2-allergy-explorer/allergochip-alex2-allergy-explorer-2-do-300-allergokomponentov-i-obshchiy-ige/' },
  { name: 'Ситилаб', logo: '/assets/figma-lab-citilab.webp', logoClass: 'test-location-modal__logo--citilab', href: 'https://citilab.ru/moskva/catalog/allergochip_alex2_allergy_explorer_292/allergochip_alex2_issledovanie_urovney_allergen_spetsificheskikh_immunoglobulinov_klassa_e_ige_k_300/' },
  { name: 'KDL', logo: '/assets/figma-lab-kdl.webp', logoClass: 'test-location-modal__logo--kdl', href: 'https://kdl.ru/analizy-i-tseny/msk/allergochip-alex2-300-komponentov' },
  { name: 'CMD', logo: '/assets/figma-lab-cmd.webp', logoClass: 'test-location-modal__logo--cmd', href: 'https://www.cmd-online.ru/analizy-i-tseny/katalog-analizov/msk/allergochip_alex_2_300_allergokomponentov_i_ig_e_obshhij_ig_e/' },
  { name: 'CHROMOLAB', logo: '/assets/figma-lab-chromolab.webp', logoClass: 'test-location-modal__logo--chromolab', href: 'https://www.chromolab.ru/issledovaniya/al866_allergochip_alex2_300_komponentov_vklyuchaet_opredelenie_obshchego_ige/' }
]);

const openRegisterModal = () => {
  isRegisterModalOpen.value = true
}

defineExpose({ openRegisterModal })

// 🟡 Открытие заглушки Личного Кабинета
const openDevModal = () => {
  isDevModalOpen.value = true
}

const restorePage = () => {
  if (!import.meta.client) return
  document.body.style.overflow = previousBodyOverflow
  previouslyFocusedElement.value?.focus?.()
}

const closeRegisterModal = () => {
  sheetDragOffset.value = 0
  isSheetDragging.value = false
  isRegisterModalOpen.value = false
}

const closeDevModal = () => {
  sheetDragOffset.value = 0
  isSheetDragging.value = false
  isDevModalOpen.value = false
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

// Тач-события для свайпа вниз на мобилках
const startSheetDrag = (event) => {
  if (!import.meta.client || window.matchMedia('(min-width: 1025px)').matches) return

  isSheetDragging.value = true
  dragStartY.value = event.clientY
  event.currentTarget.setPointerCapture?.(event.pointerId)
}

const moveSheetDrag = (event) => {
  if (!isSheetDragging.value) return
  sheetDragOffset.value = Math.max(0, event.clientY - dragStartY.value)
}

const finishSheetDrag = (event) => {
  if (!isSheetDragging.value) return

  event.currentTarget.releasePointerCapture?.(event.pointerId)
  const shouldClose = sheetDragOffset.value > Math.min(window.innerHeight * 0.2, 140)
  isSheetDragging.value = false

  if (shouldClose) {
    closeRegisterModal()
    closeDevModal()
    return
  }

  sheetDragOffset.value = 0
}

const handleKeydown = (event) => {
  if (event.key === 'Escape') {
    closeRegisterModal()
    closeDevModal()
  }
}

// Следим за состоянием обеих модалок для блокировки скролла
watch([isRegisterModalOpen, isDevModalOpen], ([regVisible, devVisible]) => {
  if (!import.meta.client) return

  if (regVisible || devVisible) {
    sheetDragOffset.value = 0
    isSheetDragging.value = false
    previouslyFocusedElement.value = document.activeElement
    previousBodyOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return
  }

  restorePage()
})

onMounted(() => window.addEventListener('keydown', handleKeydown))
onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
  restorePage()
})

// 3. Остальной функционал
const sidebarAssets = Object.freeze({
  logo: '/assets/figma-alex-logo.svg',
  cta: '/assets/figma-cta-arrow.svg',
  search: '/assets/figma-search-icon.svg',
  demo: '/assets/figma-demo-icon.svg',
  profile: '/assets/figma-profile-icon.svg',
  about: '/assets/figma-about-icon.svg',
  materials: '/assets/figma-doctor-materials-icon.svg'
})

const goTo = (path) => {
  router.visit(path)
}

const openAudienceContent = () => {
  if (isDoctor.value) {
    goTo(doctorsUrl('/blog'))
    closeMobileMenu()
    return
  }
  goTo('/quiz')
}

const openProfile = () => {
  if (isDoctor.value) {
    closeMobileMenu()
    openDevModal()
    return
  }
  goTo('/blog')
  closeMobileMenu()
}

const closeMobileMenu = () => {
  emit('close')
}

const openHome = () => {
  emit('home')
  goTo('/')
  closeMobileMenu()
}

const openMobileAbout = () => {
  emit('about')
  goTo('/alex-lab')
  closeMobileMenu()
}

const openAbout = () => {
  emit('about')
  goTo('/alex-lab')
}
</script>

<template>
  <aside
      class="sidebar-container site-sidebar"
      :class="{ 'doctor-theme': isDoctor }"
      aria-label="Навигация по сайту"
  >
    <div id="card-promo" class="promo-card">
      <div class="promo-content">
        <Link href="/" class="promo-logo-button" aria-label="Вернуться на первый экран главной" @click="openHome">
          <img
              :src="sidebarAssets.logo"
              alt="ALEX Allergy Xplorer"
              class="promo-banner-img"
              width="392"
              height="142"
              decoding="async"
              fetchpriority="high"
          />
        </Link>
        <p class="promo-text">
          Многокомпонентный анализ крови, который за одно взятие крови проверяет более 300 аллергенов, выявляя истинные причины реакции.
        </p>
        <button class="mobile-supplier-link" type="button" @click="openMobileAbout">
          О лаборатории ALEX LAB
          <span class="site-version">v{{ SITE_VERSION }}</span>
        </button>
      </div>

      <button
          id="btn-register"
          class="promo-button cta--shine-nudge"
          type="button"
          @click="openRegisterModal"
          style="--cta-period: 14s; --cta-cycles: infinite"
      >
        <span class="cta__glow" aria-hidden="true"></span>
        <span class="cta__shine" aria-hidden="true"></span>
        <span class="promo-button-text">Записаться на тест на аллергию</span>
        <span class="promo-button-icon cta__arrow" aria-hidden="true">
          <img :src="sidebarAssets.cta" alt="" width="32" height="32" decoding="async" />
        </span>
      </button>
    </div>

    <div class="cards-row">
      <Link
          id="card-search"
          href="/search"
          class="sub-card"
      >
        <div class="card-icon-wrapper">
          <img :src="sidebarAssets.search" alt="" width="42" height="42" decoding="async" />
        </div>
        <div class="card-text-group">
          <p class="card-title">Поиск аллергенов</p>
          <p class="card-subtitle">Убедитесь, что тест покажет, что вам нужно</p>
        </div>
      </Link>

      <Link
          id="card-demo"
          href="/demo-result"
          class="sub-card"
      >
        <div class="card-icon-wrapper">
          <img :src="sidebarAssets.demo" alt="" width="42" height="42" decoding="async" />
        </div>
        <div class="card-text-group">
          <p class="card-title">Посмотреть демо-результат</p>
          <p class="card-subtitle">Объясняем результаты<br />на человеческом языке</p>
        </div>
      </Link>
    </div>

    <div
        :id="isDoctor ? 'row-doctor-materials' : 'row-quiz'"
        class="sidebar-row-card"
        role="button"
        tabindex="0"
        @click="openAudienceContent"
        @keydown.enter="openAudienceContent"
        @keydown.space.prevent="openAudienceContent"
    >
      <div class="row-left-content">
        <template v-if="isDoctor">
          <span class="row-icon-icon" aria-hidden="true">
            <img :src="sidebarAssets.materials" alt="" width="24" height="24" decoding="async" />
          </span>
          <span class="row-text">Блог</span>
        </template>
        <template v-else>
          <span class="tag-badge">~1 минуту</span>
          <span class="row-text">КВИЗ: Нужен ли вам тест на аллергены?</span>
        </template>
      </div>
    </div>

    <!-- 🟡 КЛИК ТЕПЕРЬ ОТКРЫВАЕТ МОДАЛКУ ЗАГЛУШКУ -->
    <div
        id="row-profile"
        class="sidebar-row-card"
        role="button"
        tabindex="0"
        @click="openProfile"
        @keydown.enter="openProfile"
        @keydown.space.prevent="openProfile"
    >
      <div class="row-left-content">
        <span class="row-icon-icon sidebar-profile-icon" aria-hidden="true">
          <img :src="sidebarAssets.profile" alt="" width="24" height="24" decoding="async" />
        </span>
        <span class="row-text">{{ isDoctor ? 'Личный кабинет' : 'Блог' }}</span>
      </div>
    </div>

    <div
        id="row-about"
        class="sidebar-row-card"
        role="button"
        tabindex="0"
        @click="openAbout"
        @keydown.enter="openAbout"
        @keydown.space.prevent="openAbout"
    >
      <div class="row-left-content">
        <span class="row-text">О лаборатории ALEX LAB <span class="site-version">v{{ SITE_VERSION }}</span></span>
      </div>
      <span class="row-icon-icon" aria-hidden="true">
        <img :src="sidebarAssets.about" alt="" width="24" height="24" decoding="async" />
      </span>
    </div>

    <button class="mobile-close-row" type="button" @click="closeMobileMenu">
      <span class="mobile-close-icon" aria-hidden="true"></span>
      <span>Закрыть</span>
    </button>
  </aside>

  <!-- МОДАЛЬНОЕ ОКНО ВЫБОРА ЛАБОРАТОРИЙ -->
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
                    <path d="M9 5L15 12L9 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                </span>
              </button>
            </article>
          </div>
        </div>
      </section>
    </Transition>
  </Teleport>

  <!-- 🟡 НОВОЕ МОДАЛЬНОЕ ОКНО-ЗАГЛУШКА "В РАЗРАБОТКЕ" -->
  <Teleport to="body">
    <Transition name="test-location-modal-fade">
      <section
          v-if="isDevModalOpen"
          class="test-location-modal"
          aria-label="Раздел в разработке"
          role="dialog"
          aria-modal="true"
          @click.self="closeDevModal"
      >
        <div
            class="test-location-modal__sheet dev-modal__sheet"
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
              @keydown.enter.prevent="closeDevModal"
              @keydown.space.prevent="closeDevModal"
          >
            <div class="test-location-modal__grabber" aria-hidden="true"></div>
          </div>

          <header class="test-location-modal__header">
            <h2>Личный кабинет</h2>
            <button class="test-location-modal__close" type="button" aria-label="Закрыть" @click="closeDevModal">✕</button>
          </header>

          <div class="dev-modal__body">
            <div class="dev-modal__icon-box">
              🛠️
            </div>
            <p class="dev-modal__title">Раздел в разработке</p>
            <p class="dev-modal__description">
              Мы активно работаем над созданием личного кабинета. Совсем скоро здесь можно будет сохранять и отслеживать результаты ваших анализов!
            </p>
            <button class="dev-modal__button" type="button" @click="closeDevModal">
              Понятно
            </button>
          </div>
        </div>
      </section>
    </Transition>
  </Teleport>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@500;600&display=swap');

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

/* Модалка заглушки имеет более компактную ширину на десктопе */
.dev-modal__sheet {
  width: min(520px, 100%);
  gap: 24px;
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

/* 🟡 СТИЛИ ДЛЯ ВНУТРЕННОСТЕЙ ЗАГЛУШКИ */
.dev-modal__body {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 12px 0 8px;
}

.dev-modal__icon-box {
  font-size: 48px;
  line-height: 1;
  margin-bottom: 16px;
}

.dev-modal__title {
  font-family: Inter, Arial, sans-serif;
  font-size: 22px;
  font-weight: 600;
  color: #0f0f0f;
  margin: 0 0 12px 0;
}

.dev-modal__description {
  font-family: Inter, Arial, sans-serif;
  font-size: 16px;
  line-height: 1.5;
  color: #666;
  margin: 0 0 28px 0;
  max-width: 380px;
}

.dev-modal__button {
  background: #0f0f0f;
  color: #fff;
  border: 0;
  border-radius: 10.667px;
  padding: 14px 32px;
  font-family: Inter, Arial, sans-serif;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 160ms ease;
  width: 100%;
}

.dev-modal__button:hover {
  opacity: 0.9;
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

  .dev-modal__sheet {
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

  .dev-modal__icon-box {
    font-size: 40px;
    margin-bottom: 12px;
  }

  .dev-modal__title {
    font-size: 18px;
  }

  .dev-modal__description {
    font-size: 14px;
    margin-bottom: 20px;
  }
}

@media (max-width: 370px) {
  .test-location-modal__sheet { padding-left: 14px; padding-right: 14px; }
  .test-location-modal__logo-box { flex-basis: 110px; }
  .test-location-modal__lab-action { font-size: 13px; padding-left: 10px; }
}
.site-version {
  font-size: 11px;
  font-weight: 400;
  opacity: 0.4;
  margin-left: 6px;
  letter-spacing: 0.02em;
}
</style>