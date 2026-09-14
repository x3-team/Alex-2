<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  blogs: { type: Object, default: () => ({ data: [], links: {}, meta: {} }) },
  pageDescription: { type: String, default: '' },
  blogMeta: { type: Object, default: () => ({ title: '', description: '', keywords: '' }) },
  filters: { type: Object, default: () => ({ search: '' }) },
})

// 🔹 🔥 Форма для описания + meta-тегов
const descriptionForm = useForm({
  description: props.pageDescription,
  meta_title: props.blogMeta?.title || '',
  meta_description: props.blogMeta?.description || '',
  meta_keywords: props.blogMeta?.keywords || '',
})
const isEditing = ref(false)

const saveDescription = () => {
  descriptionForm.post(route('admin.blog.settings.update'), {
    preserveScroll: true,
    onSuccess: () => { isEditing.value = false }
  })
}

// 🔹 🔥 Поиск
const searchQuery = ref(props.filters?.search || '')
const searchForm = useForm({ search: searchQuery.value })

const performSearch = () => {
  router.get(route('admin.blog.index'), { search: searchQuery.value }, {
    preserveState: true,
    preserveScroll: true
  })
}

const clearSearch = () => {
  searchQuery.value = ''
  router.get(route('admin.blog.index'), {}, { preserveState: true, preserveScroll: true })
}

const destroy = (blog) => {
  if (confirm('Вы уверены, что хотите удалить этот пост?')) {
    router.delete(route('admin.blog.destroy', blog.id), { preserveScroll: true })
  }
}

const formatDate = (dateString) => {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleDateString('ru-RU', {
    day: '2-digit', month: '2-digit', year: 'numeric'
  })
}

// 🔹 Быстрый переход на страницу
const targetPage = ref(null)

const goToPage = (page) => {
  const pageNum = parseInt(page)
  const lastPage = props.blogs?.last_page || 1
  if (!pageNum || pageNum < 1 || pageNum > lastPage) return

  const params = { page: pageNum }
  if (searchQuery.value) {
    params.search = searchQuery.value
  }

  router.get(route('admin.blog.index'), params, {
    preserveState: true,
    preserveScroll: true,
    only: ['blogs', 'filters'],
  })
}
const deleteBlog = (id) => {
  if (confirm('Переместить этот пост в корзину? Вы сможете восстановить его позже.')) {
    router.delete(route('admin.blog.destroy', id), {
      preserveScroll: true,
      onSuccess: () => {
        // Inertia автоматически обновит страницу
      }
    })
  }
}
// 🔹 Генерация диапазона страниц для пагинации
const paginationRange = computed(() => {
  const total = props.blogs?.last_page || 1
  const current = props.blogs?.current_page || 1

  // Если страниц мало, показываем все
  if (total <= 7) {
    return Array.from({ length: total }, (_, i) => i + 1)
  }

  // Если в начале
  if (current <= 3) {
    return [1, 2, 3, 4, 5, '...', total]
  }

  // Если в конце
  if (current >= total - 2) {
    return [1, '...', total - 4, total - 3, total - 2, total - 1, total]
  }

  // Если в середине
  return [1, '...', current - 1, current, current + 1, '...', total]
})
</script>

<template>
  <Head title="Блог" />

  <AdminLayout>
    <div class="py-12">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- 🔹 🔥 Заголовок + поиск + кнопка создания -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
          <h2 class="text-2xl font-bold text-gray-800">Блог</h2>
          <div class="flex gap-2 w-full sm:w-auto">
            <!-- 🔹 🔥 Поиск -->
            <div class="flex gap-2 flex-1 sm:flex-initial">
              <input
                  v-model="searchQuery"
                  @keyup.enter="performSearch"
                  type="text"
                  placeholder="Поиск по заголовку или slug..."
                  class="flex-1 sm:w-64 border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
              />
              <button
                  @click="performSearch"
                  class="px-3 py-2 bg-gray-600 text-white text-sm font-medium rounded-md hover:bg-gray-700"
              >
                Искать
              </button>
              <button
                  v-if="searchQuery"
                  @click="clearSearch"
                  class="px-3 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-300"
              >
                ✕
              </button>
            </div>
            <Link
                :href="route('admin.blog.create')"
                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 whitespace-nowrap"
            >
              + Новая Статья
            </Link>
            <Link
                :href="route('admin.blog.trashed')"
                class="px-4 py-2 bg-red-100 text-red-700 rounded-md hover:bg-red-200 text-sm font-medium flex items-center gap-2"
            >
              Корзина
            </Link>
          </div>
        </div>

        <!-- 🔹 🔥 Meta-теги страницы /blog -->
        <div class="mb-6 bg-white rounded-lg shadow p-4 border border-gray-200">
          <div class="flex items-start justify-between mb-2">
            <h3 class="text-lg font-semibold text-gray-800">Meta-теги страницы /blog</h3>
            <button
                v-if="!isEditing"
                @click="isEditing = true"
                class="text-sm text-blue-600 hover:text-blue-800 font-medium"
            >
              Редактировать
            </button>
            <div v-else class="flex gap-2">
              <button
                  @click="isEditing = false"
                  class="text-sm text-gray-600 hover:text-gray-800"
              >
                Отмена
              </button>
              <button
                  @click="saveDescription"
                  :disabled="descriptionForm.processing"
                  class="text-sm text-emerald-600 hover:text-emerald-800 font-medium disabled:opacity-50"
              >
                {{ descriptionForm.processing ? 'Сохранение...' : 'Сохранить' }}
              </button>
            </div>
          </div>

          <div v-if="isEditing" class="space-y-3">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Meta Title</label>
              <input
                  v-model="descriptionForm.meta_title"
                  type="text"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                  placeholder="Блог — АLEX LAB"
                  maxlength="255"
              />
              <p class="text-xs text-gray-500 mt-1">{{ descriptionForm.meta_title?.length || 0 }}/255</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Meta Description</label>
              <textarea
                  v-model="descriptionForm.meta_description"
                  rows="2"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                  placeholder="Описание для поисковиков..."
                  maxlength="500"
              ></textarea>
              <p class="text-xs text-gray-500 mt-1">{{ descriptionForm.meta_description?.length || 0 }}/500</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Meta Keywords</label>
              <input
                  v-model="descriptionForm.meta_keywords"
                  type="text"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                  placeholder="аллергия, тест, здоровье (через запятую)"
                  maxlength="500"
              />
              <p class="text-xs text-gray-500 mt-1">{{ descriptionForm.meta_keywords?.length || 0 }}/500</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Описание страницы</label>
              <textarea
                  v-model="descriptionForm.description"
                  rows="3"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                  placeholder="Описание, отображаемое на странице /blog..."
                  maxlength="1000"
              ></textarea>
              <p class="text-xs text-gray-500 mt-1 text-right">{{ descriptionForm.description?.length || 0 }}/1000</p>
            </div>
          </div>

          <div v-else class="space-y-2">
            <div>
              <span class="text-xs font-medium text-gray-500">Title: </span>
              <span class="text-sm text-gray-700">{{ descriptionForm.meta_title || '—' }}</span>
            </div>
            <div>
              <span class="text-xs font-medium text-gray-500">Description: </span>
              <span class="text-sm text-gray-700">{{ descriptionForm.meta_description || '—' }}</span>
            </div>
            <div>
              <span class="text-xs font-medium text-gray-500">Keywords: </span>
              <span class="text-sm text-gray-700">{{ descriptionForm.meta_keywords || '—' }}</span>
            </div>
            <div>
              <span class="text-xs font-medium text-gray-500">Описание: </span>
              <span class="text-sm text-gray-700">{{ descriptionForm.description || '—' }}</span>
            </div>
          </div>
        </div>

        <!-- Сообщения об успехе -->
        <div v-if="$page.props.flash?.success" class="mb-4 p-3 bg-green-100 border border-green-300 text-green-700 rounded-md">
          {{ $page.props.flash.success }}
        </div>

        <!-- 🔹 🔥 Таблица постов -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
              <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Заголовок</th>
                <!-- 🔹 🔥 Новая колонка: Страница блога -->
                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Страница</th>
                <!-- 🔹 🔥 Новая колонка: Статус -->
                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Статус</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Автор</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата</th>
                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Действия</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Дата публикации
                </th>
              </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="blog in blogs.data" :key="blog.id" class="hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-medium text-gray-900">{{ blog.title }}</div>
                  <div class="text-xs text-gray-500 font-mono">{{ blog.slug }}</div>
                </td>
                <!-- 🔹 🔥 Кнопка "Перейти на страницу блога" -->
                <td class="px-6 py-4 whitespace-nowrap text-center">
                  <a
                      :href="`/blog/${blog.slug}`"
                      target="_blank"
                      class="inline-flex items-center gap-1 px-3 py-1.5 bg-emerald-50 text-emerald-700 text-xs font-medium rounded-md hover:bg-emerald-100 border border-emerald-200 transition-colors"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                    Открыть
                  </a>
                </td>
                <!-- 🔹 🔥 Статус -->
                <td class="px-6 py-4 whitespace-nowrap text-center">
                    <span
                        v-if="blog.is_active"
                        class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800"
                    >
                      <span class="w-2 h-2 bg-green-500 rounded-full mr-1.5"></span>
                      Активен
                    </span>
                  <span
                      v-else
                      class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800"
                  >
                      <span class="w-2 h-2 bg-red-500 rounded-full mr-1.5"></span>
                      Скрыт
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center gap-3">
                    <img v-if="blog.author?.avatar" :src="`/storage/${blog.author.avatar}`" class="w-10 h-10 rounded-full object-cover border border-gray-200" :alt="blog.author?.name" @error="e => e.target.style.display = 'none'" />
                    <div v-else class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 font-medium">{{ blog.author?.name?.charAt(0)?.toUpperCase() || '?' }}</div>
                    <span class="text-sm text-gray-700">{{ blog.author?.name || '—' }}</span>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDate(blog.created_at) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                  <Link :href="route('admin.blog.edit', blog.id)" class="text-blue-600 hover:text-blue-900 transition-colors">Редактировать</Link>
                  <button
                      @click="deleteBlog(blog.id)"
                      class="text-red-600 hover:text-red-900"
                  >
                    В корзину
                  </button></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <div v-if="blog.published_at">
                              {{ formatDate(blog.published_at) }}
                            </div>
                            <span v-else class="text-xs text-amber-600 font-medium">
              Черновик (не опубликован)
            </span>
                          </td>
              </tr>
              <tr v-if="!blogs.data?.length">
                <td colspan="6" class="px-6 py-12 text-center">
                  <div class="text-gray-400 mb-4">
                    <svg class="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                  </div>
                  <p class="text-gray-500 mb-4">
                    {{ searchQuery ? 'Ничего не найдено' : 'Постов пока нет' }}
                  </p>
                  <Link v-if="!searchQuery" :href="route('admin.blog.create')" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">Создать первый пост</Link>
                </td>
              </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Пагинация -->
        <div v-if="blogs.links && blogs.data?.length" class="mt-6 bg-white rounded-lg shadow p-4 border border-gray-200">
          <div class="flex flex-col sm:flex-row items-center justify-between gap-4">

            <!-- Информация о страницах -->
            <div class="text-sm text-gray-600">
              Показано {{ blogs.from || 0 }}–{{ blogs.to || 0 }} из {{ blogs.total || 0 }} записей
            </div>

            <!-- Навигация по страницам -->
            <div class="flex items-center gap-2 flex-wrap">

              <!-- Первая страница -->
              <button
                  v-if="blogs.current_page > 1"
                  @click="goToPage(1)"
                  class="px-3 py-2 text-sm border border-gray-300 rounded-md bg-white text-gray-700 hover:bg-gray-50 transition-colors"
                  title="Первая страница"
              >
                ««
              </button>
              <span v-else class="px-3 py-2 text-sm border border-gray-300 rounded-md bg-gray-100 text-gray-400 cursor-not-allowed">
        ««
      </span>

              <!-- Предыдущая страница -->
              <button
                  v-if="blogs.prev_page_url"
                  @click="goToPage(blogs.current_page - 1)"
                  class="px-3 py-2 text-sm border border-gray-300 rounded-md bg-white text-gray-700 hover:bg-gray-50 transition-colors"
                  title="Предыдущая страница"
              >
                ←
              </button>
              <span v-else class="px-3 py-2 text-sm border border-gray-300 rounded-md bg-gray-100 text-gray-400 cursor-not-allowed">
        ←
      </span>

              <!-- Нумерованные страницы -->
              <div class="flex gap-1">
                <template v-for="page in paginationRange" :key="page">
                  <span v-if="page === '...'" class="px-2 py-2 text-sm text-gray-500">...</span>
                  <button
                      v-else
                      @click="goToPage(page)"
                      :class="page === blogs.current_page
                ? 'bg-blue-600 text-white border-blue-600 font-semibold'
                : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                      class="w-10 h-10 text-sm border rounded-md transition-colors"
                  >
                    {{ page }}
                  </button>
                </template>
              </div>

              <!-- Следующая страница -->
              <button
                  v-if="blogs.next_page_url"
                  @click="goToPage(blogs.current_page + 1)"
                  class="px-3 py-2 text-sm border border-gray-300 rounded-md bg-white text-gray-700 hover:bg-gray-50 transition-colors"
                  title="Следующая страница"
              >
                →
              </button>
              <span v-else class="px-3 py-2 text-sm border border-gray-300 rounded-md bg-gray-100 text-gray-400 cursor-not-allowed">
        →
      </span>

              <!-- Последняя страница -->
              <button
                  v-if="blogs.current_page < blogs.last_page"
                  @click="goToPage(blogs.last_page)"
                  class="px-3 py-2 text-sm border border-gray-300 rounded-md bg-white text-gray-700 hover:bg-gray-50 transition-colors"
                  title="Последняя страница"
              >
                »»
              </button>
              <span v-else class="px-3 py-2 text-sm border border-gray-300 rounded-md bg-gray-100 text-gray-400 cursor-not-allowed">
        »»
      </span>

              <!-- Быстрый переход -->
              <div class="flex items-center gap-2 ml-4 pl-4 border-l border-gray-300">
                <label class="text-sm text-gray-600 whitespace-nowrap">Перейти:</label>
                <input
                    v-model.number="targetPage"
                    @keyup.enter="goToPage(targetPage)"
                    type="number"
                    min="1"
                    :max="blogs.last_page"
                    class="w-20 px-2 py-2 text-sm border border-gray-300 rounded-md focus:border-blue-500 focus:ring-blue-500"
                    placeholder="№"
                />
                <button
                    @click="goToPage(targetPage)"
                    :disabled="!targetPage || targetPage < 1 || targetPage > blogs.last_page"
                    class="px-3 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors"
                >
                  OK
                </button>
              </div>

            </div>
          </div>
        </div>

      </div>
    </div>
  </AdminLayout>
</template>