<template>
  <div class="patient-page-layout">
    <!-- Шапка дашборда -->
    <header class="header-nav dashboard-header">
      <a href="/" class="header-logo">ALEX LAB</a>
    </header>

    <main class="patient-main-content">
      <div class="dashboard-container">

        <!-- Приветственный блок сверху (только для статуса 1 по макету) -->


        <!-- Единая карточка статуса пациента (Frame 336211) -->
        <div class="status-card">
          <!-- Карта на статус 1 (заглушка) -->
          <div v-if="currentStatus === 1" class="map-placeholder-box">
            <!-- Сетка координат на базе SVG -->
            <svg class="map-grid-svg" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
              <defs>
                <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                  <path d="M 40 0 L 0 0 0 40" fill="none" stroke="rgba(0, 0, 0, 0.05)" stroke-width="1"/>
                </pattern>
              </defs>
              <rect width="100%" height="100%" fill="url(#grid)" />

              <!-- Имитация дорог/линий карты -->
              <path d="M-10 100 Q 200 80 400 150 T 900 120" stroke="rgba(0,0,0,0.06)" stroke-width="12" fill="none" stroke-linecap="round"/>
              <path d="M150 -10 Q 180 150 120 350" stroke="rgba(0,0,0,0.06)" stroke-width="16" fill="none" stroke-linecap="round"/>
              <path d="M500 -10 L 530 350" stroke="rgba(0,0,0,0.06)" stroke-width="10" fill="none" stroke-linecap="round"/>

              <!-- Маркер геопозиции -->
              <g transform="translate(160, 130)">
                <circle cx="0" cy="0" r="10" fill="rgba(0,0,0,0.15)"/>
                <path d="M0 -20 C-6 -20 -10 -16 -10 -10 C-10 -3 0 10 0 10 C0 10 10 -3 10 -10 C10 -16 6 -20 0 -20 Z" fill="#000000"/>
                <circle cx="0" cy="-10" r="4" fill="#FFFFFF"/>
              </g>
            </svg>

            <div class="map-placeholder-info">
              <span class="map-label">КАРТА</span>
              <p class="map-subtext">Здесь будет интегрирована Яндекс.Карта</p>
            </div>
          </div>

          <!-- Контентная часть карточки -->
          <div class="status-card-body">

            <!-- Пошаговый интерактивный элемент (Stepper) на макете Frame 336201 -->
            <div class="stepper-container">
              <!-- Шаг 1 -->
              <button
                  class="step-dot"
                  :class="{ filled: currentStatus >= 1 }"
                  @click="currentStatus = 1"
                  title="Шаг 1: Запись на анализы"
                  aria-label="Шаг 1"
              ></button>
              <span class="step-line"></span>

              <!-- Шаг 2 -->
              <button
                  class="step-dot"
                  :class="{ filled: currentStatus >= 2 }"
                  @click="currentStatus = 2"
                  title="Шаг 2: Материал получен"
                  aria-label="Шаг 2"
              ></button>
              <span class="step-line"></span>

              <!-- Шаг 3 -->
              <button
                  class="step-dot"
                  :class="{ filled: currentStatus >= 3 }"
                  @click="currentStatus = 3"
                  title="Шаг 3: Расшифровка материала"
                  aria-label="Шаг 3"
              ></button>
              <span class="step-line"></span>

              <!-- Шаг 4 -->
              <button
                  class="step-dot"
                  :class="{ filled: currentStatus >= 4 }"
                  @click="currentStatus = 4"
                  title="Шаг 4: Результат готов"
                  aria-label="Шаг 4"
              ></button>
            </div>

            <!-- Контент в зависимости от выбранного шага -->
            <div class="status-details">

              <!-- Шаг 1: Карта и Отмена записи -->
              <div v-if="currentStatus === 1" class="step-content">
                <div v-if="currentStatus === 1" class="status-card-header">
                  <h2 class="patient-welcome-title">Иван, вас ждут по адресу Казань, Островского 9. Здание Invitro.</h2>
                  <p class="patient-welcome-desc">Сдайте кровь до 31 июля — потом запись автоматически отменится</p>
                </div>
                <div class="status-action-row">
                  <button @click="cancelAppointment" class="btn-cancel-appointment">
                    <span>Отменить запись</span>
                    <span class="btn-icon-circle-small">
                      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="12" r="10" stroke="#000000" stroke-width="2"/>
                        <path d="M12 8V12" stroke="#000000" stroke-width="2" stroke-linecap="round"/>
                        <circle cx="12" cy="16" r="1" fill="#000000"/>
                      </svg>
                    </span>
                  </button>
                </div>
              </div>

              <!-- Шаг 2: Материал получен (Frame 1:188) -->
              <div v-else-if="currentStatus === 2" class="step-content text-left">
                <div class="status-badge-container">
                  <span class="status-badge-blue">Новый статус</span>
                </div>
                <h2 class="status-main-title">Материал получен,<br>ожидает расшифровки</h2>
                <p class="status-sub-desc">О новых статусах сообщаем по СМС<br>и на электронной почте</p>
              </div>

              <!-- Шаг 3: Расшифровка (Frame 1:215) -->
              <div v-else-if="currentStatus === 3" class="step-content text-left">
                <h2 class="status-main-title">Расшифровка материала</h2>
                <p class="status-sub-desc">Всё будет готово примерно 29 августа</p>
              </div>

              <!-- Шаг 4: Результат готов (Frame 1:240) -->
              <div v-else-if="currentStatus === 4" class="step-content text-left">
                <h2 class="status-main-title">Ваш результат готов</h2>
                <p class="status-sub-desc">Его можно посмотреть здесь,<br>а также на электронной почте</p>

                <div class="result-actions-group">
                  <button @click="viewOnline" class="btn-black btn-view-online">
                    <span>Посмотреть онлайн</span>
                    <span class="btn-icon">
                      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="12" r="10" stroke="#FFFFFF" stroke-width="2"/>
                        <path d="M11 8L15 12L11 16" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                      </svg>
                    </span>
                  </button>

                  <a href="#" @click.prevent="downloadPdf" class="btn-outline btn-download-pdf">
                    <span>Скачать PDF</span>
                    <span class="btn-icon">
                      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 4V16M12 16L8 12M12 16L16 12" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M4 20H20" stroke="#000000" stroke-width="2" stroke-linecap="round"/>
                      </svg>
                    </span>
                  </a>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- Нижний блок кнопок управления аккаунтом (Frame 1:37) -->
        <div class="dashboard-actions">
          <Link href="/profile" class="btn-outline dashboard-btn-left">
            <span>Данные аккаунта</span>
            <span class="btn-icon">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </span>
          </Link>

          <Link href="/logout" method="post" as="button" class="btn-outline dashboard-btn-right">
            <span>Выйти из аккаунта</span>
            <span class="btn-icon">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </span>
          </Link>
        </div>
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
const currentStatus = ref(1) // Текущий статус по умолчанию (1, 2, 3 или 4)

const cancelForm = useForm({})

const cancelAppointment = () => {
  if (confirm('Вы уверены, что хотите отменить запись на анализы?')) {
    cancelForm.post('/appointment/cancel', {
      preserveScroll: true,
      onSuccess: () => {
        alert('Запись отменена.')
      },
      onError: () => {
        alert('Не удалось отменить запись.')
      }
    })
  }
}

const viewOnline = () => {
  router.visit('/demo-result')
}

const downloadPdf = () => {
  alert('Скачивание PDF-файла с результатами (демо-режим).')
}
</script>

<style scoped>
.patient-page-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background-color: #f5f5f5;
}

.dashboard-header {
  justify-content: space-between;
}

.header-logo {
  font-size: 18px;
  font-weight: 700;
  letter-spacing: 0.1em;
}

.patient-main-content {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
}

.dashboard-container {
  width: 805px; /* Ширина по макету */
  display: flex;
  flex-direction: column;
  gap: 64px; /* gap 64px по макету */
}

@media (max-width: 840px) {
  .dashboard-container {
    width: 100%;
    gap: 40px;
  }
}

.status-card-header {
  text-align: left;
  display: flex;
  flex-direction: column;
  gap: 21px;
  width: 450px;
  max-width: 100%;

}

.patient-welcome-title {
  font-size: 24px;
  font-weight: 400;
  line-height: 1.3;
  color: #000000;
}

.patient-welcome-desc {
  font-size: 21px;
  font-weight: 400;
  color: rgba(0, 0, 0, 0.5);
}

/* Карточка статуса */
.status-card {
  background-color: #ffffff;
  border: 1px solid #dfdfdf;
  display: flex;
  flex-direction: column;
  position: relative;
}

/* Заглушка карты */
.map-placeholder-box {
  width: 100%;
  height: 291px;
  background-color: #ffffff;
  border-bottom: 1px solid #dfdfdf;
  position: relative;
  overflow: hidden;
}

.map-grid-svg {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
}

.map-placeholder-info {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
  background-color: rgba(255, 255, 255, 0.9);
  padding: 24px 40px;
  border: 1px solid rgba(0,0,0,0.08);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
  display: flex;
  flex-direction: column;
  gap: 6px;
  pointer-events: none;
}

.map-label {
  font-size: 18px;
  font-weight: 700;
  letter-spacing: 0.2em;
  color: #000000;
}

.map-subtext {
  font-size: 14px;
  color: rgba(0, 0, 0, 0.5);
}

/* Тело карточки */
.status-card-body {
  padding: 32px; /* 32px отступ по макету */
  height: 393px; /* Фиксированная высота по макету */
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
}

/* Пошаговый индикатор (Stepper) */
.stepper-container {
  display: flex;
  align-items: center;
  gap: 0;
  align-self: flex-start; /* Размещается слева */
}

.step-dot {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  border: 1px solid #000000;
  background-color: transparent;
  cursor: pointer;
  padding: 0;
  margin: 0;
  transition: background-color 0.2s ease, transform 0.2s ease;
  z-index: 2;
  box-sizing: border-box;
}

.step-dot:hover {
  transform: scale(1.15);
}

.step-dot.filled {
  background-color: #000000;
}

.step-line {
  width: 12px; /* ровно 12px по макету */
  height: 1px;
  background-color: #000000; /* сплошная линия */
  margin: 0;
  padding: 0;
  z-index: 1;
}

.status-details {
  margin-top: auto; /* Прижимает текст к низу карточки по макету */
  display: flex;
  flex-direction: column;
}

/* Шаг 1: кнопка отмены */
.status-action-row {
  display: flex;
  align-self: flex-start;
}

.btn-cancel-appointment {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 251px;
  height: 56px;
  background-color: #ffffff;
  border: 1px solid #dfdfdf;
  padding: 16px 24px;
  font-size: 18px;
  font-family: 'Roboto', sans-serif;
  color: #000000;
  cursor: pointer;
  transition: background-color 0.2s ease, border-color 0.2s ease;
}

.btn-cancel-appointment:hover {
  background-color: #fafafa;
  border-color: rgba(0, 0, 0, 0.3);
}

.btn-icon-circle-small {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
}

/* Шаги 2, 3, 4: текстовые стили */
.step-content {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.text-left {
  text-align: left;
}

.status-badge-container {
  display: flex;
  margin-bottom: 8px;
}

.status-badge-blue {
  background-color: #f1faff;
  color: #2baac9;
  padding: 12px 24px;
  font-size: 14px;
  font-weight: 500;
  line-height: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 0;
  border: 1px solid rgba(0,0,0,0.03);
}

.status-main-title {
  font-size: 42px;
  font-weight: 400;
  line-height: 1.2;
  color: #000000;
}

.status-sub-desc {
  font-size: 21px;
  line-height: 1.5;
  color: rgba(0, 0, 0, 0.5);
}

/* Группа кнопок результатов */
.result-actions-group {
  display: flex;
  gap: 4px; /* gap 4px по макету */
  margin-top: 8px;
}

.btn-view-online {
  width: 265px; /* ширина по макету */
}

.btn-download-pdf {
  width: 197px; /* ширина по макету */
}

@media (max-width: 600px) {
  .result-actions-group {
    flex-direction: column;
    width: 100%;
  }
  .btn-view-online,
  .btn-download-pdf {
    width: 100% !important;
    max-width: 100%;
  }
}

/* Нижний блок кнопок управления аккаунтом */
.dashboard-actions {
  display: flex;
  width: 100%;
}

.dashboard-btn-left {
  width: 394px;
  border-right: none;
}

.dashboard-btn-right {
  width: 411px;
  flex-grow: 1;
}

/* Мобильная адаптация */
@media (max-width: 768px) {
  .dashboard-container {
    width: 100%;
    gap: 32px;
    padding: 0 16px;
  }

  .patient-welcome-title {
    font-size: 20px;
  }

  .patient-welcome-desc {
    font-size: 16px;
  }

  .map-placeholder-box {
    height: 161px; /* 161px по мобильному макету */
  }

  .status-badge-blue {
    padding: 8px 12px; /* Компактные отступы плашки на мобильных */
  }

  .btn-cancel-appointment {
    width: 100%;
  }

  .status-main-title {
    font-size: 28px;
  }

  .status-sub-desc {
    font-size: 16px;
  }

  .dashboard-actions {
    flex-direction: column;
  }

  .dashboard-btn-left {
    width: 100% !important;
    height: 66px !important;
    border: 1px solid #dfdfdf !important;
    border-bottom: none !important;
  }

  .dashboard-btn-right {
    width: 100% !important;
    height: 66px !important;
    border: 1px solid #dfdfdf !important;
  }
}
</style>