<template>
    <Head>
        <title>{{ seoMeta?.title || 'Материалы для врачей — ALEX LAB' }}</title>
        <meta name="description" :content="seoMeta?.description || 'Материалы и клинические рекомендации для врачей'" />
        <meta name="keywords" :content="seoMeta?.keywords || 'материалы для врачей, аллергология, ALEX2'" />
    </Head>

    <div class="doctors-shell doctors-materials-page" data-audience="doctors">
        <DoctorsSidebar active="materials" />

        <div class="materials-main">
            <header class="materials-topbar">
                <nav class="materials-breadcrumbs" aria-label="Хлебные крошки">
                    <Link :href="doctorsUrl('/')" class="materials-breadcrumb-link">Главная</Link>
                    <span class="materials-breadcrumb-sep" aria-hidden="true">/</span>
                    <span class="materials-breadcrumb-current">Материалы для врачей</span>
                </nav>
            </header>

            <main class="materials-content">
                <section class="materials-hero">
                    <h1 id="materials-title">Материалы для врачей</h1>
                    <p class="materials-lead">
                        Статьи, видеолекции и документы лаборатории о молекулярной диагностике ALEX2 — для специалистов и пациентов.
                    </p>
                </section>

                <section class="materials-filters" aria-label="Фильтры категорий">
                    <button
                        type="button"
                        class="materials-filter-chip"
                        :class="{ 'is-active': !activeCategory }"
                        @click="setCategory(null)"
                    >
                        Все
                    </button>
                    <button
                        v-for="category in categories"
                        :key="category.id"
                        type="button"
                        class="materials-filter-chip"
                        :class="{ 'is-active': activeCategory === category.slug }"
                        @click="setCategory(category.slug)"
                    >
                        {{ category.name }}
                    </button>
                </section>

                <section class="materials-feed" aria-labelledby="materials-feed-title">
                    <div class="materials-feed-header">
                        <div class="materials-tabs" role="tablist" aria-label="Тип материалов">
                            <button
                                v-for="tab in tabs"
                                :key="tab.id"
                                type="button"
                                role="tab"
                                class="materials-tab"
                                :class="{ 'is-active': activeTab === tab.id }"
                                :aria-selected="activeTab === tab.id"
                                @click="setTab(tab.id)"
                            >
                                {{ tab.label }}
                            </button>
                        </div>
                        <label class="materials-sort">
                            <span>Выводить по</span>
                            <select v-model="activeSort" @change="applyFilters">
                                <option value="newest">Сначала новые</option>
                                <option value="oldest">Сначала старые</option>
                                <option value="title">По названию</option>
                            </select>
                        </label>
                    </div>
                    <h2 id="materials-feed-title" class="sr-only">Лента материалов</h2>

                    <article v-for="post in posts.data" :key="post.id" class="materials-card">
                        <div class="materials-card-meta">
                            <span v-if="post.published_at" class="materials-badge">{{ formatDate(post.published_at) }}</span>
                            <span v-if="post.duration" class="materials-badge">{{ formatDuration(post.duration) }}</span>
                            <span v-if="post.video_url" class="materials-badge">{{ post.video_platform || 'YouTube' }}</span>
                            <span v-if="post.category" class="materials-badge">{{ post.category.name }}</span>
                        </div>

                        <Link :href="post.url" class="materials-card-media">
                            <img
                                v-if="post.preview_image"
                                :src="mediaSrc(post.preview_image)"
                                :alt="post.title"
                                loading="lazy"
                                decoding="async"
                            />
                            <div v-else class="materials-card-media-fallback" />
                            <div v-if="post.video_url" class="materials-card-play" aria-hidden="true">
                                <span>▶</span>
                            </div>
                        </Link>

                        <div class="materials-card-body">
                            <div v-if="post.author" class="materials-author">
                                <div class="materials-author-avatar">
                                    <img
                                        v-if="post.author.avatar"
                                        :src="mediaSrc(post.author.avatar)"
                                        :alt="post.author.name"
                                        loading="lazy"
                                    />
                                    <span v-else>{{ post.author.name?.charAt(0) || '?' }}</span>
                                </div>
                                <div class="materials-author-meta">
                                    <span class="materials-author-name">{{ post.author.name }}</span>
                                    <span
                                        v-if="post.author.author_categories?.length"
                                        class="materials-author-role"
                                    >
                                        {{ post.author.author_categories[0].name }}
                                    </span>
                                </div>
                            </div>

                            <Link :href="post.url" class="materials-card-title-link">
                                <h3>{{ post.title }}</h3>
                            </Link>
                            <p v-if="post.excerpt" class="materials-card-excerpt">{{ post.excerpt }}</p>
                        </div>
                    </article>

                    <p v-if="!posts.data?.length" class="materials-empty">
                        Материалы не найдены. Попробуйте изменить фильтры.
                    </p>

                    <nav
                        v-if="posts.last_page > 1"
                        class="materials-pagination"
                        aria-label="Пагинация материалов"
                    >
                        <template v-for="pageNumber in paginationItems" :key="`page-${pageNumber}`">
                            <span v-if="pageNumber === '...'" class="materials-page-ellipsis">…</span>
                            <Link
                                v-else
                                :href="pageUrl(pageNumber)"
                                class="materials-page-btn"
                                :class="{ 'is-active': pageNumber === posts.current_page }"
                                preserve-scroll
                            >
                                {{ pageNumber }}
                            </Link>
                        </template>
                    </nav>
                </section>

                <section class="materials-documents" aria-labelledby="materials-documents-title">
                    <div class="materials-documents-header">
                        <h2 id="materials-documents-title">Документы лаборатории</h2>
                        <Link :href="doctorsUrl('/materials')" class="materials-documents-all">
                            Все документы
                            <span aria-hidden="true">↗</span>
                        </Link>
                    </div>
                    <div class="materials-documents-grid">
                        <article
                            v-for="plaque in documentPlaques"
                            :key="plaque.key"
                            class="materials-document-plaque"
                        >
                            <div>
                                <h3>{{ plaque.title }}</h3>
                                <p>{{ plaque.description }}</p>
                            </div>
                            <footer>
                                <span>{{ plaque.count_label || plaque.count || 'Документы' }}</span>
                                <span class="materials-document-arrow" aria-hidden="true">→</span>
                            </footer>
                        </article>
                    </div>
                </section>
            </main>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DoctorsSidebar from '@/Components/DoctorsSidebar.vue';
import { useDoctorMode } from '@/composables/useDoctorMode';

const props = defineProps({
    posts: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    documentPlaques: { type: Array, default: () => [] },
    seoMeta: { type: Object, default: () => ({}) },
});

const { doctorsUrl } = useDoctorMode();

const tabs = [
    { id: 'all', label: 'Материалы' },
    { id: 'articles', label: 'Статьи' },
    { id: 'video', label: 'Видео' },
];

const activeTab = ref(props.filters.tab || 'all');
const activeSort = ref(props.filters.sort || 'newest');
const activeCategory = ref(props.filters.category || null);
const activeTag = ref(props.filters.tag || null);

watch(
    () => props.filters,
    (filters) => {
        activeTab.value = filters.tab || 'all';
        activeSort.value = filters.sort || 'newest';
        activeCategory.value = filters.category || null;
        activeTag.value = filters.tag || null;
    },
    { deep: true },
);

const paginationItems = computed(() => {
    const last = props.posts?.last_page || 1;
    const current = props.posts?.current_page || 1;

    if (last <= 5) {
        return Array.from({ length: last }, (_, index) => index + 1);
    }

    if (current <= 3) {
        return [1, 2, 3, 4, '...', last];
    }

    if (current >= last - 2) {
        return [1, '...', last - 3, last - 2, last - 1, last];
    }

    return [1, '...', current - 1, current, current + 1, '...', last];
});

const formatDate = (value) => {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};

const formatDuration = (value) => {
    const raw = String(value).trim();

    if (!raw) {
        return '';
    }

    if (/^\d+:\d{2}/.test(raw) || raw.includes(':')) {
        return raw.replace(/^~/, '');
    }

    return `~${raw}`;
};

const mediaSrc = (path) => {
    if (!path) {
        return '';
    }

    if (String(path).startsWith('http') || String(path).startsWith('/')) {
        return path;
    }

    return `/storage/${path}`;
};

const buildQuery = (overrides = {}) => ({
    tab: activeTab.value,
    sort: activeSort.value,
    ...(props.filters.q ? { q: props.filters.q } : {}),
    ...(activeCategory.value ? { category: activeCategory.value } : {}),
    ...(activeTag.value ? { tag: activeTag.value } : {}),
    ...overrides,
});

const applyFilters = (overrides = {}) => {
    const query = buildQuery(overrides);
    delete query.page;

    router.get(doctorsUrl('/materials'), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const setTab = (tab) => {
    activeTab.value = tab;
    applyFilters();
};

const setCategory = (slug) => {
    activeCategory.value = slug;
    applyFilters();
};

const pageUrl = (pageNumber) => {
    const query = buildQuery();

    if (pageNumber > 1) {
        query.page = pageNumber;
    }

    return `${doctorsUrl('/materials')}?${new URLSearchParams(
        Object.fromEntries(Object.entries(query).filter(([, value]) => value != null && value !== '')),
    ).toString()}`;
};
</script>

<style scoped>
.doctors-materials-page {
    display: grid;
    grid-template-columns: minmax(280px, 360px) minmax(0, 1fr);
    min-height: 100dvh;
    background: #fff;
}

.materials-main {
    min-width: 0;
    height: 100dvh;
    overflow-y: auto;
    background: #fff;
}

.materials-topbar {
    position: sticky;
    top: 0;
    z-index: 5;
    background: #fff;
    border-bottom: 1px solid #efefef;
    padding: 20px 32px;
}

.materials-breadcrumbs {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 16px;
}

.materials-breadcrumb-link {
    color: rgba(0, 0, 0, 0.35);
    text-decoration: none;
}

.materials-breadcrumb-sep {
    color: rgba(0, 0, 0, 0.25);
}

.materials-breadcrumb-current {
    color: #111;
}

.materials-content {
    max-width: 980px;
    margin: 0 auto;
    padding: 48px 32px 80px;
}

.materials-hero h1 {
    font-size: clamp(32px, 4vw, 48px);
    font-weight: 500;
    line-height: 1.1;
    margin: 0 0 16px;
}

.materials-lead {
    max-width: 720px;
    font-size: 18px;
    line-height: 1.45;
    color: rgba(0, 0, 0, 0.55);
    margin: 0;
}

.materials-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 32px 0 28px;
}

.materials-filter-chip {
    border: 1px solid rgba(0, 0, 0, 0.28);
    border-radius: 999px;
    background: #fff;
    color: #111;
    padding: 8px 16px;
    font-size: 14px;
    cursor: pointer;
}

.materials-filter-chip.is-active {
    background: #111;
    border-color: #111;
    color: #fff;
}

.materials-feed-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 28px;
    padding-bottom: 12px;
    border-bottom: 1px solid #ececec;
}

.materials-tabs {
    display: flex;
    gap: 24px;
}

.materials-tab {
    border: 0;
    background: transparent;
    padding: 0 0 10px;
    cursor: pointer;
    font-size: 18px;
    color: rgba(0, 0, 0, 0.4);
    box-shadow: inset 0 -2px 0 transparent;
}

.materials-tab.is-active {
    color: #111;
    box-shadow: inset 0 -2px 0 var(--doctor-theme-color, #cba98e);
}

.materials-sort {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    color: rgba(0, 0, 0, 0.55);
}

.materials-sort select {
    border: 1px solid rgba(0, 0, 0, 0.2);
    border-radius: 10px;
    padding: 8px 12px;
    background: #fff;
    font-size: 14px;
}

.materials-card {
    margin-bottom: 48px;
}

.materials-card-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 12px;
}

.materials-badge {
    background: #f4f4f4;
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 14px;
}

.materials-card-media {
    position: relative;
    display: block;
    height: clamp(220px, 36vw, 420px);
    overflow: hidden;
    background: #eee;
}

.materials-card-media img,
.materials-card-media-fallback {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    background: #e8e8e8;
}

.materials-card-play {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    pointer-events: none;
}

.materials-card-play span {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.94);
    display: grid;
    place-items: center;
    font-size: 24px;
}

.materials-card-body {
    padding: 20px 0 0;
}

.materials-author {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
}

.materials-author-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    overflow: hidden;
    background: #f0f0f0;
    display: grid;
    place-items: center;
    font-size: 18px;
}

.materials-author-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.materials-author-name {
    display: block;
    font-size: 16px;
}

.materials-author-role {
    display: block;
    font-size: 14px;
    color: rgba(0, 0, 0, 0.5);
}

.materials-card-title-link {
    color: inherit;
    text-decoration: none;
}

.materials-card-title-link h3 {
    font-size: clamp(22px, 3vw, 32px);
    font-weight: 500;
    line-height: 1.15;
    margin: 0 0 10px;
}

.materials-card-excerpt {
    margin: 0;
    font-size: 17px;
    color: rgba(0, 0, 0, 0.58);
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.materials-empty {
    padding: 48px 24px;
    text-align: center;
    background: #f7f7f7;
    border-radius: 12px;
}

.materials-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin-top: 32px;
}

.materials-page-btn {
    min-width: 40px;
    height: 40px;
    padding: 0 10px;
    display: grid;
    place-items: center;
    background: transparent;
    color: #111;
    text-decoration: none;
    border-radius: 8px;
}

.materials-page-btn.is-active {
    background: #111;
    color: #fff;
}

.materials-page-ellipsis {
    padding: 0 8px;
    color: rgba(0, 0, 0, 0.45);
}

.materials-documents {
    margin-top: 64px;
    padding-top: 8px;
}

.materials-documents-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 20px;
}

.materials-documents-header h2 {
    font-size: 28px;
    font-weight: 500;
    margin: 0;
}

.materials-documents-all {
    color: #111;
    text-decoration: none;
    font-size: 16px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.materials-documents-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.materials-document-plaque {
    background: #f7f5f2;
    border: 1px solid #ece7e1;
    border-radius: 16px;
    padding: 24px;
    min-height: 168px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.materials-document-plaque h3 {
    font-size: 22px;
    font-weight: 500;
    margin: 0 0 8px;
}

.materials-document-plaque p {
    margin: 0;
    color: rgba(0, 0, 0, 0.55);
    font-size: 15px;
    line-height: 1.4;
}

.materials-document-plaque footer {
    margin-top: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 15px;
    color: rgba(0, 0, 0, 0.6);
}

.materials-document-arrow {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #fff;
    display: grid;
    place-items: center;
}

.sr-only {
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

@media (max-width: 1024px) {
    .doctors-materials-page {
        grid-template-columns: 1fr;
    }

    .materials-main {
        height: auto;
        overflow: visible;
    }

    .materials-content {
        padding: 28px 16px 64px;
    }

    .materials-documents-grid {
        grid-template-columns: 1fr;
    }
}
</style>
