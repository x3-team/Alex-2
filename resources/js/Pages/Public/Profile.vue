<template>
  <div class="patient-page-layout">
    <!-- Шапка страницы -->
    <header class="header-nav">
      <Link href="/dashboard" class="back-link">
        <span class="back-arrow">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M15 18L9 12L15 6" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>
        Вернуться назад
      </Link>
    </header>

    <!-- Основной контент (Профиль пациента) -->
    <main class="patient-main-content">
      <div class="profile-card-container">
        <!-- Заголовок -->
        <h2 class="profile-title">Личные данные</h2>

        <!-- Форма профиля -->
        <form @submit.prevent="submit" class="profile-form">
          <div class="form-inputs-group">
            <!-- Поле ФИО -->
            <div class="form-group">
              <label for="name" class="form-label profile-label">ФИО</label>
              <input
                  id="name"
                  type="text"
                  v-model="form.name"
                  class="form-input"
                  :class="{ 'input-error': form.errors.name }"
                  placeholder="Бурмистров Павел Сергеевич"
                  required
              />
              <span v-if="form.errors.name" class="error-message">{{ form.errors.name }}</span>
            </div>

            <!-- Поле Номер телефона -->
            <div class="form-group">
              <label for="phone" class="form-label profile-label">Номер телефона</label>
              <input
                  id="phone"
                  type="tel"
                  v-model="form.phone"
                  class="form-input"
                  :class="{ 'input-error': form.errors.phone }"
                  placeholder="+7 999 999 99 99"
                  required
              />
              <span v-if="form.errors.phone" class="error-message">{{ form.errors.phone }}</span>
            </div>

            <!-- Поле E-mail -->
            <div class="form-group">
              <label for="email" class="form-label profile-label">E-mail</label>
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
          </div>

          <!-- Кнопка Сохранить -->
          <div class="form-actions">
            <button type="submit" class="btn-black btn-save" :disabled="form.processing">
              <span>{{ form.processing ? 'Сохранение...' : (successMessage || 'Сохранить') }}</span>
              <span class="btn-icon">
                <!-- Галочка в круге -->
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <circle cx="12" cy="12" r="10" stroke="#FFFFFF" stroke-width="2"/>
                  <path d="M8 12L11 15L16 9" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </span>
            </button>
          </div>
        </form>
      </div>
    </main>
    <PublicFooter />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import '../../../css/profile.css';
import PublicFooter from '@/Components/PublicFooter.vue'
const props = defineProps({
  user: Object,
  success: String
})

// Инициализируем форму данными, пришедшими с бэкенда
const form = useForm({
  name: props.user.name,
  phone: props.user.phone || '',
  email: props.user.email,
})

const successMessage = ref('')

const submit = () => {
  form.put('/profile', {
    preserveScroll: true,
    onSuccess: () => {
      successMessage.value = 'Сохранено!'
      setTimeout(() => {
        successMessage.value = ''
        // Переход обратно на дашборд пациента после сохранения
        router.visit('/dashboard')
      }, 1500)
    }
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

.profile-card-container {
  width: 805px; /* Ширина по макету */
  display: flex;
  flex-direction: column;
  gap: 64px; /* gap 64px по макету */
}

@media (max-width: 840px) {
  .profile-card-container {
    width: 100%;
    gap: 40px;
  }
}

.profile-title {
  font-size: 42px;
  font-weight: 400;
  color: #000000;
  line-height: 1.16;
}

.profile-form {
  display: flex;
  flex-direction: column;
  gap: 64px; /* gap 64px по макету */
}

.form-inputs-group {
  display: flex;
  flex-direction: column;
  gap: 48px; /* gap 48px по макету */
}

.profile-label {
  font-size: 21px; /* Размер шрифта меток по макету */
  font-weight: 400;
  color: #000000;
}

.form-actions {
  display: flex;
  flex-direction: column;
}

/* Дополнительные стили для ошибок (как на странице логина) */
.input-error {
  border-color: #ff0000 !important;
}

.error-message {
  font-size: 14px;
  color: #ff0000;
  margin-top: 8px;
  text-align: left;
}

.back-link {
  display: flex;
  align-items: center;
  gap: 8px;
  text-decoration: none;
  color: #000;
  font-size: 16px;
  padding: 20px 40px;
}
</style>