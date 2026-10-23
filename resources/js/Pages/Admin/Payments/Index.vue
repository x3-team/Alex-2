<script setup>
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  payments: Object,
  filters: Object,
  enabled: Boolean,
  driver: String,
})

const page = usePage()
const flash = computed(() => page.props.flash || {})

const statusLabels = {
  new: 'Не создан в ЮKassa',
  pending: 'Ожидает оплаты',
  waiting_for_capture: 'Ждёт подтверждения',
  succeeded: 'Оплачен',
  canceled: 'Отменён',
}
const statusColors = {
  new: 'bg-gray-100 text-gray-800',
  pending: 'bg-yellow-100 text-yellow-800',
  waiting_for_capture: 'bg-yellow-100 text-yellow-800',
  succeeded: 'bg-green-100 text-green-800',
  canceled: 'bg-red-100 text-red-800',
}
const refundLabels = { new: 'не отправлен', pending: 'в обработке', succeeded: 'выполнен', canceled: 'отклонён' }

const filterStatus = (status) => {
  router.get('/admin/payments', { status }, { preserveState: true })
}

const sync = (payment) => {
  router.post(route('admin.payments.sync', payment.id), {}, { preserveScroll: true })
}

const refund = (payment) => {
  const reason = window.prompt(`Возврат ${payment.amount} ₽. Причина (уйдёт в ЮKassa):`, 'Отмена заказа')
  if (!reason) return
  router.post(route('admin.payments.refund', payment.id), { reason }, { preserveScroll: true })
}

const formatDate = (date) => date
  ? new Date(date).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
  : '—'
</script>

<template>
  <AdminLayout>
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
          <div class="flex flex-wrap justify-between items-center gap-4 mb-4">
            <h2 class="text-2xl font-bold text-gray-800">Платежи ЮKassa</h2>
            <div class="flex items-center gap-2 text-sm">
              <span class="px-2 py-1 rounded" :class="enabled ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'">
                {{ enabled ? 'Оплата включена' : 'Оплата выключена (PAYMENTS_ENABLED=false)' }}
              </span>
              <span v-if="driver !== 'yookassa'" class="px-2 py-1 rounded bg-blue-100 text-blue-800">Эмулятор ({{ driver }})</span>
            </div>
          </div>

          <div v-if="flash.success" class="mb-4 p-3 rounded bg-green-50 text-green-800 text-sm">{{ flash.success }}</div>
          <div v-if="flash.error" class="mb-4 p-3 rounded bg-red-50 text-red-800 text-sm">{{ flash.error }}</div>

          <div class="flex flex-wrap gap-2 mb-6 text-sm">
            <button
              v-for="(label, key) in { '': 'Все', ...statusLabels }"
              :key="key"
              type="button"
              class="px-3 py-1 rounded border"
              :class="(filters.status || '') === key ? 'bg-gray-800 text-white border-gray-800' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
              @click="filterStatus(key)"
            >{{ label }}</button>
          </div>

          <div v-if="!payments.data.length" class="text-gray-500 text-sm">Платежей пока нет.</div>

          <div class="overflow-x-auto" v-else>
            <table class="min-w-full text-sm">
              <thead>
                <tr class="text-left text-gray-500 border-b">
                  <th class="py-2 pr-4">№ / создан</th>
                  <th class="py-2 pr-4">Состав чека</th>
                  <th class="py-2 pr-4">Сумма</th>
                  <th class="py-2 pr-4">Статус</th>
                  <th class="py-2 pr-4">Возвраты</th>
                  <th class="py-2"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="payment in payments.data" :key="payment.id" class="border-b align-top">
                  <td class="py-3 pr-4">
                    <div class="font-medium">#{{ payment.id }}</div>
                    <div class="text-gray-500">{{ formatDate(payment.created_at) }}</div>
                    <div v-if="payment.test" class="text-xs text-blue-700">тестовый</div>
                    <div class="text-xs text-gray-400 break-all">{{ payment.provider_payment_id || '—' }}</div>
                  </td>
                  <td class="py-3 pr-4">
                    <div v-for="(item, i) in payment.items" :key="i">{{ item.description }} — {{ item.amount }} ₽</div>
                    <div v-if="payment.settled_at" class="text-xs text-gray-500">Чек «полный расчёт»: {{ formatDate(payment.settled_at) }}</div>
                  </td>
                  <td class="py-3 pr-4 whitespace-nowrap">
                    {{ payment.amount }} ₽
                    <div v-if="payment.refunded !== '0.00'" class="text-xs text-red-700">возвращено {{ payment.refunded }} ₽</div>
                  </td>
                  <td class="py-3 pr-4">
                    <span class="px-2 py-1 rounded text-xs font-medium" :class="statusColors[payment.status] || 'bg-gray-100'">
                      {{ statusLabels[payment.status] || payment.status }}
                    </span>
                    <div v-if="payment.paid_at" class="text-xs text-gray-500 mt-1">{{ formatDate(payment.paid_at) }}</div>
                    <div v-if="payment.cancellation_reason" class="text-xs text-gray-500 mt-1">{{ payment.cancellation_reason }}</div>
                    <div v-if="payment.last_error" class="text-xs text-red-700 mt-1">{{ payment.last_error }}</div>
                  </td>
                  <td class="py-3 pr-4">
                    <div v-for="r in payment.refunds" :key="r.id" class="text-xs">
                      {{ r.amount }} ₽ — {{ refundLabels[r.status] || r.status }}
                      <div class="text-gray-500">{{ r.reason }}</div>
                      <div v-if="r.last_error" class="text-red-700">{{ r.last_error }}</div>
                    </div>
                    <span v-if="!payment.refunds.length" class="text-gray-400">—</span>
                  </td>
                  <td class="py-3 text-right whitespace-nowrap">
                    <button type="button" class="text-blue-600 hover:underline mr-3" @click="sync(payment)">Сверить</button>
                    <button v-if="payment.refundable" type="button" class="text-red-600 hover:underline" @click="refund(payment)">Вернуть</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="payments.last_page > 1" class="flex gap-2 mt-6 text-sm">
            <button
              v-for="link in payments.links"
              :key="link.label"
              type="button"
              :disabled="!link.url"
              class="px-3 py-1 rounded border border-gray-300 disabled:opacity-40"
              :class="{ 'bg-gray-800 text-white': link.active }"
              @click="link.url && router.get(link.url, {}, { preserveState: true })"
              v-html="link.label"
            />
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
