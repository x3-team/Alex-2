<template>
    <aside class="doctors-sidebar" aria-label="Навигация для врачей">
        <div class="doctors-sidebar-brand">
            <Link :href="doctorsUrl('/')" class="doctors-sidebar-logo" aria-label="ALEX Allergy Explorer">
                <span class="doctors-sidebar-wordmark">ALEX</span>
                <span class="doctors-sidebar-tagline">Allergy Explorer</span>
            </Link>
            <p class="doctors-sidebar-lead">
                Молекулярная диагностика ALEX2 — материалы, алгоритмы и документы для специалистов.
            </p>
            <form class="doctors-sidebar-search" @submit.prevent="submitSearch">
                <label class="sr-only" for="doctors-sidebar-q">Поиск по материалам</label>
                <input
                    id="doctors-sidebar-q"
                    v-model="query"
                    type="search"
                    placeholder="Поиск"
                    autocomplete="off"
                />
            </form>
        </div>

        <nav class="doctors-sidebar-nav">
            <Link href="/search" class="doctors-sidebar-item">
                <img src="/assets/figma-search-icon.svg" alt="" width="24" height="24" decoding="async" />
                <span>Поиск аллергенов</span>
            </Link>
            <Link href="/demo-result" class="doctors-sidebar-item">
                <img src="/assets/figma-demo-icon.svg" alt="" width="24" height="24" decoding="async" />
                <span>Посмотреть демо-результат</span>
            </Link>
            <Link
                :href="doctorsUrl('/materials')"
                class="doctors-sidebar-item"
                :class="{ 'is-active': isBlog }"
            >
                <img src="/assets/figma-doctor-materials-icon.svg" alt="" width="24" height="24" decoding="async" />
                <span>Блог</span>
            </Link>
            <button
                type="button"
                class="doctors-sidebar-item"
                :class="{ 'is-active': isCabinet }"
                @click="openCabinetStub"
            >
                <img src="/assets/figma-profile-icon.svg" alt="" width="24" height="24" decoding="async" />
                <span>Личный кабинет</span>
            </button>
            <Link href="/alex-lab" class="doctors-sidebar-item">
                <img src="/assets/figma-about-icon.svg" alt="" width="24" height="24" decoding="async" />
                <span>Интерпретация ALEX LAB</span>
            </Link>
        </nav>

        <p class="doctors-sidebar-version">v{{ siteVersion }}</p>
    </aside>
    <CabinetDevModal :open="isDevModalOpen" @close="isDevModalOpen = false" />
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { SITE_VERSION } from '@/siteVersion.js';
import { useDoctorMode } from '@/composables/useDoctorMode';
import CabinetDevModal from '@/Components/CabinetDevModal.vue';

const props = defineProps({
    active: { type: String, default: 'blog' },
});

const page = usePage();
const { doctorsUrl } = useDoctorMode();
const siteVersion = SITE_VERSION;
const query = ref(String(page.props.filters?.q || ''));

const isBlog = computed(() => props.active === 'blog');
const isCabinet = computed(() => props.active === 'cabinet');
const isDevModalOpen = ref(false);

const openCabinetStub = () => {
    isDevModalOpen.value = true;
};

const submitSearch = () => {
    router.get(
        '/doctor-materials',
        { q: query.value || undefined, tab: 'all' },
        { preserveState: true, replace: true },
    );
};
</script>

<style scoped>
.doctors-sidebar {
    display: flex;
    flex-direction: column;
    min-height: 100dvh;
    background: #fff;
    border-right: 1px solid #dfdfdf;
}

.doctors-sidebar-brand {
    background: var(--doctor-theme-color, #cba98e);
    color: #fff;
    padding: 28px 24px 24px;
}

.doctors-sidebar-logo {
    display: flex;
    flex-direction: column;
    gap: 4px;
    color: #fff;
    text-decoration: none;
}

.doctors-sidebar-wordmark {
    font-size: 42px;
    font-weight: 700;
    letter-spacing: 0.08em;
    line-height: 1;
}

.doctors-sidebar-tagline {
    font-size: 14px;
    letter-spacing: 0.04em;
    opacity: 0.9;
}

.doctors-sidebar-lead {
    margin: 16px 0 20px;
    font-size: 15px;
    line-height: 1.35;
    color: rgba(255, 255, 255, 0.88);
}

.doctors-sidebar-search input {
    width: 100%;
    border: 0;
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 16px;
    background: rgba(255, 255, 255, 0.92);
    color: #111;
}

.doctors-sidebar-nav {
    display: flex;
    flex-direction: column;
    padding: 12px 0;
}

.doctors-sidebar-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 20px;
    color: #111;
    text-decoration: none;
    font-size: 16px;
    border-left: 3px solid transparent;
}

button.doctors-sidebar-item {
    width: 100%;
    background: none;
    border-top: 0;
    border-right: 0;
    border-bottom: 0;
    cursor: pointer;
    font: inherit;
    text-align: left;
}

.doctors-sidebar-item img {
    width: 24px;
    height: 24px;
    object-fit: contain;
}

.doctors-sidebar-item.is-active {
    background: #f6f1eb;
    border-left-color: var(--doctor-theme-color, #cba98e);
    font-weight: 500;
}

.doctors-sidebar-version {
    margin-top: auto;
    padding: 16px 20px 24px;
    font-size: 12px;
    color: rgba(0, 0, 0, 0.4);
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
    .doctors-sidebar {
        min-height: auto;
        border-right: 0;
        border-bottom: 1px solid #dfdfdf;
    }
}
</style>
