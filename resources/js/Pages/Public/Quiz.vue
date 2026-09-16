<script setup>
import '../../../css/main.css';
import { ref, computed, nextTick, watch, onMounted, onBeforeUnmount } from 'vue'
import { router, Head } from '@inertiajs/vue3'
import HomeBackLink from '@/Components/HomeBackLink.vue'
import SiteSidebar from '@/Components/SiteSidebar.vue'
import QuizIntroAnimation from '@/Components/QuizIntroAnimation.vue'

const props = defineProps({
  questions: { type: Array, default: () => [] },
  rules: { type: Array, default: () => [] },
  meta: { type: Object, default: () => ({})}
})

// SEO & OpenGraph
const seoTitle = computed(() => props.meta?.title || 'Нужно ли мне сдать тест на аллергию?')
const seoDescription = computed(() => props.meta?.description || '')
const seoKeywords = computed(() => props.meta?.keywords || '')
const ogTitle = computed(() => props.meta?.og_title || seoTitle.value)
const ogDescription = computed(() => props.meta?.og_desc || seoDescription.value)
const ogImage = computed(() => props.meta?.og_image || '/og-image.png')
const ogUrl = computed(() => props.meta?.og_url || '')

// Состояние квиза
const step = ref('intro')
const currentQuestionId = ref(null)
const currentAudience = ref('all') // 'all' | 'adult' | 'child'
const historyStack = ref([])
const cardContainer = ref(null)
const userAnswers = ref({}) // { questionId: answerId | [answerId] }
const resultData = ref(null)
const isSubmitting = ref(false)

// Состояние модального окна записи к врачу
const isDoctorModalOpen = ref(false)
const doctorForm = ref({
  fullName: '',
  phone: '',
  city: '',
  agreedToTerms: false
})
const isModalSubmitting = ref(false)

// Состояние модального окна выбора лабораторий
const isRegisterModalOpen = ref(false)
const sheetDragOffset = ref(0)
const isSheetDragging = ref(false)
const dragStartY = ref(0)
let previousBodyOverflow = ''

const labs = [
  { name: 'Гемотест', logo: '/assets/figma-lab-gemotest.webp', logoClass: 'test-location-modal__logo--gemotest', href: 'https://gemotest.ru/moskva/catalog/allergii/pervichnye-testy/obshchaya-allergodiagnostika/allergochip-alex-2/allergochip-alex-2-300-allergokomponentov-allergy-explorer-2-19ddaf/' },
  { name: 'ДНКОМ', logo: '/assets/figma-lab-dncom.webp', logoClass: 'test-location-modal__logo--dncom', href: 'https://dnkom.ru/analizy-i-tseny/allergochip-alex2-allergy-explorer/allergochip-alex2-allergy-explorer-2-do-300-allergokomponentov-i-obshchiy-ige/' },
  { name: 'Ситилаб', logo: '/assets/figma-lab-citilab.webp', logoClass: 'test-location-modal__logo--citilab', href: '#' },
  { name: 'KDL', logo: '/assets/figma-lab-kdl.webp', logoClass: 'test-location-modal__logo--kdl', href: 'https://kdl.ru/analizy-i-tseny/msk/allergochip-alex2-300-komponentov' },
  { name: 'CMD', logo: '/assets/figma-lab-cmd.webp', logoClass: 'test-location-modal__logo--cmd', href: 'https://www.cmd-online.ru/analizy-i-tseny/katalog-analizov/msk/allergochip_alex_2_300_allergokomponentov_i_ig_e_obshhij_ig_e/' },
  { name: 'CHROMOLAB', logo: '/assets/figma-lab-chromolab.webp', logoClass: 'test-location-modal__logo--chromolab', href: 'https://www.chromolab.ru/issledovaniya/al866_allergochip_alex2_300_komponentov_vklyuchaet_opredelenie_obshchego_ige/' }
]

const openRegisterModal = () => { isRegisterModalOpen.value = true }
const closeRegisterModal = () => {
  sheetDragOffset.value = 0
  isSheetDragging.value = false
  isRegisterModalOpen.value = false
}

const withClinicUtm = (url) => {
  if (!url || url === '#') return url
  if (url.includes('utm_source=')) return url
  return url + (url.includes('?') ? '&' : '?') + 'utm_source=site&utm_medium=cta&utm_campaign=clinic'
}

const handleLabClick = (lab) => {
  closeRegisterModal()
  if (lab.href && lab.href !== '#') window.open(withClinicUtm(lab.href), '_blank')
}

// Тач-события для модалки лабораторий
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
  if (shouldClose) { closeRegisterModal(); return }
  sheetDragOffset.value = 0
}

watch(isRegisterModalOpen, (visible) => {
  if (!import.meta.client) return
  if (visible) {
    sheetDragOffset.value = 0
    isSheetDragging.value = false
    previousBodyOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return
  }
  document.body.style.overflow = previousBodyOverflow
})

const handleKeydown = (event) => { if (event.key === 'Escape') closeRegisterModal() }
onMounted(() => window.addEventListener('keydown', handleKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', handleKeydown))

const currentAnimationVariant = computed(() => {
  if (step.value === 'intro') return 'photo'
  if (step.value === 'result') {
    const title = resultData.value?.title || ''
    const description = resultData.value?.description || ''
    if (title.includes('аллерголог') || description.includes('аллерголог') || title.includes('Следующий шаг')) return 'pollen'
    if (title.includes('подтверждён') || description.includes('подтверждён') || title.includes('возможных аллергенов')) return 'topo'
    if (title.includes('ALEX') || title.includes('рассмотреть ALEX') || description.includes('ALEX')) return 'dots'
    if (resultData.value?.animation_variant) return resultData.value.animation_variant
    return 'pollen'
  }
  return 'photo'
})

const isDoctorResult = computed(() => {
  if (step.value !== 'result') return false
  const title = resultData.value?.title || ''
  return title.includes('Следующий шаг лучше определить вместе с аллергологом')
})

const isSearchResult = computed(() => {
  if (step.value !== 'result') return false
  const title = resultData.value?.title || ''
  return title.includes('Один из возможных аллергенов уже подтверждён')
})

const isSearchResult2 = computed(() => {
  if (step.value !== 'result') return false
  const title = resultData.value?.title || ''
  return title.includes('Есть подозрение на конкретный аллерген')
})

const availableQuestions = computed(() => {
  if (!props.questions || !Array.isArray(props.questions)) return []
  return props.questions.filter(q => {
    const target = q.audience_target || q.target_audience || 'all'
    return target === 'all' || target === currentAudience.value
  })
})

const currentQuestion = computed(() => {
  if (!currentQuestionId.value) return null
  return props.questions.find(q => Number(q.id) === Number(currentQuestionId.value)) || null
})

/* 🔹 ЛОГИКА НУМЕРАЦИИ ВОПРОСОВ */

// Пока аудитория не выбрана ('all') — счетчик скрываем
const isAudienceSelected = computed(() => currentAudience.value !== 'all')

// Список вопросов исключительно для конкретной аудитории (без вводного вопроса выбора целевой группы)
const specificAudienceQuestions = computed(() => {
  if (!props.questions || !Array.isArray(props.questions)) return []
  return props.questions.filter(q => {
    const target = q.audience_target || q.target_audience || 'all'
    return target === currentAudience.value
  })
})

// Номер текущего вопроса (отсчет с 1 после выбора аудитории)
const currentQuestionIndexDisplay = computed(() => {
  if (!isAudienceSelected.value || !currentQuestion.value) return 0

  const indexInSpecific = specificAudienceQuestions.value.findIndex(q => Number(q.id) === Number(currentQuestionId.value))
  if (indexInSpecific !== -1) {
    return indexInSpecific + 1
  }

  // Если вопрос общий (all), но аудитория уже определена — считаем его позицию в доступной цепочке
  const indexInAvailable = availableQuestions.value.findIndex(q => Number(q.id) === Number(currentQuestionId.value))
  return Math.max(1, indexInAvailable)
})

// Общее количество вопросов для текущей аудитории
const totalQuestionsDisplay = computed(() => {
  if (!isAudienceSelected.value) return 0
  return availableQuestions.value.filter(q => (q.audience_target || q.target_audience) !== 'all').length || specificAudienceQuestions.value.length
})

// Текст счетчика: скрыт на первом вопросе, далее "1/5", "2/6" и т.д.
const questionCounterText = computed(() => {
  if (!isAudienceSelected.value) return null
  return `${currentQuestionIndexDisplay.value}/${totalQuestionsDisplay.value}`
})

// Проверка: выбран ли хоть один ответ на текущий вопрос
const isCurrentQuestionAnswered = computed(() => {
  if (!currentQuestion.value) return false
  const ans = userAnswers.value[currentQuestion.value.id]
  if (Array.isArray(ans)) return ans.length > 0
  return ans !== undefined && ans !== null && ans !== ''
})

const startQuiz = () => {
  step.value = 'quiz'
  currentAudience.value = 'all'
  historyStack.value = []
  userAnswers.value = {}
  const firstQ = availableQuestions.value[0] || props.questions?.[0]
  if (firstQ) currentQuestionId.value = firstQ.id
}

const isSelected = (qId, oId) => {
  const ans = userAnswers.value[qId]
  return Array.isArray(ans) ? ans.includes(oId) : ans === oId
}

const selectOption = (qId, option, type) => {
  const oId = option.id
  if (type === 'single') {
    userAnswers.value[qId] = oId
  } else {
    if (!userAnswers.value[qId]) userAnswers.value[qId] = []
    const idx = userAnswers.value[qId].indexOf(oId)
    if (idx > -1) userAnswers.value[qId].splice(idx, 1)
    else userAnswers.value[qId].push(oId)
  }
}

const goToNextStep = async () => {
  const currentQ = currentQuestion.value
  if (!currentQ || !isCurrentQuestionAnswered.value) return

  let selectedOption = null
  const currentAns = userAnswers.value[currentQ.id]
  const options = currentQ.answers || currentQ.options || []

  if (Array.isArray(currentAns)) {
    selectedOption = options.find(o => currentAns.includes(o.id))
  } else {
    selectedOption = options.find(o => o.id === currentAns)
  }

  historyStack.value.push({ questionId: currentQ.id, audience: currentAudience.value })
  if (selectedOption?.target_audience) currentAudience.value = selectedOption.target_audience

  const validQuestions = props.questions.filter(q => {
    const target = q.audience_target || q.target_audience || 'all'
    return target === 'all' || target === currentAudience.value
  })

  let nextQId = null
  if (selectedOption?.next_question_id) {
    nextQId = selectedOption.next_question_id
  } else {
    const currentIndexInValid = validQuestions.findIndex(q => Number(q.id) === Number(currentQ.id))
    if (currentIndexInValid !== -1 && currentIndexInValid < validQuestions.length - 1) {
      nextQId = validQuestions[currentIndexInValid + 1].id
    } else if (currentIndexInValid === -1) {
      const firstNext = validQuestions.find(q => (q.order ?? 0) > (currentQ.order ?? 0))
      nextQId = firstNext ? firstNext.id : null
    }
  }

  if (nextQId) {
    currentQuestionId.value = nextQId
    await nextTick()
    cardContainer.value?.scrollTo({ top: 0, behavior: 'auto' })
  } else {
    await calculateResult()
  }
}

const prevQuestion = () => {
  if (historyStack.value.length > 0) {
    const previousState = historyStack.value.pop()
    currentQuestionId.value = previousState.questionId
    currentAudience.value = previousState.audience
  } else {
    step.value = 'intro'
  }
}

const calculateResult = async () => {
  isSubmitting.value = true
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
    const response = await fetch('/api/quiz/calculate', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken || ''
      },
      body: JSON.stringify({ answers: userAnswers.value })
    })

    if (!response.ok) throw new Error(`Ошибка сервера: ${response.status}`)
    const data = await response.json()
    resultData.value = data.result
    step.value = 'result'
  } catch (error) {
    console.error('Error calculating result:', error)
    alert('Произошла ошибка при расчете результата. Пожалуйста, попробуйте еще раз.')
  } finally {
    isSubmitting.value = false
  }
}

const redirectToCart = () => openRegisterModal()
const redirectToSearch = () => router.visit('/search')

// Модальное окно врача
const openDoctorModal = () => {
  doctorForm.value = { fullName: '', phone: '', city: '', agreedToTerms: false }
  isDoctorModalOpen.value = true
}

const closeDoctorModal = () => {
  isDoctorModalOpen.value = false
}

const submitDoctorAppointment = async () => {
  if (!doctorForm.value.fullName || !doctorForm.value.phone || !doctorForm.value.city) {
    alert('Пожалуйста, заполните ФИО, номер телефона и город')
    return
  }

  if (!doctorForm.value.agreedToTerms) {
    alert('Пожалуйста, подтвердите согласие на обработку персональных данных')
    return
  }

  isModalSubmitting.value = true
  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
    const response = await fetch('/api/doctor-appointment', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken || ''
      },
      body: JSON.stringify({
        full_name: doctorForm.value.fullName,
        phone: doctorForm.value.phone,
        city: doctorForm.value.city,
        agreed_to_terms: doctorForm.value.agreedToTerms,
        quiz_answers: userAnswers.value
      })
    })

    if (!response.ok) throw new Error('Ошибка при отправке')

    alert('Заявка успешно отправлена! Мы свяжемся с вами в ближайшее время.')
    closeDoctorModal()
  } catch (e) {
    console.error(e)
    alert('Не удалось отправить заявку. Попробуйте снова позже.')
  } finally {
    isModalSubmitting.value = false
  }
}
</script>

<template>
  <Head>
    <title>{{ seoTitle }}</title>
    <meta name="description" :content="seoDescription" />
    <meta v-if="seoKeywords" name="keywords" :content="seoKeywords" />
  </Head>

  <div class="page-container site-sidebar-layout">
    <SiteSidebar @register="redirectToCart" @about="router.visit('/')" />

    <main class="content-container quiz-page-content">
      <div class="mobile-top-bar home-back-area">
        <HomeBackLink stretched show-mobile-label />
        <span class="mobile-top-title">Квиз</span>
      </div>

      <header class="header-bar desktop-only home-back-area">
        <HomeBackLink stretched />
      </header>

      <div ref="cardContainer" class="card-container" :class="{ 'question-layout': step === 'quiz' }">
        <Transition name="fade" mode="out-in">

          <div v-if="step === 'intro'" class="quiz-card-wrapper intro-state">
            <div class="glass-card main-card">
              <QuizIntroAnimation variant="photo" />
              <div class="main-card-inner">
                <h1 class="card-title">Нужно ли мне сдать<br>тест на аллергию?</h1>
                <p class="card-subtitle">
                  Не уверены, что тест подойдёт вам? Пройдите тест за ~1 минуту и получите персональную рекомендацию
                </p>
              </div>
            </div>

            <button class="action-btn next-btn" @click="startQuiz">
              <span class="btn-text">Начать тест</span>
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="btn-arrow">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="10 8 14 12 10 16"></polyline>
              </svg>
            </button>
          </div>

          <div v-else-if="step === 'quiz' && currentQuestion" class="quiz-card-wrapper quiz-state">
            <div class="options-header" @click="prevQuestion">
              <button class="back-prev-btn">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"></circle>
                  <polyline points="14 8 10 12 14 16"></polyline>
                </svg>
                <span>Вернуться назад</span>
              </button>
            </div>

            <div class="glass-card question-card">
              <div class="question-info">
                <span class="question-counter" :class="{ 'is-empty': !questionCounterText }">{{ questionCounterText || ' ' }}</span>
                <h2 class="question-text">{{ currentQuestion.question_text || currentQuestion.text }}</h2>
              </div>
            </div>

            <div class="options-list">
              <div
                  v-for="option in (currentQuestion.answers || currentQuestion.options || [])"
                  :key="option.id"
                  class="option-row"
                  :class="{
                    'selected': isSelected(currentQuestion.id, option.id),
                    'is-multiple': (currentQuestion.question_type || currentQuestion.type) === 'multiple'
                  }"
                  @click="selectOption(currentQuestion.id, option, currentQuestion.question_type || currentQuestion.type)"
              >
                <div v-if="(currentQuestion.question_type || currentQuestion.type) === 'multiple'" class="option-checkbox-square">
                  <svg v-if="isSelected(currentQuestion.id, option.id)" width="14" height="10" viewBox="0 0 14 10" fill="none">
                    <path d="M1.5 5L5 8.5L12.5 1" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </div>
                <div v-else class="option-checkbox">
                  <div class="option-checkbox-inner"></div>
                </div>
                <span class="option-label">{{ option.answer_text || option.text }}</span>
              </div>
            </div>

            <button
                class="action-btn next-btn"
                :disabled="!isCurrentQuestionAnswered"
                @click="goToNextStep"
            >
              <span class="btn-text">Далее</span>
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="btn-arrow">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="10 8 14 12 10 16"></polyline>
              </svg>
            </button>
          </div>

          <div v-else-if="step === 'result'" class="quiz-card-wrapper result-state">
            <div class="glass-card result-card">
              <QuizIntroAnimation :variant="currentAnimationVariant" />
              <div class="result-content">
                <div class="result-details">
                  <h2 class="result-title">{{ resultData?.title || 'Анализируем...' }}</h2>
                  <p class="result-subtitle">{{ resultData?.description || 'Пожалуйста, подождите' }}</p>
                </div>
              </div>
            </div>

            <button v-if="isDoctorResult" class="action-btn submit-btn" @click="openDoctorModal" :disabled="isSubmitting">
              <span class="btn-text">Записаться к врачу</span>
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="btn-arrow inverted-arrow">
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
              </svg>
            </button>

            <button v-else-if="isSearchResult || isSearchResult2" class="action-btn submit-btn" @click="redirectToSearch" :disabled="isSubmitting">
              <span class="btn-text">Подробнее узнать на поиске аллергенов</span>
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="btn-arrow inverted-arrow">
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
              </svg>
            </button>

            <button v-else class="action-btn submit-btn" @click="redirectToCart" :disabled="isSubmitting">
              <span class="btn-text">{{ isSubmitting ? 'Переход...' : 'Записаться на тест' }}</span>
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="btn-arrow inverted-arrow">
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
              </svg>
            </button>
          </div>

        </Transition>
      </div>
    </main>

    <!-- МОДАЛЬНОЕ ОКНО: Запись к врачу -->
    <Teleport to="body">
      <div v-if="isDoctorModalOpen" class="modal-backdrop" @click.self="closeDoctorModal">
        <div class="modal-card">
          <button class="modal-close-btn" @click="closeDoctorModal">&times;</button>

          <h3 class="modal-title">Запись к врачу-аллергологу</h3>
          <p class="modal-desc">Заполните форму, и мы свяжемся с вами для уточнения деталей приема.</p>

          <form @submit.prevent="submitDoctorAppointment" class="modal-form">
            <div class="form-group">
              <label>ФИО *</label>
              <input v-model="doctorForm.fullName" type="text" placeholder="Иванов Иван Иванович" required />
            </div>

            <div class="form-group">
              <label>Номер телефона *</label>
              <input v-model="doctorForm.phone" type="tel" placeholder="+7 (999) 000-00-00" required />
            </div>

            <div class="form-group">
              <label>Город *</label>
              <input v-model="doctorForm.city" type="text" placeholder="Москва" required />
            </div>

            <div class="form-group consent-group my-4">
              <label class="flex items-start space-x-2 text-sm text-gray-600 cursor-pointer" style="align-items: center">
                <input class="check"
                       v-model="doctorForm.agreedToTerms"
                       type="checkbox"
                       required
                />
                <span class="consent-text">
                  Я даю согласие на
                  <a href="https://alexallergotest.ru/consent" target="_blank" class="text-blue-600 underline hover:text-blue-800">
                    обработку персональных данных
                  </a>
                </span>
              </label>
            </div>

            <button type="submit" class="modal-submit-btn" :disabled="isModalSubmitting || !doctorForm.agreedToTerms">
              {{ isModalSubmitting ? 'Отправка...' : 'Отправить заявку' }}
            </button>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- МОДАЛЬНОЕ ОКНО: Выбор лаборатории -->
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
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@500;600&display=swap');

:root {
  --bg-page: #f5f5f5;
  --bg-card-white: #ffffff;
  --border-color-grey: #e5e7eb;
  --text-black: #111827;
  --text-muted: #6b7280;
}

.page-container {
  width: 100vw;
  max-width: none;
}

.quiz-page-content {
  display: flex;
  flex-direction: column;
  height: 100vh;
  height: 100dvh;
  position: relative;
  overflow: hidden;
  background-color: var(--bg-page);
  -webkit-text-size-adjust: 100%;
}
.consent-text {
  font-size: 11px;
  line-height: 1.1;
}
.form-group input.check{
padding: 0;
    width: 20px;
    height: 20px;
  flex-shrink: 0;
  margin: 2px 0 0;
  cursor: pointer;
  accent-color: #000000;
}
.consent-link[data-v-d76feae2] {
  text-decoration: underline;
  cursor: pointer;
  color: #000;
}
.header-bar {
  height: 72px;
  flex: 0 0 72px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.3);
  background: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  padding: 24px 32px;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  z-index: 10;
}

.card-container {
  flex: 1 1 auto;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 120px 40px 40px 40px;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  scrollbar-gutter: stable;
}

.card-container.question-layout {
  overflow: hidden;
  align-items: stretch;
}

.quiz-card-wrapper.quiz-state {
  height: 100%;
  max-height: 100%;
  min-height: 0;
}

.quiz-state .options-list {
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
  scrollbar-gutter: stable;
}

.quiz-state .next-btn {
  flex-shrink: 0;
}

.quiz-card-wrapper {
  width: 677px;
  display: flex;
  flex-direction: column;
  gap: 0;
  box-sizing: border-box;
}

@media (min-width: 1025px) {
  .quiz-card-wrapper {
    width: min(760px, 100%);
  }
}

.glass-card {
  background: var(--bg-card-white);
  border: 1px solid var(--border-color-grey);
  backdrop-filter: blur(50px);
  -webkit-backdrop-filter: blur(50px);
  padding: 32px;
  box-sizing: border-box;
}

.main-card {
  height: 538px;
  display: flex;
  align-items: flex-end;
  padding-bottom: 24px;
  position: relative;
  overflow: hidden;
}

.main-card-inner {
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: 100%;
  position: relative;
  z-index: 1;
}

.card-title {
  font-size: 32px;
  font-weight: 400;
  line-height: 1.25;
  margin: 0;
}

.card-subtitle {
  font-size: 18px;
  color: var(--text-muted);
  line-height: 1.45;
  margin: 0;
}

.action-btn {
  min-height: 72px;
  width: 100%;
  background: var(--bg-card-white);
  border: 1px solid var(--border-color-grey);
  border-top: none;
  padding: 20px 24px;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
  transition: background 0.2s ease, color 0.2s ease;
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  box-sizing: border-box;
  flex-shrink: 0;
}

.action-btn:hover {
  background: rgba(240, 240, 240, 0.9);
}

.action-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-text {
  font-size: 20px;
  font-weight: 400;
  color: var(--text-black);
}

.btn-arrow {
  width: 24px;
  height: 24px;
  flex-shrink: 0;
}

.options-header {
  min-height: 64px;
  background: var(--bg-card-white);
  border: 1px solid var(--border-color-grey);
  border-bottom: none;
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  padding: 16px 24px;
  display: flex;
  align-items: center;
  cursor: pointer;
  transition: background 0.2s ease;
  box-sizing: border-box;
}

.back-prev-btn {
  background: none;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 18px;
  color: var(--text-black);
  font-weight: 400;
  user-select: none;
  padding: 0;
}

.question-card {
  min-height: auto;
  border-bottom: 1px solid var(--border-color-grey);
  display: flex;
  align-items: flex-start;
}

.question-info {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
}

.question-counter {
  font-size: 16px;
  color: var(--text-muted);
  min-height: 1.2em;
}

.question-counter.is-empty {
  visibility: hidden;
}

.question-text {
  font-size: 28px;
  font-weight: 400;
  line-height: 1.3;
  margin: 0;
  min-height: calc(1.3em * 2);
}

.options-list {
  display: flex;
  flex-direction: column;
  min-height: 192px;
}

.option-row {
  min-height: 64px;
  height: auto;
  background: var(--bg-card-white);
  border: 1px solid var(--border-color-grey);
  border-top: none;
  padding: 18px 24px;
  display: flex;
  align-items: center;
  gap: 16px;
  cursor: pointer;
  transition: background 0.2s ease, border-color 0.2s ease;
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  box-sizing: border-box;
  box-shadow: inset 4px 0 0 transparent;
  flex-shrink: 0;
}

.option-row:hover {
  background: rgba(245, 245, 245, 0.9);
}

.option-row.selected {
  background: rgba(151, 174, 150, 0.15);
  box-shadow: inset 4px 0 0 #97ae96;
  border-left-color: var(--border-color-grey);
}

.option-checkbox {
  width: 22px;
  height: 22px;
  border: 2px solid var(--border-color-grey);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: border-color 0.2s ease;
}

.option-row.selected .option-checkbox {
  border-color: #97ae96;
}

.option-checkbox-inner {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: transparent;
  transition: background-color 0.2s ease;
}

.option-row.selected .option-checkbox-inner {
  background: #97ae96;
}

.option-checkbox-square {
  width: 22px;
  height: 22px;
  background-color: #ffffff;
  border: 1px solid #111827;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-sizing: border-box;
}

.option-label {
  font-size: 18px;
  color: var(--text-black);
  font-weight: 400;
  line-height: 1.35;
}

.result-card {
  min-height: 400px;
  display: flex;
  align-items: flex-end;
  padding: 24px;
  position: relative;
}

.result-content {
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  height: 100%;
  width: 100%;
}

.result-details {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.result-title {
  font-size: 28px;
  font-weight: 400;
  line-height: 1.3;
  margin: 0;
}

.result-subtitle {
  font-size: 16px;
  color: var(--text-muted);
  line-height: 1.45;
  margin: 0;
}

.submit-btn {
  background: var(--text-black);
  border-color: var(--text-black);
}

.submit-btn:hover {
  background: #1a1a1a;
  border-color: #1a1a1a;
}

.submit-btn .btn-text {
  color: var(--bg-card-white);
}

.inverted-arrow {
  filter: invert(1);
}

/* Стили Модального окна записи к врачу */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 16px;
}

.modal-card {
  background: #ffffff;
  width: 100%;
  max-width: 480px;
  padding: 32px;
  border-radius: 8px;
  position: relative;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

.modal-close-btn {
  position: absolute;
  top: 16px;
  right: 16px;
  background: none;
  border: none;
  font-size: 28px;
  cursor: pointer;
  color: #6b7280;
}

.modal-title {
  font-size: 22px;
  font-weight: 500;
  margin: 0 0 8px 0;
  color: #111827;
}

.modal-desc {
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 24px;
}

.modal-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-size: 14px;
  color: #374151;
  font-weight: 500;
}

.form-group input {
  height: 48px;
  padding: 0 16px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 16px;
  outline: none;
}

.form-group input:focus {
  border-color: #111827;
}

.modal-submit-btn {
  height: 52px;
  background: #111827;
  color: #ffffff;
  border: none;
  border-radius: 6px;
  font-size: 16px;
  cursor: pointer;
  margin-top: 8px;
  transition: background 0.2s;
}

.modal-submit-btn:hover {
  background: #1f2937;
}

.modal-submit-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Стили Модального окна выбора лабораторий */
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

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Мобильная адаптация */
@media (max-width: 1024px) {
  .quiz-page-content {
    height: 100dvh;
    padding: 0 !important;
    background: #f5f5f5 url('/assets/figma-mobile-info-background-frame.webp') center / cover no-repeat;
  }

  .quiz-page-content .mobile-top-bar {
    margin: 0;
    flex: 0 0 60px;
    height: 60px;
  }

  .mobile-top-title {
    display: none;
  }

  .header-bar {
    display: none;
  }

  .card-container {
    padding: 20px 16px;
    padding-bottom: calc(40px + env(safe-area-inset-bottom, 20px));
    align-items: flex-start;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior-y: contain;
  }

  .card-container.question-layout {
    padding-top: 20px;
    overflow: hidden;
    align-items: stretch;
  }

  .quiz-card-wrapper {
    width: 100%;
  }

  .glass-card {
    padding: 20px;
  }

  .main-card {
    height: clamp(360px, calc(100dvh - 297px), 538px);
    min-height: 0;
    padding: 0 24px 24px 24px;
    align-items: flex-end;
  }

  .intro-state .main-card-inner {
    width: 100%;
  }

  .card-title {
    font-size: 26px;
    line-height: 1.25;
  }

  .card-subtitle {
    font-size: 15px;
    color: rgba(0, 0, 0, 0.6);
    line-height: 1.4;
  }

  .action-btn {
    min-height: 60px;
    height: auto;
    padding: 16px 20px;
  }

  .btn-text {
    font-size: 18px;
  }

  .quiz-state .options-header {
    min-height: 52px;
    height: auto;
    padding: 12px 20px;
  }

  .quiz-state .back-prev-btn {
    font-size: 16px;
  }

  .question-card {
    padding: 20px;
  }

  .question-text {
    font-size: 22px;
    line-height: 1.3;
  }

  .question-counter {
    font-size: 14px;
  }

  .option-row {
    min-height: 54px;
    height: auto;
    padding: 14px 20px;
    gap: 12px;
  }

  .option-label {
    font-size: 16px;
    line-height: 1.35;
  }

  .result-card {
    min-height: 320px;
    padding: 20px;
  }

  .result-title {
    font-size: 24px;
    line-height: 1.3;
  }

  .result-subtitle {
    font-size: 15px;
  }

  .submit-btn {
    min-height: 60px;
    padding: 16px 20px;
  }

  /* Мобильные стили модалки лабораторий */
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
