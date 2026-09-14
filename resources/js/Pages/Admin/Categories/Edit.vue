<script setup>
import { ref, watch } from 'vue'
import { useForm, Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  category: {
    type: Object,
    required: true
  }
})

const form = useForm({
  name: props.category.name ?? '',
  slug: props.category.slug ?? '',
  description: props.category.description ?? '',
})

// Авто-генерация слага (только если поле пустое или не менялось)
const generateSlug = () => {
  if (props.category?.id && form.slug && form.slug !== oldSlug.value) return
  if (!form.name) return
  
  form.slug = form.name.toLowerCase()
    .replace(/[^\w\s-]/g, '')
    .replace(/[\s_-]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

const oldSlug = ref(props.category.slug)

// Отправка формы
const submit = () => {
  form.put(route('admin.categories.update', props.category.id), {
    preserveScroll: true,
    onSuccess: () => {
      oldSlug.value = form.slug
    },
    onError: (errors) => {
      console.error('Validation errors:', errors)
    }
  })
}

// Удаление категории
const destroy = () => {
  if (confirm('Удалить эту категорию?')) {
    router.delete(route('admin.categories.destroy', props.category.id), {
      preserveScroll: true,
      onSuccess: () => {
        // Редирект после удаления
      }
    })
  }
}
</script>

<template>
  <Head :title="`Редактировать: ${category.name}`" />
  
  <AdminLayout>
    <div class="py-12">
      <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white shadow rounded-lg p-6">
          <h2 class="text-2xl font-bold mb-6 text-gray-800">Редактировать категорию</h2>
          
          <form @submit.prevent="submit" class="space-y-6">
            
            <!-- Название -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Название *</label>
              <input 
                v-model="form.name" 
                @blur="generateSlug" 
                type="text" 
                class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border" 
                required 
              />
              <div v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</div>
            </div>

            <!-- Символьный код (ЧПУ) -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Символьный код (ЧПУ)</label>
              <input 
                v-model="form.slug" 
                type="text" 
                class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border font-mono" 
              />
              <p class="text-xs text-gray-500 mt-1">Используется в URL. Не меняйте, если категория уже используется в постах.</p>
              <div v-if="form.errors.slug" class="text-red-500 text-xs mt-1">{{ form.errors.slug }}</div>
            </div>

            <!-- Описание -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Описание</label>
              <textarea 
                v-model="form.description" 
                rows="4" 
                class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border" 
                placeholder="Краткое описание категории..."
              ></textarea>
              <p class="text-xs text-gray-500 mt-1">Отображается на странице категории (опционально)</p>
              <div v-if="form.errors.description" class="text-red-500 text-xs mt-1">{{ form.errors.description }}</div>
            </div>

            <!-- Кнопки -->
            <div class="flex items-center justify-between pt-4 border-t border-gray-100">
              <div>
                <button 
                  type="button"
                  @click="destroy"
                  class="text-red-600 hover:text-red-800 text-sm font-medium"
                >
                  🗑️ Удалить категорию
                </button>
              </div>
              
              <div class="flex gap-3">
                <a 
                  :href="route('admin.categories.index')" 
                  class="px-4 py-2 bg-white border border-gray-300 rounded-md text-gray-700 text-sm font-medium hover:bg-gray-50"
                >
                  Отмена
                </a>
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
                  <span v-else>Сохранить изменения</span>
                </button>
              </div>
            </div>
            
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>