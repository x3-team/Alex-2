<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import SiteSidebar from '@/Components/SiteSidebar.vue'
import HomeBackLink from '@/Components/HomeBackLink.vue'
import { readQuizLead } from '@/quizLead'

const props = defineProps({
  mainProduct: {
    type: Object,
    default: () => ({
      id: 'alex2',
      title: 'Сдача анализа ALEX2',
      description: 'Мультикомплексный анализ на 300 аллергенов за один забор крови.',
      price: 24900,
      tags: ['+ Забор крови', '+IgE']
    })
  },
  additionalServices: {
    type: Array,
    default: () => []
  },
  labs: {
    type: Array,
    default: () => []
  }
})

const selectedServiceIds = ref([])

const fullName = ref('')
const birthDate = ref('')
const phone = ref('')
const email = ref('')
const promo = ref('')
const consent = ref(false)

// Опции для выбора города
const cities = [
  { name: 'Москва', x: 210, y: 220 },
  { name: 'Санкт-Петербург', x: 150, y: 160 },
  { name: 'Казань', x: 300, y: 230 },
  { name: 'Екатеринбург', x: 420, y: 270 },
  { name: 'Краснодар', x: 180, y: 310 }
]

// Состояние выбора лаборатории
const showMapSelector = ref(false)
const mapStep = ref(1)
const selectedCity = ref('')
const showCityList = ref(false)
const activeLabId = ref(null)
const detailedLabId = ref(null)
const selectedLab = ref(null)

// Для мобильной версии карты
const showMapOnMobile = ref(false)
const isDesktop = ref(true)

const checkViewport = () => {
  if (typeof window !== 'undefined') {
    isDesktop.value = window.innerWidth > 900
  }
}

onMounted(() => {
  checkViewport()
  window.addEventListener('resize', checkViewport)
})

onUnmounted(() => {
  window.removeEventListener('resize', checkViewport)
})

const filteredLabs = computed(() => {
  return props.labs.filter(lab => lab.city === selectedCity.value)
})

const selectedMobileLab = computed(() => {
  return props.labs.find(l => l.id === detailedLabId.value) || null
})

const markerX = (lab) => isDesktop.value && lab.desktopX != null ? lab.desktopX : lab.x
const markerY = (lab) => isDesktop.value && lab.desktopY != null ? lab.desktopY : lab.y

const mapViewport = computed(() => {
  const targetId = detailedLabId.value || activeLabId.value
  if (targetId) {
    const lab = props.labs.find(l => l.id === targetId)
    if (lab) {
      return `${markerX(lab) - 150} ${markerY(lab) - 120} 300 240`
    }
  }
  return '0 0 1000 600'
})

// Управление интерфейсом карты
const openMapSelector = () => {
  showMapSelector.value = true
  showMapOnMobile.value = false
  detailedLabId.value = null
  activeLabId.value = null

  if (selectedLab.value) {
    selectedCity.value = selectedLab.value.city
    mapStep.value = 2
  } else {
    mapStep.value = 1
  }
}

const handleMapBack = () => {
  if (showMapOnMobile.value) {
    showMapOnMobile.value = false
    detailedLabId.value = null
    activeLabId.value = null
  } else if (mapStep.value === 2) {
    mapStep.value = 1
  } else {
    showMapSelector.value = false
  }
}

const selectCity = (cityName) => {
  selectedCity.value = cityName
  showCityList.value = false
  mapStep.value = 2
  activeLabId.value = null
  detailedLabId.value = null
}

const changeCity = () => {
  mapStep.value = 1
  showCityList.value = false
}

const clickLabCard = (lab) => {
  activeLabId.value = lab.id
  detailedLabId.value = lab.id
  if (!isDesktop.value) {
    showMapOnMobile.value = true
  }
}

const clickMapMarker = (lab) => {
  activeLabId.value = lab.id
  detailedLabId.value = lab.id
}

const closeMobileSheet = () => {
  detailedLabId.value = null
}

const confirmLabSelection = (lab) => {
  selectedLab.value = lab
  showMapSelector.value = false
  showMapOnMobile.value = false
}

// Логика выбора доп. услуг
const toggleService = (serviceId) => {
  const index = selectedServiceIds.value.indexOf(serviceId)
  if (index === -1) {
    selectedServiceIds.value.push(serviceId)
  } else {
    selectedServiceIds.value.splice(index, 1)
  }
}

const isServiceSelected = (serviceId) => {
  return selectedServiceIds.value.includes(serviceId)
}

// Подсчет цены
const formattedTotalPrice = computed(() => {
  let total = Number(props.mainProduct.price)

  props.additionalServices.forEach(service => {
    if (isServiceSelected(service.id)) {
      total += Number(service.price)
    }
  })

  return total.toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ")
})

const applyPromo = () => {
  // Логика промокода
}

const handleContinue = async () => {
  if (!consent.value) return

  const name = fullName.value.trim()
  const tel = phone.value.trim()
  if (!name || !tel) {
    alert('Укажите ФИО и телефон, чтобы заявка сохранилась.')
    return
  }

  const quiz = readQuizLead()
  const lines = [{ title: props.mainProduct.title, price: Number(props.mainProduct.price) || 0 }]
  props.additionalServices.forEach((service) => {
    if (isServiceSelected(service.id)) {
      lines.push({ title: service.title, price: Number(service.price) || 0 })
    }
  })

  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
    const response = await fetch('/api/test-order', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken || '',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({
        full_name: name,
        phone: tel,
        email: email.value.trim() || null,
        birth_date: birthDate.value.trim() || null,
        lab: selectedLab.value?.name || null,
        agreed_to_terms: true,
        lines,
        quiz_answers: quiz?.quiz_answers || null,
        quiz_result_id: quiz?.quiz_result_id || null,
        quiz_result_title: quiz?.quiz_result_title || null,
      }),
    })
    if (!response.ok) throw new Error('save failed')
    router.visit('/login')
  } catch (error) {
    console.error(error)
    alert('Не удалось сохранить заявку. Попробуйте ещё раз.')
  }
}

// -------------------------------------------------------------
// МАСКИ ВВОДА
// -------------------------------------------------------------

const handleNameInput = (event) => {
  let val = event.target.value
  // Удаляем всё, кроме кириллицы, пробелов и дефиса
  val = val.replace(/[^а-яА-ЯёЁ\s-]/g, '')
  // Убираем множественные пробелы
  val = val.replace(/\s+/g, ' ')

  event.target.value = val
  fullName.value = val
}

// Телефон: Форматирование +7 (XXX) XXX-XX-XX
const handlePhoneInput = (event) => {
  let input = event.target.value.replace(/\D/g, '')

  if (!input) {
    phone.value = ''
    event.target.value = ''
    return
  }

  if (['7', '8'].includes(input[0])) {
    input = input.substring(1)
  }

  input = input.substring(0, 10)

  let formatted = '+7'
  if (input.length > 0) {
    formatted += ' (' + input.substring(0, 3)
  }
  if (input.length >= 4) {
    formatted += ') ' + input.substring(3, 6)
  }
  if (input.length >= 7) {
    formatted += '-' + input.substring(6, 8)
  }
  if (input.length >= 9) {
    formatted += '-' + input.substring(8, 10)
  }

  event.target.value = formatted
  phone.value = formatted
}

// Дата рождения: Форматирование ДД.ММ.ГГГГ
const handleBirthDateInput = (event) => {
  let input = event.target.value.replace(/\D/g, '').substring(0, 8)
  let formatted = ''

  if (input.length > 0) {
    formatted += input.substring(0, 2)
  }
  if (input.length >= 3) {
    formatted += '.' + input.substring(2, 4)
  }
  if (input.length >= 5) {
    formatted += '.' + input.substring(4, 8)
  }

  event.target.value = formatted
  birthDate.value = formatted
}

// E-mail: Запрет русской раскладки (кириллицы) и пробелов
const handleEmailInput = (event) => {
  let val = event.target.value
  // Вырезаем кириллицу и пробелы
  val = val.replace(/[а-яА-ЯёЁ\s]/g, '')

  event.target.value = val
  email.value = val
}
</script>

<template>
  <div class="page-layout">

    <div v-if="!showMapSelector" class="main-cart-screen">
      <header class="header-nav">
        <HomeBackLink stretched />
      </header>

      <div class="page-body">
        <div class="content-container">

          <main class="left-col">
            <section class="products-section">

              <!-- Товар 1: ALEX2 (Всегда добавлен) -->
              <div class="product-row product-row--added">
                <div class="product-thumb product-thumb--added desktop-only">
                  <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                    <circle cx="16" cy="16" r="14" stroke="#ffffff" stroke-width="1.5"/>
                    <path d="M10 16L14 20L22 12" stroke="#ffffff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                  <span class="product-thumb-label">Добавлено</span>
                </div>
                <div class="product-info">
                  <div class="product-header">
                    <div>
                      <div class="product-tags-top mobile-only">
                        <span v-for="(tag, idx) in (mainProduct?.tags || [])" :key="idx" class="tag tag--blue">{{ tag }}</span>
                      </div>
                      <h2 class="product-name">{{ mainProduct.title }}</h2>
                      <p class="product-desc">{{ mainProduct.description }}</p>
                      <div class="product-tags desktop-only">
                        <span v-for="(tag, idx) in (mainProduct?.tags || [])" :key="idx" class="tag tag--blue">{{ tag }}</span>
                      </div>
                    </div>
                    <span class="product-price desktop-only">{{ Number(mainProduct.price).toLocaleString('ru-RU') }}&thinsp;₽</span>
                  </div>

                  <div class="product-mobile-action mobile-only">
                    <div class="product-mobile-btn product-mobile-btn--added">
                      <span class="product-mobile-btn-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                          <circle cx="12" cy="12" r="10" stroke="#ffffff" stroke-width="2"/>
                          <path d="M9 12l2 2 4-4" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                      </span>
                      <span class="product-mobile-btn-price">{{ Number(mainProduct.price).toLocaleString('ru-RU') }}₽</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Динамические Дополнительные услуги -->
              <div
                  v-for="service in additionalServices"
                  :key="service.id"
                  class="product-row"
                  :class="{'product-row--added-border': isServiceSelected(service.id)}"
              >
                <div class="product-thumb desktop-only" :class="isServiceSelected(service.id) ? 'product-thumb--added' : 'product-thumb--add'" @click="toggleService(service.id)">
                  <template v-if="isServiceSelected(service.id)">
                    <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                      <circle cx="16" cy="16" r="14" stroke="#ffffff" stroke-width="1.5"/>
                      <path d="M10 16L14 20L22 12" stroke="#ffffff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span class="product-thumb-label">Добавлено</span>
                  </template>
                  <template v-else>
                    <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                      <circle cx="16" cy="16" r="14" stroke="#000000" stroke-width="1.5"/>
                      <path d="M16 10V22M10 16H22" stroke="#000000" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span class="product-thumb-label product-thumb-label--dark">Добавить</span>
                  </template>
                </div>
                <div class="product-info">
                  <div class="product-header">
                    <div>
                      <div class="product-tags-top mobile-only">
                        <span v-if="service.discount" class="tag tag--green">Скидка 99% в лаборатории ALEX</span>
                      </div>
                      <h2 class="product-name">{{ service.title }}</h2>
                      <p class="product-desc">{{ service.description }}</p>
                    </div>
                    <span class="product-price desktop-only">{{ Number(service.price).toLocaleString('ru-RU') }}&thinsp;₽</span>
                  </div>

                  <div class="product-mobile-action mobile-only">
                    <button
                        class="product-mobile-btn"
                        :class="{ 'product-mobile-btn--added': isServiceSelected(service.id) }"
                        @click="toggleService(service.id)"
                    >
                      <span class="product-mobile-btn-icon">
                        <svg v-if="isServiceSelected(service.id)" width="20" height="20" viewBox="0 0 24 24" fill="none">
                          <circle cx="12" cy="12" r="10" stroke="#ffffff" stroke-width="2"/>
                          <path d="M9 12l2 2 4-4" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none">
                          <circle cx="12" cy="12" r="10" stroke="#000000" stroke-width="2"/>
                          <path d="M12 8v8M8 12h8" stroke="#000000" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                      </span>
                      <span class="product-mobile-btn-price">{{ Number(service.price).toLocaleString('ru-RU') }}₽</span>
                    </button>
                  </div>
                </div>
              </div>

            </section>

            <!-- МЕСТО СДАЧИ АНАЛИЗА -->
            <section class="section-block">
              <h2 class="section-title">Место сдачи анализа</h2>
              <div v-if="!selectedLab" class="location-empty">
                <div class="field-group">
                  <label class="field-label">Выберите город</label>
                  <div class="select-field" @click="openMapSelector">
                    <span class="select-placeholder">Выберите из списка</span>
                    <span class="select-arrow">
                      <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="10" stroke="#ABABAB" stroke-width="2"/>
                        <path d="M10 8L14 12L10 16" stroke="#ABABAB" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                      </svg>
                    </span>
                  </div>
                </div>
              </div>
              <div v-else class="location-selected">
                <div class="location-card">
                  <div class="location-info">
                    <span v-if="selectedLab.discount" class="location-badge">-100% на консультацию</span>
                    <p class="location-name">{{ selectedLab.name }}</p>
                    <div class="location-schedule">
                      <span class="schedule-days">ПН – ПТ</span>
                      <span class="schedule-hours">{{ selectedLab.hoursWeekdays }}</span>
                    </div>
                    <div class="location-schedule">
                      <span class="schedule-days">СБ – ВС</span>
                      <span class="schedule-hours">{{ selectedLab.hoursWeekend }}</span>
                    </div>
                  </div>
                  <button class="change-btn" @click="openMapSelector">
                    <img src="/assets/figma-change-icon.svg" alt="" class="change-btn-icon" />
                    <span>Изменить</span>
                  </button>
                </div>
              </div>
            </section>

            <!-- ЛИЧНЫЕ ДАННЫЕ -->
            <section class="section-block">
              <h2 class="section-title">Личные данные</h2>
              <form class="personal-form" @submit.prevent>
                <div class="form-row-two">

                  <!-- ФИО -->
                  <div class="field-group">
                    <label class="field-label field-label--md">ФИО</label>
                    <input
                        type="text"
                        :value="fullName"
                        @input="handleNameInput"
                        class="field-input"
                        placeholder="Иванов Иван Иванович"
                        maxlength="100"
                    />
                  </div>

                  <!-- Дата рождения -->
                  <div class="field-group">
                    <label class="field-label field-label--md">Дата рождения</label>
                    <input
                        type="text"
                        :value="birthDate"
                        @input="handleBirthDateInput"
                        class="field-input"
                        placeholder="ДД.ММ.ГГГГ"
                        maxlength="10"
                    />
                  </div>

                </div>

                <!-- Номер телефона -->
                <div class="field-group field-group--full">
                  <label class="field-label field-label--md">Номер телефона</label>
                  <input
                      type="tel"
                      :value="phone"
                      @input="handlePhoneInput"
                      class="field-input"
                      placeholder="+7 (999) 999-99-99"
                      maxlength="18"
                  />
                </div>

                <!-- E-mail -->
                <div class="field-group field-group--full">
                  <label class="field-label field-label--md">E-mail</label>
                  <input
                      type="email"
                      :value="email"
                      @input="handleEmailInput"
                      class="field-input"
                      placeholder="max@inmuntex.com"
                      maxlength="255"
                  />
                  <div class="field-info">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                      <circle cx="10" cy="10" r="8.5" stroke="#000000" stroke-width="1.2"/>
                      <path d="M10 7V10" stroke="#000000" stroke-width="1.2" stroke-linecap="round"/>
                      <circle cx="10" cy="13" r="0.8" fill="#000000"/>
                    </svg>
                    <span class="field-info-text">На указанную почту придут данные от личного кабинета. В нём можно онлайн отслеживать результаты.</span>
                  </div>
                </div>
              </form>
            </section>
          </main>

          <!-- RIGHT COLUMN (Сводка заказа) -->
          <aside class="right-col">
            <h2 class="section-title mobile-only mobile-summary-title">Итого</h2>
            <div class="summary-card-body">

              <div class="summary-block">
                <h3 class="summary-label">Место</h3>
                <div v-if="!selectedLab">
                  <p class="summary-value">Не выбрано</p>
                </div>
                <div v-else>
                  <span v-if="selectedLab.discount" class="summary-badge">-100% на консультацию</span>
                  <p class="summary-value summary-value--location">{{ selectedLab.name }}</p>
                  <div class="summary-schedule">
                    <div class="summary-schedule-row">
                      <span class="summary-schedule-days">ПН – ПТ</span>
                      <span>{{ selectedLab.hoursWeekdays }}</span>
                    </div>
                    <div class="summary-schedule-row">
                      <span class="summary-schedule-days">СБ – ВС</span>
                      <span>{{ selectedLab.hoursWeekend }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="summary-block">
                <h3 class="summary-label summary-label--sm">Время на результат</h3>
                <p class="summary-value summary-value--big">3 дня</p>
              </div>

              <div class="summary-block">
                <h3 class="summary-label summary-label--sm">Комплектация</h3>
                <div class="summary-items">
                  <div class="summary-item">
                    <span class="summary-item-name">{{ mainProduct.title }}</span>
                    <span class="summary-item-price">{{ Number(mainProduct.price).toLocaleString('ru-RU') }}&thinsp;₽</span>
                  </div>

                  <!-- Динамические услуги в чеке -->
                  <div
                      v-for="(service, index) in (additionalServices || [])"
                      :key="service?.id || `service-${index}`"
                      class="product-row"
                      :class="{'product-row--added-border': isServiceSelected(service?.id)}"
                  >
                    <span class="summary-item-name">{{ service.title }}</span>
                    <span class="summary-item-price">{{ Number(service.price).toLocaleString('ru-RU') }}&thinsp;₽</span>
                  </div>

                  <div class="summary-item">
                    <span class="summary-item-name">Забор крови</span>
                    <span class="summary-item-price">0₽</span>
                  </div>
                  <div class="summary-item">
                    <span class="summary-item-name">Общий IgE</span>
                    <span class="summary-item-price">0₽</span>
                  </div>
                </div>
              </div>

              <div class="promo-row">
                <div class="promo-input-wrap">
                  <input type="text" v-model="promo" class="promo-input" placeholder="Введите промокод"/>
                </div>
                <button class="promo-btn" @click="applyPromo">
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="#000000" stroke-width="2"/>
                    <path d="M10 8L14 12L10 16" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </button>
              </div>
            </div>

            <div class="total-block">
              <div class="total-inner">
                <div class="total-price-group">
                  <span class="total-label">Итого к оплате</span>
                  <span class="total-price">{{ formattedTotalPrice }}₽</span>
                </div>
                <label class="consent-row">
                  <input type="checkbox" v-model="consent" class="consent-checkbox"/>
                  <span class="consent-text">Я даю <span class="consent-link">согласие</span> на передачу моих персональных данных с целью осуществления доставки (возврата) товара третьим лицам</span>
                </label>
              </div>

              <div class="continue-btn-wrapper">
                <button class="continue-btn" :disabled="!consent" @click="handleContinue">
                  <span>Продолжить</span>
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="#ffffff" stroke-width="2"/>
                    <path d="M10 8L14 12L10 16" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </button>
              </div>
            </div>
          </aside>
        </div>
      </div>
    </div>

    <!-- ───────────────────── ЭКРАН ВЫБОРА ЛАБОРАТОРИИ (КАРТА) ───────────────────── -->
    <!-- (Здесь остается ваш оригинальный код карты без изменений, так как логика карты не менялась) -->
    <div v-else class="map-selector-screen" :class="{ 'map-selector-screen--mobile-map': showMapOnMobile }">
      <header class="header-nav">
        <button class="back-link" @click="handleMapBack">
          <span class="back-arrow">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
              <path d="M15 18L9 12L15 6" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
          <span class="back-label">Назад</span>
        </button>
      </header>
      <div class="map-selector-body">

        <!-- САЙДБАР (Слева) -->
        <aside class="map-sidebar" :class="{ 'map-sidebar--hidden': showMapOnMobile }">

          <!-- ШАГ 1: Выбор города -->
          <div v-if="mapStep === 1" class="map-step-1">
            <h2 class="map-sidebar-title map-sidebar-title--step1">Выберите город</h2>
            <div class="field-group">
              <div class="city-select-dropdown" @click="showCityList = !showCityList">
                <span class="selected-city-name" :class="{ 'selected-city-name--placeholder': !selectedCity }">{{ selectedCity || 'Выберите из списка' }}</span>
                <span class="select-arrow" :class="{ 'select-arrow--open': showCityList }">
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M6 9L12 15L18 9" stroke="#ABABAB" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </span>
              </div>

              <!-- Список городов -->
              <div v-if="showCityList" class="city-options-list">
                <div v-for="city in cities" :key="city.name" class="city-option" @click="selectCity(city.name)">
                  {{ city.name }}
                </div>
              </div>
            </div>
          </div>

          <!-- ШАГ 2: Список лабораторий города -->
          <div v-else class="map-step-2" :class="{ 'map-step-2--detailed': detailedLabId }">
            <div class="city-header-row">
              <span class="city-badge">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="1.5">
                  <path d="M12 2a8 8 0 0 0-8 8c0 5.25 8 12 8 12s8-6.75 8-12a8 8 0 0 0-8-8z"/>
                  <circle cx="12" cy="10" r="3"/>
                </svg>
                {{ selectedCity }}
                <span v-if="detailedLabId" class="lab-count">584 точек</span>
              </span>
              <button v-if="!detailedLabId" class="change-city-btn" @click="changeCity">Изменить город</button>
            </div>

            <h2 class="map-sidebar-title">Выберите лабораторию</h2>

            <!-- Зеленый инфо-блок о бесплатной консультации -->
            <div class="promo-alert-card">
              <h4 class="promo-alert-title">Бесплатная консультация в лабораториях ALEX</h4>
              <p class="promo-alert-desc">Выберите точку ALEX как место сдачи крови и скидка автоматически появится в корзине</p>
            </div>

            <!-- Список лабораторий -->
            <div class="labs-list">
              <div
                  v-for="lab in filteredLabs"
                  :key="lab.id"
                  class="lab-card"
                  :class="{
                  'lab-card--active': activeLabId === lab.id,
                  'lab-card--detailed': detailedLabId === lab.id
                }"
                  @click="clickLabCard(lab)"
              >
                <div class="lab-card-content">
                  <span v-if="lab.discount" class="lab-card-badge">-100% на консультацию</span>
                  <h3 class="lab-card-name">{{ lab.name }}</h3>

                  <div class="lab-card-schedule">
                    <div class="schedule-row">
                      <span class="schedule-days">ПН – ПТ</span>
                      <span class="schedule-hours">{{ lab.hoursWeekdays }}</span>
                    </div>
                    <div class="schedule-row">
                      <span class="schedule-days">СБ – ВС</span>
                      <span class="schedule-hours">{{ lab.hoursWeekend }}</span>
                    </div>
                  </div>
                </div>

                <!-- Блок детального выбора (Шаг 3) на десктопе -->
                <div v-if="detailedLabId === lab.id" class="lab-card-action desktop-only">
                  <button class="select-lab-btn" @click.stop="confirmLabSelection(lab)">
                    <span>Выбрать</span>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                      <circle cx="12" cy="12" r="10" stroke="#ffffff" stroke-width="2"/>
                      <path d="M10 8L14 12L10 16" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </button>
                </div>
              </div>
            </div>

            <!-- Мобильная кнопка «Выбрать на карте» -->
            <button class="mobile-map-trigger-btn" @click="showMapOnMobile = true">
              <span>Выбрать на карте</span>
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="#ffffff" stroke-width="2"/>
                <path d="M10 8L14 12L10 16" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>

          </div>

        </aside>

        <!-- ИНТЕРАКТИВНАЯ КАРТА (Справа / Полноэкранная на мобильных) -->
        <main class="map-view-container" :class="{ 'map-view-container--visible': showMapOnMobile || isDesktop }">

          <!-- КАРТА РОССИИ (Шаг 1) -->
          <div v-if="mapStep === 1" class="russia-map-layer">
            <svg class="map-svg" viewBox="0 0 1000 600" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M50 150 C150 100, 300 80, 500 120 C650 150, 800 100, 950 130 C980 200, 900 350, 850 400 C750 430, 600 480, 450 450 C300 430, 150 500, 80 400 C30 350, 20 200, 50 150 Z" fill="#e8ebf0" stroke="#cdd3de" stroke-width="2"/>
              <path d="M120 280 C180 250, 240 290, 280 240 C320 200, 380 240, 450 200" stroke="#cdd3de" stroke-width="1.5" stroke-dasharray="4 4"/>

              <g
                  v-for="city in cities"
                  :key="city.name"
                  class="map-city-marker"
                  @click="selectCity(city.name)"
              >
                <circle :cx="city.x" :cy="city.y" r="8" fill="#000000" class="marker-pulse"/>
                <circle :cx="city.x" :cy="city.y" r="4" fill="#ffffff"/>
                <text :x="city.x" :y="city.y - 12" text-anchor="middle" class="map-city-label">{{ city.name }}</text>
              </g>
            </svg>
          </div>

          <!-- КАРТА МОСКВЫ (Шаг 2 & 3) -->
          <div v-else class="moscow-map-layer" :class="{ 'moscow-map-layer--zoomed': mapStep === 3 || detailedLabId }">
            <svg
                class="map-svg map-svg--moscow"
                :viewBox="mapViewport"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
              <path d="M-100 200 L1100 200 M-100 500 L1100 500 M300 -100 L300 700 M700 -100 L700 700" stroke="#e0e4ec" stroke-width="3"/>
              <path d="M0 0 L1000 600 M1000 0 L0 600" stroke="#e0e4ec" stroke-width="2"/>
              <circle cx="500" cy="300" r="180" stroke="#e0e4ec" stroke-width="4" fill="none"/>
              <circle cx="500" cy="300" r="320" stroke="#e8ecf3" stroke-width="6" fill="none"/>

              <path d="M-100 100 Q 200 80, 350 250 T 650 350 T 1100 250" stroke="#d4e2f8" stroke-width="32" stroke-linecap="round" fill="none"/>
              <path d="M-100 100 Q 200 80, 350 250 T 650 350 T 1100 250" stroke="#c0d6f6" stroke-width="20" stroke-linecap="round" fill="none"/>

              <!-- Точки лабораторий -->
              <g
                  v-for="lab in filteredLabs"
                  :key="lab.id"
                  class="map-lab-marker"
                  :class="{
                  'map-lab-marker--active': activeLabId === lab.id,
                  'map-lab-marker--detailed': detailedLabId === lab.id
                }"
                  @click="clickMapMarker(lab)"
              >
                <template v-if="detailedLabId === lab.id">
                  <rect
                      :x="isDesktop ? markerX(lab) - 21.25 : markerX(lab) - 27"
                      :y="isDesktop ? markerY(lab) - 15.25 : markerY(lab) - 129"
                      :width="isDesktop ? 18.5 : 54"
                      :height="isDesktop ? 18.5 : 54"
                      :rx="isDesktop ? 4 : 12"
                      fill="#000000"
                  />
                </template>
                <template v-else>
                  <rect :x="markerX(lab) - 8" :y="markerY(lab) - 8" width="16" height="16" rx="4" fill="#000000"/>
                  <circle v-if="lab.discount" :cx="markerX(lab) + 8" :cy="markerY(lab) - 8" r="8" fill="#1a8a4a"/>
                  <text v-if="lab.discount" :x="markerX(lab) + 8" :y="markerY(lab) - 5" text-anchor="middle" fill="#ffffff" font-size="7" font-weight="700">%</text>
                </template>
              </g>

              <g class="map-marker-decor mobile-map-marker-decor" aria-hidden="true">
                <rect x="149" y="116" width="16" height="16" rx="4" fill="#000000"/>
              </g>
              <g class="map-marker-decor mobile-map-marker-decor" aria-hidden="true">
                <rect x="875" y="30" width="16" height="16" rx="4" fill="#000000"/>
              </g>

              <g class="desktop-map-marker-decor" aria-hidden="true">
                <rect x="40" y="140" width="16" height="16" rx="4" fill="#000000"/>
                <rect x="176" y="133" width="16" height="16" rx="4" fill="#000000"/>
                <rect x="627" y="192" width="16" height="16" rx="4" fill="#000000"/>
                <rect x="457" y="248" width="16" height="16" rx="4" fill="#000000"/>
                <rect x="263" y="313" width="16" height="16" rx="4" fill="#000000"/>
                <rect x="257" y="545" width="16" height="16" rx="4" fill="#000000"/>
                <rect x="490" y="545" width="16" height="16" rx="4" fill="#000000"/>
              </g>
            </svg>
          </div>

          <!-- МОБИЛЬНАЯ ВЫДВИЖНАЯ ПЛАШКА ИНФОРМАЦИИ (Bottom Sheet) -->
          <div
              class="mobile-lab-sheet"
              :class="{ 'mobile-lab-sheet--open': showMapOnMobile && detailedLabId }"
          >
            <div v-if="selectedMobileLab" class="mobile-sheet-content">
              <!-- Кнопка Закрыть (крестик) -->
              <button class="close-sheet-btn" @click="closeMobileSheet">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                  <path d="M18 6L6 18M6 6l12 12" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>

              <span v-if="selectedMobileLab.discount" class="lab-card-badge">-100% на консультацию</span>
              <h3 class="mobile-sheet-title">{{ selectedMobileLab.name }}</h3>

              <div class="mobile-sheet-schedule">
                <div class="schedule-row">
                  <span class="schedule-days">ПН – ПТ</span>
                  <span class="schedule-hours">{{ selectedMobileLab.hoursWeekdays }}</span>
                </div>
                <div class="schedule-row">
                  <span class="schedule-days">СБ – ВС</span>
                  <span class="schedule-hours">{{ selectedMobileLab.hoursWeekend }}</span>
                </div>
              </div>

              <!-- Кнопка Выбрать -->
              <button class="mobile-select-confirm-btn" @click="confirmLabSelection(selectedMobileLab)">
                <span>Выбрать</span>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                  <circle cx="12" cy="12" r="10" stroke="#ffffff" stroke-width="2"/>
                  <path d="M10 8L14 12L10 16" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </button>
            </div>
          </div>

        </main>

      </div>
    </div>
  </div>


</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap');

*, *::before, *::after {
  box-sizing: border-box;
}

/* ─── Вспомогательные классы скрытия/показа ─── */
.desktop-only {
  display: block;
}
.mobile-only {
  display: none !important;
}

@media (max-width: 900px) {
  .desktop-only {
    display: none !important;
  }
  .mobile-only {
    display: block !important;
  }
}

/* ─── LAYOUT ─── */
.page-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background-color: #f5f5f5;
  font-family: 'Roboto', sans-serif;
}

/* HEADER: 72px */
.header-nav {
  width: 100%;
  height: 72px;
  background-color: #ffffff;
  border-bottom: 1px solid #dfdfdf;
  display: flex;
  align-items: center;
  padding: 24px 32px;
  position: sticky;
  top: 0;
  z-index: 100;
  flex-shrink: 0;
}

.back-link {
  display: flex;
  align-items: center;
  gap: 8px;
  font-family: 'Roboto', Arial, sans-serif;
  font-size: 18px;
  line-height: 24px;
  font-weight: 400;
  color: #000000;
  text-decoration: none;
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
}
.back-link:hover { opacity: 0.7; }

.back-arrow {
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

/* ───────────────────── MAIN CART SCREEN ───────────────────── */
.main-cart-screen {
  display: flex;
  flex-direction: column;
  flex: 1;
}

.page-body {
  flex: 1;
  display: flex;
  justify-content: center;
  width: 100%;
  background-color: #f5f5f5;
}

.content-container {
  display: flex;
  width: 100%;
  max-width: 1920px;
  height: auto;
  min-height: 100%;
  position: relative;
  overflow: visible;
  padding: 0 !important;
}

.left-col {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 84px;
  padding: 106px 64px 80px 64px;
}

.right-col {
  width: 610px;
  flex-shrink: 0;
  border-left: 1px solid #dfdfdf;
  background-color: #ffffff;
  display: flex;
  flex-direction: column;
  position: sticky;
  top: 72px;
  height: calc(100vh - 72px);
  overflow-y: auto;
}

/* ───────────────────── PRODUCTS SECTION (Товары) ───────────────────── */
.products-section {
  display: flex;
  flex-direction: column;
  background-color: #ffffff;
  border: 1px solid #dfdfdf;
}

.product-row {
  display: flex;
  align-items: stretch;
  border-bottom: 1px solid #dfdfdf;
}
.product-row:last-child {
  border-bottom: none;
}

.product-thumb {
  width: 204px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 24px 32px;
  border-right: 1px solid #dfdfdf;
  user-select: none;
}

.product-thumb--added {
  background-color: #000000;
}

.product-thumb--add {
  background-color: #ffffff;
  cursor: pointer;
  transition: background-color 0.2s;
}
.product-thumb--add:hover {
  background-color: #f5f5f5;
}

.product-thumb-label {
  font-size: 16px;
  font-weight: 400;
  color: #ffffff;
  font-family: 'Roboto', sans-serif;
  text-align: center;
}
.product-thumb-label--dark {
  color: #000000;
}

.product-info {
  flex: 1;
  padding: 32px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.product-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 24px;
}

.product-name {
  font-family: 'Roboto', sans-serif;
  font-size: 24px;
  font-weight: 400;
  color: #000000;
  margin: 0 0 8px 0;
  line-height: 1.2;
}

.product-desc {
  font-family: 'Roboto', sans-serif;
  font-size: 16px;
  font-weight: 400;
  color: #000000;
  opacity: 0.5;
  margin: 0 0 16px 0;
  line-height: 1.5;
}

.product-price {
  font-family: 'Roboto', sans-serif;
  font-size: 24px;
  font-weight: 400;
  color: #000000;
  white-space: nowrap;
  flex-shrink: 0;
}

/* Теги */
.product-tags, .product-tags-top {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.product-tags-top {
  margin-bottom: 12px;
}

.tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-family: 'Roboto', sans-serif;
  font-size: 14px;
  font-weight: 400;
  padding: 4px 12px;
  border-radius: 100px;
}

.tag--blue {
  background-color: #f1faff;
  color: #2baac9;
  border: none;
}

.tag--green {
  background-color: #f1fff3;
  color: #0f5e1a;
  border: none;
}

.tag-icon {
  flex-shrink: 0;
}

/* Мобильная кнопка товара */
.product-mobile-action {
  width: 100%;
  margin-top: 16px;
}

.product-mobile-btn {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  height: 61px;
  background-color: #ffffff;
  border: 1px solid #dddddd;
  padding: 0 24px;
  font-family: 'Roboto', sans-serif;
  font-size: 18px;
  font-weight: 400;
  color: #000000;
  cursor: pointer;
  transition: background-color 0.2s, border-color 0.2s;
  outline: none;
}

.product-mobile-btn--added {
  background-color: #000000;
  border-color: #000000;
  color: #ffffff;
}

.product-mobile-btn-icon {
  display: flex;
  align-items: center;
  justify-content: center;
}

.product-mobile-btn-price {
  font-weight: 500;
}

/* ─── SECTION BLOCK ─── */
.section-block {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.section-title {
  font-family: 'Roboto', sans-serif;
  font-size: 42px;
  font-weight: 400;
  color: #000000;
  margin: 0;
  line-height: 1.166;
  text-shadow: none;
}

/* ─── LOCATION ─── */
.select-field {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  height: 61px;
  border: none;
  border-bottom: 1px solid #dddddd;
  background: transparent;
  cursor: pointer;
  transition: border-color 0.2s;
}
.select-field:hover {
  border-bottom-color: #000000;
}

.select-placeholder {
  font-size: 18px;
  font-weight: 400;
  color: rgba(0, 0, 0, 0.3);
}

.select-arrow {
  display: flex;
  align-items: center;
}

.location-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #ffffff;
  border: 1px solid #dfdfdf;
  padding: 24px;
  gap: 24px;
}

.location-info {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.location-badge {
  display: inline-block;
  font-size: 14px;
  font-weight: 400;
  color: #0f5e1a;
  background: #f1fff3;
  padding: 2px 10px;
  border-radius: 100px;
  width: fit-content;
}

.location-name {
  font-size: 24px;
  font-weight: 400;
  color: #000000;
  margin: 0;
  font-family: 'Roboto', sans-serif;
}

.location-schedule {
  display: flex;
  gap: 16px;
  font-size: 16px;
  font-weight: 400;
  color: #000000;
  opacity: 0.6;
}

.schedule-days {
  min-width: 70px;
}

.change-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  background: none;
  border: none;
  cursor: pointer;
  font-size: 18px;
  font-weight: 400;
  color: #000000;
  font-family: 'Roboto', sans-serif;
  padding: 12px 24px;
  flex-shrink: 0;
  transition: opacity 0.2s;
}
.change-btn:hover { opacity: 0.7; }

/* ─── PERSONAL FORM ─── */
.personal-form {
  display: flex;
  flex-direction: column;
  gap: 48px;
}

.form-row-two {
  display: flex;
  gap: 8px;
}

.form-row-two .field-group {
  flex: 1;
  min-width: 0;
}

.field-group--full {
  width: 100%;
}

.field-label {
  font-family: 'Roboto', sans-serif;
  font-size: 21px;
  font-weight: 400;
  color: #000000;
  margin-bottom: 12px;
  display: block;
}

.field-label--md {
  font-size: 16px;
  font-weight: 400;
  color: #000000;
}

.field-input {
  display: block;
  width: 100%;
  height: 61px;
  border: none;
  border-bottom: 1px solid #dddddd;
  background: transparent;
  padding: 0;
  font-family: 'Roboto', sans-serif;
  font-size: 18px;
  font-weight: 400;
  color: #000000;
  outline: none;
  transition: border-color 0.2s;
}

.field-input::placeholder {
  color: #000000;
  opacity: 0.4;
}

.field-input:focus {
  border-bottom-color: #000000;
}

.field-info {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-top: 16px;
}

.field-info svg {
  flex-shrink: 0;
}

.field-info-text {
  font-size: 18px;
  font-weight: 400;
  color: rgba(0, 0, 0, 0.6);
  line-height: 1.4;
}

/* ─── SUMMARY BLOCK (Сводка) ─── */
.summary-block {
  padding: 32px;
  border-bottom: 1px solid #dfdfdf;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.summary-label {
  font-family: 'Roboto', sans-serif;
  font-size: 21px;
  font-weight: 400;
  color: rgba(0, 0, 0, 0.5);
  margin: 0 0 12px 0;
  line-height: 20px;
}

.summary-label--sm {
  font-size: 16px;
}

.summary-value {
  font-family: 'Roboto', sans-serif;
  font-size: 24px;
  font-weight: 400;
  color: #000000;
  margin: 0;
}

.summary-value--muted {
  opacity: 0.4;
}

.summary-value--location {
  font-size: 24px;
  line-height: 1.25;
}

.summary-value--big {
  font-size: 24px;
}

.summary-badge {
  display: inline-block;
  font-size: 14px;
  font-weight: 400;
  color: #0f5e1a;
  background: #f1fff3;
  padding: 2px 8px;
  border-radius: 100px;
  font-family: 'Roboto', sans-serif;
  margin-bottom: 4px;
}

.summary-schedule {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 16px;
  color: #000000;
  opacity: 0.5;
}

.summary-schedule-row {
  display: flex;
  gap: 16px;
}

.summary-schedule-days {
  min-width: 60px;
}

.summary-items {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.summary-item {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  font-family: 'Roboto', sans-serif;
  font-size: 24px;
  font-weight: 400;
  color: #000000;
}

/* ─── PROMO ─── */
.promo-row {
  display: flex;
  border-top: 1px solid #dfdfdf;
  border-bottom: 1px solid #dfdfdf;
  flex-shrink: 0;
  background: #f8f8f8;
}

.promo-input-wrap {
  flex: 1;
}

.promo-input {
  width: 100%;
  height: 61px;
  border: none;
  background: #f8f8f8;
  padding: 0 24px;
  font-family: 'Roboto', sans-serif;
  font-size: 18px;
  font-weight: 400;
  color: #000000;
  outline: none;
}

.promo-input::placeholder {
  color: #000000;
  opacity: 0.2;
}

.promo-btn {
  width: 62px;
  height: 61px;
  background: #f8f8f8;
  border: none;
  border-left: 1px solid #dfdfdf;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
  transition: background 0.2s;
}
.promo-btn:hover {
  background: #eeeeee;
}

/* ─── TOTAL ─── */
.total-block {
  margin-top: auto;
  display: flex;
  flex-direction: column;
}

.total-inner {
  padding: 32px;
  background: #ffffff;
  border-top: 1px solid #dfdfdf;
  display: flex;
  flex-direction: column;
  gap: 32px;
}

.total-price-group {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.total-label {
  font-family: 'Roboto', sans-serif;
  font-size: 16px;
  font-weight: 400;
  color: #000000;
  opacity: 0.5;
}

.total-price {
  font-family: 'Roboto', sans-serif;
  font-size: 42px;
  font-weight: 400;
  color: #000000;
  line-height: 1.166;
}

.consent-row {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  cursor: pointer;
}

.consent-checkbox {
  width: 24px;
  height: 24px;
  flex-shrink: 0;
  margin: 0;
  margin-top: 2px;
  cursor: pointer;
  accent-color: #000000;
}

.consent-text {
  font-family: 'Roboto', sans-serif;
  font-size: 16px;
  font-weight: 400;
  color: #000000;
  line-height: 1.5;
}

.consent-link {
  text-decoration: underline;
  cursor: pointer;
}

.continue-btn-wrapper {
  width: 100%;
}

.continue-btn {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  height: 72px;
  background-color: #000000;
  color: #ffffff;
  border: none;
  padding: 0 32px;
  font-family: 'Roboto', sans-serif;
  font-size: 18px;
  font-weight: 400;
  cursor: pointer;
  transition: opacity 0.2s;
  flex-shrink: 0;
}
.continue-btn:hover { opacity: 0.9; }
.continue-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.right-col::-webkit-scrollbar {
  width: 4px;
}
.right-col::-webkit-scrollbar-thumb {
  background: #dddddd;
  border-radius: 2px;
}

/* ───────────────────── MAP SELECTOR SCREEN (ЭКРАН КАРТЫ) ───────────────────── */
.map-selector-screen {
  display: flex;
  flex-direction: column;
  height: 100vh;
  background-color: #ffffff;
  overflow: hidden;
}

.map-selector-body {
  display: flex;
  flex: 1;
  min-height: 0;
  position: relative;
}

/* Левый сайдбар на карте */
.map-sidebar {
  width: 624px;
  flex-shrink: 0;
  border-right: 1px solid #dfdfdf;
  padding: 64px 48px;
  display: flex;
  flex-direction: column;
  gap: 32px;
  overflow-y: auto;
  background-color: #ffffff;
  z-index: 10;
}

.map-sidebar-title {
  font-family: 'Roboto', sans-serif;
  font-size: 42px;
  font-weight: 400;
  color: #000000;
  margin: 0;
}

.map-sidebar-title--step1 {
  font-size: 21px;
  margin-bottom: 12px;
}

/* Шаг 1: Выбор города */
.city-select-dropdown {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  height: 61px;
  border: none;
  border-bottom: 1px solid #000000;
  background: transparent;
  cursor: pointer;
  transition: border-color 0.2s;
}
.city-select-dropdown:hover {
  border-bottom-color: #000000;
}

.selected-city-name {
  font-size: 18px;
  font-weight: 400;
  color: #000000;
}

.selected-city-name--placeholder {
  color: rgba(0, 0, 0, 0.3);
}

.city-options-list {
  border: 1px solid #dddddd;
  border-top: none;
  background: #ffffff;
  max-height: 250px;
  overflow-y: auto;
}

.city-option {
  padding: 16px 24px;
  font-size: 18px;
  cursor: pointer;
  transition: background-color 0.2s;
}
.city-option:hover {
  background-color: #f5f5f5;
}

/* Шаг 2: Список лабораторий */
.city-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid #dfdfdf;
  padding-bottom: 16px;
}

.city-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 18px;
  font-weight: 500;
  color: #000000;
}

.change-city-btn {
  background: none;
  border: none;
  font-size: 16px;
  color: #000000;
  opacity: 0.5;
  text-decoration: underline;
  cursor: pointer;
}
.change-city-btn:hover {
  opacity: 0.8;
}

.promo-alert-card {
  background-color: #e8f5ee;
  border-radius: 0;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.promo-alert-title {
  font-size: 18px;
  font-weight: 500;
  color: #1a8a4a;
  margin: 0;
}

.promo-alert-desc {
  font-size: 15px;
  color: #1a8a4a;
  margin: 0;
  line-height: 1.45;
}

.labs-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.lab-card {
  border: 1px solid #dfdfdf;
  background-color: #ffffff;
  cursor: pointer;
  transition: border-color 0.2s, box-shadow 0.2s;
  display: flex;
  flex-direction: column;
}
.lab-card:hover {
  border-color: #000000;
}

.lab-card--active {
  border-color: #000000;
}

.lab-card-content {
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.lab-card-badge {
  display: inline-block;
  font-size: 14px;
  font-weight: 400;
  color: #0f5e1a;
  background: #f1fff3;
  padding: 2px 8px;
  border-radius: 100px;
  width: fit-content;
}

.lab-card-name {
  font-size: 21px;
  font-weight: 400;
  color: #000000;
  margin: 0;
}

.lab-card-schedule {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 15px;
  color: #000000;
  opacity: 0.6;
}

.lab-card-schedule .schedule-row {
  display: flex;
}

.lab-card-schedule .schedule-days {
  width: 80px;
}

.select-lab-btn {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  height: 61px;
  background-color: #000000;
  color: #ffffff;
  border: none;
  padding: 0 24px;
  font-size: 17px;
  font-family: 'Roboto', sans-serif;
  cursor: pointer;
  transition: opacity 0.2s;
}
.select-lab-btn:hover {
  opacity: 0.9;
}

/* Мобильная кнопка на карте */
.mobile-map-trigger-btn {
  display: none;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  height: 72px;
  background-color: #000000;
  color: #ffffff;
  border: none;
  padding: 0 24px;
  font-size: 18px;
  font-family: 'Roboto', sans-serif;
  cursor: pointer;
  margin-top: 24px;
}

/* КАРТА CONTAINER */
.map-view-container {
  flex: 1;
  background-color: #f0f3f8;
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}

.map-svg {
  width: 100%;
  height: 100%;
  display: block;
}

.map-svg--moscow {
  transition: viewBox 0.4s ease-in-out;
}

.map-city-marker {
  cursor: pointer;
}

.map-city-marker circle {
  transition: transform 0.2s, fill 0.2s;
}

.map-city-marker:hover circle {
  fill: #ff0000;
}

.marker-pulse {
  animation: pulse 2s infinite;
}

.map-city-label {
  font-family: 'Roboto', sans-serif;
  font-size: 12px;
  font-weight: 500;
  fill: #333333;
  pointer-events: none;
}

.map-lab-marker {
  cursor: pointer;
}

.map-lab-marker circle, .map-lab-marker rect {
  transition: r 0.2s, fill 0.2s, transform 0.2s;
}

.map-lab-marker:hover circle {
  fill: #ff0000;
}

.map-lab-marker--detailed {
  z-index: 100;
}

@keyframes pulse {
  0% { r: 6; opacity: 1; }
  50% { r: 12; opacity: 0.4; }
  100% { r: 6; opacity: 1; }
}

/* MOBILE BOTTOM SHEET */
.mobile-lab-sheet {
  display: none;
  position: absolute;
  bottom: 0;
  left: 0;
  width: 100%;
  background: #ffffff;
  box-shadow: 0 -4px 24px rgba(0, 0, 0, 0.1);
  z-index: 200;
  transform: translateY(100%);
  transition: transform 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}

.mobile-lab-sheet--open {
  transform: translateY(0);
}

.mobile-sheet-content {
  padding: 32px 24px 24px 24px;
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.close-sheet-btn {
  position: absolute;
  top: 16px;
  right: 16px;
  background: none;
  border: none;
  cursor: pointer;
  padding: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.mobile-sheet-title {
  font-size: 21px;
  font-weight: 500;
  color: #000000;
  margin: 0;
  font-family: 'Roboto', sans-serif;
  max-width: 90%;
}

.mobile-sheet-schedule {
  display: flex;
  flex-direction: column;
  gap: 6px;
  font-size: 16px;
  color: #000000;
  opacity: 0.6;
}

.mobile-sheet-schedule .schedule-row {
  display: flex;
}

.mobile-sheet-schedule .schedule-days {
  width: 80px;
}

.mobile-select-confirm-btn {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  height: 61px;
  background-color: #000000;
  color: #ffffff;
  border: none;
  padding: 0 24px;
  font-size: 18px;
  font-family: 'Roboto', sans-serif;
  cursor: pointer;
  margin-top: 8px;
}

/* ───────────────────── RESPONSIVE (MOBILE АДАПТАЦИЯ) ───────────────────── */
@media (max-width: 900px) {

  .desktop-only {
    display: none !important;
  }
  .mobile-only {
    display: block !important;
  }

  .page-layout {
    height: 100dvh !important;
    max-height: 100dvh !important;
    overflow-x: hidden !important;
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch;
  }

  /* Поток главной страницы */
  .page-body {
    flex-direction: column;
  }

  .content-container {
    flex-direction: column;
  }

  .left-col {
    padding: 32px 16px; /* Отступы по бокам 16px на мобильных */
    gap: 48px;
  }

  /* Товары как отдельные белые карточки */
  .products-section {
    background: transparent;
    border: none;
    gap: 16px;
  }

  .product-row {
    flex-direction: column;
    background: #ffffff;
    border: 1px solid #dfdfdf;
    padding: 32px 24px 24px 24px;
    gap: 16px;
  }

  .product-row--added-border {
    border-color: #000000; /* Подсветка границы выбранного на мобильном */
  }

  .product-info {
    padding: 0;
  }

  .product-name {
    font-size: 24px;
    line-height: 1.25;
    margin-bottom: 8px;
  }

  .product-desc {
    font-size: 16px;
    line-height: 1.45;
    margin-bottom: 0;
  }

  /* Правая колонка переезжает вниз за левой колонкой */
  .right-col {
    width: 100%;
    position: static;
    height: auto;
    border-left: none;
    border-top: none;
    background-color: transparent;
    overflow: visible;
  }

  .mobile-summary-title {
    padding: 32px 0 16px 0;
    margin: 0;
    font-size: 32px;
    font-weight: 400;
    color: #000000;
  }

  .summary-card-body {
    background-color: #ffffff;
    border: 1px solid #dfdfdf;
    display: flex;
    flex-direction: column;
  }

  /* Переопределение отступов сводки на мобильном */
  .summary-block {
    padding: 24px 16px;
    background-color: #ffffff;
    border-bottom: 1px solid #dfdfdf;
  }

  .promo-row {
    border-top: 1px solid #dfdfdf;
    border-bottom: 1px solid #dfdfdf;
  }

  .location-card {
    display: flex;
    flex-direction: column;
    padding: 24px;
    gap: 24px;
    background: #ffffff;
    border: 1px solid #dfdfdf;
  }

  .location-info {
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
  }

  .change-btn {
    width: 100%;
    height: 64px;
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: flex-start;
    gap: 12px;
    padding: 16px 24px;
    background: #ffffff;
    border: 1px solid #dfdfdf;
    border-radius: 0;
    font-size: 18px;
    font-weight: 400;
    color: #000000;
    cursor: pointer;
    margin-top: 0;
    box-sizing: border-box;
  }

  .change-btn-icon {
    width: 32px;
    height: 32px;
    flex-shrink: 0;
  }

  .total-inner {
    padding: 32px 16px;
  }

  .continue-btn-wrapper {
    width: 100%;
    position: static;
  }

  .continue-btn {
    height: 72px;
  }

  /* Экран выбора лаборатории */
  .map-selector-body {
    flex-direction: column;
    height: calc(100vh - 72px);
    overflow: hidden;
  }

  .map-sidebar {
    width: 100%;
    height: 100%;
    padding: 40px 16px;
    overflow-y: auto;
  }

  .map-sidebar--hidden {
    display: none;
  }

  .mobile-map-trigger-btn {
    display: flex;
  }

  .map-view-container {
    display: none;
    width: 100%;
    height: 100%;
  }

  .map-view-container--visible {
    display: flex;
  }

  .mobile-lab-sheet {
    display: block;
  }

  .section-title {
    font-size: 32px;
  }

  .form-row-two {
    flex-direction: column;
    gap: 24px;
  }

  .field-label--md {
    font-size: 16px;
  }
}

/* ───────────────────── FIGMA 12:65 — FINAL GEOMETRY ───────────────────── */
:global(html),
:global(body) {
  scrollbar-width: none;
}

:global(body::-webkit-scrollbar) {
  display: none;
}

@media (min-width: 901px) {
  .map-sidebar {
    width: 626px;
    padding: 64px;
  }

  .map-step-1 {
    display: flex;
    flex-direction: column;
    gap: 23px;
    margin-top: -12px;
  }

  .map-step-1 .map-sidebar-title {
    font-size: 20px;
    line-height: 24px;
    transform: translateY(4px);
  }

  .map-step-1 .city-select-dropdown {
    height: 51px;
    border-width: 0 0 1px;
  }

  .map-step-1 .selected-city-name--placeholder,
  .map-step-1 .selected-city-name--placeholder + .select-arrow {
    opacity: 0.3;
  }

  .map-step-2 .city-header-row {
    border-bottom: 0;
    padding-bottom: 0;
  }

  .map-step-2 .city-badge {
    font-size: 20px;
    font-weight: 400;
  }

  .map-step-2 .city-badge svg {
    width: 24px;
    height: 24px;
  }

  .map-step-2 .change-city-btn {
    font-size: 20px;
  }

  .map-step-2 > .map-sidebar-title {
    margin-top: 9px;
  }

  .map-step-2 .promo-alert-card {
    margin-top: 31px;
    margin-bottom: 32px;
  }

  .map-step-2 .promo-alert-title {
    width: 300px;
  }

  .map-step-2 .labs-list {
    gap: 2px;
  }

  .map-step-2 .lab-card-name {
    font-size: 24px;
    line-height: 28px;
  }

  .map-step-2 .lab-card-content {
    padding-bottom: 28px;
  }

  .map-step-2 .lab-card-badge {
    padding: 5px 8px;
    border-radius: 0;
    line-height: 16px;
  }

  .map-step-2 .lab-card-schedule {
    color: #000000;
    opacity: 1;
  }

  .map-step-2 .lab-card-schedule .schedule-days {
    width: 92px;
    opacity: 0.45;
  }

  .map-step-2--detailed .city-header-row {
    justify-content: flex-start;
  }

  .map-step-2--detailed .lab-count {
    color: #000000;
    opacity: 0.4;
  }

  .map-step-2--detailed .promo-alert-card {
    display: none;
  }

  .map-step-2--detailed .labs-list {
    margin-top: 31px;
  }

  .map-step-2--detailed .lab-card--detailed {
    height: 365px;
  }

  .map-step-2--detailed .lab-card-action {
    margin-top: auto;
    padding: 24px;
  }

  .map-step-2--detailed .select-lab-btn {
    height: 73px;
    padding: 0 32px;
    font-size: 18px;
  }

  .map-view-container .russia-map-layer {
    background-size: 168.24% auto;
    background-position: 84.02% 43.84%;
  }

  .moscow-map-layer:not(.moscow-map-layer--zoomed) {
    background-size: 156.5% auto;
    background-position: 91.08% 43.84%;
  }

  .map-view-container .moscow-map-layer--zoomed {
    background-size: 404% auto;
    background-position: 72.11% 51.7%;
  }

  .russia-map-layer .map-city-marker {
    opacity: 0;
  }

  .mobile-map-marker-decor {
    display: none;
  }

  .map-sidebar {
    scrollbar-width: none;
  }

  .map-sidebar::-webkit-scrollbar {
    display: none;
  }

  .left-col {
    padding-top: 64px;
  }

  .product-row:nth-child(1) {
    height: 245px;
  }

  .product-row:nth-child(2) {
    height: 212px;
  }

  .product-row:nth-child(3) {
    height: 162px;
  }

  .product-thumb svg {
    width: 46px;
    height: 46px;
  }

  .product-thumb-label {
    font-size: 18px;
  }

  .product-row:first-child .product-name,
  .product-row:first-child .product-price {
    font-size: 42px;
    line-height: 49px;
  }

  .product-row:first-child .product-name {
    margin-bottom: 12px;
  }

  .product-row:first-child .product-desc {
    width: 484px;
    margin-bottom: 0;
    font-size: 21px;
    line-height: 1.15;
  }

  .product-row:first-child .product-tags {
    margin-top: 40px;
  }

  .product-row:nth-child(2) .product-header {
    transform: translateY(-3px);
  }

  .product-row:nth-child(2) .product-desc {
    width: 310px;
    margin-bottom: 28px;
  }

  .product-row:nth-child(3) .product-header {
    transform: translateY(-17px);
  }

  .tag {
    min-height: 40px;
    padding: 10px 24px;
    border-radius: 0;
    font-size: 14px;
  }

  .section-block {
    gap: 48px;
  }

  .location-empty {
    width: 439px;
  }

  .select-field {
    border-width: 0 0 1px;
    background: transparent;
  }

  .location-card {
    min-height: 173px;
  }

  .summary-item {
    font-size: 24px;
  }
}

/* Laptop widths need their own scale: the 1920px Figma geometry cannot fit
   beside the order summary unchanged at 1024–1599px. */
@media (min-width: 901px) and (max-width: 1599px) {
  .right-col {
    width: clamp(340px, 40vw, 520px);
  }

  /* Keep the familiar promo-field proportions on laptops. */
  .promo-input,
  .promo-btn {
    height: 61px;
  }

  .left-col {
    gap: 64px;
    padding: clamp(32px, 3.3vw, 52px) clamp(24px, 3vw, 48px) 64px;
  }

  .products-section,
  .product-row,
  .product-info,
  .product-header,
  .product-header > div:first-child {
    min-width: 0;
  }

  .product-row:nth-child(1),
  .product-row:nth-child(2),
  .product-row:nth-child(3) {
    height: auto;
  }

  .product-row:nth-child(1) {
    min-height: 245px;
  }

  .product-row:nth-child(2) {
    min-height: 212px;
  }

  .product-row:nth-child(3) {
    min-height: 162px;
  }

  .product-thumb {
    width: clamp(136px, 13vw, 180px);
    padding: 20px 16px;
  }

  .product-info {
    padding: clamp(18px, 2vw, 28px);
  }

  .product-header {
    gap: clamp(8px, 1.2vw, 16px);
  }

  .product-name {
    font-size: clamp(20px, 1.8vw, 24px);
  }

  .product-desc {
    font-size: clamp(14px, 1.25vw, 16px);
  }

  .product-price {
    font-size: clamp(20px, 1.75vw, 24px);
  }

  .product-row:first-child .product-name,
  .product-row:first-child .product-price {
    font-size: clamp(24px, 2.55vw, 36px);
    line-height: 1.12;
  }

  .product-row:first-child .product-name {
    overflow-wrap: break-word;
  }

  .product-row:first-child .product-desc {
    width: auto;
    font-size: clamp(15px, 1.35vw, 19px);
    line-height: 1.2;
  }

  .product-row:first-child .product-tags {
    margin-top: clamp(20px, 2.3vw, 32px);
  }

  .tag {
    min-height: 36px;
    padding: 8px clamp(10px, 1.2vw, 18px);
  }
}

.summary-block {
  padding: 32px 32px 24px;
}

.promo-row {
  margin-top: auto;
}

.total-block {
  margin-top: 0;
}

.continue-btn:disabled {
  opacity: 1;
}

.russia-map-layer,
.moscow-map-layer {
  position: absolute;
  inset: 0;
  background-position: center;
  background-repeat: no-repeat;
  background-size: cover;
}

.russia-map-layer {
  background-image: url('/assets/cart-map-country.png');
}

.moscow-map-layer {
  background-image: url('/assets/cart-map-moscow.png');
}

.moscow-map-layer--zoomed {
  background-image: url('/assets/cart-map-moscow-detail.png');
}

.russia-map-layer .map-svg > path,
.moscow-map-layer .map-svg > path,
.moscow-map-layer .map-svg > circle {
  display: none;
}

@media (max-width: 900px) {
  .desktop-map-marker-decor {
    display: none;
  }

  .header-nav {
    height: 72px;
    padding: 24px 32px;
  }

  .back-label {
    display: none;
  }

  .page-body {
    padding-bottom: 0;
  }

  .left-col {
    gap: 64px;
    padding: 20px 16px 64px;
  }

  .products-section {
    gap: 12px;
  }

  .product-row {
    height: auto;
    padding: 24px;
    gap: 0;
  }

  .product-row:nth-child(1) {
    height: 342px;
  }

  .product-row:nth-child(2) {
    height: 295px;
  }

  .product-row:nth-child(3) {
    height: 218px;
  }

  .product-info {
    height: 100%;
    justify-content: flex-start;
  }

  .product-tags-top {
    margin-bottom: 24px;
    gap: 4px;
  }

  .tag {
    min-height: 32px;
    padding: 8px 12px;
    border-radius: 0;
    font-size: 14px;
  }

  .product-row:first-child .product-name {
    font-size: 32px;
    line-height: 38px;
  }

  .product-name {
    margin-bottom: 12px;
    font-size: 24px;
    line-height: 1;
  }

  .product-desc {
    margin: 0;
    color: #929292;
    opacity: 1;
    font-size: 18px;
    line-height: 1.1;
  }

  .product-row:first-child .product-desc {
    font-size: 20px;
    line-height: 1;
  }

  .product-mobile-action {
    margin-top: auto;
  }

  .product-mobile-btn {
    height: 64px;
    justify-content: flex-start;
    gap: 12px;
    padding: 0 24px;
  }

  .product-mobile-btn-icon svg {
    width: 32px;
    height: 32px;
  }

  .product-mobile-btn-price {
    font-size: 18px;
    font-weight: 400;
  }

  .section-block {
    gap: 32px;
  }

  .section-title {
    font-size: 24px;
    line-height: 17px;
  }

  .select-field {
    border: none;
    border-bottom: 1px solid #dddddd;
    background: transparent;
  }

  .personal-form,
  .form-row-two {
    gap: 24px;
  }

  .field-label,
  .field-label--md {
    margin-bottom: 6px;
    font-size: 16px;
  }

  .field-input {
    height: 61px;
    padding: 0 24px;
  }

  .field-info {
    gap: 16px;
    margin-top: 32px;
  }

  .field-info svg {
    width: 24px;
    height: 24px;
  }

  .field-info-text {
    color: #000;
    opacity: 1;
    font-size: 18px;
    line-height: 1.05;
  }

  .mobile-summary-title {
    padding: 0 16px 32px;
  }

  .map-selector-body {
    height: calc(100dvh - 72px);
  }

  .map-selector-screen > .header-nav {
    height: 72px;
    padding: 0 32px;
  }

  .map-sidebar {
    padding: 24px;
    scrollbar-width: none;
  }

  .map-sidebar::-webkit-scrollbar {
    display: none;
  }

  .map-step-1,
  .map-step-2 {
    display: flex;
    min-height: 100%;
    flex-direction: column;
  }

  .map-step-2 {
    gap: 24px;
  }

  .city-header-row {
    min-height: 72px;
    margin: -24px -24px 0;
    padding: 0 24px;
  }

  .map-sidebar-title {
    font-size: 32px;
  }

  .map-step-1 .field-group {
    margin-top: 24px;
  }

  .city-select-dropdown {
    border-width: 0 0 1px;
  }

  .mobile-map-trigger-btn {
    position: sticky;
    bottom: -24px;
    width: calc(100% + 48px);
    margin: auto -24px -24px;
    padding: 0 32px;
    flex-shrink: 0;
  }

  .labs-list {
    gap: 2px;
    margin-top: -6px;
  }

  .promo-alert-desc {
    font-size: 16px;
    line-height: 20px;
  }

  .lab-card-name {
    font-size: 24px;
    line-height: 28px;
  }

  .lab-card-badge {
    padding: 5px 8px;
    border-radius: 0;
    line-height: 16px;
  }

  .lab-card-schedule {
    color: #000000;
    opacity: 1;
  }

  .lab-card-schedule .schedule-days {
    opacity: 0.45;
  }

  .lab-card-schedule .schedule-days {
    width: 92px;
  }

  .map-view-container {
    height: calc(100dvh - 72px);
  }

  .russia-map-layer,
  .moscow-map-layer {
    background-size: auto 100%;
  }

  .moscow-map-layer:not(.moscow-map-layer--zoomed) {
    background-size: 481.9% auto;
    background-position: -187.2vw -47.27vw;
  }

  .moscow-map-layer--zoomed {
    background-size: 482.3% auto;
    background-position: -187.4vw -47.4vw;
  }

  .moscow-map-layer:not(.moscow-map-layer--zoomed) .map-lab-marker,
  .moscow-map-layer:not(.moscow-map-layer--zoomed) .map-marker-decor {
    transform: scale(3);
    transform-box: fill-box;
    transform-origin: center;
  }

  .mobile-sheet-content {
    min-height: 275px;
    padding: 24px;
    gap: 16px;
  }

  .mobile-sheet-title {
    margin-top: -6px;
    font-size: 24px;
    font-weight: 400;
    line-height: 28px;
  }

  .mobile-sheet-schedule {
    margin-top: 6px;
    opacity: 1;
  }

  .mobile-sheet-schedule .schedule-days {
    width: 92px;
    opacity: 0.45;
  }

  .mobile-select-confirm-btn {
    height: 72px;
    margin-top: auto;
    padding: 0 32px;
    font-weight: 400;
  }

  .close-sheet-btn {
    right: 20px;
  }
}

/* Compact desktop layout for laptop-height screens.
   It reproduces the useful density of a 75% browser zoom without asking the
   visitor to change zoom: the whole summary stays visible and the next section
   peeks into the first viewport on the left. */
@media (min-width: 901px) and (max-height: 900px) {
  .right-col {
    overflow-y: hidden;
  }

  .left-col {
    gap: clamp(40px, 5vh, 48px);
    padding-top: clamp(28px, 4.5vh, 40px);
  }

  .product-row:nth-child(1) {
    min-height: 0;
    height: clamp(174px, 24vh, 202px);
  }

  .product-row:nth-child(2) {
    min-height: 0;
    height: clamp(140px, 19vh, 162px);
  }

  .product-row:nth-child(3) {
    min-height: 0;
    height: clamp(104px, 14vh, 120px);
  }

  .product-thumb svg {
    width: 36px;
    height: 36px;
  }

  .product-info {
    padding-top: 20px;
    padding-bottom: 20px;
  }

  .product-row:first-child .product-name,
  .product-row:first-child .product-price {
    font-size: clamp(20px, 1.7vw, 24px);
    line-height: 1.1;
  }

  .product-row:first-child .product-name {
    margin-bottom: 8px;
  }

  .product-row:first-child .product-desc {
    font-size: 13px;
    line-height: 1.15;
  }

  .product-row:first-child .product-tags {
    margin-top: 12px;
  }

  .product-row:nth-child(2) .product-desc {
    margin-bottom: 12px;
  }

  .product-name,
  .product-price {
    font-size: 18px;
  }

  .product-desc {
    font-size: 12px;
    line-height: 1.25;
  }

  .product-row:nth-child(2) .product-header,
  .product-row:nth-child(3) .product-header {
    transform: none;
  }

  .tag {
    min-height: 28px;
    padding: 6px 12px;
    font-size: 12px;
  }

  .section-block {
    gap: clamp(24px, 4vh, 36px);
  }

  .section-title {
    font-size: clamp(32px, 3vw, 38px);
  }

  .summary-block {
    padding: 18px 24px;
    gap: 8px;
  }

  .summary-items {
    gap: 8px;
  }

  .summary-item {
    font-size: 14px;
  }

  .summary-label,
  .total-label {
    font-size: 12px;
  }

  .summary-value,
  .summary-value--big {
    font-size: 18px;
  }

  .summary-value--location {
    font-size: 14px;
  }

  .summary-schedule {
    font-size: 13px;
  }

  .summary-badge {
    font-size: 12px;
  }

  .promo-input,
  .promo-btn {
    height: 52px;
  }

  .promo-input {
    font-size: 14px;
  }

  .total-inner {
    padding: 18px 24px;
    gap: 14px;
  }

  .total-price-group {
    gap: 6px;
  }

  .total-price {
    font-size: 26px;
  }

  .consent-text {
    font-size: 11px;
    line-height: 1.25;
  }

  .continue-btn {
    height: 60px;
    padding: 0 24px;
    font-size: 16px;
  }
}

/* Extra-short notebook windows (for example 1024x555) still keep every
   checkout control in the fixed summary column. */
@media (min-width: 901px) and (max-height: 650px) {
  .left-col {
    gap: 24px;
    padding-top: 16px;
  }

  .product-row:nth-child(1) {
    height: 184px;
  }

  .product-row:nth-child(2) {
    height: 116px;
  }

  .product-row:nth-child(3) {
    height: 88px;
  }

  .product-thumb {
    padding-top: 12px;
    padding-bottom: 12px;
  }

  .product-thumb svg {
    width: 30px;
    height: 30px;
  }

  .product-thumb-label,
  .product-desc,
  .product-row:first-child .product-desc {
    font-size: 13px;
  }

  .product-name,
  .product-price,
  .product-row:first-child .product-name,
  .product-row:first-child .product-price {
    font-size: 22px;
  }

  .product-row:first-child .product-tags {
    margin-top: 8px;
  }

  .tag {
    min-height: 24px;
    padding: 4px 8px;
    font-size: 12px;
  }

  .section-title {
    font-size: 30px;
  }

  .summary-block {
    padding: 10px 20px;
    gap: 4px;
  }

  .summary-label,
  .total-label {
    font-size: 13px;
  }

  .summary-value,
  .summary-value--big,
  .summary-item {
    font-size: 16px;
  }

  .summary-items {
    gap: 4px;
  }

  .summary-value--location {
    font-size: 14px;
    line-height: 1.1;
  }

  .summary-badge {
    margin-bottom: 0;
    padding: 1px 6px;
    font-size: 11px;
  }

  .summary-schedule {
    gap: 0;
    font-size: 12px;
    line-height: 1.1;
  }

  .summary-schedule-row {
    gap: 8px;
  }

  .promo-input,
  .promo-btn {
    height: 40px;
  }

  .promo-input {
    font-size: 15px;
  }

  .total-inner {
    padding: 10px 20px;
    gap: 8px;
  }

  .total-price-group {
    gap: 2px;
  }

  .total-price {
    font-size: 26px;
  }

  .consent-row {
    gap: 8px;
  }

  .consent-checkbox {
    width: 20px;
    height: 20px;
  }

  .consent-text {
    font-size: 11px;
    line-height: 1.1;
  }

  .continue-btn {
    height: 48px;
    font-size: 16px;
  }
}
</style>