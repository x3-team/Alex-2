<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  description: String
})

const form = useForm({
  description: props.description || ''
})

const submit = () => {
  form.post(route('admin.authors.settings.update'), {
    preserveScroll: true
  })
}
</script>

<template>
  <Head title="Настройки страницы авторов" />

  <AdminLayout>
    <div class="py-12">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white shadow rounded-lg p-6">
          <h2 class="text-2xl font-bold mb-6 text-gray-800">Описание страницы авторов</h2>

          <form @submit.prevent="submit" class="space-y-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Текст описания</label>
              <textarea
                  v-model="form.description"
                  rows="4"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border"
                  placeholder="Введите описание, которое будет отображаться на странице /blog/authors..."
                  maxlength="1000"
              ></textarea>
              <p class="text-xs text-gray-500 mt-1">
                {{ form.description?.length || 0 }}/1000 символов
              </p>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
              <Link
                  :href="route('admin.authors.index')"
                  class="px-4 py-2 bg-white border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
              >
                Отмена
              </Link>
              <button
                  type="submit"
                  :disabled="form.processing"
                  class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
              >
                Сохранить
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>