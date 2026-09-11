<template>
    <Head>
        <meta name="description" :content="metaDescription" />
        <meta name="keywords" :content="metaKeywords" />
        <link rel="canonical" href="https://alexallergotest.ru" />
        <link
            rel="preload"
            as="image"
            type="image/webp"
            href="/videos/posters/hero-mobile.webp"
            media="(max-width: 1024px)"
            fetchpriority="high"
        />
        <link
            rel="preload"
            as="image"
            type="image/webp"
            href="/videos/posters/hero-desktop.webp"
            media="(min-width: 1025px)"
            fetchpriority="high"
        />
    </Head>

    <div
        class="page-container home-page-container site-sidebar-layout"
        :class="{ 'mobile-menu-open': mobileMenuOpen, 'doctor-mode': isDoctorMode }"
        :data-audience="isDoctorMode ? 'doctors' : 'patients'"
    >
        <SiteSidebar
            v-if="sidebarReady"
            ref="siteSidebarRef"
            :doctor-mode="isDoctorMode"
            @register="openCta"
            @home="goToSlide('slide-1')"
            @about="goToSlide('slide-1')"
            @materials="goToSlide('slide-17')"
            @close="closeMobileMenu"
        />
        <aside v-else class="site-sidebar site-sidebar-placeholder" aria-hidden="true" />

        <main
            ref="scrollContainer"
            class="content-container home-content"
            :data-active-slide="slides[currentIndex]?.id"
        >
            <button
                class="figma-doctors-button"
                type="button"
                @click.prevent="goAudienceHome()"
            >
                <span>{{ isDoctorMode ? 'Для пациентов' : 'Для врачей' }}</span>
                <img src="/assets/figma-doctors-icon.svg" alt="" width="20" height="20" />
            </button>

            <div
                class="figma-right-menu"
                :class="{
                    visible: showRightMenu,
                    'mobile-intro-menu': currentIndex === 1,
                    'mobile-menu-expanded': mobileSectionMenuOpen,
                    'doctor-backdrop-visible':
                        isDoctorMode && isStoryBackdropIndex(currentIndex, slideDeckRanges),
                    'results-top-backdrop-visible':
                        !isDoctorMode &&
                        currentIndex >= slideDeckRanges.resultsIntro &&
                        currentIndex <= slideDeckRanges.lastResult,
                    'mobile-backdrop-visible': isStoryBackdropIndex(currentIndex, slideDeckRanges),
                }"
            >
                <button
                    v-for="(item, index) in menuItems"
                    :key="item.label"
                    class="figma-menu-item"
                    :class="{ active: index === activeMenuIndex }"
                    type="button"
                    :aria-expanded="
                        isMobile && (index === activeMenuIndex || (activeMenuIndex === -1 && index === 0))
                            ? mobileSectionMenuOpen
                            : undefined
                    "
                    @click="onMenuItemClick(item, index, $event)"
                >
                    {{ item.label }}
                </button>
            </div>

            <div
                v-if="persistentSteps"
                class="figma-step-indicator figma-persistent-step-indicator"
                :class="`persistent-${persistentSteps.type}`"
                :aria-label="persistentSteps.type === 'advantages' ? 'Шаги преимуществ' : 'Шаги результатов'"
            >
                <button
                    v-for="step in persistentSteps.total"
                    :key="step"
                    type="button"
                    class="figma-step-circle"
                    :class="{ active: step === persistentSteps.current }"
                    :aria-label="`Перейти к шагу ${step}`"
                    @click="goToPersistentStep(step)"
                >
                    {{ step }}
                </button>
            </div>

            <div
                class="video-bg-container"
                :class="{
                    'no-video': !activeSlideHasVideo,
                    'faq-fixed-background': slides[currentIndex]?.id === 'slide-16' || faqBackgroundHeld,
                    'faq-transition-active': faqTransitioning || faqBackgroundHeld,
                    'patient-editorial-background': !isDoctorMode && isPatientEditorialSlide(slides[currentIndex]),
                    'doctor-editorial-background': isDoctorMode && isEditorialSlide(slides[currentIndex]),
                    'suppress-held-frame': suppressHeldFrame,
                }"
                aria-hidden="true"
            >
                <picture class="hero-poster" :class="{ 'is-hidden': videoReady }">
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
                    :class="{ active: isVideoLayerActive('A') && videoReady, incoming: activeVideoLayer === 'A' }"
                    muted
                    playsinline
                    webkit-playsinline
                    preload="metadata"
                />
                <video
                    ref="videoB"
                    class="bg-video"
                    :class="{ active: isVideoLayerActive('B') && videoReady, incoming: activeVideoLayer === 'B' }"
                    muted
                    playsinline
                    webkit-playsinline
                    preload="metadata"
                />
                <div class="video-overlay" />
            </div>

            <div class="mobile-info-background" :class="{ visible: showMobileInfoBackground }" aria-hidden="true" />
            <div
                class="figma-smoke-cloud-fixed"
                :class="[
                    {
                        visible: showSmokeCloud,
                        'results-cloud': isResultsCloud,
                        'content-pending': !isMobile && contentReadyIndex !== currentIndex,
                        'mobile-cloud-second-scene': isMobile && currentIndex === 1,
                    },
                    resultsCloudClass,
                    mobileCloudClass,
                ]"
            />

            <div class="slides-deck-container">
                <div
                    v-for="(slide, index) in slides"
                    :key="slide.id"
                    class="slide-layer"
                    :class="[
                        slideLayerClass(index),
                        {
                            'content-exiting': index === exitingIndex,
                            'content-exiting-back': index === exitingIndex && exitingBack,
                        },
                    ]"
                    :data-direction="slide.direction"
                    :data-slide-id="slide.id"
                    :data-has-video="slideHasVideo(slide)"
                    :data-content-ready="index === contentReadyIndex"
                >
                    <template v-if="slide.id === 'slide-1'">
                        <div v-if="showHeroHint" class="figma-scroll-icon">
                            <img class="scroll-mouse" src="/assets/figma-mouse.svg" alt="" width="24" height="24" />
                            <img class="scroll-chevron scroll-chevron-1" src="/assets/figma-scroll-chevron-1.svg" alt="" width="16" height="16" />
                            <img class="scroll-chevron scroll-chevron-2" src="/assets/figma-scroll-chevron-2.svg" alt="" width="16" height="16" />
                            <img class="scroll-chevron scroll-chevron-3" src="/assets/figma-scroll-chevron-3.svg" alt="" width="16" height="16" />
                            <div class="hint hint--dot-chevrons" aria-hidden="true">
                                <span class="hint__swipe">
                                    <span class="hint__trail" />
                                    <span class="hint__head" />
                                </span>
                                <div class="hint__chevrons">
                                    <svg v-for="n in 3" :key="n" class="hint__chevron" :class="`hint__chevron--${n}`" width="32" height="13" viewBox="-3 -3 36 17" fill="none">
                                        <path d="M0 0L15 11L30 0" stroke="black" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <h1 class="figma-hero-title">{{ getSlideTitle(slide) }}</h1>
                    </template>

                    <h2 v-else-if="slide.id === 'slide-2'" id="slide-2-title" class="figma-intro-title white">
                        {{ getSlideTitle(slide) }}
                    </h2>

                    <div v-else-if="slide.label === 'Преимущества'" class="figma-content-wrapper">
                        <div class="figma-step-block advantages-pos">
                            <div class="figma-step-indicator slide-step-indicator" aria-label="Шаги преимуществ">
                                <button
                                    v-for="step in Number(slide.totalSteps)"
                                    :key="step"
                                    type="button"
                                    class="figma-step-circle"
                                    :class="{ active: step === Number(slide.step) }"
                                    :aria-label="`Перейти к шагу ${step}`"
                                    @click="goToPersistentStep(step)"
                                >
                                    {{ step }}
                                </button>
                            </div>
                            <div class="figma-step-text-stack">
                                <h3 class="figma-slide-title-inline">{{ getSlideTitle(slide) }}</h3>
                                <p class="figma-slide-desc-inline">{{ getSlideSubtitle(slide) }}</p>
                            </div>
                        </div>
                    </div>

                    <h2 v-else-if="slide.id === 'slide-8'" class="figma-intro-title">
                        <template v-if="isDoctorMode">{{ getSlideTitle(slide) }}</template>
                        <template v-else>Что вы получите <br />по итогам теста на аллергию</template>
                    </h2>

                    <div v-else-if="slide.label === 'О результатах' && slide.step" class="figma-content-wrapper results-wrapper">
                        <div class="figma-step-block results-pos">
                            <div class="figma-step-indicator slide-step-indicator" aria-label="Шаги результатов">
                                <button
                                    v-for="step in Number(slide.totalSteps)"
                                    :key="step"
                                    type="button"
                                    class="figma-step-circle"
                                    :class="{ active: step === Number(slide.step) }"
                                    :aria-label="`Перейти к шагу ${step}`"
                                    @click="goToPersistentStep(step)"
                                >
                                    {{ step }}
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

                    <section v-else-if="slide.type === 'faq'" class="figma-exact-info-slide exact-faq-slide">
                        <div v-if="faqItems.length > 0" class="exact-faq-list">
                            <div
                                v-for="(item, faqIndex) in faqItems"
                                :key="faqIndex"
                                class="exact-faq-item"
                                :class="{ open: openFaqIndex === faqIndex }"
                            >
                                <button class="exact-faq-question" type="button" @click="toggleFaq(faqIndex)">
                                    <h3>{{ item.title || item.question }}</h3>
                                    <img
                                        src="/assets/figma-faq-chevron.svg"
                                        alt=""
                                        class="exact-faq-chevron"
                                        :class="{ 'is-active': openFaqIndex === faqIndex }"
                                        width="24"
                                        height="24"
                                    />
                                </button>
                                <div class="exact-faq-answer-wrap" :class="{ open: openFaqIndex === faqIndex }">
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
                                v-for="(post, postIndex) in featuredBlogCards"
                                :key="post.id || postIndex"
                                :href="`/blog/${post.slug || post.id}`"
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
                                <div class="exact-blog-author">
                                    <img
                                        :src="post.authorAvatarDisplay || post.author_avatar || ''"
                                        :srcset="post.authorAvatarSrcset || undefined"
                                        sizes="48px"
                                        :alt="post.author_name || 'Александра Ковальчук'"
                                        width="48"
                                        height="48"
                                        loading="lazy"
                                        decoding="async"
                                    />
                                    <div>
                                        <p class="exact-blog-author-name">{{ post.author_name || 'Александра Ковальчук' }}</p>
                                        <p class="exact-blog-author-role">{{ post.author_role || 'Профессор МГУ' }}</p>
                                    </div>
                                </div>
                                <div class="exact-blog-copy">
                                    <h3 class="exact-blog-card-title">{{ post.title }}</h3>
                                    <p class="exact-blog-description">{{ post.description || post.excerpt }}</p>
                                </div>
                            </Link>
                        </div>
                        <Link href="/blog" class="exact-bottom-link">
                            <span>Все материалы</span>
                            <img src="/assets/figma-faq-link-arrow.svg" alt="" width="16" height="16" />
                        </Link>
                    </section>

                    <template v-else-if="slide.label === 'Как сдать тест'">
                        <FigmaInfoSlide
                            v-if="isNeighborSlide(index)"
                            :slide="slide"
                            :doctor-mode="isDoctorMode"
                            @navigate="goToSlide"
                            @action="slide.type === 'faq' ? visitPath('/faq') : visitPath('/blog')"
                        />
                    </template>

                    <template v-else-if="slide.type === 'contacts'">
                        <FigmaContactsSlide
                            v-if="isNeighborSlide(index)"
                            :blog-href="BLOG_HREF"
                            @register="openContactsRegister"
                            @quiz="openQuizFromContacts"
                            @navigate="goToSlide"
                            @blog="onBlogPointerDown"
                        />
                    </template>
                </div>
            </div>

            <nav class="mobile-home-dock" aria-label="Быстрые действия">
                <button
                    class="mobile-home-register cta--shine-nudge"
                    type="button"
                    style="--cta-period: 14s; --cta-cycles: infinite"
                    @click="openCta"
                >
                    <span class="cta__glow" aria-hidden="true" />
                    <span class="cta__shine" aria-hidden="true" />
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
        v-if="blogTransitionActive"
        :active="blogTransitionActive"
        :duration-ms="BLOG_TRANSITION_MS"
        @complete="finishBlogTransition"
    />
    <QuizModal
        v-if="quizOpen"
        :is-open="quizOpen"
        @close="quizOpen = false"
        @open-demo="demoOpen = true"
        @open-register="openCta"
    />
    <LabsModal v-if="labsOpen" :is-open="labsOpen" @close="closeLabs" />

    <Transition name="fade">
        <div v-if="demoOpen" class="modal-overlay" @click.self="demoOpen = false">
            <div class="modal-card">
                <button class="modal-close-btn" type="button" @click="demoOpen = false">×</button>
                <h3 class="modal-title">Пример результатов теста</h3>
                <p class="modal-desc">Здесь показано, как выглядит расшифровка теста на 300+ аллергенов на понятном языке.</p>
                <div class="demo-results-list">
                    <div class="demo-result-item high">
                        <span class="allergen-name">Пыльца березы (t3)</span>
                        <span class="allergen-level">Высокий уровень (45.2 kU/l)</span>
                        <div class="level-bar"><div class="fill" style="width: 90%; background: #ff4d4f" /></div>
                    </div>
                    <div class="demo-result-item medium">
                        <span class="allergen-name">Шерсть кошки (e1)</span>
                        <span class="allergen-level">Средний уровень (12.4 kU/l)</span>
                        <div class="level-bar"><div class="fill" style="width: 50%; background: #faad14" /></div>
                    </div>
                    <div class="demo-result-item low">
                        <span class="allergen-name">Арахис (f13)</span>
                        <span class="allergen-level">Следовые реакции (0.2 kU/l)</span>
                        <div class="level-bar"><div class="fill" style="width: 5%; background: #52c41a" /></div>
                    </div>
                </div>
                <p style="font-size: 14px; color: #666; margin-top: 15px; line-height: 1.4">
                    * Полный отчет включает рекомендации аллерголога, разбор перекрестных реакций и карту диеты.
                </p>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { computed, defineAsyncComponent, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDoctorMode } from '@/Composables/useDoctorMode';
import { SITE_VERSION } from '@/siteVersion';
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
} from '@/composables/welcomeSlideDeck';

const SiteSidebar = defineAsyncComponent(() => import('@/Components/SiteSidebar.vue'));
const FigmaContactsSlide = defineAsyncComponent(() => import('@/Components/FigmaContactsSlide.vue'));
const FigmaInfoSlide = defineAsyncComponent(() => import('@/Components/FigmaInfoSlide.vue'));
const DoctorBlogTransition = defineAsyncComponent(() => import('@/Components/DoctorBlogTransition.vue'));
const QuizModal = defineAsyncComponent(() => import('@/Components/QuizModal.vue'));
const LabsModal = defineAsyncComponent(() => import('@/Components/LabsModal.vue'));

const props = defineProps({
    isDoctorRoute: { type: Boolean, default: false },
    meta: { type: Object, default: () => ({}) },
    homeSettings: {
        type: Object,
        default: () => ({
            patient: {
                advantages: [],
                results: [],
                how_to_pass: [],
                faq: [],
                hero_title: '',
                hero_subtitle: '',
                why_subtitle: '',
                cta_text: '',
                cta_url: '',
            },
            doctor: {
                advantages: [],
                results: [],
                how_to_pass: [],
                faq: [],
                hero_title: '',
                hero_subtitle: '',
                why_subtitle: '',
                cta_text: '',
                cta_url: '',
            },
        }),
    },
    featuredBlogs: { type: Array, default: () => [] },
});

const BLOG_HREF = 'https://alexallergotest.ru/blog';
const BLOG_TRANSITION_MS = 1100;
const PHONE_VIDEO_BASE = '/videos/PHONE%20NAREZKA';
const MOB_VIDEO_BASE = '/videos/MOB';
const DOCTOR_VIDEO_BASE = '/videos/rezak%20previs%20for%20doc';
const MOBILE_VIDEO_VERSION = 'v26';
const SLIDE_TRANSITION_MS = 850;
const SLOW_EXIT_MS = 1400;
const TEXT_REVEAL_SECONDS = 1;
const DOCTOR_DELAYED_TEXT_SECONDS = 2.5;
const SWAP_PAUSE_MS = 300;
const WHEEL_THRESHOLD = 160;
const WHEEL_RESET_MS = 200;
const SCROLL_LOCK_MS = 420;

const { isDoctorMode, doctorsUrl, toggleAudienceMode } = useDoctorMode();
const patientHomeUrl = 'https://alexallergotest.ru/';

const goAudienceHome = () => {
    if (isDoctorMode.value) {
        window.location.href = patientHomeUrl;
        return;
    }

    window.location.href = doctorsUrl('/');
};

onMounted(() => {
    if (props.isDoctorRoute && !isDoctorMode.value) {
        toggleAudienceMode();
    }
});

const homeCopy = computed(() => (isDoctorMode.value ? props.homeSettings?.doctor || {} : props.homeSettings?.patient || {}));
const faqItems = computed(() => (Array.isArray(homeCopy.value?.faq) ? homeCopy.value.faq : []));
const metaDescription = computed(() => props.meta?.description || '');
const metaKeywords = computed(() => props.meta?.keywords || '');

const demoOpen = ref(false);
const quizOpen = ref(false);
const labsOpen = ref(false);
const blogTransitionActive = ref(false);
const openFaqIndex = ref(null);

const toggleFaq = (index) => {
    openFaqIndex.value = openFaqIndex.value === index ? null : index;
};

const visitPath = (path) => {
    router.visit(path);
};

const ctaText = computed(() => (homeCopy.value?.cta_text || '').trim() || 'Записаться на тест на аллергию');
const ctaUrl = computed(() => (homeCopy.value?.cta_url || '').trim());

const openCta = () => {
    if (ctaUrl.value) {
        if (ctaUrl.value.startsWith('http://') || ctaUrl.value.startsWith('https://')) {
            window.location.assign(ctaUrl.value);
            return;
        }
        router.visit(ctaUrl.value.startsWith('/') ? ctaUrl.value : `/${ctaUrl.value}`);
        return;
    }
    labsOpen.value = true;
};

const closeLabs = () => {
    labsOpen.value = false;
};

const siteSidebarRef = ref(null);
const scrollContainer = ref(null);
const videoA = ref(null);
const videoB = ref(null);
const activeVideoLayer = ref('A');
const incomingVideoLayer = ref(null);
const suppressHeldFrame = ref(false);
const isVideoLayerActive = (layer) => activeVideoLayer.value === layer || incomingVideoLayer.value === layer;

let playbackGeneration = 0;
const scrollLocked = ref(false);
const navigationKind = ref('scroll');
const videoReady = ref(false);
const contentReadyIndex = ref(0);
const showHeroHint = ref(false);
const idleWarmReady = ref(false);
const sidebarReady = ref(false);
const currentIndex = ref(0);
const isNeighborSlide = (index) => (idleWarmReady.value ? Math.abs(index - currentIndex.value) <= 1 : false);

const markIdleWarm = () => {
    idleWarmReady.value = true;
};

const markSidebarReady = () => {
    sidebarReady.value = true;
};

const goBlog = (href) => {
    window.location.assign(href);
};

const onBlogPointerDown = (event) => {
    if (!isDoctorMode.value || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }
    event.preventDefault();
    if (!blogTransitionActive.value) {
        blogTransitionActive.value = true;
    }
};

const finishBlogTransition = () => {
    if (blogTransitionActive.value) {
        goBlog(BLOG_HREF);
    }
};

const mobileVideo = (index) =>
    index < 4
        ? `${PHONE_VIDEO_BASE}/video/s${index}.webm`
        : index === 4
          ? `${MOB_VIDEO_BASE}/s4-v29.webm`
          : index <= 9
            ? `${MOB_VIDEO_BASE}/s${index}-${MOBILE_VIDEO_VERSION}.webm`
            : `${MOB_VIDEO_BASE}/s${index}.webm`;

const mobileReverseVideo = (index) =>
    index === 3
        ? `${PHONE_VIDEO_BASE}/rev/s3r-v28.webm`
        : index < 4
          ? `${PHONE_VIDEO_BASE}/rev/s${index}r.webm`
          : index === 4
            ? `${MOB_VIDEO_BASE}/s4r-v29.webm`
            : index <= 9
              ? `${MOB_VIDEO_BASE}/s${index}r-${MOBILE_VIDEO_VERSION}.webm`
              : `${MOB_VIDEO_BASE}/s${index}r.webm`;

const doctorVideoIndexBySlide = Object.freeze({
    'slide-1': 1,
    'slide-2': 2,
    'slide-3': 3,
    'slide-4': 4,
    'slide-5': 5,
    'slide-6': 6,
    'slide-7': 7,
    'slide-8': 8,
    'slide-9': 9,
    'slide-10': 10,
    'slide-11': 11,
    'slide-16': 12,
});

const hasOwn = (object, key) => !!(object && Object.prototype.hasOwnProperty.call(object, key));

const isMobile = ref(false);
const mobileMenuOpen = ref(false);
const mobileSectionMenuOpen = ref(false);

const doctorStoryFile = (index, reverse = false) =>
    index === 3
        ? reverse
            ? 's3r-chip-v80.webm'
            : 's3-chip-v80.webm'
        : index === 4
          ? reverse
              ? 's4r-chipzoom-v82.webm'
              : 's4-chipzoom-v82.webm'
          : index === 5
            ? reverse
                ? 's5r-ige-v84.webm'
                : 's5-ige-v84.webm'
            : index === 6
              ? reverse
                  ? 's6r-ccd-v3.webm'
                  : 's6-ccd-v3.webm'
              : index === 8
                ? reverse
                    ? 's8r-zoomout-v1.webm'
                    : 's8-zoomout-v1.webm'
                : reverse
                  ? `s${index}r.webm`
                  : `s${index}.webm`;

const doctorStoryHasMobile = (index) => (index >= 3 && index <= 6) || index === 8;

const doctorForwardPlaybackRate = (src) => {
    const value = String(src || '');
    return value.includes('s3-chip') || value.includes('/s3.') || /(^|\/)s3\.webm/.test(value) ? 1.44 : 1;
};

const getSlideVideo = (slide) => {
    if (!slide) {
        return '';
    }

    const doctorVideoIndex = doctorVideoIndexBySlide[slide.id];
    if (isDoctorMode.value && doctorVideoIndex) {
        const file = doctorStoryFile(doctorVideoIndex, false);
        const folder = isMobile.value && doctorStoryHasMobile(doctorVideoIndex) ? 'mob/main' : 'main';
        return `${DOCTOR_VIDEO_BASE}/${folder}/${file}?v=${SITE_VERSION}`;
    }

    return isMobile.value && hasOwn(slide, 'mobileVideo') ? slide.mobileVideo : slide.video || '';
};

const getSlideReverseVideo = (slide) => {
    if (!slide) {
        return '';
    }

    const doctorVideoIndex = doctorVideoIndexBySlide[slide.id];
    if (isDoctorMode.value && doctorVideoIndex) {
        const file = doctorStoryFile(doctorVideoIndex, true);
        const folder = isMobile.value && doctorStoryHasMobile(doctorVideoIndex) ? 'mob/rev' : 'rev';
        return `${DOCTOR_VIDEO_BASE}/${folder}/${file}?v=${SITE_VERSION}`;
    }

    return isMobile.value && hasOwn(slide, 'mobileReverseVideo') ? slide.mobileReverseVideo : slide.reverseVideo || '';
};

const slideHasVideo = (slide) => !!(getSlideVideo(slide) || slide?.holdVideo);

const openMobileMenu = () => {
    markSidebarReady();
    mobileMenuOpen.value = true;
};

const closeMobileMenu = () => {
    mobileMenuOpen.value = false;
};

const slides = computed(() => {
    const advantages = homeCopy.value?.advantages || [];
    const results = homeCopy.value?.results || [];
    const howToPass = homeCopy.value?.how_to_pass || [];
    const advantageTotal = String(advantageStepTotal(isDoctorMode.value));

    const deck = [
        {
            id: 'slide-1',
            label: '',
            title: (homeCopy.value?.hero_title || '').trim() || 'Тест на аллергию ALEX² — один анализ, который даёт ответы',
            subtitle: (homeCopy.value?.hero_subtitle || '').trim(),
            direction: 'down',
            video: '/videos/scroll/forward/scr1.webm',
            reverseVideo: '/videos/scroll/reverse/scrr1.webm',
            mobileVideo: mobileVideo(1),
            mobileReverseVideo: mobileReverseVideo(1),
        },
        {
            id: 'slide-2',
            label: '',
            title: 'Почему ALEX2?',
            subtitle: (homeCopy.value?.why_subtitle || '').trim(),
            direction: 'down',
            video: '/videos/scroll/forward/scr2.webm',
            reverseVideo: '/videos/scroll/reverse/scrr2.webm',
            mobileVideo: mobileVideo(2),
            mobileReverseVideo: mobileReverseVideo(2),
        },
        {
            id: 'slide-3',
            step: '1',
            totalSteps: advantageTotal,
            label: 'Преимущества',
            title: advantages[0]?.title || 'Всё за один сеанс',
            subtitle:
                advantages[0]?.description ||
                'За одно исследование аллергочип проверяет реакцию организма сразу на 300 различных веществ...',
            direction: 'down',
            video: '/videos/scroll/forward/scr3.webm',
            reverseVideo: '/videos/scroll/reverse/scrr3-fixed.webm',
            mobileVideo: mobileVideo(3),
            mobileReverseVideo: mobileReverseVideo(3),
        },
        {
            id: 'slide-4',
            step: '2',
            totalSteps: advantageTotal,
            label: 'Преимущества',
            title: advantages[1]?.title || 'Точечный результат',
            subtitle: advantages[1]?.description || 'В природе многие растения и продукты содержат похожие белки...',
            direction: 'right',
            video: '/videos/PC/s4.webm',
            reverseVideo: '/videos/PC/s4r.webm',
            mobileVideo: mobileVideo(4),
            mobileReverseVideo: mobileReverseVideo(4),
        },
        {
            id: 'slide-5',
            step: '3',
            totalSteps: advantageTotal,
            label: 'Преимущества',
            title: advantages[2]?.title || 'Отчёт и консультация',
            subtitle: advantages[2]?.description || 'Мы не бросаем человека с непонятными результатами...',
            direction: 'right',
            video: '/videos/PC/s4.5-v19.webm',
            reverseVideo: '/videos/PC/s4.5r-v19.webm',
            mobileVideo: mobileVideo(5),
            mobileReverseVideo: mobileReverseVideo(5),
        },
        {
            id: 'slide-6',
            step: '4',
            totalSteps: advantageTotal,
            label: 'Преимущества',
            title: advantages[3]?.title || 'Без диет и отмены лекарств',
            subtitle: advantages[3]?.description || 'Анализ можно сдавать на фоне приёма антигистаминных...',
            direction: 'right',
            video: '/videos/PC/s5.webm',
            reverseVideo: '/videos/PC/s5r.webm',
            mobileVideo: mobileVideo(6),
            mobileReverseVideo: mobileReverseVideo(6),
        },
    ];

    if (shouldIncludePatientSlide7(isDoctorMode.value)) {
        deck.push({
            id: 'slide-7',
            step: '5',
            totalSteps: advantageTotal,
            label: 'Преимущества',
            title: advantages[4]?.title || 'Подходит детям',
            subtitle: advantages[4]?.description || 'Тест можно сдавать с шести месяцев...',
            direction: 'right',
            video: PATIENT_SLIDE_7_VIDEOS.video,
            reverseVideo: PATIENT_SLIDE_7_VIDEOS.reverseVideo,
            mobileVideo: mobileVideo(7),
            mobileReverseVideo: mobileReverseVideo(7),
        });
    }

    deck.push(
        {
            id: 'slide-8',
            label: 'О результатах',
            title: 'Что вы получите по итогам теста на аллергию',
            subtitle: '',
            direction: 'down',
            video: '/videos/PC/s7-v21.webm',
            reverseVideo: '/videos/PC/s7r-v21.webm',
            mobileVideo: mobileVideo(8),
            mobileReverseVideo: mobileReverseVideo(8),
            holdVideo: true,
        },
        {
            id: 'slide-9',
            step: '1',
            totalSteps: '3',
            label: 'О результатах',
            title: results[0]?.title || 'Аллерго-паспорт',
            subtitle: results[0]?.description || 'Список из 300 аллергенов...',
            direction: 'down',
            video: '/videos/PC/s8-v22.webm',
            reverseVideo: '/videos/PC/s8r-v22.webm',
            mobileVideo: mobileVideo(9),
            mobileReverseVideo: mobileReverseVideo(9),
        },
        {
            id: 'slide-10',
            step: '2',
            totalSteps: '3',
            label: 'О результатах',
            title: results[1]?.title || 'Рекомендации по аллергенам',
            subtitle: results[1]?.description || 'Список из 300 аллергенов...',
            direction: 'right',
            video: '/videos/scroll/forward/s9.webm',
            reverseVideo: '/videos/scroll/reverse/s9r.webm',
            mobileVideo: mobileVideo(10),
            mobileReverseVideo: mobileReverseVideo(10),
        },
        {
            id: 'slide-11',
            step: '3',
            totalSteps: '3',
            label: 'О результатах',
            title: results[2]?.title || 'Консультация',
            subtitle: results[2]?.description || 'Список из 300 аллергенов...',
            direction: 'right',
            video: '/videos/scroll/forward/s10.webm',
            reverseVideo: '/videos/scroll/reverse/s10r.webm',
            mobileVideo: mobileVideo(11),
            mobileReverseVideo: mobileReverseVideo(11),
            badge: 'Опционально',
        },
        {
            id: 'slide-16',
            label: 'FAQ',
            type: 'faq',
            title: '',
            subtitle: '',
            direction: 'down',
            video: '/videos/scroll/reverse/s11r.webm',
            reverseVideo: '/videos/scroll/reverse/s11r.webm',
            mobileVideo: mobileVideo(12),
            mobileReverseVideo: mobileReverseVideo(12),
            mobileBackgroundVideo: mobileVideo(12),
            holdVideo: true,
        },
        {
            id: 'slide-17',
            label: 'Блог про аллергию',
            type: 'blog',
            title: '',
            subtitle: '',
            direction: 'down',
            video: '',
            reverseVideo: '',
            mobileBackgroundVideo: mobileVideo(1),
            holdVideo: true,
        },
        {
            id: 'slide-18',
            step: '1',
            totalSteps: '3',
            label: 'Как сдать тест',
            title: howToPass[0]?.title || 'Запишитесь на тест через наш сайт',
            subtitle:
                howToPass[0]?.description ||
                'А если хотите получить дополнительно консультацию бесплатно — выберите сдачу теста в лабораториях ALEX',
            direction: 'down',
            video: '',
            reverseVideo: '',
            mobileBackgroundVideo: mobileVideo(1),
            holdVideo: true,
        },
        {
            id: 'slide-19',
            step: '2',
            totalSteps: '3',
            label: 'Как сдать тест',
            title: howToPass[1]?.title || 'Сдайте кровь в выбранной лаборатории',
            subtitle: howToPass[1]?.description || 'Важно: За 4 часа до сдачи крови нужно не кушать. Кровь берут из вены.',
            direction: 'right',
            video: '',
            reverseVideo: '',
            mobileBackgroundVideo: mobileVideo(1),
            holdVideo: true,
        },
        {
            id: 'slide-20',
            step: '3',
            totalSteps: '3',
            label: 'Как сдать тест',
            title: howToPass[2]?.title || 'Отслеживайте результат в личном кабинете и на электронной почте',
            subtitle:
                howToPass[2]?.description ||
                'В личном кабинете показываем статусы — туда же придёт результат. Дополнительно всё дублируем на почту.',
            direction: 'right',
            video: '',
            reverseVideo: '',
            mobileBackgroundVideo: mobileVideo(1),
            holdVideo: true,
        },
        {
            id: 'slide-21',
            label: 'Контакты',
            type: 'contacts',
            title: 'Контакты',
            subtitle: '',
            direction: 'down',
            video: '',
            reverseVideo: '',
            mobileBackgroundVideo: mobileVideo(1),
            holdVideo: true,
        },
    );

    return deck;
});

const slideDeckRanges = computed(() => getSlideDeckRanges(slides.value, isDoctorMode.value));

const doctorSlideCopy = Object.freeze({
    'slide-1': {
        title: 'Аллергочип ALEX² — расширенный анализ на аллергию. 300+ аллергенов',
    },
    'slide-2': {
        title: 'ALEX² — лучший тест на аллергию, что есть на рынке.',
    },
    'slide-5': {
        title: 'Профиль сенсибилизации',
        subtitle: 'Показывает весь спектр сенсибилизации к каждому компоненту.',
    },
    'slide-6': {
        title: 'CCD-ингибиция',
        subtitle: 'Блокирует перекрёстную реакцию на углеводные детерминанты — точнее различает истинную сенсибилизацию.',
    },
    'slide-8': {
        title: 'Как назначать тест пациентам',
    },
});

const getSlideTitle = (slide) => {
    if (slide.id === 'slide-1') {
        const heroTitle = (homeCopy.value?.hero_title || '').trim();
        if (heroTitle) {
            return heroTitle;
        }
    }
    return isDoctorMode.value && doctorSlideCopy[slide.id]?.title ? doctorSlideCopy[slide.id].title : slide.title;
};

const getSlideSubtitle = (slide) => {
    if (slide.id === 'slide-1') {
        return (homeCopy.value?.hero_subtitle || '').trim();
    }
    if (slide.id === 'slide-2') {
        return (homeCopy.value?.why_subtitle || '').trim();
    }
    if (isDoctorMode.value && doctorSlideCopy[slide.id]?.subtitle !== undefined) {
        return doctorSlideCopy[slide.id].subtitle;
    }
    return slide.subtitle;
};

const showMobileInfoBackground = computed(
    () => isMobile.value && currentIndex.value >= slideDeckRanges.value.blog,
);

const slideLayerClass = (index) => {
    if (index === currentIndex.value) {
        return 'active';
    }
    if (index > currentIndex.value) {
        return slides.value[index].direction === 'right' ? 'future-right' : 'future-down';
    }
    if (index < currentIndex.value) {
        return slides.value[index + 1].direction === 'right' ? 'past-left' : 'past-up';
    }
    return '';
};

const setSlideIndex = (index, kind = 'direct') => {
    const next = Math.max(0, Math.min(index, slides.value.length - 1));
    if (next !== currentIndex.value) {
        navigationKind.value = kind;
        currentIndex.value = next;
    }
};

const goToSlide = (slideId) => {
    const index = slides.value.findIndex((slide) => slide.id === slideId);
    if (index !== -1) {
        setSlideIndex(index, 'direct');
    }
};

const goToPersistentStep = (step) => {
    if (!persistentSteps.value) {
        return;
    }
    const ranges = slideDeckRanges.value;
    const base = persistentSteps.value.type === 'advantages' ? ranges.firstAdvantage : ranges.firstResult;
    setSlideIndex(base + step - 1, 'direct');
};

const menuItems = computed(() => [
    { label: 'Преимущества', slideId: 'slide-3' },
    { label: 'Результаты', slideId: 'slide-9' },
    { label: 'FAQ', slideId: 'slide-16' },
    { label: 'Блог про аллергию', slideId: 'slide-17' },
    { label: 'Как сдать тест', slideId: 'slide-18' },
]);

const activeMenuIndex = computed(() => getActiveMenuIndex(currentIndex.value, slideDeckRanges.value));

const showRightMenu = computed(() => slides.value[currentIndex.value]?.type !== 'contacts' && activeMenuIndex.value !== -1);

const mobileCloudClass = computed(() =>
    isLargeMobileCloudIndex(currentIndex.value, slideDeckRanges.value)
        ? 'mobile-cloud-large'
        : 'mobile-cloud-compact',
);

const onMenuItemClick = (item, index, event) => {
    if (event?.currentTarget && typeof event.currentTarget.blur === 'function') {
        event.currentTarget.blur();
    }
    if (!isMobile.value) {
        goToSlide(item.slideId);
        return;
    }
    if (!mobileSectionMenuOpen.value) {
        mobileSectionMenuOpen.value = true;
        return;
    }
    if (index === activeMenuIndex.value || (activeMenuIndex.value === -1 && index === 0)) {
        mobileSectionMenuOpen.value = false;
        return;
    }
    mobileSectionMenuOpen.value = false;
    goToSlide(item.slideId);
};

const showSmokeCloud = computed(() => {
    const index = currentIndex.value;
    const ranges = slideDeckRanges.value;
    if (isMobile.value) {
        return isStoryBackdropIndex(index, ranges);
    }
    if (isDoctorMode.value) {
        if (!isDoctorSmokeIndex(index, ranges)) {
            return false;
        }
        return contentReadyIndex.value === index;
    }
    if (contentReadyIndex.value !== index) {
        return false;
    }
    return isStoryBackdropIndex(index, ranges);
});

const isResultsCloud = computed(() => isResultsCloudIndex(currentIndex.value, slideDeckRanges.value));

const resultsCloudClass = computed(() => resultsCloudClassForIndex(currentIndex.value, slideDeckRanges.value));

const persistentSteps = computed(() => getPersistentStepGroup(currentIndex.value, slideDeckRanges.value));

const activeSlideHasVideo = computed(() => slideHasVideo(slides.value[currentIndex.value]));

const DOCTOR_DELAYED_TEXT_SLIDE_IDS = new Set(['slide-4', 'slide-5', 'slide-6']);

let wheelDelta = 0;
let wheelResetTimer = 0;
let wheelLockUntil = 0;
let exitTimer = 0;
let contentReadyTimer = 0;
let slideWatchGeneration = 0;
let stopPlaybackWatch = () => {};

const exitingIndex = ref(-1);
const exitingBack = ref(false);

const markContentReady = (index) => {
    if (currentIndex.value === index) {
        contentReadyIndex.value = index;
    }
};

const startContentExit = (index, { slow = false } = {}) => {
    window.clearTimeout(exitTimer);
    exitingIndex.value = index;
    exitingBack.value = slow;
    exitTimer = window.setTimeout(
        () => {
            if (exitingIndex.value === index) {
                exitingIndex.value = -1;
                exitingBack.value = false;
            }
        },
        slow ? SLOW_EXIT_MS : SLIDE_TRANSITION_MS,
    );
};

const normalizedDeltaY = (event) => {
    if (event.deltaMode === 1) return event.deltaY * 16;
    if (event.deltaMode === 2) return event.deltaY * window.innerHeight;
    return event.deltaY;
};

const goNext = () => {
    if (scrollLocked.value || currentIndex.value >= slides.value.length - 1) {
        return;
    }
    scrollLocked.value = true;
    setSlideIndex(currentIndex.value + 1, 'scroll');
};

const goPrev = () => {
    if (scrollLocked.value || currentIndex.value <= 0) {
        return;
    }
    scrollLocked.value = true;
    setSlideIndex(currentIndex.value - 1, 'scroll');
};

const isFaqInnerScroll = (event) => {
    const scroller = event.target?.closest?.('.exact-faq-list, .figma-faq-list, .card-container.question-layout');
    if (!scroller || scroller.scrollHeight <= scroller.clientHeight + 1) {
        return false;
    }
    const delta = normalizedDeltaY(event);
    if (!delta) {
        return true;
    }
    const atTop = scroller.scrollTop <= 0;
    const atBottom = scroller.scrollTop + scroller.clientHeight >= scroller.scrollHeight - 1;
    return (delta < 0 && !atTop) || (delta > 0 && !atBottom);
};

const onWheel = (event) => {
    const inner = isFaqInnerScroll(event);
    if (!inner) {
        event.preventDefault();
    }
    if (scrollLocked.value || performance.now() < wheelLockUntil || inner) {
        return;
    }
    const delta = normalizedDeltaY(event);
    if (!delta) {
        return;
    }
    window.clearTimeout(wheelResetTimer);
    wheelResetTimer = window.setTimeout(() => {
        wheelDelta = 0;
    }, WHEEL_RESET_MS);
    wheelDelta += delta;
    if (wheelDelta > WHEEL_THRESHOLD) {
        wheelDelta = 0;
        wheelLockUntil = performance.now() + SCROLL_LOCK_MS;
        goNext();
    } else if (wheelDelta < -WHEEL_THRESHOLD) {
        wheelDelta = 0;
        wheelLockUntil = performance.now() + SCROLL_LOCK_MS;
        goPrev();
    }
};

let pointerStartY = 0;
let pointerStartX = 0;

const onPointerDown = (event) => {
    pointerStartY = event.clientY;
    pointerStartX = event.clientX;
};

const onPointerUp = (event) => {
    if (event.pointerType === 'touch' || scrollLocked.value) {
        return;
    }
    const dy = pointerStartY - event.clientY;
    const dx = pointerStartX - event.clientX;
    if (Math.abs(dy) > 40 && Math.abs(dy) > Math.abs(dx)) {
        dy > 0 ? goNext() : goPrev();
    }
};

const onKeydown = (event) => {
    if (blogTransitionActive.value) {
        event.preventDefault();
        return;
    }
    if (event.repeat) {
        return;
    }
    switch (event.key) {
        case 'ArrowDown':
        case 'PageDown':
        case ' ':
            event.preventDefault();
            goNext();
            break;
        case 'ArrowUp':
        case 'PageUp':
            event.preventDefault();
            goPrev();
            break;
        case 'Home':
            event.preventDefault();
            setSlideIndex(0, 'direct');
            break;
        case 'End':
            event.preventDefault();
            setSlideIndex(slides.value.length - 1, 'direct');
            break;
        default:
            break;
    }
};

const onTouchStart = (event) => {
    if (!event.touches?.length) {
        return;
    }
    pointerStartY = event.touches[0].clientY;
    pointerStartX = event.touches[0].clientX;
};

const onTouchMove = () => {};

const onTouchEnd = (event) => {
    if (!event.changedTouches?.length) {
        return;
    }
    const dy = pointerStartY - event.changedTouches[0].clientY;
    const dx = pointerStartX - event.changedTouches[0].clientX;
    if (Math.abs(dy) > 40 && Math.abs(dy) > Math.abs(dx)) {
        dy > 0 ? goNext() : goPrev();
    }
};

const absoluteUrl = (value) => {
    if (!value) {
        return '';
    }
    try {
        return new URL(value, window.location.href).href;
    } catch {
        return value;
    }
};

const prepareVideo = (el) => {
    if (!el) {
        return;
    }
    el.muted = true;
    el.defaultMuted = true;
    el.loop = false;
    el.playsInline = true;
    el.setAttribute('playsinline', '');
    el.setAttribute('webkit-playsinline', 'true');
    el.disablePictureInPicture = true;
};

const playVideo = async (el) => {
    if (!el) {
        return false;
    }
    prepareVideo(el);
    try {
        const playing = el.play();
        if (playing !== undefined) {
            await playing;
        }
        return true;
    } catch {
        const unlock = () => {
            el.play().catch(() => {});
            window.removeEventListener('touchstart', unlock);
        };
        window.addEventListener('touchstart', unlock, { once: true });
        return false;
    }
};

const watchUntilTime = (el, slideIndex, generation, onDone, { targetTime } = {}) => {
    stopPlaybackWatch();
    const duration = Number.isFinite(el.duration) ? el.duration : 0;
    if (duration <= 0) {
        onDone();
        return;
    }
    const goal = Number.isFinite(targetTime) ? Math.max(0, Math.min(targetTime, duration)) : Math.min(TEXT_REVEAL_SECONDS, duration);
    let timer = 0;
    let stopped = false;
    const clear = () => window.clearTimeout(timer);
    const tick = () => {
        if (stopped || generation !== playbackGeneration || currentIndex.value !== slideIndex) {
            clear();
            return;
        }
        if (el.currentTime >= goal || el.ended) {
            clear();
            onDone();
            return;
        }
        timer = window.setTimeout(tick, 150);
    };
    stopPlaybackWatch = () => {
        stopped = true;
        clear();
    };
    tick();
};

const preloadOppositeLayer = () => {
    const el = activeVideoLayer.value === 'A' ? videoB.value : videoA.value;
    if (!el) {
        return;
    }
    let src = '';
    const current = slides.value[currentIndex.value];
    const reverse = getSlideReverseVideo(current);
    if (currentIndex.value > 0 && reverse) {
        src = reverse;
    } else {
        for (let index = currentIndex.value + 1; index < slides.value.length; index += 1) {
            const slide = slides.value[index];
            const forward = getSlideVideo(slide);
            if (forward) {
                src = forward;
                break;
            }
            if (!slide.holdVideo) {
                break;
            }
        }
    }
    if (!src || el.src === absoluteUrl(src)) {
        return;
    }
    el.pause();
    prepareVideo(el);
    el.src = src;
    el.load();
};

const retireLayer = async (el, generation, { preload = false } = {}) => {
    await nextTick();
    if (generation !== playbackGeneration || !el) {
        return;
    }
    await new Promise((resolve) => {
        requestAnimationFrame(() => requestAnimationFrame(resolve));
    });
    if (generation !== playbackGeneration) {
        return;
    }
    el.pause();
    if ((el === videoA.value && incomingVideoLayer.value === 'A') || (el === videoB.value && incomingVideoLayer.value === 'B')) {
        incomingVideoLayer.value = null;
    }
    if (preload) {
        preloadOppositeLayer();
    }
};

const heldOrForwardSrc = (index) => {
    const slide = slides.value[index];
    if (!slide) {
        return '';
    }
    if (isMobile.value && slide.mobileBackgroundVideo) {
        return slide.mobileBackgroundVideo;
    }
    const forward = getSlideVideo(slide);
    if (forward) {
        return forward;
    }
    if (!slide.holdVideo) {
        return '';
    }
    for (let cursor = index - 1; cursor >= 0; cursor -= 1) {
        const previous = slides.value[cursor];
        const previousForward = getSlideVideo(previous);
        if (previousForward) {
            return previousForward;
        }
        if (!previous?.holdVideo) {
            break;
        }
    }
    return '';
};

const pauseBoth = async () => {
    videoReady.value = false;
    videoA.value?.pause();
    videoB.value?.pause();
    await new Promise((resolve) => window.setTimeout(resolve, SWAP_PAUSE_MS));
};

const waitForFrame = (el, { ignoreCurrent = false } = {}) =>
    new Promise((resolve) => {
        if (!el) {
            resolve();
            return;
        }
        let done = false;
        const finish = () => {
            if (done) {
                return;
            }
            done = true;
            el.removeEventListener('loadeddata', onReady);
            el.removeEventListener('canplay', onReady);
            window.clearTimeout(timeout);
            resolve();
        };
        const onReady = () => {
            if (el.readyState < 2 || el.videoWidth <= 0) {
                return;
            }
            if (typeof el.requestVideoFrameCallback === 'function') {
                el.requestVideoFrameCallback(() => finish());
                return;
            }
            finish();
        };
        const timeout = window.setTimeout(finish, 450);
        if (!ignoreCurrent && el.readyState >= 2 && el.videoWidth > 0) {
            onReady();
            return;
        }
        el.addEventListener('loadeddata', onReady);
        el.addEventListener('canplay', onReady);
    });

const seekToLastFrame = (el) =>
    new Promise((resolve) => {
        if (!el) {
            resolve();
            return;
        }
        let done = false;
        const finish = () => {
            if (done) {
                return;
            }
            done = true;
            el.removeEventListener('loadedmetadata', onMeta);
            el.removeEventListener('seeked', onSeeked);
            window.clearTimeout(timeout);
            resolve();
        };
        const onSeeked = () => {
            if (typeof el.requestVideoFrameCallback === 'function') {
                el.requestVideoFrameCallback(() => finish());
                return;
            }
            finish();
        };
        const seek = () => {
            const duration = Number.isFinite(el.duration) ? el.duration : 0;
            if (duration <= 0.05) {
                finish();
                return;
            }
            el.addEventListener('seeked', onSeeked);
            el.currentTime = Math.max(0, duration - 0.04);
        };
        const onMeta = () => seek();
        const timeout = window.setTimeout(finish, 400);
        if (Number.isFinite(el.duration) && el.duration > 0) {
            seek();
            return;
        }
        el.addEventListener('loadedmetadata', onMeta);
    });

const playSrcOnIncoming = async (src, { playbackRate = 1, onPlaybackStarted, onComplete } = {}) => {
    if (!src) {
        onComplete?.();
        return;
    }
    const generation = ++playbackGeneration;
    const outgoing = activeVideoLayer.value === 'A' ? videoA.value : videoB.value;
    const incoming = activeVideoLayer.value === 'A' ? videoB.value : videoA.value;
    if (!outgoing || !incoming) {
        onComplete?.();
        return;
    }
    try {
        prepareVideo(incoming);
        if (incoming.src !== absoluteUrl(src)) {
            incoming.src = src;
            incoming.load();
        }
        incoming.playbackRate = playbackRate;
        await waitForFrame(incoming);
        if (generation !== playbackGeneration) {
            return;
        }
        if (playbackRate > 1 && Number.isFinite(incoming.duration) && incoming.duration < 0.6) {
            incoming.playbackRate = 1;
        }
        if (incoming.currentTime > 0.001) {
            incoming.currentTime = 0;
            await new Promise((resolve) => {
                const timeout = window.setTimeout(resolve, 250);
                const onSeeked = () => {
                    incoming.removeEventListener('seeked', onSeeked);
                    window.clearTimeout(timeout);
                    resolve();
                };
                incoming.addEventListener('seeked', onSeeked);
            });
        }
        if (generation !== playbackGeneration) {
            return;
        }
        const started = await playVideo(incoming);
        if (generation !== playbackGeneration) {
            return;
        }
        if (!started && incoming.readyState < 2) {
            onComplete?.();
            return;
        }
        if (typeof incoming.requestVideoFrameCallback === 'function') {
            await new Promise((resolve) => {
                const timeout = window.setTimeout(resolve, 200);
                incoming.requestVideoFrameCallback(() => {
                    window.clearTimeout(timeout);
                    resolve();
                });
            });
        }
        if (generation !== playbackGeneration) {
            return;
        }
        videoReady.value = true;
        incomingVideoLayer.value = activeVideoLayer.value;
        activeVideoLayer.value = activeVideoLayer.value === 'A' ? 'B' : 'A';
        retireLayer(outgoing, generation, { preload: true });
        onPlaybackStarted?.(incoming, generation);
        if (!started) {
            onComplete?.();
        }
    } catch {
        if (generation === playbackGeneration) {
            videoReady.value = false;
            onComplete?.();
        }
    }
};

const holdSrcOnIncoming = async (src, generation) => {
    if (!src || generation !== playbackGeneration) {
        return;
    }
    const outgoing = activeVideoLayer.value === 'A' ? videoA.value : videoB.value;
    const incoming = activeVideoLayer.value === 'A' ? videoB.value : videoA.value;
    if (!outgoing || !incoming) {
        return;
    }
    try {
        prepareVideo(incoming);
        const changed = incoming.src !== absoluteUrl(src);
        if (changed) {
            incoming.src = src;
            incoming.load();
        }
        await waitForFrame(incoming, { ignoreCurrent: changed });
        if (generation !== playbackGeneration) {
            return;
        }
        await seekToLastFrame(incoming);
        if (generation !== playbackGeneration) {
            return;
        }
        videoReady.value = true;
        incomingVideoLayer.value = activeVideoLayer.value;
        activeVideoLayer.value = activeVideoLayer.value === 'A' ? 'B' : 'A';
        retireLayer(outgoing, generation, { preload: true });
    } catch {
        if (generation === playbackGeneration) {
            await pauseBoth();
        }
    }
};

watch(isDoctorMode, async () => {
    suppressHeldFrame.value = false;
    await nextTick();
    const slide = slides.value[currentIndex.value];
    const forward = getSlideVideo(slide);
    if (forward) {
        playSrcOnIncoming(forward);
        return;
    }
    const generation = ++playbackGeneration;
    const held = heldOrForwardSrc(currentIndex.value);
    if (held) {
        holdSrcOnIncoming(held, generation);
        return;
    }
    pauseBoth();
});

const faqTransitioning = ref(false);
const faqBackgroundHeld = ref(false);
const EDITORIAL_SLIDE_IDS = new Set(['slide-17', 'slide-18', 'slide-19', 'slide-20', 'slide-21']);
const isEditorialSlide = (slide) => EDITORIAL_SLIDE_IDS.has(slide?.id);
const isPatientEditorialSlide = (slide) => !isMobile.value && isEditorialSlide(slide);

watch(currentIndex, (index) => {
    showHeroHint.value = index === 0;
});

watch(currentIndex, (nextIndex, prevIndex) => {
    if (nextIndex >= slideDeckRanges.value.faq) {
        markIdleWarm();
    }
    mobileSectionMenuOpen.value = false;
    suppressHeldFrame.value = false;

    const generation = ++slideWatchGeneration;
    const goingBack = nextIndex < prevIndex;
    const nextSlide = slides.value[nextIndex];
    const prevSlide = slides.value[prevIndex];
    const textDelayMs =
        !goingBack && isDoctorMode.value && DOCTOR_DELAYED_TEXT_SLIDE_IDS.has(nextSlide?.id)
            ? Math.ceil(DOCTOR_DELAYED_TEXT_SECONDS * 1000) + 800
            : isMobile.value
              ? goingBack
                  ? 500
                  : 700
              : goingBack
                ? 1200
                : 900;

    const fallbackReady = setTimeout(() => {
        if (generation === slideWatchGeneration) {
            contentReadyIndex.value = nextIndex;
            scrollLocked.value = false;
        }
    }, textDelayMs);

    const startedAt = performance.now();
    let completed = false;
    const nextSrc = getSlideVideo(nextSlide);
    const prevReverse = getSlideReverseVideo(prevSlide);
    const jumped = navigationKind.value === 'direct' && Math.abs(nextIndex - prevIndex) > 1;
    const isHowToPass = (index) => isHowToPassIndex(index, slideDeckRanges.value);

    if (isHowToPass(nextIndex)) {
        stopPlaybackWatch();
        contentReadyIndex.value = nextIndex;
        if (!(isMobile.value && isHowToPass(prevIndex))) {
            scrollLocked.value = false;
        }
        if (isHowToPass(prevIndex)) {
            window.clearTimeout(exitTimer);
            exitingIndex.value = -1;
            exitingBack.value = false;
            if (isMobile.value) {
                window.setTimeout(() => {
                    if (generation === slideWatchGeneration) {
                        scrollLocked.value = false;
                    }
                }, SCROLL_LOCK_MS);
            }
            return;
        }
    }

    const nextHasVideo = slideHasVideo(nextSlide);
    const prevHasVideo = slideHasVideo(prevSlide);
    if (!nextHasVideo || !prevHasVideo) {
        videoReady.value = false;
    }
    scrollLocked.value = true;

    const stillCurrent = () => generation === slideWatchGeneration && currentIndex.value === nextIndex;
    const waitMinimum = async () => {
        const remain = (goingBack ? 350 : SLIDE_TRANSITION_MS) - (performance.now() - startedAt);
        if (remain > 0) {
            await new Promise((resolve) => window.setTimeout(resolve, remain));
        }
    };
    const finishNow = async () => {
        if (completed) {
            return;
        }
        completed = true;
        await waitMinimum();
        if (stillCurrent()) {
            await revealContent();
        }
    };
    const onPlaybackStarted = (el, generationId) => {
        if (!stillCurrent()) {
            return;
        }
        const slideId = slides.value[nextIndex]?.id;
        const targetTime =
            !goingBack && isDoctorMode.value && DOCTOR_DELAYED_TEXT_SLIDE_IDS.has(slideId)
                ? DOCTOR_DELAYED_TEXT_SECONDS
                : TEXT_REVEAL_SECONDS;
        watchUntilTime(el, nextIndex, generationId, revealContent, { targetTime });
    };
    const revealContent = async () => {
        clearTimeout(fallbackReady);
        await waitMinimum();
        if (stillCurrent()) {
            if (contentReadyIndex.value !== nextIndex) {
                markContentReady(nextIndex);
            }
            scrollLocked.value = false;
        }
    };

    stopPlaybackWatch();
    window.clearTimeout(contentReadyTimer);
    startContentExit(prevIndex, { slow: goingBack });
    contentReadyIndex.value = -1;

    if (jumped) {
        if (nextSrc) {
            playSrcOnIncoming(nextSrc, { playbackRate: doctorForwardPlaybackRate(nextSrc), onPlaybackStarted, onComplete: finishNow });
        } else {
            const generationId = ++playbackGeneration;
            const held = heldOrForwardSrc(nextIndex);
            (held ? holdSrcOnIncoming(held, generationId) : pauseBoth()).finally(finishNow);
        }
        return;
    }

    if (goingBack) {
        if (isDoctorMode.value && prevSlide?.id === 'slide-17' && nextSlide?.id === 'slide-16') {
            ++playbackGeneration;
            videoA.value?.pause();
            videoB.value?.pause();
            videoReady.value = true;
            suppressHeldFrame.value = true;
            finishNow();
            return;
        }
        if (prevReverse) {
            playSrcOnIncoming(prevReverse, {
                playbackRate: String(prevReverse).includes('s3r') ? 7 : 3,
                onPlaybackStarted,
                onComplete: finishNow,
            });
        } else {
            const generationId = ++playbackGeneration;
            const held = heldOrForwardSrc(nextIndex);
            (held ? holdSrcOnIncoming(held, generationId) : pauseBoth()).finally(finishNow);
        }
        return;
    }

    if (nextSrc) {
        playSrcOnIncoming(nextSrc, { playbackRate: doctorForwardPlaybackRate(nextSrc), onPlaybackStarted, onComplete: finishNow });
        return;
    }

    if (nextSlide.holdVideo) {
        const mobileHeld = isMobile.value ? nextSlide.mobileBackgroundVideo : '';
        if (mobileHeld) {
            const generationId = ++playbackGeneration;
            holdSrcOnIncoming(mobileHeld, generationId).finally(finishNow);
        } else {
            finishNow();
        }
        return;
    }

    const generationId = ++playbackGeneration;
    pauseBoth().finally(finishNow);
});

const resetWindowScroll = () => {
    try {
        if ('scrollRestoration' in history) {
            history.scrollRestoration = 'manual';
        }
    } catch {}
    try {
        window.scrollTo(0, 0);
        document.documentElement.scrollTop = 0;
        document.body.scrollTop = 0;
        const scroller = scrollContainer.value;
        if (scroller) {
            scroller.scrollTop = 0;
        }
    } catch {}
};

const onVisibility = (event) => {
    const persisted = event && event.type === 'pageshow' && event.persisted;
    if (document.visibilityState === 'visible' || persisted) {
        resetWindowScroll();
        if (currentIndex.value === 0) {
            showHeroHint.value = true;
        }
        const el = activeVideoLayer.value === 'A' ? videoA.value : videoB.value;
        if (el) {
            playVideo(el);
        }
    }
};

let mobileMq = null;
let onMobileMq = null;

onMounted(() => {
    mobileMq = window.matchMedia('(max-width: 1024px)');
    isMobile.value = mobileMq.matches;
    onMobileMq = (event) => {
        isMobile.value = event.matches;
    };
    mobileMq.addEventListener('change', onMobileMq);
    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('pageshow', onVisibility);
    resetWindowScroll();
    nextTick(() => {
        resetWindowScroll();
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                if (currentIndex.value === 0) {
                    showHeroHint.value = true;
                }
            });
        });

        const bindScroller = () => {
            const scroller = scrollContainer.value;
            if (!scroller) {
                return false;
            }
            scroller.addEventListener('wheel', onWheel, { passive: false });
            scroller.addEventListener('touchstart', onTouchStart, { passive: true });
            scroller.addEventListener('touchmove', onTouchMove, { passive: true });
            scroller.addEventListener('touchend', onTouchEnd, { passive: true });
            window.addEventListener('pointerdown', onPointerDown);
            window.addEventListener('pointerup', onPointerUp);
            window.addEventListener('keydown', onKeydown);
            return true;
        };

        if (!bindScroller()) {
            requestAnimationFrame(() => {
                if (!bindScroller()) {
                    window.setTimeout(bindScroller, 50);
                }
            });
        }

        let started = false;
        const startHero = () => {
            if (started || currentIndex.value !== 0) {
                return;
            }
            const src = getSlideVideo(slides.value[currentIndex.value]);
            if (!src || !videoA.value || !videoB.value) {
                return;
            }
            started = true;
            playSrcOnIncoming(src);
        };
        const idle = window.requestIdleCallback || ((fn, options) => window.setTimeout(fn, options?.timeout || 1200));
        const scheduleHero = () => {
            const run = () => window.setTimeout(() => startHero(), 400);
            if (typeof window.requestAnimationFrame === 'function') {
                window.requestAnimationFrame(() => window.requestAnimationFrame(run));
            } else {
                run();
            }
        };
        scheduleHero();
        idle(() => startHero(), { timeout: 1600 });
        idle(() => markSidebarReady(), { timeout: 2200 });
        idle(() => markIdleWarm(), { timeout: 2800 });
        window.addEventListener(
            'load',
            () => {
                markSidebarReady();
                markIdleWarm();
            },
            { once: true },
        );
        const warmOnGesture = () => {
            startHero();
            markSidebarReady();
        };
        window.addEventListener('pointerdown', warmOnGesture, { once: true, passive: true });
        window.addEventListener('touchstart', warmOnGesture, { once: true, passive: true });
        window.addEventListener('wheel', warmOnGesture, { once: true, passive: true });
    });
});

onBeforeUnmount(() => {
    const scroller = scrollContainer.value;
    if (scroller) {
        scroller.removeEventListener('wheel', onWheel);
        scroller.removeEventListener('touchstart', onTouchStart);
        scroller.removeEventListener('touchmove', onTouchMove);
        scroller.removeEventListener('touchend', onTouchEnd);
    }
    window.removeEventListener('pointerdown', onPointerDown);
    window.removeEventListener('pointerup', onPointerUp);
    window.removeEventListener('keydown', onKeydown);
    document.removeEventListener('visibilitychange', onVisibility);
    window.removeEventListener('pageshow', onVisibility);
    showHeroHint.value = false;
    if (mobileMq && onMobileMq) {
        mobileMq.removeEventListener('change', onMobileMq);
    }
    videoA.value?.pause();
    videoB.value?.pause();
    window.clearTimeout(wheelResetTimer);
    window.clearTimeout(exitTimer);
    stopPlaybackWatch();
    window.clearTimeout(contentReadyTimer);
    slideWatchGeneration += 1;
    playbackGeneration += 1;
});

const featuredBlogCards = computed(() => {
    const firstLine = (value) => (value ? value.split(/[\n\r,./;]/)[0]?.trim() || value.trim() : '');
    const sized = (url, width) => {
        if (!url || url.startsWith('http')) {
            return '';
        }
        const query = url.indexOf('?');
        const path = query === -1 ? url : url.slice(0, query);
        if (!/\.webp$/i.test(path)) {
            return '';
        }
        const dot = path.lastIndexOf('.');
        return dot === -1 ? '' : `${path.slice(0, dot)}-${width}w.webp`;
    };

    return props.featuredBlogs.map((post) => {
        const coverRaw =
            post.preview_image && post.preview_image !== '0' && post.preview_image !== 0
                ? post.preview_image.startsWith('http') || post.preview_image.startsWith('/')
                    ? post.preview_image
                    : `/storage/${post.preview_image}`
                : '';
        const avatarRaw =
            post.author?.avatar && post.author.avatar !== '0' && post.author.avatar !== 0
                ? post.author.avatar.startsWith('http') || post.author.avatar.startsWith('/')
                    ? post.author.avatar
                    : `/storage/${post.author.avatar}`
                : '';
        const role = firstLine(post.author?.description || post.author?.bio) || 'Профессор МГУ';
        const cover640 = sized(coverRaw, 640);
        const cover960 = sized(coverRaw, 960);
        const coverSrcset = coverRaw
            ? [cover640 && `${cover640} 640w`, cover960 && `${cover960} 960w`, `${coverRaw} 1200w`].filter(Boolean).join(', ')
            : '';
        const avatar96 = sized(avatarRaw, 96);

        return {
            id: post.id,
            title: post.title,
            slug: post.slug,
            description: post.excerpt || '',
            cover: cover640 || coverRaw,
            coverSrcset,
            coverSizes: '(max-width: 1024px) 92vw, 420px',
            read_time: (() => {
                if (!post.duration) return '~16 минут';
                const value = String(post.duration).trim();
                return /мин/i.test(value) ? (value.startsWith('~') ? value : `~${value}`) : `~${value} мин`;
            })(),
            category: post.category?.name || 'Наука',
            author_name: post.author?.name || 'Александра Ковальчук',
            author_role: role,
            author_avatar: avatarRaw,
            authorAvatarDisplay: avatar96 || avatarRaw,
            authorAvatarSrcset: avatar96 ? `${avatar96} 96w, ${avatarRaw} 541w` : '',
        };
    });
});

const openContactsRegister = () => {
    markSidebarReady();
    if (siteSidebarRef.value?.openRegisterModal) {
        siteSidebarRef.value.openRegisterModal();
        return;
    }
    openCta();
};

const openQuizFromContacts = () => {
    quizOpen.value = true;
    goToSlide('slide-1');
};
</script>
