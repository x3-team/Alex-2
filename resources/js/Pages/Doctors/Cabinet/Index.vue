<template>
    <Head title="Личный кабинет врача — ALEX LAB" />

    <div class="doctors-shell doctors-cabinet-page" data-audience="doctors">
        <DoctorsSidebar active="cabinet" />

        <main class="doctor-cabinet-main">
            <h1>Личный кабинет врача</h1>
            <p>Раздел в разработке. Вы вошли как {{ $page.props.auth?.user?.name }}.</p>
            <form @submit.prevent="logout">
                <button type="submit">Выйти</button>
            </form>
        </main>
    </div>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import DoctorsSidebar from '@/Components/DoctorsSidebar.vue';
import { useDoctorMode } from '@/composables/useDoctorMode';

const { doctorsUrl } = useDoctorMode();

const logout = () => {
    router.post(doctorsUrl('/logout'));
};
</script>

<style scoped>
.doctors-cabinet-page {
    display: grid;
    grid-template-columns: minmax(280px, 360px) minmax(0, 1fr);
    min-height: 100dvh;
    background: #fff;
}

.doctor-cabinet-main {
    min-height: 100dvh;
    padding: 48px;
    background: #fff;
}

h1 {
    font-size: 32px;
    font-weight: 500;
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

@media (max-width: 1024px) {
    .doctors-cabinet-page {
        grid-template-columns: 1fr;
    }
}
</style>
