<template>
    <Head title="Личный кабинет врача — ALEX LAB" />

    <div class="page-container site-sidebar-layout doctor-mode" :style="doctorThemeStyle">
        <SiteSidebar :doctor-mode="true" />

        <main class="doctor-cabinet-main">
            <h1>Личный кабинет врача</h1>
            <p>Раздел в разработке. Вы вошли как {{ $page.props.auth.user?.name }}.</p>
            <form method="post" :action="doctorsUrl('/logout')" @submit.prevent="logout">
                <input type="hidden" name="_token" :value="$page.props.csrf_token" />
                <button type="submit">Выйти</button>
            </form>
        </main>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import SiteSidebar from '@/Components/SiteSidebar.vue';
import { useDoctorMode } from '@/composables/useDoctorMode';

defineProps({
    site: { type: Object, default: () => ({}) },
});

const { doctorsUrl, themeColor } = useDoctorMode();

const doctorThemeStyle = computed(() => ({
    '--doctor-theme-color': themeColor.value || '#cba98e',
}));

const logout = () => {
    router.post(doctorsUrl('/logout'));
};
</script>

<style scoped>
.doctor-cabinet-main {
    min-height: 100dvh;
    padding: 48px;
    background: #f7f7f7;
}

h1 {
    font-size: 32px;
    font-weight: 400;
    margin: 0 0 12px;
}

button {
    margin-top: 24px;
    border: 0;
    border-radius: 10px;
    background: #121212;
    color: #fff;
    padding: 12px 18px;
    cursor: pointer;
}
</style>
