<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const form = useForm({
  from_url: '',
  to_url: '',
  status_code: 301,
  is_active: true,
})

const submit = () => {
  form.post(route('admin.redirects.store'))
}
</script>

<template>
  <Head title="Создать редирект" />

  <AdminLayout>
    <div class="py-12">
      <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg">

          <div class="p-6 border-b border-gray-200">
            <h2 class="text-2xl font-bold text-gray-800">Создать редирект</h2>
          </div>

          <form @submit.prevent="submit" class="p-6 space-y-6">

            <!-- From URL -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Откуда (старый URL) *
              </label>
              <input
                  v-model="form.from_url"
                  type="text"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border"
                  placeholder="/old-page или /blog/old-article"
                  required
              />
              <p class="text-xs text-gray-500 mt-1">
                Можно указать полный URL или путь. Протокол и домен будут удалены автоматически.
              </p>
              <div v-if="form.errors.from_url" class="text-red-500 text-xs mt-1">
                {{ form.errors.from_url }}
              </div>
            </div>

            <!-- To URL -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Куда (новый URL) *
              </label>
              <input
                  v-model="form.to_url"
                  type="text"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border"
                  placeholder="/new-page или /blog/new-article"
                  required
              />
              <div v-if="form.errors.to_url" class="text-red-500 text-xs mt-1">
                {{ form.errors.to_url }}
              </div>
            </div>

            <!-- Status Code -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Код редиректа
              </label>
              <select
                  v-model="form.status_code"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border"
              >
                <option :value="301">301 — Постоянный (рекомендуется для SEO)</option>
                <option :value="302">302 — Временный</option>
              </select>
              <p class="text-xs text-gray-500 mt-1">
                301 — страница переехала навсегда. 302 — временный переезд.
              </p>
            </div>

            <!-- Is Active -->
            <div class="flex items-center gap-2">
              <input
                  v-model="form.is_active"
                  type="checkbox"
                  id="is_active"
                  class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
              />
              <label for="is_active" class="text-sm font-medium text-gray-700">
                Активен
              </label>
            </div>

            <!-- Buttons -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
              <Link
                  :href="route('admin.redirects.index')"
                  class="px-4 py-2 bg-white border border-gray-300 rounded-md text-gray-700 text-sm font-medium hover:bg-gray-50"
              >
                Отмена
              </Link>
              <button
                  type="submit"
                  :disabled="form.processing"
                  class="px-4 py-2 bg-blue-600 border border-transparent rounded-md text-white text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
              >
                {{ form.processing ? 'Сохранение...' : 'Создать' }}
              </button>
            </div>

          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>