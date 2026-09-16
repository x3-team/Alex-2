<script setup>
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  orders: Object,
  filters: Object
})

const changeStatus = (orderId, status) => {
  router.put(route('admin.orders.update-status', orderId), { status })
}

const viewOrder = (orderId) => {
  router.get(route('admin.orders.show', orderId))
}

const formatDate = (date) => {
  return new Date(date).toLocaleString('ru-RU', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
  })
}
</script>

<template>
  <AdminLayout>
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

          <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Заявки</h2>

            <!-- Фильтр по статусу -->
            <select
                v-model="filters.status"
                @change="router.get(route('admin.orders.index'), filters, { preserveState: true })"
                class="border-gray-300 rounded-md shadow-sm"
            >
              <option value="">Все статусы</option>
              <option value="new">Новые</option>
              <option value="processing">В работе</option>
              <option value="completed">Выполненные</option>
              <option value="cancelled">Отмененные</option>
            </select>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID / Дата</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Клиент</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Состав заказа</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Сумма</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Статус</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Действия</th>
              </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
              <tr
                  v-for="order in orders.data"
                  :key="order.id"
                  @click="viewOrder(order.id)"
                  class="hover:bg-gray-50 cursor-pointer transition-colors"
              >
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-medium text-blue-600 hover:underline">#{{ order.id }}</div>
                  <div class="text-sm text-gray-500">{{ formatDate(order.created_at) }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-medium text-gray-900">{{ order.customer_name }}</div>
                  <div class="text-sm text-gray-500">{{ order.customer_phone }}</div>
                  <div class="text-sm text-gray-500">{{ order.customer_email }}</div>
                </td>
                <td class="px-6 py-4">
                  <div v-if="order.items && order.items.type === 'doctor_appointment'" class="text-sm text-indigo-600 font-semibold">
                    🩺 {{ order.items.title || 'Запись к врачу' }}
                  </div>
                  <ul v-else class="text-sm text-gray-700 space-y-1">
                    <li v-for="(item, index) in order.items" :key="index" class="flex justify-between">
                      <span>{{ item.title }}</span>
                      <span class="font-medium">{{ item.price }} ₽</span>
                    </li>
                  </ul>
                  <div v-if="order.comment" class="mt-2 text-xs text-gray-500 italic">
                    {{ order.comment }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-bold text-gray-900">{{ order.total_amount }} ₽</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span :class="order.status_color" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                      {{ order.status_label }}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium" @click.stop>
                  <select
                      :value="order.status"
                      @change="changeStatus(order.id, $event.target.value)"
                      class="text-sm border-gray-300 rounded-md shadow-sm"
                  >
                    <option value="new">Новая</option>
                    <option value="processing">В работе</option>
                    <option value="completed">Выполнена</option>
                    <option value="cancelled">Отмененная</option>
                  </select>
                </td>
              </tr>
              <tr v-if="orders.data.length === 0">
                <td colspan="6" class="px-6 py-4 text-center text-gray-500">Заявок пока нет</td>
              </tr>
              </tbody>
            </table>
          </div>

          <div class="mt-4 flex justify-center" v-if="orders.links.length > 3">
            <div class="flex space-x-1">
              <button
                  v-for="(link, index) in orders.links"
                  :key="index"
                  @click="router.get(link.url)"
                  :disabled="!link.url"
                  v-html="link.label"
                  class="px-3 py-1 border rounded-md text-sm"
                  :class="link.active ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 hover:bg-gray-50'"
              ></button>
            </div>
          </div>

        </div>
      </div>
    </div>
  </AdminLayout>
</template>