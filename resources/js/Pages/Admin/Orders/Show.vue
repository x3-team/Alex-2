<script setup>
import { router, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  order: Object,
  quizSheet: { type: Array, default: () => [] },
  quizResult: { type: String, default: null }
})

const changeStatus = (status) => {
  router.put(route('admin.orders.update-status', props.order.id), { status })
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
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

          <div class="flex justify-between items-center mb-6">
            <Link :href="route('admin.orders.index')" class="text-blue-600 hover:underline text-sm flex items-center gap-1">
              ← Назад к списку
            </Link>

            <div class="flex items-center space-x-3">
              <span class="text-sm font-medium text-gray-500">Статус:</span>
              <select
                  :value="order.status"
                  @change="changeStatus($event.target.value)"
                  class="text-sm border-gray-300 rounded-md shadow-sm"
              >
                <option value="new">Новая</option>
                <option value="processing">В работе</option>
                <option value="completed">Выполнена</option>
                <option value="cancelled">Отмененная</option>
              </select>
            </div>
          </div>

          <h2 class="text-2xl font-bold text-gray-800 mb-4">Заявка #{{ order.id }}</h2>
          <div class="text-sm text-gray-500 mb-6">Дата создания: {{ formatDate(order.created_at) }}</div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 border-t pt-6">
            <div>
              <h3 class="font-semibold text-gray-700 text-lg mb-3">Данные клиента</h3>
              <p><strong>ФИО:</strong> {{ order.customer_name }}</p>
              <p><strong>Телефон:</strong> {{ order.customer_phone }}</p>
              <p v-if="order.customer_email"><strong>Email:</strong> {{ order.customer_email }}</p>
              <p v-if="order.comment"><strong>Примечание / Город:</strong> {{ order.comment }}</p>
            </div>

            <div>
              <h3 class="font-semibold text-gray-700 text-lg mb-3">Детали заказа</h3>

              <div v-if="order.items && (order.items.type === 'doctor_appointment' || order.items.type === 'test_order')">
                <p class="text-indigo-600 font-medium">{{ order.items.title }}</p>
                <p v-if="order.items.city"><strong>Город:</strong> {{ order.items.city }}</p>
                <p v-if="order.items.lab"><strong>Лаборатория:</strong> {{ order.items.lab }}</p>
                <p v-if="order.items.birth_date"><strong>Дата рождения:</strong> {{ order.items.birth_date }}</p>

                <ul v-if="order.items.lines && order.items.lines.length" class="mt-3 divide-y divide-gray-100">
                  <li v-for="(item, idx) in order.items.lines" :key="idx" class="py-2 flex justify-between">
                    <span>{{ item.title }}</span>
                    <span class="font-medium">{{ item.price }} ₽</span>
                  </li>
                </ul>

                <div v-if="quizResult" class="mt-4 bg-emerald-50 border-l-4 border-emerald-300 p-3 rounded">
                  <div class="text-xs text-gray-500">Результат квиза</div>
                  <div class="font-medium text-gray-800">{{ quizResult }}</div>
                </div>

                <div v-if="quizSheet.length" class="mt-4 bg-gray-50 p-4 rounded-lg">
                  <h4 class="font-medium text-gray-700 mb-2">Ответы на квиз</h4>
                  <dl class="divide-y divide-gray-200">
                    <div v-for="(row, idx) in quizSheet" :key="idx" class="py-2">
                      <dt class="text-sm text-gray-500">{{ row.question }}</dt>
                      <dd class="text-sm font-medium text-gray-800">{{ row.answers.join(', ') }}</dd>
                    </div>
                  </dl>

                  <details v-if="order.items.quiz_answers" class="mt-3">
                    <summary class="text-xs text-gray-400 cursor-pointer">Исходные id ответов</summary>
                    <pre class="mt-2 text-xs bg-gray-100 p-2 rounded overflow-x-auto">{{ JSON.stringify(order.items.quiz_answers, null, 2) }}</pre>
                  </details>
                </div>
              </div>

              <div v-else>
                <ul class="divide-y divide-gray-100">
                  <li v-for="(item, idx) in order.items" :key="idx" class="py-2 flex justify-between">
                    <span>{{ item.title }}</span>
                    <span class="font-medium">{{ item.price }} ₽</span>
                  </li>
                </ul>
                <div class="mt-4 text-right font-bold text-lg">
                  Итого: {{ order.total_amount }} ₽
                </div>
              </div>

            </div>
          </div>

        </div>
      </div>
    </div>
  </AdminLayout>
</template>