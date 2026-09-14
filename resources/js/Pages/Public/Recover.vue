<template>
  <div class="patient-page-layout">
    <header class="header-nav">
      <Link href="/login" class="back-link">
        <span class="back-arrow">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M15 18L9 12L15 6" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>
        Назад
      </Link>
    </header>

    <main class="patient-main-content">
      <div class="recover-card-container">
        <div class="title-block">
          <h2 class="recover-title">Восстановление доступа</h2>
          <p class="recover-subtitle">Введите электронную почту, которую указывали при оформлении заказа</p>
        </div>

        <form @submit.prevent="submit" class="recover-form">
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
              <span v-if="form.errors.email" class="error-message">Почта не найдена или неверна</span>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-black btn-next" :disabled="form.processing">
              <span>{{ form.processing ? 'Отправка...' : 'Далее' }}</span>
              <span class="btn-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <circle cx="12" cy="12" r="10" stroke="#FFFFFF" stroke-width="2"/>
                  <path d="M11 8L15 12L11 16" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </span>
            </button>
          </div>
        </form>
      </div>
    </main>
  </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'

const form = useForm({ email: '' })

const submit = () => {
  form.post('/recover', {
    preserveScroll: true,
    onSuccess: () => {
      // Можно добавить уведомление об успехе
    }
  })
}
</script>

<style scoped>
.patient-page-layout { min-height: 100vh; display: flex; flex-direction: column; background-color: #f5f5f5; }
.patient-main-content { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
.recover-card-container { width: 541px; display: flex; flex-direction: column; gap: 48px; }
@media (max-width: 600px) { .recover-card-container { width: 100%; gap: 40px; } }
.title-block { text-align: center; display: flex; flex-direction: column; gap: 16px; }
.recover-title { font-size: 42px; font-weight: 400; color: #000000; line-height: 1.16; }
.recover-subtitle { font-size: 18px; line-height: 1.5; color: #000000; opacity: 0.5; max-width: 398px; margin: 0 auto; }
.recover-form { display: flex; flex-direction: column; gap: 48px; }
.form-inputs-group { display: flex; flex-direction: column; gap: 24px; position: relative; }
.input-error { border-color: #ff0000 !important; }
.error-message { font-size: 14px; color: #ff0000; margin-top: 4px; text-align: left; }
.form-actions { display: flex; flex-direction: column; }
.back-link { display: flex; align-items: center; gap: 8px; text-decoration: none; color: #000; font-size: 16px; padding: 20px; }
</style>