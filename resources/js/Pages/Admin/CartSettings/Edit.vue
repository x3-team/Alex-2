<script setup>
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  mainProduct: Object,
  services: Array,
})

const form = useForm({
  main_product: { ...props.mainProduct },
  services: props.services.length ? props.services.map(s => ({...s})) : [{ id: null, title: '', description: '', price: 0, is_active: true }],
})

const addService = () => {
  form.services.push({ id: null, title: '', description: '', price: 0, is_active: true })
}

const removeService = (index) => {
  form.services.splice(index, 1)
}

const submit = () => {
  form.put(route('admin.cart-settings.update'))
}
</script>

<template>
  <AdminLayout>
    <div class="py-12">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
          <h2 class="text-2xl font-bold text-gray-800 mb-6">Настройки корзины</h2>

          <form @submit.prevent="submit" class="space-y-8">

            <!-- Основной товар: ALEX2 -->
            <div class="border border-blue-200 bg-blue-50 p-6 rounded-lg">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-blue-800">1. Основной анализ</h3>
                <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-bold rounded-full">ВСЕГДА В КОРЗИНЕ</span>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-gray-700">Название</label>
                  <input v-model="form.main_product.title" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
                </div>
                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-gray-700">Описание</label>
                  <textarea v-model="form.main_product.description" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></textarea>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Цена (₽)</label>
                  <input v-model="form.main_product.price" type="number" step="0.01" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
                </div>
              </div>
            </div>

            <!-- Динамические дополнительные услуги -->
            <div>
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800">2. Дополнительные услуги</h3>
                <button type="button" @click="addService" class="text-sm bg-blue-50 text-blue-600 px-3 py-1 rounded hover:bg-blue-100">
                  + Добавить услугу
                </button>
              </div>

              <div v-for="(service, index) in form.services" :key="index" class="border border-gray-200 p-6 rounded-lg mb-4" :class="{ 'opacity-60': !service.is_active }">
                <div class="flex items-center justify-between mb-4">
                  <h4 class="text-md font-medium text-gray-700">Услуга #{{ index + 1 }}</h4>
                  <div class="flex items-center gap-4">
                    <label class="flex items-center cursor-pointer">
                      <input v-model="service.is_active" type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500" />
                      <span class="ml-2 text-sm text-gray-700">Активна</span>
                    </label>
                    <button type="button" @click="removeService(index)" class="text-sm text-red-500 hover:text-red-700">Удалить</button>
                  </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4" :class="{ 'pointer-events-none': !service.is_active }">
                  <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Название</label>
                    <input v-model="service.title" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
                  </div>
                  <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Описание</label>
                    <textarea v-model="service.description" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm"></textarea>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Цена (₽)</label>
                    <input v-model="service.price" type="number" step="0.01" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
                  </div>
                </div>
              </div>
            </div>

            <div class="flex justify-end pt-4">
              <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50">
                Сохранить настройки
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>