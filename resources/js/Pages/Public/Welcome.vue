<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick, markRaw, defineAsyncComponent } from 'vue'
import { router, Head, Link } from '@inertiajs/vue3'
import { useDoctorMode } from '@/Composables/useDoctorMode'
import { SITE_VERSION } from '@/siteVersion.js'
import {
  advantageStepTotal,
  getActiveMenuIndex,
  getPersistentStepGroup,
  getSlideDeckRanges,
  isDoctorSmokeIndex,
  isHowToPassIndex,
  isLargeMobileCloudIndex,
  isResultsCloudIndex,
  isStoryBackdropIndex,
  PATIENT_SLIDE_7_VIDEOS,
  resultsCloudClassForIndex,
  shouldIncludePatientSlide7,
} from '@/composables/welcomeSlideDeck'

// How-to is part of the doctor/patient menu jump path — keep it sync so
// «Как сдать тест» cannot land on editorial beige while the chunk loads.
import FigmaInfoSlide from '@/Components/FigmaInfoSlide.vue'
// Асинхронные компоненты для оптимизации первоначальной загрузки (Code Splitting)
const HomeBackLink = defineAsyncComponent(() => import('@/Components/HomeBackLink.vue'))
const SiteSidebar = defineAsyncComponent(() => import('@/Components/SiteSidebar.vue'))
const FigmaContactsSlide = defineAsyncComponent(() => import('@/Components/FigmaContactsSlide.vue'))
const DoctorBlogTransition = defineAsyncComponent(() => import('@/Components/DoctorBlogTransition.vue'))

const QuizModal = defineAsyncComponent(() => import('@/Components/QuizModal.vue'))
const LabsModal = defineAsyncComponent(() => import('@/Components/LabsModal.vue'))

const props = defineProps({
  isDoctorRoute: { type: Boolean, default: false },
  meta: { type: Object, default: () => ({}) },
  homeSettings: {
    type: Object,
    default: () => ({
      patient: { advantages: [], results: [], how_to_pass: [], faq: [], hero_title: '', hero_subtitle: '', why_subtitle: '', cta_text: '', cta_url: '' },
      doctor: { advantages: [], results: [], how_to_pass: [], faq: [], hero_title: '', hero_subtitle: '', why_subtitle: '', cta_text: '', cta_url: '' }
    })
  },
  featuredBlogs: {
    type: Array,
    default: () => []
  }
})

const { isDoctorMode, toggleAudienceMode, doctorsUrl } = useDoctorMode()

onMounted(() => {
  if (props.isDoctorRoute && !isDoctorMode.value) {
    toggleAudienceMode()
  }
})

const activeSettings = computed(() => {
  if (isDoctorMode.value) {
    return props.homeSettings?.doctor || {}
  }
  return props.homeSettings?.patient || {}
})

const faqList = computed(() => {
  const currentFaq = activeSettings.value?.faq
  return Array.isArray(currentFaq) ? currentFaq : []
})

const handleAudienceToggle = () => {
  toggleAudienceMode()
  mobileSectionMenuExpanded.value = false
  mobileMenuOpen.value = false
}

const seoTitle = computed(() => props.meta?.title || '')
const seoDescription = computed(() => props.meta?.description || '')
const seoKeywords = computed(() => props.meta?.keywords || '')

const showDemoModal = ref(false)
const showSearchModal = ref(false)
const showQuizModal = ref(false)
const showRegisterModal = ref(false)
const isRegisterModalOpen = ref(false)
const isBlogTransitionActive = ref(false)
const activeFaqIndex = ref(null)

const toggleFaq = (index) => {
  activeFaqIndex.value = activeFaqIndex.value === index ? null : index
}

const goTo = (path) => {
  router.visit(path)
}

const blogListingUrl = computed(() => (isDoctorMode.value ? doctorsUrl('/materials') : '/blog'))
const BLOG_TRANSITION_DURATION = 1100

// Search State
const searchQuery = ref('')
const searchResult = ref(null)
const mockAllergens = [
  'Пыльца березы', 'Шерсть кошки', 'Шерсть собаки', 'Арахис',
  'Молоко коровье', 'Яичный белок', 'Клещ домашней пыли',
  'Пшеничная мука', 'Глютен', 'Полынь', 'Альфа-лактальбумин',
  'Креветки', 'Томаты', 'Шоколад', 'Пыльца ольхи', 'Яд осы'
]

const handleSearch = () => {
  if (!searchQuery.value.trim()) {
    searchResult.value = null
    return
  }
  const query = searchQuery.value.toLowerCase()
  const found = mockAllergens.filter(a => a.toLowerCase().includes(query))
  if (found.length > 0) {
    searchResult.value = { success: true, items: found }
  } else {
    searchResult.value = {
      success: false,
      message: 'Аллерген не найден в базе, но наш тест проверяет более 300 показателей. Скорее всего, он там есть!'
    }
  }
}

const ctaText = computed(() => {
  const text = (activeSettings.value?.cta_text || '').trim()
  return text || 'Записаться на тест на аллергию'
})

const ctaUrl = computed(() => (activeSettings.value?.cta_url || '').trim())

const openCart = () => {
  if (ctaUrl.value) {
    if (ctaUrl.value.startsWith('http://') || ctaUrl.value.startsWith('https://')) {
      window.location.assign(ctaUrl.value)
      return
    }
    router.visit(ctaUrl.value.startsWith('/') ? ctaUrl.value : `/${ctaUrl.value}`)
    return
  }
  isRegisterModalOpen.value = true
}

const closeRegisterModal = () => {
  isRegisterModalOpen.value = false
}

const siteSidebarRef = ref(null)
const openSiteRegisterModal = () => {
  ensureSidebarMounted()
  if (siteSidebarRef.value?.openRegisterModal) {
    siteSidebarRef.value.openRegisterModal()
    return
  }
  openCart()
}


// Slide Deck Definition
const currentSlideIndex = ref(0)
const scrollContainer = ref(null)

const videoA = ref(null)
const videoB = ref(null)
const activeVideoElement = ref('A')
const holdingVideoElement = ref(null)
const suppressHeldVideoFrame = ref(false)

const isVideoLayerActive = (letter) => (
  activeVideoElement.value === letter || holdingVideoElement.value === letter
)
let videoRequestId = 0
const isSlideTransitioning = ref(false)
const navigationMode = ref('scroll')
const videoBackgroundReady = ref(false) // poster first paint / LCP; flip true when video frame ready
const contentReadySlideIndex = ref(0)
/* Client-only: omit scroll-hint from SSR / first paint so QR cold open never
   shows storytelling chevrons over the hero before layout CSS applies. */
const scrollHintReady = ref(false)
// Defer below-fold homepage chunks so hero H1 is not blocked by Contacts/Info/Sidebar JS+CSS.
const mountBelowFoldSlides = ref(false)
const mountSidebar = ref(false)
const shouldMountHeavySlide = (index) => {
  // Direct menu jump to how-to/contacts used to render before the idle
  // below-fold flag flipped, so the editorial still painted with no body.
  if (index === currentSlideIndex.value) return true
  if (!mountBelowFoldSlides.value) return false
  return Math.abs(index - currentSlideIndex.value) <= 1
}
const ensureBelowFoldMounted = () => { mountBelowFoldSlides.value = true }
const ensureSidebarMounted = () => { mountSidebar.value = true }

const navigateToExternal = (url) => {
  window.location.assign(url)
}

const handleBlogClick = (event) => {
  if (!isDoctorMode.value) return

  const isModifiedClick = event.button !== 0
      || event.metaKey
      || event.ctrlKey
      || event.shiftKey
      || event.altKey

  if (isModifiedClick) return

  event.preventDefault()
  if (isBlogTransitionActive.value) return

  isBlogTransitionActive.value = true
}

const finishBlogTransition = () => {
  if (!isBlogTransitionActive.value) return
  navigateToExternal(blogListingUrl.value)
}

const PHONE_VIDEO_ROOT = '/videos/PHONE%20NAREZKA'
const MOB_VIDEO_ROOT = '/videos/MOB'
const DOCTOR_VIDEO_ROOT = '/videos/rezak%20previs%20for%20doc'

const MOB_STORY_VER = 'v26'
const phoneVideo = (index) => {
    if (index < 4) return `${PHONE_VIDEO_ROOT}/video/s${index}.webm`
    if (index === 4) return `${MOB_VIDEO_ROOT}/s4-v29.webm`
    if (index <= 9) return `${MOB_VIDEO_ROOT}/s${index}-${MOB_STORY_VER}.webm`
    return `${MOB_VIDEO_ROOT}/s${index}.webm`
}
const phoneReverseVideo = (index) => {
    if (index === 3) return `${PHONE_VIDEO_ROOT}/rev/s3r-v28.webm`
    if (index < 4) return `${PHONE_VIDEO_ROOT}/rev/s${index}r.webm`
    if (index === 4) return `${MOB_VIDEO_ROOT}/s4r-v29.webm`
    if (index <= 9) return `${MOB_VIDEO_ROOT}/s${index}r-${MOB_STORY_VER}.webm`
    return `${MOB_VIDEO_ROOT}/s${index}r.webm`
}

const doctorVideoIndexBySlide = Object.freeze({
  'slide-1': 1, 'slide-2': 2, 'slide-3': 3, 'slide-4': 4,
  'slide-5': 5, 'slide-6': 6, 'slide-8': 8,
  'slide-9': 9, 'slide-10': 10, 'slide-11': 11, 'slide-16': 12
})

const hasOwnKey = (obj, key) => !!(obj && Object.prototype.hasOwnProperty.call(obj, key))
const isMobileViewport = ref(false)
const mobileMenuOpen = ref(false)
const mobileSectionMenuExpanded = ref(false)
let mobileViewportQuery = null
let mobileViewportChangeHandler = null

const doctorStoryFile = (index, reverse = false) => {
  if (index === 3) return reverse ? 's3r-chip-v80.webm' : 's3-chip-v80.webm'
  if (index === 4) return reverse ? 's4r-chipzoom-v82.webm' : 's4-chipzoom-v82.webm'
  if (index === 5) return reverse ? 's5r-ige-v84.webm' : 's5-ige-v84.webm'
  if (index === 6) return reverse ? 's6r-ccd-v3.webm' : 's6-ccd-v3.webm'
  if (index === 8) return reverse ? 's8r-zoomout-v1.webm' : 's8-zoomout-v1.webm'
  if (index === 9) return reverse ? 's9r-pan-v2.webm' : 's9-pan-v2.webm'
  return reverse ? `s${index}r.webm` : `s${index}.webm`
}

const doctorStoryHasMobile = (index) => (index >= 3 && index <= 6) || index === 8 || index === 9 || index === 10 || index === 11 || index === 12

const doctorForwardPlaybackRate = (src) => {
  const s = String(src || '')
  const isS3 = s.includes('s3-chip') || s.includes('/s3.') || /(^|\/)s3\.webm/.test(s)
  const isS5 = s.includes('s5-ige') || s.includes('/s5.') || /(^|\/)s5\.webm/.test(s)
  if (isMobileViewport.value && (isS3 || isS5)) return 1.4
  if (isS3) return 1.44
  return 1
}


const getSlideVideo = (slide) => {
  if (!slide) return ''
  const doctorVideoIndex = doctorVideoIndexBySlide[slide.id]
  if (isDoctorMode.value && doctorVideoIndex) {
    const file = doctorStoryFile(doctorVideoIndex, false)
    const folder = (isMobileViewport.value && doctorStoryHasMobile(doctorVideoIndex))
      ? 'mob/main'
      : 'main'
    return `${DOCTOR_VIDEO_ROOT}/${folder}/${file}?v=${SITE_VERSION}`
  }
  if (isMobileViewport.value && hasOwnKey(slide, 'mobileVideo')) return slide.mobileVideo
  return slide.video || ''
}

const getSlideReverseVideo = (slide) => {
  if (!slide) return ''
  const doctorVideoIndex = doctorVideoIndexBySlide[slide.id]
  if (isDoctorMode.value && doctorVideoIndex) {
    const file = doctorStoryFile(doctorVideoIndex, true)
    const folder = (isMobileViewport.value && doctorStoryHasMobile(doctorVideoIndex))
      ? 'mob/rev'
      : 'rev'
    return `${DOCTOR_VIDEO_ROOT}/${folder}/${file}?v=${SITE_VERSION}`
  }
  if (isMobileViewport.value && hasOwnKey(slide, 'mobileReverseVideo')) return slide.mobileReverseVideo
  return slide.reverseVideo || ''
}

const hasSlideVideo = (slide) => Boolean(getSlideVideo(slide) || slide?.holdVideo)
const openMobileMenu = () => { ensureSidebarMounted(); mobileMenuOpen.value = true }
const closeMobileMenu = () => { mobileMenuOpen.value = false }

// Показываем, пока соответствующее поле в админке пустое. Тексты совпадают с
// App\Support\HomeSlideCopy — тем, что миграция кладёт в базу.
const SLIDE_COPY_FALLBACK = Object.freeze({
  patient: {
    'slide-1': 'Тест на аллергию ALEX² — один анализ, который даёт ответы',
    'slide-2': 'Почему ALEX2?',
    'slide-8': 'Что вы получите по итогам теста на аллергию',
  },
  doctor: {
    'slide-1': 'Аллергочип ALEX² — расширенный анализ на аллергию. 300+ аллергенов',
    'slide-2': 'ALEX² — лучший тест на аллергию, что есть на рынке.',
    'slide-8': 'Как назначать тест пациентам',
  },
})

const slides = computed(() => {
  const advs = activeSettings.value?.advantages || []
  const res = activeSettings.value?.results || []
  const htp = activeSettings.value?.how_to_pass || []
  const advantageTotal = String(advantageStepTotal(isDoctorMode.value))
  const copy = (field, id) => (activeSettings.value?.[field] || '').trim() || SLIDE_COPY_FALLBACK[isDoctorMode.value ? 'doctor' : 'patient'][id]

  const deck = [
    { id: 'slide-1', label: '', title: copy('hero_title', 'slide-1'), subtitle: (activeSettings.value?.hero_subtitle || '').trim(), direction: 'down', video: '/videos/scroll/forward/scr1.webm', reverseVideo: '/videos/scroll/reverse/scrr1.webm', mobileVideo: phoneVideo(1), mobileReverseVideo: phoneReverseVideo(1) },
    { id: 'slide-2', label: '', title: copy('why_title', 'slide-2'), subtitle: (activeSettings.value?.why_subtitle || '').trim(), direction: 'down', video: '/videos/scroll/forward/scr2.webm', reverseVideo: '/videos/scroll/reverse/scrr2.webm', mobileVideo: phoneVideo(2), mobileReverseVideo: phoneReverseVideo(2) },
    { id: 'slide-3', step: '1', totalSteps: advantageTotal, label: 'Преимущества', title: advs[0]?.title || 'Всё за один сеанс', subtitle: advs[0]?.description || 'За одно исследование аллергочип проверяет реакцию организма сразу на 300 различных веществ...', direction: 'down', video: '/videos/scroll/forward/scr3.webm', reverseVideo: '/videos/scroll/reverse/scrr3-fixed.webm', mobileVideo: phoneVideo(3), mobileReverseVideo: phoneReverseVideo(3) },
    { id: 'slide-4', step: '2', totalSteps: advantageTotal, label: 'Преимущества', title: advs[1]?.title || 'Точечный результат', subtitle: advs[1]?.description || 'В природе многие растения и продукты содержат похожие белки...', direction: 'right', video: '/videos/PC/s4.webm', reverseVideo: '/videos/PC/s4r.webm', mobileVideo: phoneVideo(4), mobileReverseVideo: phoneReverseVideo(4) },
    { id: 'slide-5', step: '3', totalSteps: advantageTotal, label: 'Преимущества', title: advs[2]?.title || 'Отчёт и консультация', subtitle: advs[2]?.description || 'Мы не бросаем человека с непонятными результатами...', direction: 'right', video: '/videos/PC/s4.5-v19.webm', reverseVideo: '/videos/PC/s4.5r-v19.webm', mobileVideo: phoneVideo(5), mobileReverseVideo: phoneReverseVideo(5) },
    { id: 'slide-6', step: '4', totalSteps: advantageTotal, label: 'Преимущества', title: advs[3]?.title || 'Без диет и отмены лекарств', subtitle: advs[3]?.description || 'Анализ можно сдавать на фоне приёма антигистаминных...', direction: 'right', video: '/videos/PC/s5.webm', reverseVideo: '/videos/PC/s5r.webm', mobileVideo: phoneVideo(6), mobileReverseVideo: phoneReverseVideo(6) },
  ]

  if (shouldIncludePatientSlide7(isDoctorMode.value)) {
    deck.push({
      id: 'slide-7',
      step: '5',
      totalSteps: advantageTotal,
      label: 'Преимущества',
      title: advs[4]?.title || 'Подходит детям',
      subtitle: advs[4]?.description || 'Тест можно сдавать с шести месяцев...',
      direction: 'right',
      video: PATIENT_SLIDE_7_VIDEOS.video,
      reverseVideo: PATIENT_SLIDE_7_VIDEOS.reverseVideo,
      mobileVideo: phoneVideo(7),
      mobileReverseVideo: phoneReverseVideo(7),
    })
  }

  deck.push(
    { id: 'slide-8', label: 'О результатах', title: copy('results_intro_title', 'slide-8'), subtitle: '', direction: 'down', video: '/videos/PC/s7-v21.webm', reverseVideo: '/videos/PC/s7r-v21.webm', mobileVideo: phoneVideo(8), mobileReverseVideo: phoneReverseVideo(8), holdVideo: true },
    { id: 'slide-9', step: '1', totalSteps: '3', label: 'О результатах', title: res[0]?.title || 'Аллерго-паспорт', subtitle: res[0]?.description || 'Список из 300 аллергенов...', direction: 'down', video: '/videos/PC/s8-v22.webm', reverseVideo: '/videos/PC/s8r-v22.webm', mobileVideo: phoneVideo(9), mobileReverseVideo: phoneReverseVideo(9) },
    { id: 'slide-10', step: '2', totalSteps: '3', label: 'О результатах', title: res[1]?.title || 'Рекомендации по аллергенам', subtitle: res[1]?.description || 'Список из 300 аллергенов...', direction: 'right', video: '/videos/scroll/forward/s9.webm', reverseVideo: '/videos/scroll/reverse/s9r.webm', mobileVideo: phoneVideo(10), mobileReverseVideo: phoneReverseVideo(10) },
    { id: 'slide-11', step: '3', totalSteps: '3', label: 'О результатах', title: res[2]?.title || 'Консультация', subtitle: res[2]?.description || 'Список из 300 аллергенов...', direction: 'right', video: '/videos/scroll/forward/s10.webm', reverseVideo: '/videos/scroll/reverse/s10r.webm', mobileVideo: phoneVideo(11), mobileReverseVideo: phoneReverseVideo(11), badge: isDoctorMode.value ? '' : 'Опционально' },
    { id: 'slide-16', label: 'FAQ', type: 'faq', title: '', subtitle: '', direction: 'down', video: '/videos/scroll/reverse/s11r.webm', reverseVideo: '/videos/scroll/reverse/s11r.webm', mobileVideo: phoneVideo(12), mobileReverseVideo: phoneReverseVideo(12), mobileBackgroundVideo: phoneVideo(12), holdVideo: true },
    { id: 'slide-17', label: 'Блог про аллергию', type: 'blog', title: '', subtitle: '', direction: 'down', video: '', reverseVideo: '', mobileBackgroundVideo: phoneVideo(1), holdVideo: true },
    { id: 'slide-18', step: '1', totalSteps: '3', label: 'Как сдать тест', title: htp[0]?.title || 'Запишитесь на тест через наш сайт', subtitle: htp[0]?.description || 'А если хотите получить дополнительно консультацию бесплатно — выберите сдачу теста в лабораториях ALEX', direction: 'down', video: '', reverseVideo: '', mobileBackgroundVideo: phoneVideo(1), holdVideo: true },
    { id: 'slide-19', step: '2', totalSteps: '3', label: 'Как сдать тест', title: htp[1]?.title || 'Сдайте кровь в выбранной лаборатории', subtitle: htp[1]?.description || 'Важно: За 4 часа до сдачи крови нужно не кушать. Кровь берут из вены.', direction: 'right', video: '', reverseVideo: '', mobileBackgroundVideo: phoneVideo(1), holdVideo: true },
    { id: 'slide-20', step: '3', totalSteps: '3', label: 'Как сдать тест', title: htp[2]?.title || 'Отслеживайте результат в личном кабинете и на электронной почте', subtitle: htp[2]?.description || 'В личном кабинете показываем статусы — туда же придёт результат. Дополнительно всё дублируем на почту.', direction: 'right', video: '', reverseVideo: '', mobileBackgroundVideo: phoneVideo(1), holdVideo: true },
    { id: 'slide-21', label: 'Контакты', type: 'contacts', title: 'Контакты', subtitle: '', direction: 'down', video: '', reverseVideo: '', mobileBackgroundVideo: phoneVideo(1), holdVideo: true }
  )

  return markRaw(deck)
})

const slideDeckRanges = computed(() => getSlideDeckRanges(slides.value, isDoctorMode.value))

const getSlideTitle = (slide) => slide.title

const getSlideSubtitle = (slide) => slide.subtitle

const showMobileInfoBackground = computed(() => (
    isMobileViewport.value && currentSlideIndex.value >= slideDeckRanges.value.faq
))

const getSlideClass = (index) => {
  if (index === currentSlideIndex.value) return 'active'
  if (index > currentSlideIndex.value) {
    const slide = slides.value[index]
    return slide.direction === 'right' ? 'future-right' : 'future-down'
  }
  if (index < currentSlideIndex.value) {
    const nextSlide = slides.value[index + 1]
    return nextSlide.direction === 'right' ? 'past-left' : 'past-up'
  }
  return ''
}

const navigateToSlideIndex = (index, mode = 'direct') => {
  const targetIndex = Math.max(0, Math.min(index, slides.value.length - 1))
  if (targetIndex === currentSlideIndex.value) return

  navigationMode.value = mode
  currentSlideIndex.value = targetIndex
}

const goToSlideById = (id) => {
  const idx = slides.value.findIndex(s => s.id === id)
  if (idx !== -1) navigateToSlideIndex(idx, 'direct')
}

const goToPersistentStep = (step) => {
  const group = persistentStepGroup.value
  if (!group) return

  const firstSlideIndex = group.type === 'advantages' ? 2 : 7
  navigateToSlideIndex(firstSlideIndex + step - 1, 'direct')
}

const menuItems = computed(() => [
  { label: 'Преимущества', slideId: 'slide-3' },
  { label: 'Результаты', slideId: 'slide-9' },
  { label: 'FAQ', slideId: 'slide-16' },
  { label: 'Блог про аллергию', slideId: 'slide-17' },
  { label: 'Как сдать тест', slideId: 'slide-18' }
])

const activeMenuIndex = computed(() => getActiveMenuIndex(currentSlideIndex.value, slideDeckRanges.value))

const showMenu = computed(() => {
  const currentSlide = slides.value[currentSlideIndex.value]
  return currentSlide?.type !== 'contacts' && activeMenuIndex.value !== -1
})

const mobileSmokeCloudVariant = computed(() => {
  return isLargeMobileCloudIndex(currentSlideIndex.value, slideDeckRanges.value)
      ? 'mobile-cloud-large'
      : 'mobile-cloud-compact'
})

const handleSectionMenuItemClick = (item, idx, event) => {
  if (event?.currentTarget && typeof event.currentTarget.blur === 'function') {
    event.currentTarget.blur()
  }

  if (!isMobileViewport.value) {
    goToSlideById(item.slideId)
    return
  }

  if (!mobileSectionMenuExpanded.value) {
    mobileSectionMenuExpanded.value = true
    return
  }

  if (idx === activeMenuIndex.value || (activeMenuIndex.value === -1 && idx === 0)) {
    mobileSectionMenuExpanded.value = false
    return
  }

  mobileSectionMenuExpanded.value = false
  goToSlideById(item.slideId)
}

const showSmokeCloud = computed(() => {
  const idx = currentSlideIndex.value
  const ranges = slideDeckRanges.value
  if (isMobileViewport.value) {
    return isStoryBackdropIndex(idx, ranges)
  }
  if (isDoctorMode.value) {
    if (!isDoctorSmokeIndex(idx, ranges)) return false
    return contentReadySlideIndex.value === idx
  }
  if (contentReadySlideIndex.value !== idx) return false
  return isStoryBackdropIndex(idx, ranges)
})

const isResultsSection = computed(() => isResultsCloudIndex(currentSlideIndex.value, slideDeckRanges.value))

const resultsCloudVariant = computed(() => resultsCloudClassForIndex(currentSlideIndex.value, slideDeckRanges.value))

const persistentStepGroup = computed(() => getPersistentStepGroup(currentSlideIndex.value, slideDeckRanges.value))

const isVideoBackgroundVisible = computed(() => {
  const slide = slides.value[currentSlideIndex.value]
  // Keep container visible whenever the slide has video so the hero poster
  // paints on SSR/first paint. videoBackgroundReady only toggles poster→video.
  return hasSlideVideo(slide)
})

const CONTENT_EXIT_DURATION = 850
const CONTENT_EXIT_BACK_DURATION = 1400
const VIDEO_CONTENT_REVEAL_TIME = 1.0
const DOCTOR_MOBILE_S8_CONTENT_REVEAL_TIME = 2.5
// Doctor advantages steps 2–4 of 4 (slide-4 panel, slide-5 IgE, slide-6 CCD): hold text during the shot
const DOCTOR_ADVANTAGE_CONTENT_REVEAL_TIME = 2.5
const DOCTOR_DELAYED_TEXT_SLIDE_IDS = new Set(['slide-4', 'slide-5', 'slide-6'])
const VIDEO_CONTAINER_FADE_DURATION = 300
const WHEEL_THRESHOLD = 160
const WHEEL_GESTURE_RESET_DURATION = 200
const WHEEL_COOLDOWN_MS = 420
let wheelAccum = 0
let wheelGestureResetTimer = 0
let wheelCooldownUntil = 0
let contentExitTimer = 0
let resultsIntroTimer = 0
let slideTransitionId = 0
let cancelEarlyContentReveal = () => {}
const exitingContentSlideIndex = ref(-1)
const exitingContentIsBack = ref(false)

const revealSlideContent = (index) => {
  if (currentSlideIndex.value !== index) return
  contentReadySlideIndex.value = index
}

const animateOutgoingContent = (index, { slow = false } = {}) => {
  window.clearTimeout(contentExitTimer)
  exitingContentSlideIndex.value = index
  exitingContentIsBack.value = slow
  const duration = slow ? CONTENT_EXIT_BACK_DURATION : CONTENT_EXIT_DURATION
  contentExitTimer = window.setTimeout(() => {
    if (exitingContentSlideIndex.value === index) {
      exitingContentSlideIndex.value = -1
      exitingContentIsBack.value = false
    }
  }, duration)
}

const normalizeWheelDelta = (e) => {
  if (e.deltaMode === 1) return e.deltaY * 16
  if (e.deltaMode === 2) return e.deltaY * window.innerHeight
  return e.deltaY
}

const goNext = () => {
  if (isSlideTransitioning.value) return
  if (currentSlideIndex.value >= slides.value.length - 1) return
  isSlideTransitioning.value = true
  navigateToSlideIndex(currentSlideIndex.value + 1, 'scroll')
}

const goPrev = () => {
  if (isSlideTransitioning.value) return
  if (currentSlideIndex.value <= 0) return
  isSlideTransitioning.value = true
  navigateToSlideIndex(currentSlideIndex.value - 1, 'scroll')
}

const isWheelInsideNestedScroller = (e) => {
  const el = e.target?.closest?.('.exact-faq-list, .figma-faq-list, .card-container.question-layout')
  if (!el) return false
  if (el.scrollHeight <= el.clientHeight + 1) return false
  const delta = normalizeWheelDelta(e)
  if (!delta) return true
  const atTop = el.scrollTop <= 0
  const atBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - 1
  if (delta < 0 && !atTop) return true
  if (delta > 0 && !atBottom) return true
  return false
}

const handleWheel = (e) => {
  // Stop Mac rubber-band / document scroll on the storytelling canvas.
  // Nested FAQ (and similar) lists may still scroll natively when in bounds.
  const nestedScroll = isWheelInsideNestedScroller(e)
  if (!nestedScroll) {
    e.preventDefault()
  }

  if (isSlideTransitioning.value) return
  if (performance.now() < wheelCooldownUntil) return
  if (nestedScroll) return

  const delta = normalizeWheelDelta(e)
  if (!delta) return

  window.clearTimeout(wheelGestureResetTimer)
  wheelGestureResetTimer = window.setTimeout(() => {
    wheelAccum = 0
  }, WHEEL_GESTURE_RESET_DURATION)

  wheelAccum += delta

  if (wheelAccum > WHEEL_THRESHOLD) {
    wheelAccum = 0
    wheelCooldownUntil = performance.now() + WHEEL_COOLDOWN_MS
    goNext()
  } else if (wheelAccum < -WHEEL_THRESHOLD) {
    wheelAccum = 0
    wheelCooldownUntil = performance.now() + WHEEL_COOLDOWN_MS
    goPrev()
  }
}
const handlePointerDown = (e) => {
  touchStartY = e.clientY
  touchStartX = e.clientX
}

const handlePointerUp = (e) => {
  if (e.pointerType === 'touch') return
  if (isSlideTransitioning.value) return

  const diffY = touchStartY - e.clientY
  const diffX = touchStartX - e.clientX

  // Срабатывание свайпа при перетаскивании мышью или пальцем (> 40px)
  if (Math.abs(diffY) > 40 && Math.abs(diffY) > Math.abs(diffX)) {
    if (diffY > 0) goNext()
    else goPrev()
  }
}
const handleKeydown = (e) => {
  if (isBlogTransitionActive.value) {
    e.preventDefault()
    return
  }
  if (e.repeat) return

  switch (e.key) {
    case 'ArrowDown':
    case 'PageDown':
    case ' ':
      e.preventDefault()
      goNext()
      break
    case 'ArrowUp':
    case 'PageUp':
      e.preventDefault()
      goPrev()
      break
    case 'Home':
      e.preventDefault()
      navigateToSlideIndex(0, 'direct')
      break
    case 'End':
      e.preventDefault()
      navigateToSlideIndex(slides.value.length - 1, 'direct')
      break
  }
}

let touchStartY = 0
let touchStartX = 0
const handleTouchStart = (e) => {
  if (!e.touches || !e.touches.length) return
  touchStartY = e.touches[0].clientY
  touchStartX = e.touches[0].clientX
}

const handleTouchMove = (e) => {}

const handleTouchEnd = (e) => {
  if (!e.changedTouches || !e.changedTouches.length) return
  const touchEndY = e.changedTouches[0].clientY
  const touchEndX = e.changedTouches[0].clientX
  const diffY = touchStartY - touchEndY
  const diffX = touchStartX - touchEndX

  // Порог чувствительности свайпа (40px)
  if (Math.abs(diffY) > 40 && Math.abs(diffY) > Math.abs(diffX)) {
    if (diffY > 0) goNext()
    else goPrev()
  }
}

const getAbsoluteVideoUrl = (src) => {
  if (!src) return ''
  try {
    return new URL(src, window.location.href).href
  } catch (e) {
    return src
  }
}

const videoElementUrl = (video) => String(video?.currentSrc || video?.src || '')
const isDoctorS12FamilyUrl = (url) => /\/s12r?\.webm(?:\?|$)/.test(String(url || ''))
const isDoctorS12ForwardUrl = (url) => /\/s12\.webm(?:\?|$)/.test(String(url || ''))
const isDoctorS11Url = (url) => /\/s11\.webm(?:\?|$)/.test(String(url || ''))
const isDoctorS8Url = (url) => /\/s8-zoomout-v1\.webm(?:\?|$)|\/s8\.webm(?:\?|$)/.test(String(url || ''))

const forceAssignVideoSrc = (video, src) => {
  if (!video || !src) return
  configureVideoElement(video)
  video.pause()
  // Detach any preloaded clip first so stale duration/currentTime cannot leak.
  video.removeAttribute('src')
  video.load()
  video.src = src
  video.load()
}

// Улучшенная безопасная настройка видеоэлемента под iOS Safari
const configureVideoElement = (video) => {
  if (!video) return
  video.muted = true
  video.defaultMuted = true
  video.loop = false
  video.playsInline = true
  video.setAttribute('playsinline', '')
  video.setAttribute('webkit-playsinline', 'true')
  video.disablePictureInPicture = true
}

// Надежная обработка .play() для предотвращения зависаний в iOS Safari
const safePlayVideo = async (videoEl) => {
  if (!videoEl) return false
  configureVideoElement(videoEl)
  try {
    const playPromise = videoEl.play()
    if (playPromise !== undefined) {
      await playPromise
    }
    return true
  } catch (error) {
    // iOS/Safari autoplay block: unlock on first gesture, but do not pretend play succeeded.
    const handleTouchStartPlay = () => {
      videoEl.play().catch(() => {})
      window.removeEventListener('touchstart', handleTouchStartPlay)
    }
    window.addEventListener('touchstart', handleTouchStartPlay, { once: true })
    return false
  }
}

const revealContentAtVideoProgress = (
    video,
    slideIndex,
    requestId,
    reveal,
    { targetTime: requestedTargetTime } = {}
) => {
  cancelEarlyContentReveal()

  const duration = Number.isFinite(video.duration) ? video.duration : 0
  if (duration <= 0) {
    reveal()
    return
  }

  const targetTime = Number.isFinite(requestedTargetTime)
      ? Math.max(0, Math.min(requestedTargetTime, duration))
      : Math.min(VIDEO_CONTENT_REVEAL_TIME, duration)
  let fallbackTimer = 0
  let cancelled = false

  const cleanup = () => {
    window.clearTimeout(fallbackTimer)
  }

  const checkProgress = () => {
    if (cancelled || requestId !== videoRequestId || currentSlideIndex.value !== slideIndex) {
      cleanup()
      return
    }

    if (video.currentTime >= targetTime || video.ended) {
      cleanup()
      reveal()
      return
    }

    fallbackTimer = window.setTimeout(checkProgress, 150)
  }

  cancelEarlyContentReveal = () => {
    cancelled = true
    cleanup()
  }
  checkProgress()
}

const preloadUpcomingVideo = () => {
  const inactiveEl = activeVideoElement.value === 'A' ? videoB.value : videoA.value
  if (!inactiveEl) return

  let nextSrc = ''
  const currentSlide = slides.value[currentSlideIndex.value]
  const currentReverseSrc = getSlideReverseVideo(currentSlide)
  if (isDoctorMode.value && currentSlide?.id === 'slide-16') {
    // FAQ reverse (s12r) is never played; preloading it races the p.3 settle
    // and can flash Babutin (last frame of s12r / first of s12).
    const resultsPoint3 = slides.value.find((s) => s.id === 'slide-11')
    nextSrc = getSlideVideo(resultsPoint3)
  } else if (isDoctorMode.value && isMobileViewport.value && currentSlide?.id === 'slide-6') {
    // Next is Results intro (s8). Preloading s6r leaves the chip on the
    // inactive layer and races the mobile s8 settle.
    const resultsIntro = slides.value.find((s) => s.id === 'slide-8')
    nextSrc = getSlideVideo(resultsIntro)
  } else if (currentSlideIndex.value > 0 && currentReverseSrc) {
    nextSrc = currentReverseSrc
  } else {
    for (let index = currentSlideIndex.value + 1; index < slides.value.length; index++) {
      const nextSlide = slides.value[index]
      const nextVideoSrc = getSlideVideo(nextSlide)
      if (nextVideoSrc) {
        nextSrc = nextVideoSrc
        break
      }
      if (!nextSlide.holdVideo) break
    }
  }

  if (!nextSrc || inactiveEl.src === getAbsoluteVideoUrl(nextSrc)) return

  inactiveEl.pause()
  configureVideoElement(inactiveEl)
  inactiveEl.src = nextSrc
  inactiveEl.load()
}

const finishVideoLayerSwap = async (outgoingEl, requestId, { preload = false } = {}) => {
  await nextTick()
  if (requestId !== videoRequestId || !outgoingEl) return

  await new Promise((resolve) => {
    requestAnimationFrame(() => requestAnimationFrame(resolve))
  })
  if (requestId !== videoRequestId) return

  outgoingEl.pause()
  if (
    (outgoingEl === videoA.value && holdingVideoElement.value === 'A')
    || (outgoingEl === videoB.value && holdingVideoElement.value === 'B')
  ) {
    holdingVideoElement.value = null
  }

  if (preload) {
    preloadUpcomingVideo()
  }
}

const getSlideBackgroundSource = (slideIndex) => {
  const slide = slides.value[slideIndex]
  if (!slide) return ''
  if (isMobileViewport.value && slide.mobileBackgroundVideo) {
    return slide.mobileBackgroundVideo
  }
  const slideVideoSrc = getSlideVideo(slide)
  if (slideVideoSrc) return slideVideoSrc
  if (!slide.holdVideo) return ''

  for (let index = slideIndex - 1; index >= 0; index--) {
    const previousSlide = slides.value[index]
    const previousVideoSrc = getSlideVideo(previousSlide)
    if (previousVideoSrc) return previousVideoSrc
    if (!previousSlide?.holdVideo) break
  }

  return ''
}

const settleWithoutVideo = async (requestId) => {
  videoBackgroundReady.value = false
  videoA.value?.pause()
  videoB.value?.pause()
  await new Promise(resolve => window.setTimeout(resolve, VIDEO_CONTAINER_FADE_DURATION))
  if (requestId !== videoRequestId) return
}

const waitForDecodedFrame = (video, { ignoreCurrent = false } = {}) => new Promise((resolve) => {
  if (!video) {
    resolve()
    return
  }

  let settled = false
  const finish = () => {
    if (settled) return
    settled = true
    video.removeEventListener('loadeddata', onReady)
    video.removeEventListener('canplay', onReady)
    window.clearTimeout(safety)
    resolve()
  }

  const onReady = () => {
    if (video.readyState < 2 || video.videoWidth <= 0) return
    if (typeof video.requestVideoFrameCallback === 'function') {
      video.requestVideoFrameCallback(() => finish())
      return
    }
    finish()
  }

  const safety = window.setTimeout(finish, 450)

  if (!ignoreCurrent && video.readyState >= 2 && video.videoWidth > 0) {
    onReady()
    return
  }

  video.addEventListener('loadeddata', onReady)
  video.addEventListener('canplay', onReady)
})

const seekToLastFrame = (video) => new Promise((resolve) => {
  if (!video) {
    resolve()
    return
  }

  let settled = false
  const finish = () => {
    if (settled) return
    settled = true
    video.removeEventListener('loadedmetadata', onMeta)
    video.removeEventListener('seeked', onSeeked)
    window.clearTimeout(safety)
    resolve()
  }

  const onSeeked = () => {
    if (typeof video.requestVideoFrameCallback === 'function') {
      video.requestVideoFrameCallback(() => finish())
      return
    }
    finish()
  }

  const apply = () => {
    const duration = Number.isFinite(video.duration) ? video.duration : 0
    if (duration <= 0.05) {
      finish()
      return
    }
    video.addEventListener('seeked', onSeeked)
    video.currentTime = Math.max(0, duration - 0.04)
  }

  const onMeta = () => apply()
  const safety = window.setTimeout(finish, 400)

  if (Number.isFinite(video.duration) && video.duration > 0) {
    apply()
    return
  }

  video.addEventListener('loadedmetadata', onMeta)
})

/** Park s12 on its last (FAQ beige) frame. s12r last frame is Babutin — park at t=0. */
const pinDoctorFaqHeldFrame = async (video) => {
  if (!video || !isDoctorS12FamilyUrl(videoElementUrl(video))) return
  video.pause()
  if (isDoctorS12ForwardUrl(videoElementUrl(video))) {
    await seekToLastFrame(video)
  } else if (video.currentTime > 0.001) {
    await new Promise((resolve) => {
      const t = window.setTimeout(resolve, 250)
      const onSeeked = () => {
        video.removeEventListener('seeked', onSeeked)
        window.clearTimeout(t)
        resolve()
      }
      video.addEventListener('seeked', onSeeked)
      video.currentTime = 0
    })
  }
  video.pause()
}

const attachPinDoctorS12OnEnded = (video) => {
  if (!video || !isDoctorS12ForwardUrl(videoElementUrl(video) || video.src)) return
  const pin = () => { void pinDoctorFaqHeldFrame(video) }
  video.addEventListener('ended', pin, { once: true })
  if (video.ended) pin()
}

/** After s8-zoomout plays, stay on the QR last frame — do not snap back to the CCD chip. */
const pinDoctorS8HeldFrame = async (video) => {
  if (!video || !isDoctorS8Url(videoElementUrl(video))) return
  video.pause()
  await seekToLastFrame(video)
  video.pause()
}

const attachPinDoctorS8OnEnded = (video) => {
  if (!video || !isDoctorS8Url(videoElementUrl(video) || video.src)) return
  const pin = () => { void pinDoctorS8HeldFrame(video) }
  video.addEventListener('ended', pin, { once: true })
  if (video.ended) pin()
}

const playVideoShot = async (src, { playbackRate = 1, onPlaybackStarted, onComplete } = {}) => {
  if (!src) { onComplete?.(); return }

  const requestId = ++videoRequestId
  const activeEl = activeVideoElement.value === 'A' ? videoA.value : videoB.value
  const incomingEl = activeVideoElement.value === 'A' ? videoB.value : videoA.value

  if (!activeEl || !incomingEl) { onComplete?.(); return }

  try {
    configureVideoElement(incomingEl)

    if (incomingEl.src !== getAbsoluteVideoUrl(src)) {
      incomingEl.src = src
      incomingEl.load()
    }

    incomingEl.playbackRate = playbackRate

    await waitForDecodedFrame(incomingEl)
    if (requestId !== videoRequestId) return

    // Reverse default 3; s3r uses 7; keep 1x for already-very-short clips (e.g. s8r ~0.43s).
    if (playbackRate > 1 && Number.isFinite(incomingEl.duration) && incomingEl.duration < 0.6) {
      incomingEl.playbackRate = 1
    }

    // Always start the shot from the first frame. A preloaded reverse (or a
    // clip that already ended) is already at the last frame — play() then looks frozen.
    if (incomingEl.currentTime > 0.001) {
      incomingEl.currentTime = 0
      await new Promise((resolve) => {
        const t = window.setTimeout(resolve, 250)
        const onSeeked = () => {
          incomingEl.removeEventListener('seeked', onSeeked)
          window.clearTimeout(t)
          resolve()
        }
        incomingEl.addEventListener('seeked', onSeeked)
      })
    }
    if (requestId !== videoRequestId) return

    const played = await safePlayVideo(incomingEl)
    if (requestId !== videoRequestId) return

    // Autoplay may be blocked, but a decoded frame is enough to leave the poster
    // and keep storytelling transitions unlocked.
    if (!played && incomingEl.readyState < 2) {
      onComplete?.()
      return
    }

    if (typeof incomingEl.requestVideoFrameCallback === 'function') {
      await new Promise((resolve) => {
        const t = window.setTimeout(resolve, 200)
        incomingEl.requestVideoFrameCallback(() => {
          window.clearTimeout(t)
          resolve()
        })
      })
    }
    if (requestId !== videoRequestId) return

    videoBackgroundReady.value = true
    holdingVideoElement.value = activeVideoElement.value
    activeVideoElement.value = activeVideoElement.value === 'A' ? 'B' : 'A'
    finishVideoLayerSwap(activeEl, requestId, { preload: true })
    attachPinDoctorS12OnEnded(incomingEl)
    attachPinDoctorS8OnEnded(incomingEl)

    onPlaybackStarted?.(incomingEl, requestId)
    if (!played) {
      if (isDoctorS8Url(videoElementUrl(incomingEl) || incomingEl.src)) {
        await pinDoctorS8HeldFrame(incomingEl)
      }
      // Content can reveal; actual playback resumes on first gesture via safePlayVideo.
      onComplete?.()
    }
  } catch (error) {
    if (requestId === videoRequestId) {
      videoBackgroundReady.value = false
      onComplete?.()
    }
  }
}

const settleOnSlideFrame = async (src, requestId) => {
  if (!src || requestId !== videoRequestId) return

  const activeEl = activeVideoElement.value === 'A' ? videoA.value : videoB.value
  const stillEl = activeVideoElement.value === 'A' ? videoB.value : videoA.value
  if (!activeEl || !stillEl) return

  try {
    configureVideoElement(stillEl)

    const srcChanged = stillEl.src !== getAbsoluteVideoUrl(src)
    if (srcChanged) {
      stillEl.src = src
      stillEl.load()
    }

    await waitForDecodedFrame(stillEl, { ignoreCurrent: srcChanged })
    if (requestId !== videoRequestId) return

    await seekToLastFrame(stillEl)
    if (requestId !== videoRequestId) return

    videoBackgroundReady.value = true
    holdingVideoElement.value = activeVideoElement.value
    activeVideoElement.value = activeVideoElement.value === 'A' ? 'B' : 'A'
    finishVideoLayerSwap(activeEl, requestId, { preload: true })
  } catch (error) {
    if (requestId === videoRequestId) {
      await settleWithoutVideo(requestId)
    }
  }
}

/** FAQ → Results p.3: never show s12 / s12r (Babutin). Only last frame of s11 (mob on mobile). */
const settleDoctorFaqToResultsPoint3 = async (src, requestId) => {
  if (!src || requestId !== videoRequestId) return

  const activeEl = activeVideoElement.value === 'A' ? videoA.value : videoB.value
  const stillEl = activeVideoElement.value === 'A' ? videoB.value : videoA.value
  if (!activeEl || !stillEl) return

  const stillReadyAsS11 = () => (
    isDoctorS11Url(videoElementUrl(stillEl))
    && stillEl.src === getAbsoluteVideoUrl(src)
    && !isDoctorS12FamilyUrl(videoElementUrl(stillEl))
  )

  const ensureStillIsS11 = async () => {
    for (let attempt = 0; attempt < 3; attempt++) {
      if (requestId !== videoRequestId) return false
      if (!stillReadyAsS11()) {
        forceAssignVideoSrc(stillEl, src)
      } else {
        configureVideoElement(stillEl)
        stillEl.pause()
      }
      await waitForDecodedFrame(stillEl, { ignoreCurrent: !stillReadyAsS11() })
      if (requestId !== videoRequestId) return false
      if (stillReadyAsS11()) return true
    }
    return stillReadyAsS11()
  }

  try {
    // Pin FAQ beige (s12 last / s12r first) so a t≈0 snap cannot flash Babutin.
    if (isDoctorS12FamilyUrl(videoElementUrl(activeEl))) {
      await pinDoctorFaqHeldFrame(activeEl)
      if (requestId !== videoRequestId) return
    }

    // Keep that FAQ frame on the active layer only. Do not hold s12 during
    // the swap — holding a t=0 s12 layer would flash Babutin.
    holdingVideoElement.value = null
    stillEl.pause()

    if (!await ensureStillIsS11()) {
      return
    }

    await seekToLastFrame(stillEl)
    if (requestId !== videoRequestId) return

    if (!stillReadyAsS11()) {
      if (!await ensureStillIsS11()) return
      await seekToLastFrame(stillEl)
      if (requestId !== videoRequestId) return
      if (!stillReadyAsS11()) return
    }

    const stillLetter = activeVideoElement.value === 'A' ? 'B' : 'A'
    videoBackgroundReady.value = true
    holdingVideoElement.value = null
    activeVideoElement.value = stillLetter
    finishVideoLayerSwap(activeEl, requestId, { preload: true })
  } catch (error) {
    // Keep the FAQ frame; never blank the background on this path.
  }
}

/** Mobile doctor slide-8 direct-jump only: last frame of s8 (QR), skip playing the zoom-out. */
const settleDoctorMobileResultsIntro = async (src, requestId) => {
  if (!src || requestId !== videoRequestId) return

  const activeEl = activeVideoElement.value === 'A' ? videoA.value : videoB.value
  const stillEl = activeVideoElement.value === 'A' ? videoB.value : videoA.value
  if (!activeEl || !stillEl) return

  const stillReadyAsS8 = () => (
    isDoctorS8Url(videoElementUrl(stillEl))
    && stillEl.src === getAbsoluteVideoUrl(src)
  )

  const ensureStillIsS8 = async () => {
    for (let attempt = 0; attempt < 3; attempt++) {
      if (requestId !== videoRequestId) return false
      if (!stillReadyAsS8()) {
        forceAssignVideoSrc(stillEl, src)
      } else {
        configureVideoElement(stillEl)
        stillEl.pause()
      }
      await waitForDecodedFrame(stillEl, { ignoreCurrent: !stillReadyAsS8() })
      if (requestId !== videoRequestId) return false
      if (stillReadyAsS8()) return true
    }
    return stillReadyAsS8()
  }

  try {
    holdingVideoElement.value = null
    stillEl.pause()

    if (!await ensureStillIsS8()) {
      return
    }

    await seekToLastFrame(stillEl)
    if (requestId !== videoRequestId) return
    if (!stillReadyAsS8()) {
      if (!await ensureStillIsS8()) return
      await seekToLastFrame(stillEl)
      if (requestId !== videoRequestId) return
      if (!stillReadyAsS8()) return
    }

    const stillLetter = activeVideoElement.value === 'A' ? 'B' : 'A'
    videoBackgroundReady.value = true
    holdingVideoElement.value = null
    activeVideoElement.value = stillLetter
    finishVideoLayerSwap(activeEl, requestId, { preload: true })
  } catch (error) {
    // Keep the current frame; do not blank the background.
  }
}

watch(isDoctorMode, async () => {
  suppressHeldVideoFrame.value = false
  await nextTick()
  const currentSlide = slides.value[currentSlideIndex.value]
  const src = getSlideVideo(currentSlide)

  if (src) {
    playVideoShot(src)
    return
  }

  const requestId = ++videoRequestId
  const backgroundSrc = getSlideBackgroundSource(currentSlideIndex.value)
  if (backgroundSrc) settleOnSlideFrame(backgroundSrc, requestId)
  else settleWithoutVideo(requestId)
})

const isFaqForwardTransitionActive = ref(false)
const isFaqReverseTransitionActive = ref(false)

const EDITORIAL_SLIDE_IDS = new Set(['slide-17', 'slide-18', 'slide-19', 'slide-20', 'slide-21'])
const isEditorialSlide = (slide) => EDITORIAL_SLIDE_IDS.has(slide?.id)
const hasDesktopEditorialStill = (slide) => !isMobileViewport.value && isEditorialSlide(slide)

watch(currentSlideIndex, (idx) => {
  // Client-only scroll hint: only on landing slide.
  if (idx === 0) {
    scrollHintReady.value = true
  } else {
    scrollHintReady.value = false
  }
})

watch(currentSlideIndex, (newIndex, oldIndex) => {
  if (newIndex >= slideDeckRanges.value.faq) ensureBelowFoldMounted()
  mobileSectionMenuExpanded.value = false
  suppressHeldVideoFrame.value = false
  const transitionId = ++slideTransitionId

  const goingBack = newIndex < oldIndex
  const slide = slides.value[newIndex]
  const oldSlide = slides.value[oldIndex]
  const delayDoctorAdvantageText = (
    !goingBack
    && isDoctorMode.value
    && DOCTOR_DELAYED_TEXT_SLIDE_IDS.has(slide?.id)
  )
  const isDoctorMobileResultsIntro = (
    isDoctorMode.value
    && isMobileViewport.value
    && slide?.id === 'slide-8'
    && !goingBack
  )
  const safetyFallbackMs = isDoctorMobileResultsIntro
    ? Math.ceil(DOCTOR_MOBILE_S8_CONTENT_REVEAL_TIME * 1000) + 800
    : delayDoctorAdvantageText
      ? Math.ceil(DOCTOR_ADVANTAGE_CONTENT_REVEAL_TIME * 1000) + 800
      : isMobileViewport.value
        ? (goingBack ? 500 : 700)
        : (goingBack ? 1200 : 900)
  const safetyFallbackTimer = setTimeout(() => {
    if (transitionId === slideTransitionId) {
      contentReadySlideIndex.value = newIndex
      isSlideTransitioning.value = false
    }
  }, safetyFallbackMs)

  const transitionStartedAt = performance.now()
  let transitionFinishing = false
  const slideVideoSrc = getSlideVideo(slide)
  const oldSlideReverseSrc = getSlideReverseVideo(oldSlide)
  const isDirectJump = navigationMode.value === 'direct' && Math.abs(newIndex - oldIndex) > 1
  const isHowToSection = (idx) => isHowToPassIndex(idx, slideDeckRanges.value)

  if (isHowToSection(newIndex)) {
    cancelEarlyContentReveal()
    clearTimeout(safetyFallbackTimer)
    contentReadySlideIndex.value = newIndex
    // First menu entry used to fall through, reset contentReady to -1, and
    // settle a previous FAQ frame — leaving editorial beige with no how-to body.
    window.clearTimeout(contentExitTimer)
    exitingContentSlideIndex.value = -1
    exitingContentIsBack.value = false
    const intraHowTo = isHowToSection(oldIndex)
    // Desktop wheel already has a cooldown, so unlocking immediately is fine.
    // On mobile, 1.0.33 unlocked instantly and one gesture (touchend + pointerup
    // or touchend + wheel) consumed two how-to steps (1 → 3). Keep the lock.
    if (!(isMobileViewport.value && intraHowTo)) {
      isSlideTransitioning.value = false
    } else {
      window.setTimeout(() => {
        if (transitionId === slideTransitionId) {
          isSlideTransitioning.value = false
        }
      }, WHEEL_COOLDOWN_MS)
    }
    return
  }

  const destinationHasVideo = hasSlideVideo(slide)
  const originHasVideo = hasSlideVideo(oldSlide)
  if (!destinationHasVideo || !originHasVideo) videoBackgroundReady.value = false
  isSlideTransitioning.value = true

  const isCurrentTransition = () => (
      transitionId === slideTransitionId
      && currentSlideIndex.value === newIndex
  )

  const waitForOutgoingContent = async () => {
    const exitMs = goingBack ? 350 : CONTENT_EXIT_DURATION
    const remaining = exitMs - (performance.now() - transitionStartedAt)
    if (remaining > 0) {
      await new Promise(resolve => window.setTimeout(resolve, remaining))
    }
  }

  const finishCurrentTransition = async () => {
    if (transitionFinishing) return
    transitionFinishing = true
    await waitForOutgoingContent()
    if (!isCurrentTransition()) return
    await revealCurrentSlide()
  }

  const revealDuringPlayback = (video, requestId) => {
    if (!isCurrentTransition()) return
    const slideId = slides.value[newIndex]?.id
    const targetTime = (
      !goingBack
      && isDoctorMode.value
      && DOCTOR_DELAYED_TEXT_SLIDE_IDS.has(slideId)
    )
      ? DOCTOR_ADVANTAGE_CONTENT_REVEAL_TIME
      : (
        !goingBack
        && isDoctorMode.value
        && isMobileViewport.value
        && slideId === 'slide-8'
      )
        ? DOCTOR_MOBILE_S8_CONTENT_REVEAL_TIME
        : VIDEO_CONTENT_REVEAL_TIME
    revealContentAtVideoProgress(video, newIndex, requestId, revealCurrentSlide, {
      targetTime
    })
  }

  const revealCurrentSlide = async () => {
    clearTimeout(safetyFallbackTimer)
    await waitForOutgoingContent()
    if (!isCurrentTransition()) return
    if (contentReadySlideIndex.value !== newIndex) revealSlideContent(newIndex)
    isSlideTransitioning.value = false
  }

  cancelEarlyContentReveal()
  window.clearTimeout(resultsIntroTimer)
  animateOutgoingContent(oldIndex, { slow: goingBack })
  contentReadySlideIndex.value = -1

  if (isDirectJump) {
    if (isDoctorMobileResultsIntro && slideVideoSrc) {
      const requestId = ++videoRequestId
      settleDoctorMobileResultsIntro(slideVideoSrc, requestId)
        .finally(finishCurrentTransition)
      return
    }
    if (slideVideoSrc) {
      playVideoShot(slideVideoSrc, {
        playbackRate: doctorForwardPlaybackRate(slideVideoSrc),
        onPlaybackStarted: revealDuringPlayback,
        onComplete: finishCurrentTransition
      })
    } else {
      const requestId = ++videoRequestId
      const backgroundSrc = getSlideBackgroundSource(newIndex)
      const settle = backgroundSrc
          ? settleOnSlideFrame(backgroundSrc, requestId)
          : settleWithoutVideo(requestId)
      settle.finally(finishCurrentTransition)
    }
    return
  }

  if (goingBack) {
    const isDoctorBlogToFaq = (
        isDoctorMode.value
        && oldSlide?.id === 'slide-17'
        && slide?.id === 'slide-16'
    )

    if (isDoctorBlogToFaq) {
      ++videoRequestId
      videoA.value?.pause()
      videoB.value?.pause()
      videoBackgroundReady.value = true
      suppressHeldVideoFrame.value = true
      finishCurrentTransition()
      return
    }

    // Doctor FAQ → Results point 3: skip s12r (FAQ reverse) on desktop and mobile.
    // Forward point 3 → FAQ still plays s12 via the normal slideVideoSrc path.
    const isDoctorFaqToResultsPoint3 = (
        isDoctorMode.value
        && oldSlide?.id === 'slide-16'
        && slide?.id === 'slide-11'
    )

    if (isDoctorFaqToResultsPoint3) {
      const requestId = ++videoRequestId
      // Keep FAQ held frame visible until s11 last frame is ready — do not
      // suppress layers (that flashes empty beige) and do not settleWithoutVideo.
      const destSrc = getSlideVideo(slide)
      const settle = destSrc
          ? settleDoctorFaqToResultsPoint3(destSrc, requestId)
          : Promise.resolve()
      settle.finally(finishCurrentTransition)
      return
    }

    if (oldSlideReverseSrc) {
      playVideoShot(oldSlideReverseSrc, {
        playbackRate: String(oldSlideReverseSrc).includes('s3r') ? 7 : 3,
        onPlaybackStarted: revealDuringPlayback,
        onComplete: finishCurrentTransition
      })
    } else {
      const requestId = ++videoRequestId
      const backgroundSrc = getSlideBackgroundSource(newIndex)
      const settle = backgroundSrc
          ? settleOnSlideFrame(backgroundSrc, requestId)
          : settleWithoutVideo(requestId)
      settle.finally(finishCurrentTransition)
    }
    return
  }

  if (slideVideoSrc) {
    playVideoShot(slideVideoSrc, {
        playbackRate: doctorForwardPlaybackRate(slideVideoSrc),
      onPlaybackStarted: revealDuringPlayback,
      onComplete: finishCurrentTransition
    })
  } else if (!slide.holdVideo) {
    const requestId = ++videoRequestId
    settleWithoutVideo(requestId).finally(finishCurrentTransition)
  } else {
    const mobileBackgroundSrc = isMobileViewport.value
        ? slide.mobileBackgroundVideo
        : ''

    if (mobileBackgroundSrc) {
      const requestId = ++videoRequestId
      settleOnSlideFrame(mobileBackgroundSrc, requestId).finally(finishCurrentTransition)
    } else {
      finishCurrentTransition()
    }
  }
})

// 1.0.75: revert QR chrome experiments. Dock stays in-shell (1.0.68).
// Minimal fix only: client-only scroll-hint (v-if) + optional scroll reset on load.
const resetHomeScroll = () => {
  try {
    if ('scrollRestoration' in history) {
      history.scrollRestoration = 'manual'
    }
  } catch (_) { /* ignore */ }
  try {
    window.scrollTo(0, 0)
    document.documentElement.scrollTop = 0
    document.body.scrollTop = 0
    const container = scrollContainer.value
    if (container) container.scrollTop = 0
  } catch (_) { /* ignore */ }
}

// Обработка видимости и восстановления страницы на iOS при возврате назад
const handleVisibilityOrPageShow = (event) => {
  const fromBfCache = event && event.type === 'pageshow' && event.persisted
  if (document.visibilityState === 'visible' || fromBfCache) {
    resetHomeScroll()
    if (currentSlideIndex.value === 0) {
      scrollHintReady.value = true
    }
    const activeEl = activeVideoElement.value === 'A' ? videoA.value : videoB.value
    if (activeEl) {
      safePlayVideo(activeEl)
    }
  }
}

onMounted(() => {
  mobileViewportQuery = window.matchMedia('(max-width: 1024px)')
  isMobileViewport.value = mobileViewportQuery.matches
  mobileViewportChangeHandler = (event) => {
    isMobileViewport.value = event.matches
  }
  mobileViewportQuery.addEventListener('change', mobileViewportChangeHandler)

  // Добавляем слушатели возврата со страницы для iOS Safari
  document.addEventListener('visibilitychange', handleVisibilityOrPageShow)
  window.addEventListener('pageshow', handleVisibilityOrPageShow)

  resetHomeScroll()

  nextTick(() => {
    resetHomeScroll()
    // Show scroll-hint after mount with original CSS position (no JS top hacks).
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        if (currentSlideIndex.value === 0) {
          scrollHintReady.value = true
        }
      })
    })
    const attachScrollHandlers = () => {
      const container = scrollContainer.value
      if (!container) return false
      container.addEventListener('wheel', handleWheel, { passive: false })
      container.addEventListener('touchstart', handleTouchStart, { passive: true })
      container.addEventListener('touchmove', handleTouchMove, { passive: true })
      container.addEventListener('touchend', handleTouchEnd, { passive: true })
      window.addEventListener('pointerdown', handlePointerDown)
      window.addEventListener('pointerup', handlePointerUp)
      window.addEventListener('keydown', handleKeydown)
      return true
    }
    if (!attachScrollHandlers()) {
      requestAnimationFrame(() => {
        if (!attachScrollHandlers()) window.setTimeout(attachScrollHandlers, 50)
      })
    }


      let heroVideoArmed = false
      const startInitialHeroVideo = () => {
        if (heroVideoArmed) return
        // Only arm the landing hero. Later slides are owned by the slide watcher.
        if (currentSlideIndex.value !== 0) return
        const slide = slides.value[currentSlideIndex.value]
        const initialSrc = getSlideVideo(slide)
        if (!initialSrc || !videoA.value || !videoB.value) return
        heroVideoArmed = true
        // playVideoShot waits for a decoded frame before flipping videoBackgroundReady,
        // so the poster stays until a real frame exists (no empty active <video> cover).
        playVideoShot(initialSrc)
      }
      const idle = window.requestIdleCallback || ((cb, opts) => window.setTimeout(cb, (opts && opts.timeout) || 1200))
      // Delay only decode/start so poster wins LCP/FCP; scroll handlers already attached.
      // Gestures still arm immediately via armOnFirstGesture. Never flip videoBackgroundReady
      // here — playVideoShot waits for a real decoded frame (no empty active video over poster).
      const scheduleHeroVideoAfterFirstPaint = () => {
        const kick = () => window.setTimeout(() => startInitialHeroVideo(), 400)
        if (typeof window.requestAnimationFrame === "function") {
          window.requestAnimationFrame(() => window.requestAnimationFrame(kick))
        } else {
          kick()
        }
      }
      scheduleHeroVideoAfterFirstPaint()
      idle(() => startInitialHeroVideo(), { timeout: 1600 })
      idle(() => { ensureSidebarMounted() }, { timeout: 2200 })
      idle(() => { ensureBelowFoldMounted() }, { timeout: 2800 })
      window.addEventListener('load', () => {
        ensureSidebarMounted()
        ensureBelowFoldMounted()
      }, { once: true })
      const armOnFirstGesture = () => {
        startInitialHeroVideo()
        ensureSidebarMounted()
        // Keep Contacts/Info off the first interaction path; mount on scroll near them.
      }
      window.addEventListener('pointerdown', armOnFirstGesture, { once: true, passive: true })
      window.addEventListener('touchstart', armOnFirstGesture, { once: true, passive: true })
      window.addEventListener('wheel', armOnFirstGesture, { once: true, passive: true })

  })
})

onUnmounted(() => {
  const container = scrollContainer.value
  if (container) {
    container.removeEventListener('wheel', handleWheel)
    container.removeEventListener('touchstart', handleTouchStart)
    container.removeEventListener('touchmove', handleTouchMove)
    container.removeEventListener('touchend', handleTouchEnd)
  }
  window.removeEventListener('pointerdown', handlePointerDown)
  window.removeEventListener('pointerup', handlePointerUp)
  window.removeEventListener('keydown', handleKeydown)
  document.removeEventListener('visibilitychange', handleVisibilityOrPageShow)
  window.removeEventListener('pageshow', handleVisibilityOrPageShow)
  scrollHintReady.value = false

  if (mobileViewportQuery && mobileViewportChangeHandler) {
    mobileViewportQuery.removeEventListener('change', mobileViewportChangeHandler)
  }

  if (videoA.value) videoA.value.pause()
  if (videoB.value) videoB.value.pause()
  window.clearTimeout(wheelGestureResetTimer)
  window.clearTimeout(contentExitTimer)
  cancelEarlyContentReveal()
  window.clearTimeout(resultsIntroTimer)
  slideTransitionId++
  videoRequestId++
})

const formattedBlogs = computed(() => {
  const getFirstRole = (roleText) => {
    if (!roleText) return ''
    const firstLine = roleText.split(/[\n\r,./;]/)[0]?.trim()
    return firstLine || roleText.trim()
  }

  return props.featuredBlogs.map(post => {
    const hasCover = post.preview_image && post.preview_image !== '0' && post.preview_image !== 0
    const coverPath = hasCover
        ? (post.preview_image.startsWith('http') || post.preview_image.startsWith('/')
            ? post.preview_image
            : `/storage/${post.preview_image}`)
        : ''

    const hasPublicAuthor = Boolean(post.author?.id)
    const hasAvatar = hasPublicAuthor && post.author?.avatar && post.author.avatar !== '0' && post.author.avatar !== 0
    const avatarPath = hasAvatar
        ? (post.author.avatar.startsWith('http') || post.author.avatar.startsWith('/')
            ? post.author.avatar
            : `/storage/${post.author.avatar}`)
        : ''

    const rawRole = hasPublicAuthor ? (post.author?.description || post.author?.bio) : ''
    const firstRole = hasPublicAuthor ? (getFirstRole(rawRole) || 'Профессор МГУ') : ''

    const sizedVariant = (path, width) => {
      if (!path || path.startsWith('http')) return ''
      const q = path.indexOf('?')
      const clean = q === -1 ? path : path.slice(0, q)
      // Srcset variants exist only for WebP masters (GIF kept as .gif, no -Nw.webp)
      if (!/\.webp$/i.test(clean)) return ''
      const dot = clean.lastIndexOf('.')
      if (dot === -1) return ''
      return `${clean.slice(0, dot)}-${width}w.webp`
    }

    const cover640 = sizedVariant(coverPath, 640)
    const cover960 = sizedVariant(coverPath, 960)
    const coverSrcset = coverPath
      ? [cover640 && `${cover640} 640w`, cover960 && `${cover960} 960w`, `${coverPath} 1200w`].filter(Boolean).join(', ')
      : ''

    const avatar96 = sizedVariant(avatarPath, 96)
    const authorAvatarDisplay = avatar96 || avatarPath
    const authorAvatarSrcset = avatar96
      ? `${avatar96} 96w, ${avatarPath} 541w`
      : ''

    return {
      id: post.id,
      title: post.title,
      slug: post.slug,
      description: post.excerpt || '',
      cover: cover640 || coverPath,
      coverSrcset,
      coverSizes: '(max-width: 1024px) 92vw, 420px',
      read_time: (() => {
        if (!post.duration) return '~16 минут'
        const raw = String(post.duration).trim()
        if (/мин/i.test(raw)) {
          return raw.startsWith('~') ? raw : `~${raw}`
        }
        return `~${raw} мин`
      })(),
      category: post.category?.name || 'Наука',
      author_name: hasPublicAuthor ? post.author.name : '',
      author_role: firstRole,
      author_avatar: avatarPath,
      authorAvatarDisplay,
      authorAvatarSrcset,
    }
  })
})

const isMobile = ref(false)

const checkMobile = () => {
  isMobile.value = window.innerWidth <= 768
}

onMounted(() => {
  checkMobile()
  window.addEventListener('resize', checkMobile)
})

onUnmounted(() => {
  window.removeEventListener('resize', checkMobile)
})
</script>

<template>
  <Head>
    <meta name="description" :content="seoDescription" />
    <meta name="keywords" :content="seoKeywords" />
    <link rel="canonical" href="https://alexallergotest.ru" />
    <link rel="preload" as="image" type="image/webp" href="/videos/posters/hero-mobile.webp" media="(max-width: 1024px)" fetchpriority="high" />
    <link rel="preload" as="image" type="image/webp" href="/videos/posters/hero-desktop.webp" media="(min-width: 1025px)" fetchpriority="high" />
  </Head>

  <div
      class="page-container home-page-container site-sidebar-layout"
      :class="{
      'mobile-menu-open': mobileMenuOpen,
      'doctor-mode': isDoctorMode
    }"
      :data-audience="isDoctorMode ? 'doctors' : 'patients'"
  >
    <SiteSidebar
        v-if="mountSidebar"
        ref="siteSidebarRef"
        :doctor-mode="isDoctorMode"
        show-audience-switch
        @register="openCart"
        @home="goToSlideById('slide-1')"
        @about="goToSlideById('slide-1')"
        @materials="goToSlideById('slide-17')"
        @close="closeMobileMenu"
    />
    <aside
        v-else
        class="site-sidebar site-sidebar-placeholder"
        aria-hidden="true"
    ></aside>

    <main
        class="content-container home-content"
        ref="scrollContainer"
        :data-active-slide="slides[currentSlideIndex]?.id"
    >
      <div
          class="figma-right-menu"
          :class="{
          visible: showMenu,
          'mobile-intro-menu': currentSlideIndex === 1,
          'mobile-menu-expanded': mobileSectionMenuExpanded,
          'doctor-backdrop-visible': isDoctorMode && isStoryBackdropIndex(currentSlideIndex, slideDeckRanges),
          'results-top-backdrop-visible': !isDoctorMode && currentSlideIndex >= slideDeckRanges.resultsIntro && currentSlideIndex <= slideDeckRanges.lastResult,
          'mobile-backdrop-visible': isStoryBackdropIndex(currentSlideIndex, slideDeckRanges)
        }"
      >
        <button
            v-for="(item, idx) in menuItems"
            :key="item.label"
            class="figma-menu-item"
            :class="{ active: idx === activeMenuIndex }"
            type="button"
            :aria-expanded="isMobileViewport && (idx === activeMenuIndex || (activeMenuIndex === -1 && idx === 0)) ? mobileSectionMenuExpanded : undefined"
            @click="handleSectionMenuItemClick(item, idx, $event)"
        >
          {{ item.label }}
        </button>
      </div>

      <div
          v-if="persistentStepGroup"
          class="figma-step-indicator figma-persistent-step-indicator"
          :class="`persistent-${persistentStepGroup.type}`"
          :aria-label="persistentStepGroup.type === 'advantages' ? 'Шаги преимуществ' : 'Шаги результатов'"
      >
        <button
            v-for="i in persistentStepGroup.total"
            :key="i"
            type="button"
            class="figma-step-circle"
            :class="{ active: i === persistentStepGroup.current }"
            :aria-label="`Перейти к шагу ${i}`"
            @click="goToPersistentStep(i)"
        >
          {{ i }}
        </button>
      </div>

      <div
          class="video-bg-container"
          :class="{
          'no-video': !isVideoBackgroundVisible,
          'faq-fixed-background': slides[currentSlideIndex]?.id === 'slide-16' || isFaqReverseTransitionActive,
          'faq-transition-active': isFaqForwardTransitionActive || isFaqReverseTransitionActive,
          'patient-editorial-background': !isDoctorMode && hasDesktopEditorialStill(slides[currentSlideIndex]),
          'doctor-editorial-background': isDoctorMode && isEditorialSlide(slides[currentSlideIndex]),
          'suppress-held-frame': suppressHeldVideoFrame
        }"
          aria-hidden="true"
      >
        <picture class="hero-poster" :class="{ 'is-hidden': videoBackgroundReady }">
          <source media="(max-width: 1024px)" srcset="/videos/posters/hero-mobile.webp" />
          <img
              src="/videos/posters/hero-desktop.webp"
              alt=""
              width="1280"
              height="960"
              fetchpriority="high"
              decoding="sync"
              loading="eager"
          />
        </picture>
        <video
            ref="videoA"
            class="bg-video"
            :class="{ active: isVideoLayerActive('A') && videoBackgroundReady, incoming: activeVideoElement === 'A' }"
            muted
            playsinline
            webkit-playsinline
            preload="metadata"
        ></video>
        <video
            ref="videoB"
            class="bg-video"
            :class="{ active: isVideoLayerActive('B') && videoBackgroundReady, incoming: activeVideoElement === 'B' }"
            muted
            playsinline
            webkit-playsinline
            preload="metadata"
        ></video>
        <div class="video-overlay"></div>
      </div>

      <div
          class="mobile-info-background"
          :class="{ visible: showMobileInfoBackground }"
          aria-hidden="true"
      ></div>

      <div
          class="figma-smoke-cloud-fixed"
          :class="[
          {
            visible: showSmokeCloud,
            'results-cloud': isResultsSection,
            'content-pending': !isMobileViewport && contentReadySlideIndex !== currentSlideIndex,
            'mobile-cloud-second-scene': isMobileViewport && currentSlideIndex === 1
          },
          resultsCloudVariant,
          mobileSmokeCloudVariant
        ]"
      ></div>

      <div class="slides-deck-container">
        <div
            v-for="(slide, index) in slides"
            :key="slide.id"
            class="slide-layer"
            :class="[getSlideClass(index), { 'content-exiting': index === exitingContentSlideIndex, 'content-exiting-back': index === exitingContentSlideIndex && exitingContentIsBack }]"
            :data-direction="slide.direction"
            :data-slide-id="slide.id"
            :data-has-video="hasSlideVideo(slide)"
            :data-content-ready="index === contentReadySlideIndex"
        >
          <template v-if="slide.id === 'slide-1'">
            <div v-if="scrollHintReady" class="figma-scroll-icon">
              <img class="scroll-mouse" src="/assets/figma-mouse.svg" alt="" width="24" height="24" />
              <img class="scroll-chevron scroll-chevron-1" src="/assets/figma-scroll-chevron-1.svg" alt="" width="16" height="16" />
              <img class="scroll-chevron scroll-chevron-2" src="/assets/figma-scroll-chevron-2.svg" alt="" width="16" height="16" />
              <img class="scroll-chevron scroll-chevron-3" src="/assets/figma-scroll-chevron-3.svg" alt="" width="16" height="16" />
              <div class="hint hint--dot-chevrons" aria-hidden="true">
                <span class="hint__swipe">
                  <span class="hint__trail"></span>
                  <span class="hint__head"></span>
                </span>
                <div class="hint__chevrons">
                  <svg class="hint__chevron hint__chevron--1" width="32" height="13" viewBox="-3 -3 36 17" fill="none">
                    <path d="M0 0L15 11L30 0" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                  <svg class="hint__chevron hint__chevron--2" width="32" height="13" viewBox="-3 -3 36 17" fill="none">
                    <path d="M0 0L15 11L30 0" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                  <svg class="hint__chevron hint__chevron--3" width="32" height="13" viewBox="-3 -3 36 17" fill="none">
                    <path d="M0 0L15 11L30 0" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </div>
              </div>
            </div>
            <h1 class="figma-hero-title">{{ getSlideTitle(slide) }}</h1>
          </template>

          <template v-else-if="slide.id === 'slide-2'">
            <h2 id="slide-2-title" class="figma-intro-title white">
              {{ getSlideTitle(slide) }}
            </h2>
          </template>

          <template v-else-if="slide.label === 'Преимущества'">
            <div class="figma-content-wrapper">
              <div class="figma-step-block advantages-pos">
                <div class="figma-step-indicator slide-step-indicator" aria-label="Шаги преимуществ">
                  <button
                      v-for="i in Number(slide.totalSteps)"
                      :key="i"
                      type="button"
                      class="figma-step-circle"
                      :class="{ active: i === Number(slide.step) }"
                      :aria-label="`Перейти к шагу ${i}`"
                      @click="goToPersistentStep(i)"
                  >
                    {{ i }}
                  </button>
                </div>

                <div class="figma-step-text-stack">
                  <h3 class="figma-slide-title-inline">{{ getSlideTitle(slide) }}</h3>
                  <p class="figma-slide-desc-inline">{{ getSlideSubtitle(slide) }}</p>
                </div>
              </div>
            </div>
          </template>

          <template v-else-if="slide.id === 'slide-8'">
            <h2 class="figma-intro-title">
              <template v-if="isDoctorMode">{{ getSlideTitle(slide) }}</template>
              <template v-else>Что вы получите <br>по итогам теста на аллергию</template>
            </h2>
          </template>

          <template v-else-if="slide.label === 'О результатах' && slide.step">
            <div class="figma-content-wrapper results-wrapper">
              <div class="figma-step-block results-pos">
                <div class="figma-step-indicator slide-step-indicator" aria-label="Шаги результатов">
                  <button
                      v-for="i in Number(slide.totalSteps)"
                      :key="i"
                      type="button"
                      class="figma-step-circle"
                      :class="{ active: i === Number(slide.step) }"
                      :aria-label="`Перейти к шагу ${i}`"
                      @click="goToPersistentStep(i)"
                  >
                    {{ i }}
                  </button>
                </div>

                <div class="figma-step-text-stack">
                  <div class="figma-result-title-row">
                    <h2 class="figma-slide-title-inline">{{ getSlideTitle(slide) }}</h2>
                    <span v-if="slide.badge" class="figma-badge">{{ slide.badge }}</span>
                  </div>
                  <p class="figma-slide-desc-inline">{{ getSlideSubtitle(slide) }}</p>
                </div>
              </div>
            </div>
          </template>

          <section v-else-if="slide.type === 'faq'" class="figma-exact-info-slide exact-faq-slide">
            <div v-if="faqList.length > 0" class="exact-faq-list">
              <div
                  v-for="(item, index) in faqList"
                  :key="index"
                  class="exact-faq-item"
                  :class="{ open: activeFaqIndex === index }"
              >
                <button
                    class="exact-faq-question"
                    type="button"
                    @click="toggleFaq(index)"
                >
                  <h3>{{ item.title || item.question }}</h3>
                  <img
                      src="/assets/figma-faq-chevron.svg"
                      alt=""
                      class="exact-faq-chevron"
                      :class="{ 'is-active': activeFaqIndex === index }"
                      width="24"
                      height="24"
                  />
                </button>

                <div class="exact-faq-answer-wrap" :class="{ open: activeFaqIndex === index }">
                  <div class="exact-faq-answer">
                    <p>{{ item.description || item.answer }}</p>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <section v-else-if="slide.type === 'blog'" class="figma-exact-info-slide exact-blog-slide">
            <h2 class="exact-section-title">Блог про аллергию</h2>

            <div class="exact-blog-grid">
              <Link
                  v-for="(post, bIdx) in formattedBlogs"
                  :key="post.id || bIdx"
                  :href="isDoctorMode ? doctorsUrl(`/materials/${post.slug || post.id}`) : `/blog/${post.slug || post.id}`"
                  class="exact-blog-card"
              >
                <div class="exact-blog-cover">
                  <img
                      :src="post.cover || ''"
                      :srcset="post.coverSrcset || undefined"
                      :sizes="post.coverSizes || '(max-width: 1024px) 90vw, 420px'"
                      :alt="post.title"
                      width="640"
                      height="320"
                      loading="lazy"
                      decoding="async"
                  />
                  <div class="exact-blog-tags">
                    <span>{{ post.read_time || '~16 минут' }}</span>
                    <span>{{ post.category || 'Наука' }}</span>
                  </div>
                </div>

                <div v-if="post.author_name" class="exact-blog-author">
                  <img
                      :src="post.authorAvatarDisplay || post.author_avatar || ''"
                      :srcset="post.authorAvatarSrcset || undefined"
                      sizes="48px"
                      :alt="post.author_name"
                      width="48"
                      height="48"
                      loading="lazy"
                      decoding="async"
                  />
                  <div>
                    <p class="exact-blog-author-name">{{ post.author_name }}</p>
                    <p v-if="post.author_role" class="exact-blog-author-role">{{ post.author_role }}</p>
                  </div>
                </div>

                <div class="exact-blog-copy">
                  <h3 class="exact-blog-card-title">{{ post.title }}</h3>
                  <p class="exact-blog-description">{{ post.description || post.excerpt }}</p>
                </div>
              </Link>
            </div>

            <Link :href="blogListingUrl" class="exact-bottom-link">
              <span>Все материалы</span>
              <img src="/assets/figma-faq-link-arrow.svg" alt="" width="16" height="16" />
            </Link>
          </section>

          <template v-else-if="slide.label === 'Как сдать тест'">
            <FigmaInfoSlide
                v-if="shouldMountHeavySlide(index)"
                :slide="slide"
                :doctor-mode="isDoctorMode"
                @navigate="goToSlideById"
                @action="slide.type === 'faq' ? goTo('/faq') : goTo('/blog')"
            />
          </template>

          <template v-else-if="slide.type === 'contacts'">
            <FigmaContactsSlide
                v-if="shouldMountHeavySlide(index)"
                :blog-href="blogListingUrl"
                @register="openSiteRegisterModal"
                @quiz="showQuizModal = true; goToSlideById('slide-1')"
                @navigate="goToSlideById"
                @blog="handleBlogClick"
            />
          </template>
        </div>
      </div>

      <nav class="mobile-home-dock" aria-label="Быстрые действия">
          <button
              class="mobile-home-register cta--shine-nudge"
              type="button"
              @click="openCart"
              style="--cta-period: 14s; --cta-cycles: infinite"
          >
            <span class="cta__glow" aria-hidden="true"></span>
            <span class="cta__shine" aria-hidden="true"></span>
            <span class="mobile-home-register-text">{{ ctaText }}</span>
            <span class="cta__arrow" aria-hidden="true">
              <img src="/assets/figma-about-icon.svg" alt="" width="24" height="24" />
            </span>
          </button>
          <button
              class="mobile-home-menu-button"
              type="button"
              aria-label="Открыть меню"
              :aria-expanded="mobileMenuOpen"
              @click="openMobileMenu"
          >
            <img src="/assets/figma-mobile-menu-icon.svg" alt="" width="24" height="24" />
          </button>
      </nav>
    </main>
  </div>

  <DoctorBlogTransition
      v-if="isBlogTransitionActive"
      :active="isBlogTransitionActive"
      :duration-ms="BLOG_TRANSITION_DURATION"
      @complete="finishBlogTransition"
  />

  <!-- Асинхронные модальные окна -->
  <QuizModal
      v-if="showQuizModal"
      :is-open="showQuizModal"
      @close="showQuizModal = false"
      @openDemo="showDemoModal = true"
      @openRegister="openCart"
  />

  <LabsModal
      v-if="isRegisterModalOpen"
      :is-open="isRegisterModalOpen"
      @close="closeRegisterModal"
  />

  <!-- Оптимизированный показ вспомогательных модалок через v-if -->
  <Transition name="fade">
    <div class="modal-overlay" v-if="showDemoModal" @click.self="showDemoModal = false">
      <div class="modal-card">
        <button class="modal-close-btn" @click="showDemoModal = false">&times;</button>
        <h3 class="modal-title">Пример результатов теста</h3>
        <p class="modal-desc">Здесь показано, как выглядит расшифровка теста на 300+ аллергенов на понятном языке.</p>

        <div class="demo-results-list">
          <div class="demo-result-item high">
            <span class="allergen-name">Пыльца березы (t3)</span>
            <span class="allergen-level">Высокий уровень (45.2 kU/l)</span>
            <div class="level-bar"><div class="fill" style="width: 90%; background: #ff4d4f;"></div></div>
          </div>
          <div class="demo-result-item medium">
            <span class="allergen-name">Шерсть кошки (e1)</span>
            <span class="allergen-level">Средний уровень (12.4 kU/l)</span>
            <div class="level-bar"><div class="fill" style="width: 50%; background: #faad14;"></div></div>
          </div>
          <div class="demo-result-item low">
            <span class="allergen-name">Арахис (f13)</span>
            <span class="allergen-level">Следовые реакции (0.2 kU/l)</span>
            <div class="level-bar"><div class="fill" style="width: 5%; background: #52c41a;"></div></div>
          </div>
        </div>
        <p style="font-size: 14px; color: #666; margin-top: 15px; line-height: 1.4;">
          * Полный отчет включает рекомендации аллерголога, разбор перекрестных реакций и карту диеты.
        </p>
      </div>
    </div>
  </Transition>

  <Transition name="fade">
    <div class="modal-overlay" v-if="showSearchModal" @click.self="showSearchModal = false">
      <div class="modal-card">
        <button class="modal-close-btn" @click="showSearchModal = false">&times;</button>
        <h3 class="modal-title">Проверить аллерген</h3>
        <p class="modal-desc">Введите название продукта, растения или животного, чтобы узнать, входит ли он в наш тест.</p>

        <div style="display: flex; gap: 10px; margin-top: 15px; margin-bottom: 15px;">
          <input
              type="text"
              v-model="searchQuery"
              @input="handleSearch"
              placeholder="Например: береза, кошка, яйцо"
              style="flex: 1; padding: 12px 16px; border: 1px solid #dfdfdf; border-radius: 8px; font-size: 16px; outline: none;"
          />
        </div>

        <div v-if="searchResult" class="search-results-box">
          <div v-if="searchResult.success" style="color: #52c41a; font-weight: 500;">
            <p style="margin-bottom: 8px;">✓ Да, эти показатели проверяются в тесте:</p>
            <ul style="list-style: inside; color: #333; font-weight: normal; margin-left: 10px;">
              <li v-for="item in searchResult.items" :key="item">{{ item }}</li>
            </ul>
          </div>
          <div v-else style="color: #ff4d4f;">
            {{ searchResult.message }}
          </div>
        </div>
        <div v-else style="color: #999; font-size: 14px; text-align: center; padding: 10px 0;">
          Начните вводить название...
        </div>
      </div>
    </div>
  </Transition>

  <Transition name="fade">
    <div class="modal-overlay" v-if="showRegisterModal" @click.self="showRegisterModal = false">
      <div class="modal-card">
        <button class="modal-close-btn" @click="showRegisterModal = false">&times;</button>
        <h3 class="modal-title">Запись на тест</h3>
        <p class="modal-desc">Оставьте ваши контакты, администратор ALEX LAB свяжется с вами для подбора даты забора крови.</p>

        <form @submit.prevent="alert('Заявка успешно отправлена! Наш администратор свяжется с вами в течение 15 минут.'); showRegisterModal = false" style="display: flex; flex-direction: column; gap: 16px; margin-top: 15px;">
          <div style="display: flex; flex-direction: column; gap: 6px;">
            <label style="font-size: 14px; font-weight: 500; color: #666;">Ваше имя</label>
            <input type="text" required placeholder="Иван Иванов" style="padding: 12px; border: 1px solid #dfdfdf; border-radius: 8px; font-size: 16px;" />
          </div>
          <div style="display: flex; flex-direction: column; gap: 6px;">
            <label style="font-size: 14px; font-weight: 500; color: #666;">Номер телефона</label>
            <input type="tel" required placeholder="+7 (999) 999-99-99" style="padding: 12px; border: 1px solid #dfdfdf; border-radius: 8px; font-size: 16px;" />
          </div>
          <button type="submit" class="quiz-btn-primary" style="margin-top: 10px;">Отправить заявку</button>
        </form>
      </div>
    </div>
  </Transition>
</template>

