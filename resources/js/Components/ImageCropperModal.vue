<script setup>
import { ref, watch } from 'vue'
import { Cropper, CircleStencil } from 'vue-advanced-cropper'
import 'vue-advanced-cropper/dist/style.css'

const props = defineProps({
  show: Boolean,
  imageSrc: String,
})

const emit = defineEmits(['close', 'crop'])

const cropper = ref(null)

const crop = () => {
  if (cropper.value) {
    const result = cropper.value.getResult()
    if (result && result.canvas) {
      result.canvas.toBlob((blob) => {
        const file = new File([blob], 'avatar.webp', { type: 'image/webp' })
        emit('crop', file)
        emit('close')
      }, 'image/webp', 0.9)
    }
  }
}

const close = () => {
  emit('close')
}
</script>

<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 backdrop-blur-sm">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 overflow-hidden">
        <!-- Заголовок -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
          <h3 class="text-lg font-semibold text-gray-900">Кадрирование аватара</h3>
          <button @click="close" class="text-gray-400 hover:text-gray-600 transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <!-- Кроппер -->
        <div class="p-6 bg-gray-900" style="height: 400px;">
          <Cropper
              v-if="imageSrc"
              ref="cropper"
              :src="imageSrc"
              :stencil-component="CircleStencil"
              :stencil-props="{
              aspectRatio: 1,
              movable: true,
              resizable: true,
              minWidth: 100,
              minHeight: 100,
            }"
              class="cropper w-full h-full"
          />
        </div>

        <!-- Кнопки -->
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200">
          <button @click="close" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
            Отмена
          </button>
          <button @click="crop" class="px-4 py-2 text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
            Обрезать и сохранить
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style>
.cropper .vue-advanced-cropper__stencil {
  border: 2px solid #fff;
  box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.5);
}
</style>