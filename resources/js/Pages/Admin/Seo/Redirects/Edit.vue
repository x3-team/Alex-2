<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  redirect: Object,
})

const form = useForm({
  from_url: props.redirect.from_url,
  to_url: props.redirect.to_url,
  status_code: props.redirect.status_code,
  is_active: props.redirect.is_active,
})

const submit = () => {
  form.put(route('admin.redirects.update', props.redirect.id))
}
</script>

<template>
  <Head title="Редактировать редирект" />

  <AdminLayout>
    <div class="py-12">
      <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg">

          <div class="p-6 border-b border-gray-200">
            <h2 class="text-2xl font-bold text-gray-800">Редактировать редирект</h2>
            <div class="mt-2 text-sm text-gray-500">
              Срабатываний: <strong>{{ redirect.hits_count }}</strong>
              <span v-if="redirect.last_hit_at">
                (последний: {{ new Date(redirect.last_hit_at).toLocaleString('ru-RU') }})
              </span>
            </div>
          </div>

          <form @submit.prevent="submit" class="p-6 space-y-6">

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Откуда *</label>
              <input
                  v-model="form.from_url"
                  type="text"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border"
                  required
              />
              <div v-if="form.errors.from_url" class="text-red-500 text-xs mt-1">
                {{ form.errors.from_url }}
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Куда *</label>
              <input
                  v-model="form.to_url"
                  type="text"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border"
                  required
              />
              <div v-if="form.errors.to_url" class="text-red-500 text-xs mt-1">
                {{ form.errors.to_url }}
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Код редиректа</label>
              <select
                  v-model="form.status_code"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border"
              >
                <option :value="301">301 — Постоянный</option>
                <option :value="302">302 — Временный</option>
              </select>
            </div>

            <div class="flex items-center gap-2">
              <input
                  v-model="form.is_active"
                  type="checkbox"
                  id="is_active"
                  class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
              />
              <label for="is_active" class="text-sm font-medium text-gray-700">Активен</label>
            </div>

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
                {{ form.processing ? 'Сохранение...' : 'Сохранить' }}
              </button>
            </div>

          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>