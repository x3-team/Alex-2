<script setup>
import { watch, onMounted, onBeforeUnmount, ref } from 'vue'

const props = defineProps({
  open: { type: Boolean, default: false },
})

const emit = defineEmits(['close'])

const sheetDragOffset = ref(0)
const isSheetDragging = ref(false)
const dragStartY = ref(0)
let previousBodyOverflow = ''

const close = () => {
  sheetDragOffset.value = 0
  isSheetDragging.value = false
  emit('close')
}

const startSheetDrag = (event) => {
  if (!import.meta.client || window.matchMedia('(min-width: 1025px)').matches) return
  isSheetDragging.value = true
  dragStartY.value = event.clientY
  event.currentTarget.setPointerCapture?.(event.pointerId)
}

const moveSheetDrag = (event) => {
  if (!isSheetDragging.value) return
  sheetDragOffset.value = Math.max(0, event.clientY - dragStartY.value)
}

const finishSheetDrag = (event) => {
  if (!isSheetDragging.value) return
  event.currentTarget.releasePointerCapture?.(event.pointerId)
  const shouldClose = sheetDragOffset.value > Math.min(window.innerHeight * 0.2, 140)
  isSheetDragging.value = false
  if (shouldClose) {
    close()
    return
  }
  sheetDragOffset.value = 0
}

const handleKeydown = (event) => {
  if (event.key === 'Escape' && props.open) close()
}

watch(() => props.open, (visible) => {
  if (!import.meta.client) return
  if (visible) {
    sheetDragOffset.value = 0
    isSheetDragging.value = false
    previousBodyOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return
  }
  document.body.style.overflow = previousBodyOverflow
})

onMounted(() => window.addEventListener('keydown', handleKeydown))
onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
  if (import.meta.client) document.body.style.overflow = previousBodyOverflow
})
</script>

<template>
  <Teleport to="body">
    <Transition name="cabinet-dev-modal-fade">
      <section
        v-if="open"
        class="cabinet-dev-modal"
        aria-label="Раздел в разработке"
        role="dialog"
        aria-modal="true"
        @click.self="close"
      >
        <div
          class="cabinet-dev-modal__sheet"
          :class="{ 'is-dragging': isSheetDragging }"
          :style="{ '--sheet-drag-offset': `${sheetDragOffset}px` }"
        >
          <div
            class="cabinet-dev-modal__grabber-area"
            role="button"
            tabindex="0"
            aria-label="Потяните вниз, чтобы закрыть"
            @pointerdown="startSheetDrag"
            @pointermove="moveSheetDrag"
            @pointerup="finishSheetDrag"
            @pointercancel="finishSheetDrag"
            @keydown.enter.prevent="close"
            @keydown.space.prevent="close"
          >
            <div class="cabinet-dev-modal__grabber" aria-hidden="true"></div>
          </div>

          <header class="cabinet-dev-modal__header">
            <h2>Личный кабинет</h2>
            <button class="cabinet-dev-modal__close" type="button" aria-label="Закрыть" @click="close">✕</button>
          </header>

          <div class="cabinet-dev-modal__body">
            <div class="cabinet-dev-modal__icon" aria-hidden="true">🛠️</div>
            <p class="cabinet-dev-modal__title">Раздел в разработке</p>
            <p class="cabinet-dev-modal__description">
              Мы активно работаем над созданием личного кабинета. Совсем скоро здесь можно будет сохранять и отслеживать результаты ваших анализов!
            </p>
            <button class="cabinet-dev-modal__button" type="button" @click="close">
              Понятно
            </button>
          </div>
        </div>
      </section>
    </Transition>
  </Teleport>
</template>

<style scoped>
.cabinet-dev-modal {
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

.cabinet-dev-modal__sheet {
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 32px 85.333px rgb(0 0 0 / 24%);
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  gap: 24px;
  max-height: calc(100vh - 48px);
  overflow: auto;
  padding: 42.667px;
  width: min(520px, 100%);
}

.cabinet-dev-modal__grabber,
.cabinet-dev-modal__grabber-area {
  display: none;
}

.cabinet-dev-modal__header {
  align-items: center;
  display: flex;
  justify-content: space-between;
  min-height: 53.333px;
}

.cabinet-dev-modal__header h2 {
  color: #0f0f0f;
  font-family: Inter, Arial, sans-serif;
  font-size: 37.333px;
  font-weight: 600;
  letter-spacing: -0.02em;
  line-height: 45.333px;
  margin: 0;
}

.cabinet-dev-modal__close {
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

.cabinet-dev-modal__body {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 12px 0 8px;
}

.cabinet-dev-modal__icon {
  font-size: 48px;
  line-height: 1;
  margin-bottom: 16px;
}

.cabinet-dev-modal__title {
  font-family: Inter, Arial, sans-serif;
  font-size: 22px;
  font-weight: 600;
  color: #0f0f0f;
  margin: 0 0 12px 0;
}

.cabinet-dev-modal__description {
  font-family: Inter, Arial, sans-serif;
  font-size: 16px;
  line-height: 1.5;
  color: #666;
  margin: 0 0 28px 0;
  max-width: 380px;
}

.cabinet-dev-modal__button {
  background: #0f0f0f;
  color: #fff;
  border: 0;
  border-radius: 10.667px;
  padding: 14px 32px;
  font-family: Inter, Arial, sans-serif;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 160ms ease;
  width: 100%;
}

.cabinet-dev-modal__button:hover {
  opacity: 0.9;
}

.cabinet-dev-modal-fade-enter-active,
.cabinet-dev-modal-fade-leave-active { transition: opacity 180ms ease; }
.cabinet-dev-modal-fade-enter-from,
.cabinet-dev-modal-fade-leave-to { opacity: 0; }

@media (max-width: 1024px) {
  .cabinet-dev-modal {
    align-items: flex-end;
    padding: 0;
  }

  .cabinet-dev-modal__sheet {
    border-radius: 21.538px 21.538px 0 0;
    gap: 17.231px;
    max-height: min(100dvh, 620.308px);
    padding: 12.923px 21.538px 36.615px;
    transform: translateY(var(--sheet-drag-offset, 0));
    transition: transform 340ms cubic-bezier(0.22, 1, 0.36, 1);
    width: min(420px, 100vw);
  }

  .cabinet-dev-modal__sheet.is-dragging {
    transition: none;
  }

  .cabinet-dev-modal__grabber-area {
    cursor: grab;
    display: flex;
    justify-content: center;
    padding: 4px 0 8px;
    touch-action: none;
  }

  .cabinet-dev-modal__grabber {
    background: #d9d9d9;
    border-radius: 99px;
    display: block;
    height: 4px;
    width: 42px;
  }

  .cabinet-dev-modal__header h2 {
    font-size: 24px;
    line-height: 1.2;
  }

  .cabinet-dev-modal__close {
    flex-basis: 40px;
    font-size: 18px;
    height: 40px;
  }

  .cabinet-dev-modal__icon {
    font-size: 40px;
    margin-bottom: 12px;
  }

  .cabinet-dev-modal__title {
    font-size: 18px;
  }

  .cabinet-dev-modal__description {
    font-size: 14px;
    margin-bottom: 20px;
  }
}
</style>
