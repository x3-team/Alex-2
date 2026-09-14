<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  redirects: Object,
  filters: Object,
})

const searchQuery = ref(props.filters.search || '')
const statusFilter = ref(props.filters.status || '')

const handleSearch = () => {
  router.get(route('admin.redirects.index'), {
    search: searchQuery.value,
    status: statusFilter.value,
  }, {
    preserveState: true,
    preserveScroll: true,
  })
}

const deleteRedirect = (id) => {
  if (confirm('Удалить этот редирект?')) {
    router.delete(route('admin.redirects.destroy', id), {
      preserveScroll: true,
    })
  }
}

const toggleRedirect = (id) => {
  router.post(route('admin.redirects.toggle', id), {}, {
    preserveScroll: true,
  })
}
</script>

<template>
  <Head title="Редиректы" />

  <AdminLayout>
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg">

          <!-- Заголовок -->
          <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
              <div>
                <h2 class="text-2xl font-bold text-gray-800">Редиректы</h2>
                <p class="text-sm text-gray-600 mt-1">
                  Управление перенаправлениями URL
                </p>
              </div>
              <Link
                  :href="route('admin.redirects.create')"
                  class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-medium"
              >
                + Создать редирект
              </Link>
            </div>
          </div>

          <!-- Фильтры -->
          <div class="p-4 bg-gray-50 border-b border-gray-200">
            <form @submit.prevent="handleSearch" class="flex gap-3">
              <input
                  v-model="searchQuery"
                  type="text"
                  placeholder="Поиск по URL..."
                  class="flex-1 border-gray-300 rounded-md shadow-sm px-3 py-2 border focus:border-blue-500 focus:ring-blue-500"
              />
              <select
                  v-model="statusFilter"
                  class="border-gray-300 rounded-md shadow-sm px-3 py-2 border focus:border-blue-500 focus:ring-blue-500"
              >
                <option value="">Все статусы</option>
                <option value="active">Активные</option>
                <option value="inactive">Неактивные</option>
              </select>
              <button
                  type="submit"
                  class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 text-sm font-medium"
              >
                Поиск
              </button>
            </form>
          </div>

          <!-- Таблица -->
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Откуда</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Куда</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Код</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Срабатываний</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Статус</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Действия</th>
              </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="redirects.data.length === 0">
                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                  Редиректы не найдены
                </td>
              </tr>
              <tr
                  v-for="redirect in redirects.data"
                  :key="redirect.id"
                  :class="!redirect.is_active ? 'bg-gray-50 opacity-60' : 'hover:bg-gray-50'"
              >
                <td class="px-6 py-4 text-sm font-mono text-gray-900 max-w-xs truncate">
                  {{ redirect.from_url }}
                </td>
                <td class="px-6 py-4 text-sm font-mono text-gray-900 max-w-xs truncate">
                  {{ redirect.to_url }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span
                        :class="[
                        'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                        redirect.status_code === 301
                          ? 'bg-blue-100 text-blue-800'
                          : 'bg-yellow-100 text-yellow-800'
                      ]"
                    >
                      {{ redirect.status_code }}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ redirect.hits_count }}
                  <span v-if="redirect.last_hit_at" class="text-xs text-gray-400 block">
                      {{ new Date(redirect.last_hit_at).toLocaleString('ru-RU') }}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <button
                      @click="toggleRedirect(redirect.id)"
                      :class="[
                        'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2',
                        redirect.is_active ? 'bg-green-600' : 'bg-gray-200'
                      ]"
                  >
                      <span
                          :class="[
                          'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                          redirect.is_active ? 'translate-x-5' : 'translate-x-0'
                        ]"
                      />
                  </button>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                  <Link
                      :href="route('admin.redirects.edit', redirect.id)"
                      class="text-blue-600 hover:text-blue-900"
                  >
                    Редактировать
                  </Link>
                  <button
                      @click="deleteRedirect(redirect.id)"
                      class="text-red-600 hover:text-red-900"
                  >
                    Удалить
                  </button>
                </td>
              </tr>
              </tbody>
            </table>
          </div>

          <!-- Пагинация -->
          <div v-if="redirects.links.length > 3" class="px-6 py-4 border-t border-gray-200">
            <div class="flex justify-center gap-2">
              <Link
                  v-for="link in redirects.links"
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