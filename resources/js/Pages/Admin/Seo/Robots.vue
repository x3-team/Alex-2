<script setup>
import { ref } from 'vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  robotsContent: { type: String, default: '' },
  sitemapUrl: { type: String, default: '' },
})

const form = useForm({
  robots_content: props.robotsContent,
})

const submit = () => {
  form.post(route('admin.robots.update'), {
    preserveScroll: true,
    onSuccess: () => {
      // Успех
    }
  })
}

// Подсчёт символов
const charCount = ref(form.robots_content?.length || 0)
</script>

<template>
  <Head title="Robots.txt" />

  <AdminLayout>
    <div class="py-12">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg">

          <!-- Заголовок -->
          <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
              <div>
                <h2 class="text-2xl font-bold text-gray-800">Редактирование Robots.txt</h2>
                <p class="text-sm text-gray-600 mt-1">
                  Управляйте файлом robots.txt для поисковых систем
                </p>
              </div>
              <Link
                  :href="route('admin.home')"
                  class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 text-sm font-medium"
              >
                ← Назад
              </Link>
            </div>
          </div>

          <!-- Форма -->
          <form @submit.prevent="submit" class="p-6 space-y-6">

            <!-- Сообщение об успехе -->
            <div v-if="$page.props.flash?.success" class="p-4 bg-green-50 border border-green-200 rounded-md">
              <p class="text-sm text-green-700">{{ $page.props.flash.success }}</p>
            </div>

            <!-- Описание -->
            <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
              <h3 class="text-sm font-semibold text-blue-900 mb-2">Подсказки:</h3>
              <ul class="text-sm text-blue-800 space-y-1 list-disc list-inside">
                <li><code>User-agent: *</code> — правила для всех поисковиков</li>
                <li><code>Allow: /</code> — разрешить индексацию</li>
                <li><code>Disallow: /admin</code> — запретить индексацию админки</li>
                <li><code>Sitemap:</code> — указать путь к карте сайта</li>
              </ul>
            </div>

            <!-- Текущий URL sitemap -->
            <div class="bg-gray-50 border border-gray-200 rounded-md p-3">
              <p class="text-xs text-gray-600 mb-1">URL вашего Sitemap:</p>
              <a
                  :href="sitemapUrl"
                  target="_blank"
                  class="text-sm text-blue-600 hover:underline font-mono"
              >
                {{ sitemapUrl }}
              </a>
            </div>

            <!-- Textarea -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Содержимое Robots.txt
              </label>
              <textarea
                  v-model="form.robots_content"
                  @input="charCount = form.robots_content.length"
                  rows="15"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border font-mono text-sm"
                  placeholder="User-agent: *&#10;Allow: /&#10;Disallow: /admin&#10;Sitemap: https://example.com/sitemap.xml"
              ></textarea>
              <div class="flex justify-between items-center mt-2">
                <p class="text-xs text-gray-500">
                  Используйте моноширинный шрифт для удобства редактирования
                </p>
                <p class="text-xs text-gray-500">
                  {{ charCount }} / 5000 символов
                </p>
              </div>
              <div v-if="form.errors.robots_content" class="text-red-500 text-xs mt-1">
                {{ form.errors.robots_content }}
              </div>
            </div>

            <!-- Предпросмотр -->
            <details class="border border-gray-200 rounded-md">
              <summary class="px-4 py-3 cursor-pointer text-sm font-medium text-gray-700 hover:bg-gray-50">
                ️ Предпросмотр файла
              </summary>
              <div class="p-4 bg-gray-900 text-green-400 font-mono text-xs overflow-x-auto">
                <pre class="whitespace-pre-wrap">{{ form.robots_content || '(пусто)' }}</pre>
              </div>
            </details>

            <!-- Кнопки -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
              <Link
                  :href="route('admin.home')"
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
    </div>
  </AdminLayout>
</template>