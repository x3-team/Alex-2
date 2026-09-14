<template>
  <div class="patient-page-layout">
    <header class="header-nav">
      <Link href="/" class="back-link">
        <span class="back-arrow">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M15 18L9 12L15 6" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>
        На главную
      </Link>
    </header>

    <main class="patient-main-content">
      <div class="login-card-container">
        <div class="title-block">
          <h2 class="login-title">Добро пожаловать!</h2>
        </div>

        <form @submit.prevent="submit" class="login-form">
          <div class="form-inputs-group">
            <div class="form-group">
              <label for="email" class="form-label">E-mail</label>
              <input
                  id="email"
                  type="email"
                  v-model="form.email"
                  class="form-input"
                  :class="{ 'input-error': form.errors.email }"
                  placeholder="max@inmuntex.com"
                  required
              />
              <span v-if="form.errors.email" class="error-message">{{ form.errors.email }}</span>
            </div>

            <div class="form-group">
              <label for="password" class="form-label">Пароль</label>
              <input
                  id="password"
                  type="password"
                  v-model="form.password"
                  class="form-input"
                  :class="{ 'input-error': form.errors.password }"
                  placeholder="*************"
                  required
              />
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-black btn-login" :disabled="form.processing">
              <span>{{ form.processing ? 'Вход...' : 'Войти' }}</span>
              <span class="btn-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <circle cx="12" cy="12" r="10" stroke="#FFFFFF" stroke-width="2"/>
                  <path d="M11 8L15 12L11 16" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </span>
            </button>

            <Link href="/recover" class="btn-outline btn-full">
              <span>Забыли пароль?</span>
              <span class="btn-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <circle cx="12" cy="12" r="10" stroke="#000000" stroke-width="2"/>
                  <path d="M11 8L15 12L11 16" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </span>
            </Link>
          </div>

          <div class="info-block">
            <span class="info-icon">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="10" stroke="#000000" stroke-width="2"/>
                <path d="M12 8V12" stroke="#000000" stroke-width="2" stroke-linecap="round"/>
                <circle cx="12" cy="16" r="1" fill="#000000"/>
              </svg>
            </span>
            <p class="info-text">
              Первичные данные должны прийти на электронную почту, которую вы указали при оформлении заказа. Если что-то пошло не так — напишите нам.
            </p>
          </div>
        </form>
      </div>
    </main>
  </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import '../../../css/profile.css';

const form = useForm({
  email: '',
  password: '',
})

const submit = () => {
  form.post('/login', {
    preserveScroll: true,
  })
}
</script>

<style scoped>
.patient-page-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background-color: #f5f5f5;
}

.patient-main-content {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
}

.login-card-container {
  width: 541px;
  display: flex;
  flex-direction: column;
  gap: 64px; /* gap 64px из макета */
}

@media (max-width: 600px) {
  .login-card-container {
    width: 100%;
    gap: 40px;
  }
}

.title-block {
  text-align: center;
}

.login-title {
  font-size: 42px;
  font-weight: 400;
  color: #000000;
  line-height: 1.16;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 48px; /* gap 48px из макета */
}

.form-inputs-group {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.form-actions {
  display: flex;
  flex-direction: column;
  gap: 4px; /* Расстояние между кнопками 4px */
}

.btn-full {
  width: 100%;
}

.info-block {
  display: flex;
  gap: 16px; /* gap 16px из макета */
  align-items: flex-start;
  text-align: left;
}

.info-icon {
  flex-shrink: 0;
  width: 24px;
  height: 24px;
  margin-top: 2px;
}

.info-text {
  font-size: 18px;
  line-height: 1.5;
  color: #000000;
}</style>