<script setup>
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import TiptapEditor from '@/Components/TiptapEditor.vue'

const props = defineProps({
  page: Object,
})

const keyLabels = {
  about: 'О нас',
  licenses: 'Лицензии',
  contacts: 'Контакты',
}

const form = useForm({
  title: props.page?.title || '',
  content: props.page?.content || '',
})
</script>

<template>
  <Head :title="`Редактировать: ${keyLabels[page.key] || page.key}`" />

  <AdminLayout>
    <div class="py-12">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Заголовок + Назад -->
        <div class="flex items-center justify-between mb-6">
          <h1 class="text-2xl font-bold text-gray-800">
            Редактировать: {{ keyLabels[page.key] || page.key }}
          </h1>
          <Link
              :href="route('admin.alex-lab.index')"
              class="text-gray-600 hover:text-gray-900 text-sm"
          >
            ← Назад к списку
          </Link>
        </div>

        <form @submit.prevent="form.put(route('admin.alex-lab.update', page.key))" class="space-y-6">

          <!-- Заголовок страницы -->
          <div class="bg-white shadow rounded-lg p-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Заголовок раздела
            </label>
            <input
                v-model="form.title"
                type="text"
                class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border"
                placeholder="Название раздела"
            />
            <div v-if="form.errors.title" class="text-red-500 text-xs mt-1">
              {{ form.errors.title }}
            </div>
          </div>

          <!-- Контент с редактором -->
          <div class="bg-white shadow rounded-lg p-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Контент
            </label>
            <TiptapEditor v-model="form.content" />
            <div v-if="form.errors.content" class="text-red-500 text-xs mt-1">
              {{ form.errors.content }}
            </div>
          </div>

          <!-- Кнопки -->
          <div class="flex items-center justify-end gap-3">
            <Link
                :href="route('admin.alex-lab.index')"
                class="px-4 py-2 bg-white border border-gray-300 rounded-md text-gray-700 text-sm font-medium hover:bg-gray-50"
            >
              Отмена
            </Link>
            <button
                type="submit"
                :disabled="form.processing"
                class="px-4 py-2 bg-blue-600 border border-transparent rounded-md text-white text-sm font-medium hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
            >
              <span v-if="form.processing" class="flex items-center gap-2">
                <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Сохранение...
              </span>
              <span v-else>Сохранить</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>