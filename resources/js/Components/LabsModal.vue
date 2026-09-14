<script setup>
import { ref } from 'vue'

const props = defineProps({
  isOpen: { type: Boolean, default: false }
})

const emit = defineEmits(['close'])

const isSheetDragging = ref(false)
const sheetDragOffset = ref(0)
let startY = 0

const labs = [
  { name: 'Гемотест', logo: '/assets/figma-lab-gemotest.webp', logoClass: 'test-location-modal__logo--gemotest', href: 'https://gemotest.ru/moskva/catalog/allergii/pervichnye-testy/obshchaya-allergodiagnostika/allergochip-alex-2/allergochip-alex-2-300-allergokomponentov-allergy-explorer-2-19ddaf/' },
  { name: 'ДНКОМ', logo: '/assets/figma-lab-dncom.webp', logoClass: 'test-location-modal__logo--dncom', href: 'https://dnkom.ru/analizy-i-tseny/allergochip-alex2-allergy-explorer/allergochip-alex2-allergy-explorer-2-do-300-allergokomponentov-i-obshchiy-ige/' },
  { name: 'Ситилаб', logo: '/assets/figma-lab-citilab.webp', logoClass: 'test-location-modal__logo--citilab', href: '#' },
  { name: 'KDL', logo: '/assets/figma-lab-kdl.webp', logoClass: 'test-location-modal__logo--kdl', href: 'https://kdl.ru/analizy-i-tseny/msk/allergochip-alex2-300-komponentov' },
  { name: 'CMD', logo: '/assets/figma-lab-cmd.webp', logoClass: 'test-location-modal__logo--cmd', href: 'https://www.cmd-online.ru/analizy-i-tseny/katalog-analizov/msk/allergochip_alex_2_300_allergokomponentov_i_ig_e_obshhij_ig_e/' },
  { name: 'CHROMOLAB', logo: '/assets/figma-lab-chromolab.webp', logoClass: 'test-location-modal__logo--chromolab', href: 'https://www.chromolab.ru/issledovaniya/al866_allergochip_alex2_300_komponentov_vklyuchaet_opredelenie_obshchego_ige/' }
]

const startSheetDrag = (e) => {
  isSheetDragging.value = true
  startY = e.clientY || e.touches?.[0]?.clientY || 0
}

const moveSheetDrag = (e) => {
  if (!isSheetDragging.value) return
  const currentY = e.clientY || e.touches?.[0]?.clientY || 0
  const delta = currentY - startY
  if (delta > 0) {
    sheetDragOffset.value = delta
  }
}

const finishSheetDrag = () => {
  if (!isSheetDragging.value) return
  isSheetDragging.value = false
  if (sheetDragOffset.value > 120) {
    handleClose()
  }
  sheetDragOffset.value = 0
}

const handleClose = () => {
  sheetDragOffset.value = 0
  emit('close')
}

const withClinicUtm = (url) => {
  if (!url || url === '#') return url
  if (url.includes('utm_source=')) return url
  return url + (url.includes('?') ? '&' : '?') + 'utm_source=site&utm_medium=cta&utm_campaign=clinic'
}

const handleLabClick = (lab) => {
  handleClose()
  if (lab.href && lab.href !== '#') {
    window.open(withClinicUtm(lab.href), '_blank')
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="test-location-modal-fade">
      <section
          v-if="isOpen"
          class="test-location-modal"
          aria-label="Выбор лаборатории для сдачи теста"
          role="dialog"
          aria-modal="true"
          @click.self="handleClose"
      >
        <div
            class="test-location-modal__sheet"
            :class="{ 'test-location-modal__sheet--dragging': isSheetDragging }"
            :style="{ '--sheet-drag-offset': `${sheetDragOffset}px` }"
        >
          <div
              class="test-location-modal__grabber-area"
              role="button"
              tabindex="0"
              aria-label="Потяните вниз, чтобы закрыть"
              @pointerdown="startSheetDrag"
              @pointermove="moveSheetDrag"
              @pointerup="finishSheetDrag"
              @pointercancel="finishSheetDrag"
              @keydown.enter.prevent="handleClose"
              @keydown.space.prevent="handleClose"
          >
            <div class="test-location-modal__grabber" aria-hidden="true"></div>
          </div>

          <header class="test-location-modal__header">
            <h2>Где сдать тест?</h2>
            <button class="test-location-modal__close" type="button" aria-label="Закрыть" @click="handleClose">✕</button>
          </header>

          <div class="test-location-modal__labs">
            <article v-for="lab in labs" :key="lab.name" class="test-location-modal__lab-card">
              <div class="test-location-modal__logo-box">
                <img :src="lab.logo" :class="lab.logoClass" :alt="`Логотип ${lab.name}`" loading="lazy" />
              </div>
              <button
                  class="test-location-modal__lab-action"
                  type="button"
                  :aria-label="`Записаться на тест в ${lab.name}`"
                  @click="handleLabClick(lab)"
              >
                <span>Записаться на тест</span>
                <span class="test-location-modal__arrow" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none">
                    <path d="M9 5L15 12L9 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                </span>
              </button>
            </article>
          </div>
        </div>
      </section>
    </Transition>
  </Teleport>
</template>

<style scoped>
/* Стили лабораторий */
.test-location-modal {
  align-items: center;
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  background: rgb(78 98 77 / 63%);
  display: flex;
  inset: 0;
  justify-content: center;
  padding: 24px;
  position: fixed;
  z-index: 2000;
}

.test-location-modal__sheet {
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 32px 85.333px rgb(0 0 0 / 24%);
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 32px;
  max-height: calc(100vh - 48px);
  overflow: auto;
  padding: 42.667px;
  width: min(1226.667px, 100%);
}

.test-location-modal__grabber { display: none; }
.test-location-modal__grabber-area { display: none; }

.test-location-modal__header {
  align-items: center;
  display: flex;
  justify-content: space-between;
  min-height: 53.333px;
}

.test-location-modal__header h2 {
  color: #0f0f0f;
  font-family: Inter, Arial, sans-serif;
  font-size: 37.333px;
  font-weight: 600;
  letter-spacing: -0.02em;
  line-height: 45.333px;
  margin: 0;
}

.test-location-modal__close {
  align-items: center;
  background: #f4f4f2;
  border: 0;
  border-radius: 10.667px;
  color: #0f0f0f;
  cursor: pointer;
  display: flex;
  flex: 0 0 53.333px;
  font-family: Inter, Arial, sans-serif;
  font-size: 21.333px;
  font-weight: 500;
  height: 53.333px;
  justify-content: center;
  line-height: 1;
  padding: 0;
}

.test-location-modal__close:focus-visible,
.test-location-modal__lab-card:focus-within {
  outline: 2px solid #0f0f0f;
  outline-offset: 0;
}

.test-location-modal__labs {
  display: grid;
  gap: 21.333px;
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.test-location-modal__lab-card {
  background: #f5f5f3;
  border: 1.333px solid #e7e7e5;
  border-radius: 13.333px;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 21.333px;
  min-width: 0;
  padding: 21.333px;
  transition: border-color 160ms ease, box-shadow 160ms ease;
}

.test-location-modal__lab-card:hover {
  border-color: #0f0f0f;
  box-shadow: 0 6px 16px rgb(0 0 0 / 10%);
}

.test-location-modal__logo-box {
  align-items: center;
  background: #fff;
  border-radius: 10.667px;
  display: flex;
  height: 117.333px;
  justify-content: center;
  overflow: hidden;
}

.test-location-modal__logo-box img {
  display: block;
  max-height: 58px;
  max-width: calc(100% - 32px);
  object-fit: contain;
}

.test-location-modal__logo--gemotest,
.test-location-modal__logo--chromolab { width: min(268px, calc(100% - 32px)); }
.test-location-modal__logo--dncom { width: min(156px, calc(100% - 32px)); }
.test-location-modal__logo--citilab,
.test-location-modal__logo--kdl { width: min(196px, calc(100% - 32px)); }
.test-location-modal__logo--cmd { width: min(152px, calc(100% - 32px)); }

.test-location-modal__lab-action {
  align-items: center;
  background: #0f0f0f;
  border: 0;
  border-radius: 10.667px;
  color: #fff;
  cursor: pointer;
  display: flex;
  font-family: Inter, Arial, sans-serif;
  font-size: 18.667px;
  font-weight: 600;
  justify-content: space-between;
  letter-spacing: -0.01em;
  line-height: 26.667px;
  min-height: 64px;
  padding: 16px 16px 16px 21.333px;
  text-align: left;
}

.test-location-modal__arrow {
  align-items: center;
  border: 1.333px solid rgb(255 255 255 / 50%);
  border-radius: 50%;
  display: flex;
  flex: 0 0 29.333px;
  height: 29.333px;
  justify-content: center;
  margin-left: 12px;
}

.test-location-modal__arrow svg {
  height: 16px;
  width: 16px;
}

.test-location-modal-fade-enter-active,
.test-location-modal-fade-leave-active { transition: opacity 180ms ease; }
.test-location-modal-fade-enter-from,
.test-location-modal-fade-leave-to { opacity: 0; }

@media (max-width: 1024px) {
  .test-location-modal {
    align-items: flex-end;
    padding: 0;
  }

  .test-location-modal__sheet {
    border-radius: 21.538px 21.538px 0 0;
    gap: 17.231px;
    max-height: min(100dvh, 620.308px);
    padding: 12.923px 21.538px 36.615px;
    transform: translateY(var(--sheet-drag-offset, 0));
    transition: transform 340ms cubic-bezier(0.22, 1, 0.36, 1);
    width: min(420px, 100vw);
  }

  .test-location-modal__sheet--dragging { transition: none; }

  .test-location-modal-fade-enter-active .test-location-modal__sheet,
  .test-location-modal-fade-leave-active .test-location-modal__sheet {
    transition: transform 340ms cubic-bezier(0.22, 1, 0.36, 1);
  }

  .test-location-modal-fade-enter-from .test-location-modal__sheet,
  .test-location-modal-fade-leave-to .test-location-modal__sheet {
    transform: translateY(100%);
  }

  .test-location-modal__grabber-area {
    align-items: center;
    cursor: grab;
    display: flex;
    justify-content: center;
    margin: -12.923px -21.538px -8px;
    min-height: 30px;
    touch-action: none;
  }

  .test-location-modal__grabber-area:active { cursor: grabbing; }

  .test-location-modal__grabber-area:focus-visible {
    outline: 2px solid #0f0f0f;
    outline-offset: -2px;
  }

  .test-location-modal__grabber {
    background: #d9d9d6;
    border-radius: 2.154px;
    display: block;
    height: 4.308px;
    margin: 0 auto;
    width: 43.077px;
  }

  .test-location-modal__header { min-height: 38.769px; }

  .test-location-modal__header h2 {
    font-size: 23.692px;
    line-height: 30.154px;
  }

  .test-location-modal__close {
    border-radius: 8.615px;
    flex-basis: 38.769px;
    font-size: 15.077px;
    height: 38.769px;
  }

  .test-location-modal__labs {
    display: flex;
    flex-direction: column;
    gap: 10.769px;
  }

  .test-location-modal__lab-card {
    align-items: center;
    border-width: 1.077px;
    border-radius: 10.769px;
    flex-direction: row;
    gap: 10.769px;
    min-height: 73.231px;
    padding: 10.769px;
  }

  .test-location-modal__logo-box {
    border-radius: 8.615px;
    flex: 0 0 133.538px;
    height: 51.692px;
  }

  .test-location-modal__logo-box img {
    max-height: 34px;
    max-width: calc(100% - 18px);
  }

  .test-location-modal__logo--gemotest,
  .test-location-modal__logo--chromolab { width: 111px; }
  .test-location-modal__logo--dncom { width: 72px; }
  .test-location-modal__logo--citilab,
  .test-location-modal__logo--kdl { width: 90px; }
  .test-location-modal__logo--cmd { width: 70px; }

  .test-location-modal__lab-action {
    border-radius: 8.615px;
    flex: 1 1 auto;
    font-size: 15.077px;
    line-height: 20px;
    min-height: 47.385px;
    padding: 11.846px 10.769px 11.846px 15.077px;
  }

  .test-location-modal__arrow {
    border-width: 1.077px;
    flex-basis: 21.538px;
    height: 21.538px;
    margin-left: 8px;
  }

  .test-location-modal__arrow svg {
    height: 12px;
    width: 12px;
  }
}

@media (max-width: 370px) {
  .test-location-modal__sheet { padding-left: 14px; padding-right: 14px; }
  .test-location-modal__logo-box { flex-basis: 110px; }
  .test-location-modal__lab-action { font-size: 13px; padding-left: 10px; }
}
</style>