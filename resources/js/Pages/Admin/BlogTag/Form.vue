<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  tag: Object // null при создании, объект тега при редактировании
})

const isEdit = !!props.tag

const form = useForm({
  name: props.tag?.name || '',
  slug: props.tag?.slug || ''
})

const submit = () => {
  if (isEdit) {
    form.put(route('admin.blog-tags.update', props.tag.id), {
      preserveScroll: true
    })
  } else {
    form.post(route('admin.blog-tags.store'), {
      preserveScroll: true
    })
  }
}

// Авто-генерация слага при вводе названия (если поле slug пустое)
const generateSlug = () => {
  if (!form.name || form.slug) return
  form.slug = form.name.toLowerCase()
    .replace(/[^\w\s-]/g, '')
    .replace(/[\s_-]+/g, '-')
    .replace(/^-+|-+$/g, '')
}
</script>

<template>
  <Head :title="isEdit ? 'Редактировать тег' : 'Новый тег'" />
  
  <AdminLayout>
    <div class="py-12">
      <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white shadow rounded-lg p-6">
          <h2 class="text-xl font-bold text-gray-800 mb-6">
            {{ isEdit ? 'Редактировать тег' : 'Создать новый тег' }}
          </h2>
          
          <form @submit.prevent="submit" class="space-y-6">
            
            <!-- Название тега -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Название тега *
              </label>
              <input 
                v-model="form.name" 
                @blur="generateSlug"
                type="text" 
                class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border" 
                placeholder="Например: Аллергия, Диагностика, Здоровье"
                required
                maxlength="255"
              />
              <p class="text-xs text-gray-500 mt-1">
                Отображается на сайте и в фильтре
              </p>
              <div v-if="form.errors.name" class="text-red-500 text-xs mt-1">
                {{ form.errors.name }}
              </div>
            </div>
            
            <!-- Слаг (ЧПУ) -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Символьный код (ЧПУ)
              </label>
              <input 
                v-model="form.slug" 
                type="text" 
                class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border" 
                placeholder="allergiya (генерируется автоматически)"
                maxlength="255"
              />
              <p class="text-xs text-gray-500 mt-1">
                Используется в URL. Если пусто — создастся из названия
              </p>
              <div v-if="form.errors.slug" class="text-red-500 text-xs mt-1">
                {{ form.errors.slug }}
              </div>
            </div>
            
            <!-- Кнопки -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
              <Link 
                :href="route('admin.blog-tags.index')" 
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
                <span v-else>{{ isEdit ? 'Сохранить изменения' : 'Создать тег' }}</span>
              </button>
            </div>
            
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>