<template>
    <aside
        class="sidebar-container site-sidebar"
        :class="{ 'doctor-theme': isDoctorModeActive }"
        aria-label="Навигация по сайту"
    >
        <div class="promo-card">
            <div class="promo-content">
                <Link href="/" class="promo-logo-button" aria-label="На главную">
                    <img
                        src="/assets/figma-alex-logo.svg"
                        alt="ALEX Allergy Xplorer"
                        class="promo-banner-img"
                        width="392"
                        height="142"
                        decoding="async"
                    />
                </Link>
                <p class="promo-text">
                    Многокомпонентный анализ крови, который за одно взятие крови проверяет более 300 аллергенов.
                </p>
                <span class="site-version">v{{ siteVersion }}</span>
            </div>
            <button
                v-if="!isDoctorModeActive"
                class="promo-button"
                type="button"
                @click="$emit('register')"
            >
                Записаться на тест на аллергию
            </button>
        </div>

        <div class="cards-row">
            <Link href="/search" class="sub-card">
                <span class="card-title">Поиск аллергенов</span>
            </Link>
            <Link href="/demo-result" class="sub-card">
                <span class="card-title">Посмотреть демо-результат</span>
            </Link>
        </div>

        <button type="button" class="sidebar-row-card" @click="openMaterials">
            {{ isDoctorModeActive ? 'Материалы для врачей' : 'КВИЗ: Нужен ли вам тест?' }}
        </button>

        <button type="button" class="sidebar-row-card" @click="openProfile">
            Личный кабинет
        </button>

        <button type="button" class="sidebar-row-card" @click="openAbout">
            О лаборатории ALEX LAB
        </button>
    </aside>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { SITE_VERSION } from '@/siteVersion.js';
import { useDoctorMode } from '@/composables/useDoctorMode';

const props = defineProps({
    doctorMode: { type: Boolean, default: null },
});

defineEmits(['register', 'about', 'close', 'materials', 'home']);

const page = usePage();
const { isDoctorMode, doctorsUrl } = useDoctorMode();

const isDoctorModeActive = computed(() =>
    props.doctorMode !== null ? props.doctorMode : isDoctorMode.value,
);

const siteVersion = SITE_VERSION;

const openMaterials = () => {
    router.visit(isDoctorModeActive.value ? doctorsUrl('/materials') : '/doctor-materials');
};

const openProfile = () => {
    if (isDoctorModeActive.value) {
        const user = page.props.auth?.user;
        router.visit(doctorsUrl(user ? '/cabinet' : '/login'));
        return;
    }

    router.visit('/login');
};

const openAbout = () => {
    router.visit('/alex-lab');
};
</script>

<style scoped>
.sidebar-container {
    background: #fff;
    border-right: 1px solid #dfdfdf;
    min-height: 100dvh;
}

.promo-card {
    padding: 24px;
    background: linear-gradient(178deg, #a0ae9d, #7b8b7a);
    color: #fff;
    display: grid;
    gap: 16px;
}

.sidebar-container.doctor-theme .promo-card {
    background: linear-gradient(
        178deg,
        color-mix(in srgb, var(--doctor-theme-color, #cba98e) 82%, #ffffff 18%),
        var(--doctor-theme-color, #cba98e)
    );
}

.promo-logo-button {
    display: block;
}

.promo-banner-img {
    max-width: 100%;
    height: auto;
}

.promo-text {
    font-size: 16px;
    line-height: 1.35;
}

.site-version {
    font-size: 11px;
    opacity: 0.5;
}

.promo-button,
.sidebar-row-card {
    width: 100%;
    text-align: left;
    border: 1px solid #dfdfdf;
    background: #fff;
    padding: 16px 20px;
    font-size: 16px;
    cursor: pointer;
}

.cards-row {
    display: grid;
    gap: 8px;
    padding: 16px;
}

.sub-card {
    display: block;
    padding: 16px;
    border: 1px solid #dfdfdf;
    text-decoration: none;
    color: inherit;
}

.card-title {
    font-size: 16px;
    font-weight: 500;
}

.sidebar-row-card {
    margin: 0 16px 8px;
    width: calc(100% - 32px);
}
</style>
