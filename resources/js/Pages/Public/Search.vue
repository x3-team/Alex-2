<template>
    <div
        class="search-page-container site-sidebar-layout"
        :class="{ 'mobile-menu-open': mobileMenuOpen, 'doctor-mode': isDoctorMode }"
        :data-audience="isDoctorMode ? 'doctors' : 'patients'"
    >
        <SiteSidebar
            @register="openTestLocationModal"
            @about="router.visit('/')"
        />

        <main ref="searchMainRef" class="search-main">
            <div class="search-back-row home-back-area">
                <HomeBackLink stretched />
            </div>

            <div
                class="search-center"
                :class="{
                    'search-center--detail': !!selectedAllergen,
                    'search-center--filtered': !!searchQuery.trim() && !selectedAllergen,
                }"
            >
                <section class="search-hero">
                    <SearchParticleBackground />
                    <div class="search-hero-content">
                        <h1 class="search-heading">
                            Поиск аллергенов,<br />
                            которые покажет ALEX²
                        </h1>
                        <p class="search-subheading">
                            Более 300 аллергенов в одном тесте —<br />
                            убедитесь сами
                        </p>
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
                                    <path d="M3 3L11 11M11 3L3 11" stroke="#000" stroke-width="1.6" stroke-linecap="round" />
                                </svg>
                            </button>
                            <button class="search-icon-btn" type="button" aria-label="Поиск">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <circle cx="11" cy="11" r="7" stroke="#000" stroke-width="1.8" />
                                    <path d="M16.5 16.5L21 21" stroke="#000" stroke-width="1.8" stroke-linecap="round" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </section>

                <div class="search-divider" />

                <div class="search-bottom-content">
                    <div
                        v-if="selectedAllergen"
                        ref="detailAnchorRef"
                        class="search-details-grid"
                    >
                        <div class="details-left-col">
                            <div class="details-left-main">
                                <div class="allergen-icon-box">
                                    <img
                                        v-if="selectedAllergen.icon_url"
                                        :src="selectedAllergen.icon_url"
                                        :alt="selectedAllergen.name"
                                        class="allergen-box-icon"
                                        decoding="async"
                                    />
                                    <img
                                        v-else
                                        src="/assets/Alergen.svg"
                                        alt=""
                                        aria-hidden="true"
                                        class="allergen-box-icon"
                                    />
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
                            <div class="details-left-footer" @click="clearSelectedAllergen">
                                <div class="back-arrow-circle">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22Z" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M14 8L10 12L14 16" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </div>
                                <span>Назад ко всем аллергенам</span>
                            </div>
                        </div>

                        <div class="details-right-col">
                            <h3 class="related-title">Связанные аллергены:</h3>
                            <div class="related-tags-container">
                                <button
                                    v-for="(related, index) in selectedAllergen.related"
                                    :key="index"
                                    class="related-tag-btn"
                                    @click="selectRelated(typeof related === 'object' ? related.name : related)"
                                >
                                    <img
                                        v-if="typeof related === 'object' && related.icon_url"
                                        :src="related.icon_url"
                                        alt=""
                                        class="tag-admin-icon"
                                        loading="lazy"
                                        decoding="async"
                                    />
                                    <span>{{ typeof related === 'object' ? related.name : related }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-else class="search-results">
                        <button
                            v-for="(allergen, index) in allergenResults"
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
                            v-for="blog in blogResults"
                            :key="'blog-' + blog.id"
                            class="allergen-tag blog-search-tag"
                            :href="blog.url"
                        >
                            <span>{{ blog.title }}</span>
                        </a>

                        <div v-if="allergenResults.length === 0 && blogResults.length === 0" class="search-empty-state">
                            Ничего не найдено. Попробуйте изменить запрос.
                        </div>
                    </div>
                </div>
            </div>

            <PublicFooter />

            <nav class="mobile-home-dock" aria-label="Быстрые действия">
                <button class="mobile-home-register" type="button" @click="openTestLocationModal">
                    <span>Записаться на тест на аллергию</span>
                    <img src="/assets/figma-about-icon.svg" alt="" width="24" height="24" />
                </button>
            </nav>
        </main>
    </div>

    <Teleport to="body">
        <Transition name="test-location-modal-fade">
            <section
                v-if="testLocationModalOpen"
                class="test-location-modal"
                aria-label="Выбор лаборатории для сдачи теста"
                role="dialog"
                aria-modal="true"
                @click.self="closeTestLocationModal"
            >
                <div
                    class="test-location-modal__sheet"
                    :class="{ 'test-location-modal__sheet--dragging': sheetDragging }"
                    :style="{ '--sheet-drag-offset': `${sheetDragOffset}px` }"
                >
                    <div
                        class="test-location-modal__grabber-area"
                        role="button"
                        tabindex="0"
                        aria-label="Потяните вниз, чтобы закрыть"
                        @pointerdown="onSheetPointerDown"
                        @pointermove="onSheetPointerMove"
                        @pointerup="onSheetPointerUp"
                        @pointercancel="onSheetPointerUp"
                        @keydown.enter.prevent="closeTestLocationModal"
                        @keydown.space.prevent="closeTestLocationModal"
                    >
                        <div class="test-location-modal__grabber" aria-hidden="true" />
                    </div>

                    <header class="test-location-modal__header">
                        <h2>Где сдать тест?</h2>
                        <button
                            class="test-location-modal__close"
                            type="button"
                            aria-label="Закрыть"
                            @click="closeTestLocationModal"
                        >
                            ✕
                        </button>
                    </header>

                    <div class="test-location-modal__labs">
                        <article
                            v-for="lab in testLabs"
                            :key="lab.name"
                            class="test-location-modal__lab-card"
                        >
                            <div class="test-location-modal__logo-box">
                                <img
                                    :src="lab.logo"
                                    :class="lab.logoClass"
                                    :alt="`Логотип ${lab.name}`"
                                    loading="lazy"
                                    decoding="async"
                                />
                            </div>
                            <button
                                class="test-location-modal__lab-action"
                                type="button"
                                :aria-label="`Записаться на тест в ${lab.name}`"
                                @click="openLabLink(lab)"
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
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import MiniSearch from 'minisearch';
import SiteSidebar from '@/Components/SiteSidebar.vue';
import HomeBackLink from '@/Components/HomeBackLink.vue';
import PublicFooter from '@/Components/PublicFooter.vue';
import SearchParticleBackground from '@/Components/SearchParticleBackground.vue';
import { useDoctorMode } from '@/composables/useDoctorMode';
import { useSearchDetailScroll } from '@/composables/useSearchDetailScroll';

const props = defineProps({
    allergensData: { type: Array, default: () => [] },
    blogsData: { type: Array, default: () => [] },
});

const { isDoctorMode } = useDoctorMode();

const searchQuery = ref('');
const selectedAllergen = ref(null);
const mobileMenuOpen = ref(false);
const testLocationModalOpen = ref(false);
const sheetDragging = ref(false);
const sheetDragOffset = ref(0);

const searchMainRef = ref(null);
const detailAnchorRef = ref(null);
const { scrollToDetail } = useSearchDetailScroll(searchMainRef, detailAnchorRef);

const featuredNames = [
    'Береза', 'Клещ домашней пыли', 'Кошка', 'Собака', 'Молоко', 'Яйцо', 'Арахис',
    'Полынь', 'Амброзия', 'Тимофеевка', 'Яблоко', 'Пшеница', 'Соя', 'Орех фундук',
    'Грецкий орех', 'Альтернария', 'Треска', 'Креветка', 'Оса', 'Пчела', 'Персик',
    'Томат', 'Морковь', 'Лошадь', 'Плесень',
];

const normalizeTerm = (value) =>
    String(value ?? '')
        .toLowerCase()
        .replace(/ё/g, 'е');

const allergenIndex = new MiniSearch({
    fields: ['name', 'code', 'description', 'category'],
    storeFields: ['id'],
    processTerm: (term) => normalizeTerm(term) || null,
    searchOptions: {
        boost: { name: 3, code: 2 },
        prefix: true,
        combineWith: 'AND',
    },
});

allergenIndex.addAll(
    props.allergensData.map((allergen) => ({
        id: allergen.id,
        name: allergen.name ?? '',
        code: allergen.code ?? '',
        description: allergen.description ?? '',
        category: allergen.category ?? '',
    })),
);

const blogIndex = new MiniSearch({
    fields: ['title', 'excerpt'],
    storeFields: ['id'],
    processTerm: (term) => normalizeTerm(term) || null,
    searchOptions: {
        boost: { title: 3, excerpt: 1 },
        prefix: true,
        combineWith: 'AND',
    },
});

const blogById = new Map((props.blogsData || []).map((blog) => [`blog-${blog.id}`, blog]));

blogIndex.addAll(
    (props.blogsData || []).map((blog) => ({
        id: `blog-${blog.id}`,
        title: blog.title ?? '',
        excerpt: blog.excerpt ?? '',
    })),
);

const allergenById = new Map(props.allergensData.map((allergen) => [allergen.id, allergen]));

const defaultAllergens = computed(() => {
    const featured = props.allergensData.filter((allergen) =>
        featuredNames.some((name) => normalizeTerm(allergen.name).includes(normalizeTerm(name))),
    );

    if (featured.length < 20) {
        const rest = props.allergensData.filter((allergen) => !featured.includes(allergen));
        return [...featured, ...rest].slice(0, 25);
    }

    return featured.slice(0, 30);
});

const allergenResults = computed(() => {
    const query = searchQuery.value.trim();
    if (!query) {
        return defaultAllergens.value;
    }

    return allergenIndex
        .search(query, { fuzzy: query.length > 3 ? 0.2 : false })
        .map((result) => allergenById.get(result.id))
        .filter(Boolean);
});

const blogResults = computed(() => {
    const query = searchQuery.value.trim();
    if (!query) {
        return [];
    }

    return blogIndex
        .search(query, { fuzzy: query.length > 3 ? 0.2 : false })
        .map((result) => blogById.get(result.id))
        .filter(Boolean);
});

const selectAllergen = (allergen) => {
    selectedAllergen.value = allergen;
    searchQuery.value = allergen.name;
    scrollToDetail();
};

const selectRelated = (name) => {
    const match = props.allergensData.find(
        (allergen) => allergen.name.toLowerCase() === name.toLowerCase(),
    );

    if (match) {
        selectAllergen(match);
        return;
    }

    selectedAllergen.value = {
        name,
        category: 'Другое',
        description: 'Компонент входит в панель теста ALEX²',
        included: true,
        icon_url: null,
        related: [],
    };
    searchQuery.value = name;
    scrollToDetail();
};

const clearSelectedAllergen = () => {
    selectedAllergen.value = null;
    searchQuery.value = '';
};

const clearSearch = () => {
    searchQuery.value = '';
    selectedAllergen.value = null;
};

watch(searchQuery, (value) => {
    if (selectedAllergen.value && value !== selectedAllergen.value.name) {
        selectedAllergen.value = null;
    }
});

const testLabs = [
    {
        name: 'Гемотест',
        logo: '/assets/figma-lab-gemotest.webp',
        logoClass: 'test-location-modal__logo--gemotest',
        href: 'https://gemotest.ru/moskva/catalog/allergii/pervichnye-testy/obshchaya-allergodiagnostika/allergochip-alex-2/allergochip-alex-2-300-allergokomponentov-allergy-explorer-2-19ddaf/',
    },
    {
        name: 'ДНКОМ',
        logo: '/assets/figma-lab-dncom.webp',
        logoClass: 'test-location-modal__logo--dncom',
        href: 'https://dncom.ru/analizy-i-tseny/allergochip-alex2-allergy-explorer/allergochip-alex2-allergy-explorer-2-do-300-allergokomponentov-i-obshchiy-ige/',
    },
    {
        name: 'Ситилаб',
        logo: '/assets/figma-lab-citilab.webp',
        logoClass: 'test-location-modal__logo--citilab',
        href: '#',
    },
    {
        name: 'KDL',
        logo: '/assets/figma-lab-kdl.webp',
        logoClass: 'test-location-modal__logo--kdl',
        href: 'https://kdl.ru/analizy-i-tseny/msk/allergochip-alex2-300-komponentov',
    },
    {
        name: 'CMD',
        logo: '/assets/figma-lab-cmd.webp',
        logoClass: 'test-location-modal__logo--cmd',
        href: 'https://www.cmd-online.ru/analizy-i-tseny/katalog-analizov/msk/allergochip_alex_2_300_allergokomponentov_i_ig_e_obshhij_ig_e/',
    },
    {
        name: 'CHROMOLAB',
        logo: '/assets/figma-lab-chromolab.webp',
        logoClass: 'test-location-modal__logo--chromolab',
        href: 'https://www.chromolab.ru/issledovaniya/al866_allergochip_alex2_300_komponentov_vklyuchaet_opredelenie_obshchego_ige/',
    },
];

const withUtm = (href) => {
    if (!href || href === '#' || href.includes('utm_source=')) {
        return href;
    }

    return `${href}${href.includes('?') ? '&' : '?'}utm_source=site&utm_medium=cta&utm_campaign=clinic`;
};

const openTestLocationModal = () => {
    testLocationModalOpen.value = true;
};

const closeTestLocationModal = () => {
    testLocationModalOpen.value = false;
    sheetDragOffset.value = 0;
};

const openLabLink = (lab) => {
    closeTestLocationModal();
    if (lab.href && lab.href !== '#') {
        window.open(withUtm(lab.href), '_blank');
    }
};

let sheetPointerStartY = 0;

const onSheetPointerDown = (event) => {
    sheetDragging.value = true;
    sheetPointerStartY = event.clientY || event.touches?.[0]?.clientY || 0;
};

const onSheetPointerMove = (event) => {
    if (!sheetDragging.value) {
        return;
    }

    const delta = (event.clientY || event.touches?.[0]?.clientY || 0) - sheetPointerStartY;
    if (delta > 0) {
        sheetDragOffset.value = delta;
    }
};

const onSheetPointerUp = () => {
    if (!sheetDragging.value) {
        return;
    }

    if (sheetDragOffset.value > 120) {
        closeTestLocationModal();
    }

    sheetDragging.value = false;
    sheetDragOffset.value = 0;
};
</script>

<style scoped>
@import '../../../css/search-page-scoped.css';

.search-bottom-content {
    overflow-anchor: none;
}
</style>
