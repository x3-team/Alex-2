<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  blogs: Object,
  filters: Object,
})

const searchQuery = ref(props.filters.search || '')

const handleSearch = () => {
  router.get(route('admin.blog.trashed'), { search: searchQuery.value }, {
    preserveState: true,
    preserveScroll: true,
  })
}

const restoreBlog = (id) => {
  if (confirm('Восстановить этот пост?')) {
    router.post(route('admin.blog.restore', id), {}, {
      preserveScroll: true,
      onSuccess: () => {
        // Inertia автоматически обновит страницу
      }
    })
  }
}

const forceDeleteBlog = (id) => {
  if (confirm('Удалить этот пост ОКОНЧАТЕЛЬНО? Это действие нельзя отменить!')) {
    router.delete(route('admin.blog.force-delete', id), {
      preserveScroll: true,
      onSuccess: () => {
        // Inertia автоматически обновит страницу
      }
    })
  }
}
</script>

<template>
  <Head title="Удалённые статьи" />

  <AdminLayout>
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 bg-white border-b border-gray-200">

            <!-- Заголовок и навигация -->
            <div class="flex items-center justify-between mb-6">
              <div>
                <h2 class="text-2xl font-bold text-gray-800">Корзина</h2>
                <p class="text-sm text-gray-600 mt-1">
                  Удалённые статьи. Можно восстановить или удалить окончательно.
                </p>
              </div>
              <Link
                  :href="route('admin.blog.index')"
                  class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-medium"
              >
                Все статьи
              </Link>
            </div>

            <!-- Поиск -->
            <div class="mb-4">
              <form @submit.prevent="handleSearch" class="flex gap-2">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Поиск по названию..."
                    class="flex-1 border-gray-300 rounded-md shadow-sm px-3 py-2 border focus:border-blue-500 focus:ring-blue-500"
                />
                <button
                    type="submit"
                    class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 text-sm font-medium"
                >
                  Поиск
                </button>
              </form>
            </div>

            <!-- Таблица удалённых статей -->
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Название
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Категория
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Автор
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Удалено
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Действия
                  </th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                <tr v-if="blogs.data.length === 0">
                  <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                    Корзина пуста
                  </td>
                </tr>
                <tr v-for="blog in blogs.data" :key="blog.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">{{ blog.title }}</div>
                    <div class="text-sm text-gray-500 font-mono truncate max-w-xs">
                      /{{ blog.slug }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ blog.category?.name || 'Без категории' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ blog.author?.name || 'Неизвестен' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ new Date(blog.deleted_at).toLocaleString('ru-RU') }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                    <button
                        @click="restoreBlog(blog.id)"
                        class="text-green-600 hover:text-green-900"
                    >
                      Восстановить
                    </button>
                    <button
                        @click="forceDeleteBlog(blog.id)"
                        class="text-red-600 hover:text-red-900"
                    >
                      Удалить навсегда
                    </button>
                  </td>
                </tr>
                </tbody>
              </table>
            </div>

            <!-- Пагинация -->
            <div v-if="blogs.links.length > 3" class="mt-6 flex justify-center gap-2">
              <Link
                  v-for="link in blogs.links"
                  :key="link.label"
                  :href="link.url"
                  :class="[
                  'px-3 py-1 rounded text-sm',
                  link.active ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                  !link.url && 'opacity-50 cursor-not-allowed'
                ]"
                  v-html="link.label"
              />
            </div>

          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>