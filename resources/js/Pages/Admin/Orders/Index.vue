<script setup>
import { computed, nextTick, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  orders: Object,
  filters: Object,
  leadsMailTo: { type: String, default: '' },
  leadsMailFallback: { type: String, default: 'info@alexallergotest.ru' },
})

const mailTo = ref(props.leadsMailTo || '')

const applyFilters = async () => {
  await nextTick()
  router.get('/admin/orders', {
    status: props.filters.status || '',
    type: props.filters.type || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
  }, { preserveState: true })
}

const saveMail = () => {
  router.put('/admin/orders/mail-settings', { leads_mail_to: mailTo.value }, { preserveScroll: true })
}

const exportHref = computed(() => {
  const params = new URLSearchParams()
  for (const key of ['status', 'type', 'date_from', 'date_to']) {
    const value = props.filters?.[key]
    if (value) params.set(key, value)
  }
  const query = params.toString()
  return query ? `/admin/orders/export?${query}` : '/admin/orders/export'
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
            <a
                :href="exportHref"
                class="inline-flex items-center px-4 py-2 bg-gray-900 text-white text-sm rounded-md hover:bg-gray-700"
            >Выгрузить в CSV</a>
          </div>

          <form class="mb-6 p-4 bg-gray-50 border border-gray-200 rounded-lg" @submit.prevent="saveMail">
            <label for="leads-mail-to" class="block text-sm font-medium text-gray-800">Почта для заявок</label>
            <p class="text-xs text-gray-500 mt-1 mb-2">Несколько адресов через запятую. Если поле пустое, письма идут на {{ leadsMailFallback }}.</p>
            <div class="flex flex-col sm:flex-row gap-2">
              <input
                  id="leads-mail-to"
                  v-model="mailTo"
                  type="text"
                  name="leads_mail_to"
                  :placeholder="leadsMailFallback"
                  class="flex-1 border-gray-300 rounded-md shadow-sm"
              />
              <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">Сохранить</button>
            </div>
          </form>

          <div class="flex flex-wrap gap-3 mb-6">
            <select v-model="filters.type" @change="applyFilters" class="border-gray-300 rounded-md shadow-sm">
              <option value="">Все типы</option>
              <option value="doctor_appointment">Запись к врачу</option>
              <option value="test_order">Запись на тест</option>
            </select>
            <input v-model="filters.date_from" type="date" @change="applyFilters" class="border-gray-300 rounded-md shadow-sm" aria-label="Дата от" />
            <input v-model="filters.date_to" type="date" @change="applyFilters" class="border-gray-300 rounded-md shadow-sm" aria-label="Дата до" />
            <select v-model="filters.status" @change="applyFilters" class="border-gray-300 rounded-md shadow-sm">
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
                  <div v-else-if="order.items && order.items.type === 'test_order'" class="text-sm text-emerald-700 font-semibold">
                    {{ order.items.title || 'Запись на тест' }}
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