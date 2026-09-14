<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  currentPdfUrl: String,
  currentPdfName: String,
  seoMeta: { type: Object, default: () => ({ title: '', description: '', keywords: '' }) },
})

const form = useForm({
  pdf_file: null,
  meta_title: props.seoMeta?.title || '',
  meta_description: props.seoMeta?.description || '',
  meta_keywords: props.seoMeta?.keywords || '',
})

const isEditingSeo = ref(false)

const submit = () => {
  form.post(route('admin.demo-result.update'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      isEditingSeo.value = false
    },
  })
}
</script>

<template>
  <Head title="Настройка демо-результата" />

  <AdminLayout>
    <div class="py-12">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <!-- Флэш-сообщение -->
        <div v-if="$page.props.flash?.success" class="p-4 bg-green-100 border border-green-300 text-green-700 rounded-lg">
          {{ $page.props.flash.success }}
        </div>

        <!-- 🔹 🔥 Блок Meta-тегов (SEO) -->
        <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
          <div class="flex items-start justify-between mb-4">
            <div>
              <h3 class="text-lg font-semibold text-gray-800">SEO настройки (Meta-теги)</h3>
              <p class="text-xs text-gray-500">Управление мета-тегами страницы демо-результата</p>
            </div>
            <button
                v-if="!isEditingSeo"
                type="button"
                @click="isEditingSeo = true"
                class="text-sm text-blue-600 hover:text-blue-800 font-medium"
            >
              Редактировать
            </button>
            <button
                v-else
                type="button"
                @click="isEditingSeo = false"
                class="text-sm text-gray-600 hover:text-gray-800"
            >
              Отмена
            </button>
          </div>

          <!-- Режим редактирования SEO -->
          <div v-if="isEditingSeo" class="space-y-4">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Meta Title</label>
              <input
                  v-model="form.meta_title"
                  type="text"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                  placeholder="Демонстрационный результат анализа — ALEX LAB"
                  maxlength="255"
              />
              <p class="text-xs text-gray-500 mt-1">{{ form.meta_title?.length || 0 }}/255</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Meta Description</label>
              <textarea
                  v-model="form.meta_description"
                  rows="2"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                  placeholder="Пример расшифровки аллергочипа ALEX2..."
                  maxlength="500"
              ></textarea>
              <p class="text-xs text-gray-500 mt-1">{{ form.meta_description?.length || 0 }}/500</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Meta Keywords</label>
              <input
                  v-model="form.meta_keywords"
                  type="text"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                  placeholder="демо результат, пример анализа, ALEX2"
                  maxlength="500"
              />
              <p class="text-xs text-gray-500 mt-1">{{ form.meta_keywords?.length || 0 }}/500</p>
            </div>
          </div>

          <!-- Режим просмотра SEO -->
          <div v-else class="space-y-2 text-sm">
            <div>
              <span class="font-medium text-gray-500 text-xs">Title: </span>
              <span class="text-gray-800 font-medium">{{ form.meta_title || '—' }}</span>
            </div>
            <div>
              <span class="font-medium text-gray-500 text-xs">Description: </span>
              <span class="text-gray-700">{{ form.meta_description || '—' }}</span>
            </div>
            <div>
              <span class="font-medium text-gray-500 text-xs">Keywords: </span>
              <span class="text-gray-700">{{ form.meta_keywords || '—' }}</span>
            </div>
          </div>
        </div>

        <!-- 🔹 Основная форма -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-200">
          <h1 class="text-2xl font-bold text-gray-800 mb-6">Настройка демо-результата</h1>

          <form @submit.prevent="submit">
            <div class="mb-6">
              <label class="block text-sm font-medium text-gray-700 mb-2">Загрузить PDF файл</label>
              <input
                  type="file"
                  @input="form.pdf_file = $event.target.files[0]"
                  accept="application/pdf"
                  class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
              />
              <p class="mt-1 text-xs text-gray-500">Допустимый формат: PDF. Максимальный размер: 20 МБ.</p>
            </div>

            <div v-if="currentPdfUrl" class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
              <p class="text-sm font-medium text-gray-700 mb-1">Текущий файл:</p>
              <a :href="currentPdfUrl" target="_blank" class="text-blue-600 hover:underline text-sm flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                demoresult
              </a>
            </div>

            <div class="flex justify-end">
              <button
                  type="submit"
                  :disabled="form.processing"
                  class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed font-medium transition-colors"
              >
                {{ form.processing ? 'Сохранение...' : 'Сохранить изменения' }}
              </button>
            </div>
          </form>
        </div>

      </div>
    </div>
  </AdminLayout>
</template>