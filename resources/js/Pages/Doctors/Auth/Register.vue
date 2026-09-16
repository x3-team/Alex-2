<template>
    <Head title="Регистрация врача — ALEX LAB" />

    <div class="doctor-auth-page doctor-mode" :style="doctorThemeStyle">
        <div class="doctor-auth-card">
            <Link :href="doctorsUrl('/')" class="doctor-auth-logo">ALEX ALLERGY EXPLORER</Link>
            <h1>Регистрация врача</h1>

            <form @submit.prevent="submit">
                <label>
                    <span>ФИО</span>
                    <input v-model="form.name" type="text" autocomplete="name" required />
                    <span v-if="form.errors.name" class="doctor-auth-error">{{ form.errors.name }}</span>
                </label>

                <label>
                    <span>Email</span>
                    <input v-model="form.email" type="email" autocomplete="username" required />
                    <span v-if="form.errors.email" class="doctor-auth-error">{{ form.errors.email }}</span>
                </label>

                <label>
                    <span>Пароль</span>
                    <input v-model="form.password" type="password" autocomplete="new-password" required />
                    <span v-if="form.errors.password" class="doctor-auth-error">{{ form.errors.password }}</span>
                </label>

                <label>
                    <span>Подтверждение пароля</span>
                    <input v-model="form.password_confirmation" type="password" autocomplete="new-password" required />
                </label>

                <button type="submit" :disabled="form.processing">Зарегистрироваться</button>
            </form>

            <p class="doctor-auth-switch">
                Уже есть аккаунт?
                <Link :href="doctorsUrl('/login')">Войти</Link>
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useDoctorMode } from '@/composables/useDoctorMode';

defineProps({
    site: { type: Object, default: () => ({}) },
});

const { doctorsUrl, themeColor } = useDoctorMode();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const doctorThemeStyle = computed(() => ({
    '--doctor-theme-color': themeColor.value || '#cba98e',
}));

const submit = () => {
    form.post(doctorsUrl('/register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<style scoped>
.doctor-auth-page {
    min-height: 100dvh;
    display: grid;
    place-items: center;
    background: var(--doctor-theme-color, #cba98e);
    padding: 24px;
}

.doctor-auth-card {
    width: min(440px, 100%);
    background: #fff;
    border-radius: 16px;
    padding: 32px;
    box-shadow: 0 24px 64px rgba(0, 0, 0, 0.12);
}

.doctor-auth-logo {
    display: inline-block;
    margin-bottom: 24px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-decoration: none;
    color: #000;
}

h1 {
    margin: 0 0 24px;
    font-size: 28px;
    font-weight: 500;
}

form {
    display: grid;
    gap: 16px;
}

label {
    display: grid;
    gap: 8px;
    font-size: 14px;
}

input[type='text'],
input[type='email'],
input[type='password'] {
    border: 1px solid #dfdfdf;
    border-radius: 10px;
    padding: 12px 14px;
    font-size: 16px;
}

button {
    border: 0;
    border-radius: 10px;
    background: #121212;
    color: #fff;
    padding: 14px 16px;
    font-size: 16px;
    cursor: pointer;
}

button:disabled {
    opacity: 0.6;
    cursor: wait;
}

.doctor-auth-error {
    color: #b42318;
    font-size: 13px;
}

.doctor-auth-switch {
    margin: 20px 0 0;
    font-size: 15px;
}

.doctor-auth-switch a {
    color: #000;
}
</style>
