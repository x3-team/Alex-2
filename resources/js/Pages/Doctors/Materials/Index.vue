<template>
    <Head>
        <title>{{ seoMeta?.title || 'Материалы для врачей — ALEX LAB' }}</title>
        <meta name="description" :content="seoMeta?.description || 'Материалы и клинические рекомендации для врачей'" />
        <meta name="keywords" :content="seoMeta?.keywords || 'материалы для врачей, аллергология, ALEX2'" />
    </Head>

    <div
        class="page-container site-sidebar-layout doctors-materials-page"
        :class="{ 'doctor-mode': isDoctorMode }"
        :data-audience="'doctors'"
        :style="doctorThemeStyle"
    >
        <SiteSidebar :doctor-mode="true" />

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
                        Статьи, видео и документы лаборатории для специалистов и пациентов.
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
                        <h2 id="materials-feed-title">Материалы</h2>
                        <div class="materials-feed-controls">
                            <div class="materials-tabs" role="tablist" aria-label="Тип материалов">
                                <button
                                    type="button"
                                    role="tab"
                                    class="materials-tab"
                                    :class="{ 'is-active': activeTab === 'articles' }"
                                    :aria-selected="activeTab === 'articles'"
                                    @click="setTab('articles')"
                                >
                                    Статьи
                                </button>
                                <button
                                    type="button"
                                    role="tab"
                                    class="materials-tab"
                                    :class="{ 'is-active': activeTab === 'video' }"
                                    :aria-selected="activeTab === 'video'"
                                    @click="setTab('video')"
                                >
                                    Видео
                                </button>
                            </div>
                            <label class="materials-sort">
                                <span class="sr-only">Сортировка</span>
                                <select v-model="activeSort" @change="applyFilters">
                                    <option value="newest">Сначала новые</option>
                                    <option value="oldest">Сначала старые</option>
                                    <option value="title">По названию</option>
                                </select>
                            </label>
                        </div>
                    </div>

                    <div class="materials-tag-row" v-if="tags.length">
                        <button
                            v-for="tag in tags.slice(0, 8)"
                            :key="tag.id"
                            type="button"
                            class="materials-tag-chip"
                            :class="{ 'is-active': activeTag === tag.slug }"
                            @click="setTag(activeTag === tag.slug ? null : tag.slug)"
                        >
                            {{ tag.name }}
                        </button>
                    </div>

                    <article
                        v-for="(post, index) in posts.data"
                        :key="post.id"
                        class="materials-card"
                    >
                        <Link :href="post.url" class="materials-card-media">
                            <img
                                v-if="post.preview_image"
                                :src="`/storage/${post.preview_image}`"
                                :alt="post.title"
                                loading="lazy"
                                decoding="async"
                            />
                            <div v-else class="materials-card-media-fallback" />
                            <div v-if="post.video_url" class="materials-card-play" aria-hidden="true">
                                <span>▶</span>
                            </div>
                            <div class="materials-card-badges">
                                <span v-if="post.published_at" class="materials-badge">
                                    {{ formatDate(post.published_at) }}
                                </span>
                                <span v-if="post.duration" class="materials-badge">~{{ post.duration }}</span>
                                <span v-if="post.video_platform" class="materials-badge">{{ post.video_platform }}</span>
                                <span v-else-if="post.video_url" class="materials-badge">YouTube</span>
                                <span v-if="post.category" class="materials-badge">{{ post.category.name }}</span>
                            </div>
                        </Link>

                        <div class="materials-card-body">
                            <div v-if="post.author" class="materials-author">
                                <div class="materials-author-avatar">
                                    <img
                                        v-if="post.author.avatar"
                                        :src="`/storage/${post.author.avatar}`"
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
                        <Link
                            v-if="posts.current_page > 1"
                            :href="pageUrl(posts.current_page - 1)"
                            class="materials-page-btn"
                            preserve-scroll
                        >
                            ‹
                        </Link>
                        <template v-for="page in paginationItems" :key="`page-${page}`">
                            <span v-if="page === '...'" class="materials-page-ellipsis">…</span>
                            <Link
                                v-else
                                :href="pageUrl(page)"
                                class="materials-page-btn"
                                :class="{ 'is-active': page === posts.current_page }"
                                preserve-scroll
                            >
                                {{ page }}
                            </Link>
                        </template>
                        <Link
                            v-if="posts.current_page < posts.last_page"
                            :href="pageUrl(posts.current_page + 1)"
                            class="materials-page-btn"
                            preserve-scroll
                        >
                            ›
                        </Link>
                    </nav>
                </section>

                <section class="materials-documents" aria-labelledby="materials-documents-title">
                    <div class="materials-documents-header">
                        <h2 id="materials-documents-title">Документы лаборатории</h2>
                        <Link :href="doctorsUrl('/materials')" class="materials-documents-all">Все документы</Link>
                    </div>
                    <div class="materials-documents-grid">
                        <article
                            v-for="plaque in documentPlaques"
                            :key="plaque.key"
                            class="materials-document-plaque"
                        >
                            <h3>{{ plaque.title }}</h3>
                            <p>{{ plaque.description }}</p>
                            <footer>
                                <span v-if="plaque.count">{{ plaque.count }} документов</span>
                                <span v-else aria-hidden="true">→</span>
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
import SiteSidebar from '@/Components/SiteSidebar.vue';
import { useDoctorMode } from '@/composables/useDoctorMode';

const props = defineProps({
    posts: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    documentPlaques: { type: Array, default: () => [] },
    seoMeta: { type: Object, default: () => ({}) },
    site: { type: Object, default: () => ({}) },
});

const { isDoctorMode, themeColor, doctorsUrl } = useDoctorMode();

const activeTab = ref(props.filters.tab || 'articles');
const activeSort = ref(props.filters.sort || 'newest');
const activeCategory = ref(props.filters.category || null);
const activeTag = ref(props.filters.tag || null);

watch(
    () => props.filters,
    (filters) => {
        activeTab.value = filters.tab || 'articles';
        activeSort.value = filters.sort || 'newest';
        activeCategory.value = filters.category || null;
        activeTag.value = filters.tag || null;
    },
    { deep: true },
);

const doctorThemeStyle = computed(() => {
    const color = themeColor.value || props.site?.themeColor || '#cba98e';

    return isDoctorMode.value ? { '--doctor-theme-color': color } : {};
});

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

const buildQuery = (overrides = {}) => ({
    tab: activeTab.value,
    sort: activeSort.value,
    ...(activeCategory.value ? { category: activeCategory.value } : {}),
    ...(activeTag.value ? { tag: activeTag.value } : {}),
    ...overrides,
});

const applyFilters = (overrides = {}) => {
    router.get(doctorsUrl('/materials'), buildQuery(overrides), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const setTab = (tab) => {
    activeTab.value = tab;
    applyFilters({ page: undefined });
};

const setCategory = (slug) => {
    activeCategory.value = slug;
    applyFilters({ page: undefined });
};

const setTag = (slug) => {
    activeTag.value = slug;
    applyFilters({ page: undefined });
};

const pageUrl = (pageNumber) => {
    const query = buildQuery();

    if (pageNumber > 1) {
        query.page = pageNumber;
    }

    return doctorsUrl('/materials') + '?' + new URLSearchParams(query).toString();
};
</script>

<style scoped>
.doctors-materials-page {
    background: #f7f7f7;
}

.doctors-materials-page.doctor-mode {
    background: var(--doctor-theme-color, #cba98e);
}

.materials-main {
    min-width: 0;
    height: 100dvh;
    overflow-y: auto;
    background: #f7f7f7;
}

.materials-topbar {
    position: sticky;
    top: 0;
    z-index: 5;
    background: #fff;
    border-bottom: 1px solid #dfdfdf;
    padding: 24px 32px;
}

.materials-breadcrumbs {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 18px;
}

.materials-breadcrumb-link {
    color: rgba(0, 0, 0, 0.3);
    text-decoration: none;
}

.materials-breadcrumb-current {
    color: #000;
}

.materials-content {
    max-width: 1115px;
    margin: 0 auto;
    padding: 64px 32px 80px;
}

.materials-hero h1 {
    font-size: clamp(28px, 4vw, 42px);
    font-weight: 400;
    line-height: 1.15;
    margin: 0 0 16px;
}

.materials-lead {
    max-width: 720px;
    font-size: 21px;
    line-height: 1.25;
    color: rgba(0, 0, 0, 0.5);
    margin: 0;
}

.materials-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 32px 0 24px;
    padding-top: 16px;
    border-top: 1px solid rgba(0, 0, 0, 0.3);
}

.materials-filter-chip,
.materials-tag-chip {
    border: 1px solid rgba(0, 0, 0, 0.4);
    border-radius: 8px;
    background: transparent;
    color: #000;
    padding: 10px 24px;
    font-size: 16px;
    cursor: pointer;
}

.materials-filter-chip.is-active,
.materials-tag-chip.is-active {
    background: #000;
    color: #fff;
}

.materials-feed-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 24px;
}

.materials-feed-header h2 {
    font-size: 32px;
    font-weight: 400;
    margin: 0;
}

.materials-feed-controls {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 16px;
}

.materials-tabs {
    display: inline-flex;
    border: 1px solid rgba(0, 0, 0, 0.2);
    border-radius: 10px;
    overflow: hidden;
}

.materials-tab {
    border: 0;
    background: #fff;
    padding: 10px 20px;
    cursor: pointer;
    font-size: 16px;
}

.materials-tab.is-active {
    background: #000;
    color: #fff;
}

.materials-sort select {
    border: 1px solid rgba(0, 0, 0, 0.4);
    border-radius: 10px;
    padding: 10px 16px;
    background: #fff;
    font-size: 16px;
}

.materials-tag-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 24px;
}

.materials-card {
    margin-bottom: 48px;
}

.materials-card-media {
    position: relative;
    display: block;
    height: clamp(250px, 40vw, 494px);
    overflow: hidden;
    border-radius: 0;
    text-decoration: none;
}

.materials-card-media img,
.materials-card-media-fallback {
    width: 100%;
    height: 100%;
    object-fit: cover;
    background: #e8e8e8;
    display: block;
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
    background: rgba(255, 255, 255, 0.92);
    display: grid;
    place-items: center;
    font-size: 24px;
}

.materials-card-badges {
    position: absolute;
    top: 16px;
    left: 16px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.materials-badge {
    background: #fff;
    border-radius: 8px;
    padding: 8px 14px;
    font-size: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.materials-card-body {
    padding: 24px 0 0;
}

.materials-author {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.materials-author-avatar {
    width: 60px;
    height: 60px;
    border-radius: 10px;
    overflow: hidden;
    background: #fff;
    display: grid;
    place-items: center;
    font-size: 24px;
}

.materials-author-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.materials-author-name {
    display: block;
    font-size: 24px;
}

.materials-author-role {
    display: block;
    font-size: 18px;
    color: rgba(0, 0, 0, 0.5);
}

.materials-card-title-link {
    color: inherit;
    text-decoration: none;
}

.materials-card-title-link h3 {
    font-size: clamp(22px, 3vw, 32px);
    font-weight: 400;
    line-height: 1.1;
    margin: 0 0 8px;
}

.materials-card-excerpt {
    margin: 0;
    font-size: 21px;
    color: rgba(0, 0, 0, 0.6);
    line-height: 1.2;
}

.materials-empty {
    padding: 48px 24px;
    text-align: center;
    background: #efefef;
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
    width: 48px;
    height: 48px;
    display: grid;
    place-items: center;
    background: #eee;
    color: #000;
    text-decoration: none;
    border-radius: 12px;
}

.materials-page-btn.is-active {
    opacity: 1;
}

.materials-page-ellipsis {
    padding: 0 8px;
    color: rgba(0, 0, 0, 0.5);
}

.materials-documents {
    margin-top: 64px;
    padding-top: 32px;
    border-top: 1px solid rgba(0, 0, 0, 0.15);
}

.materials-documents-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 24px;
}

.materials-documents-header h2 {
    font-size: 32px;
    font-weight: 400;
    margin: 0;
}

.materials-documents-all {
    color: #000;
    text-decoration: none;
    font-size: 18px;
}

.materials-documents-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.materials-document-plaque {
    background: #fff;
    border: 1px solid #dfdfdf;
    border-radius: 12px;
    padding: 24px;
    min-height: 160px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.materials-document-plaque h3 {
    font-size: 24px;
    font-weight: 500;
    margin: 0 0 8px;
}

.materials-document-plaque p {
    margin: 0;
    color: rgba(0, 0, 0, 0.55);
    font-size: 16px;
    line-height: 1.4;
}

.materials-document-plaque footer {
    margin-top: 16px;
    font-size: 16px;
    color: rgba(0, 0, 0, 0.65);
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
    .materials-content {
        padding: 32px 16px 64px;
    }

    .materials-documents-grid {
        grid-template-columns: 1fr;
    }
}
</style>
