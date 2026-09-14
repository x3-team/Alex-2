<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref, onMounted } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  authors: {
    type: Object,
    default: () => ({ data: [], links: {}, meta: {} })
  },
  // 🔹 Новые пропсы для SEO
  authorsPageDescription: { type: String, default: '' },
  authorsMeta: { type: Object, default: () => ({ title: '', description: '', keywords: '' }) },
})

// Метод удаления с подтверждением
const destroy = (author) => {
  if (confirm('Удалить этого автора?')) {
    router.delete(route('admin.authors.destroy', author.id), {
      preserveScroll: true
    })
  }
}

// 🔹 Логика для SEO и описания
const seoForm = useForm({
  authors_page_description: props.authorsPageDescription || '',
  authors_page_meta_title: props.authorsMeta?.title || '',
  authors_page_meta_description: props.authorsMeta?.description || '',
  authors_page_meta_keywords: props.authorsMeta?.keywords || '',
})

const isEditingSeo = ref(false)

const saveSeoSettings = () => {
  // Исправлено имя маршрута на authors.update-seo
  seoForm.post(route('admin.authors.update-seo'), {
    preserveScroll: true,
    onSuccess: () => {
      isEditingSeo.value = false
    }
  })
}
</script>

<template>
  <Head title="Авторы" />

  <AdminLayout>
    <div class="py-12">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Заголовок + кнопка создания -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
          <h2 class="text-2xl font-bold text-gray-800">Авторы</h2>
          <Link
              :href="route('admin.authors.create')"
              class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            + Новый автор
          </Link>
        </div>

        <!-- 🔹 Блок SEO настроек -->
        <div class="mb-6 bg-white rounded-lg shadow p-4 border border-gray-200">
          <div class="flex items-start justify-between mb-2">
            <h3 class="text-lg font-semibold text-gray-800">SEO настройки страницы авторов</h3>
            <button
                v-if="!isEditingSeo"
                @click="isEditingSeo = true"
                class="text-sm text-blue-600 hover:text-blue-800 font-medium"
            >
              Редактировать
            </button>
            <div v-else class="flex gap-2">
              <button
                  @click="isEditingSeo = false"
                  class="text-sm text-gray-600 hover:text-gray-800"
              >
                Отмена
              </button>
              <button
                  @click="saveSeoSettings"
                  :disabled="seoForm.processing"
                  class="text-sm text-emerald-600 hover:text-emerald-800 font-medium disabled:opacity-50"
              >
                {{ seoForm.processing ? 'Сохранение...' : 'Сохранить' }}
              </button>
            </div>
          </div>

          <div v-if="isEditingSeo" class="space-y-3">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Meta Title</label>
              <input
                  v-model="seoForm.authors_page_meta_title"
                  type="text"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                  placeholder="Авторы — АLEX LAB"
                  maxlength="255"
              />
              <p class="text-xs text-gray-500 mt-1">{{ seoForm.authors_page_meta_title?.length || 0 }}/255</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Meta Description</label>
              <textarea
                  v-model="seoForm.authors_page_meta_description"
                  rows="2"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                  placeholder="Описание для поисковиков..."
                  maxlength="500"
              ></textarea>
              <p class="text-xs text-gray-500 mt-1">{{ seoForm.authors_page_meta_description?.length || 0 }}/500</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Meta Keywords</label>
              <input
                  v-model="seoForm.authors_page_meta_keywords"
                  type="text"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                  placeholder="врачи, эксперты, аллергия (через запятую)"
                  maxlength="500"
              />
              <p class="text-xs text-gray-500 mt-1">{{ seoForm.authors_page_meta_keywords?.length || 0 }}/500</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Описание страницы (видимое)</label>
              <textarea
                  v-model="seoForm.authors_page_description"
                  rows="3"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                  placeholder="Текст под заголовком..."
                  maxlength="1000"
              ></textarea>
              <p class="text-xs text-gray-500 mt-1 text-right">{{ seoForm.authors_page_description?.length || 0 }}/1000</p>
            </div>
          </div>

          <div v-else class="space-y-2">
            <div>
              <span class="text-xs font-medium text-gray-500">Title: </span>
              <span class="text-sm text-gray-700">{{ seoForm.authors_page_meta_title || '—' }}</span>
            </div>
            <div>
              <span class="text-xs font-medium text-gray-500">Description: </span>
              <span class="text-sm text-gray-700">{{ seoForm.authors_page_meta_description || '—' }}</span>
            </div>
            <div>
              <span class="text-xs font-medium text-gray-500">Keywords: </span>
              <span class="text-sm text-gray-700">{{ seoForm.authors_page_meta_keywords || '—' }}</span>
            </div>
            <div>
              <span class="text-xs font-medium text-gray-500">Описание: </span>
              <span class="text-sm text-gray-700">{{ seoForm.authors_page_description || '—' }}</span>
            </div>
          </div>
        </div>

        <!-- Сообщения об успехе/ошибке -->
        <div v-if="$page.props.flash?.success" class="mb-4 p-3 bg-green-100 border border-green-300 text-green-700 rounded-md">
          {{ $page.props.flash.success }}
        </div>
        <div v-if="$page.props.flash?.error" class="mb-4 p-3 bg-red-100 border border-red-300 text-red-700 rounded-md">
          {{ $page.props.flash.error }}
        </div>

        <!-- Таблица авторов -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
              <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Автор</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Описание</th>
                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Действия</th>
              </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="author in authors.data" :key="author.id" class="hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center gap-3">
                    <img
                        v-if="author.avatar"
                        :src="`/storage/${author.avatar}`"
                        class="w-10 h-10 rounded-full object-cover border border-gray-200"
                        :alt="author.name"
                        @error="e => e.target.style.display = 'none'"
                    />
                    <div v-else class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 font-medium">
                      {{ author.name?.charAt(0)?.toUpperCase() || '?' }}
                    </div>
                    <span class="font-medium text-gray-900">{{ author.name }}</span>
                    <span
                        v-if="author.is_admin"
                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-300"
                    >Админ
        </span>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ author.email }}</td>
                <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">{{ author.bio || '—' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                  <Link :href="route('admin.authors.edit', author.id)" class="text-blue-600 hover:text-blue-900 transition-colors">Редактировать</Link>
                  <button @click.prevent="destroy(author)" class="text-red-600 hover:text-red-900 transition-colors">Удалить</button>
                </td>
              </tr>
              <tr v-if="!authors.data?.length">
                <td colspan="4" class="px-6 py-12 text-center">
                  <div class="text-gray-400 mb-4">
                    <svg class="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                  </div>
                  <p class="text-gray-500 mb-4">Авторов пока нет</p>
                  <Link :href="route('admin.authors.create')" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">Создать первого автора</Link>
                </td>
              </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Пагинация -->
        <div v-if="authors.links && authors.data?.length" class="mt-4 flex flex-col sm:flex-row justify-between items-center gap-3">
          <div class="text-sm text-gray-600">
            Показано {{ authors.meta?.from || 0 }}–{{ authors.meta?.to || 0 }} из {{ authors.meta?.total || 0 }}
          </div>
          <div class="flex gap-1">
            <Link v-if="authors.prev_page_url" :href="authors.prev_page_url" class="px-3 py-2 text-sm border border-gray-300 rounded-md bg-white text-gray-700 hover:bg-gray-50">← Назад</Link>
            <span v-else class="px-3 py-2 text-sm border border-gray-300 rounded-md bg-gray-100 text-gray-400 cursor-not-allowed">← Назад</span>
            <span class="px-3 py-2 text-sm text-gray-600">Стр. {{ authors.meta?.current_page || 1 }} из {{ authors.meta?.last_page || 1 }}</span>
            <Link v-if="authors.next_page_url" :href="authors.next_page_url" class="px-3 py-2 text-sm border border-gray-300 rounded-md bg-white text-gray-700 hover:bg-gray-50">Вперёд →</Link>
            <span v-else class="px-3 py-2 text-sm border border-gray-300 rounded-md bg-gray-100 text-gray-400 cursor-not-allowed">Вперёд →</span>
          </div>
        </div>

      </div>
    </div>
  </AdminLayout>
</template>