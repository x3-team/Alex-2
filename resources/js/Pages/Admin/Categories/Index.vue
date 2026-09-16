<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({
  categories: Object
})

const deleteForm = useForm({})

const destroy = (id) => {
  if (confirm('Удалить категорию?')) {
    deleteForm.delete(route('admin.categories.destroy', id), {
      preserveScroll: true,
      onSuccess: () => {
        // Список обновится автоматически
      },
      onError: (errors) => {
        console.error(errors)
        alert('Не удалось удалить категорию.')
      }
    })
  }
}
</script>

<template>
  <Head title="Категории" />
  <AdminLayout>
    <div class="py-12">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Сообщения об успехе/ошибке -->
        <div v-if="$page.props.flash?.success" class="mb-4 p-4 bg-green-100 text-green-700 rounded-lg">
          {{ $page.props.flash.success }}
        </div>
        <div v-if="$page.props.flash?.error" class="mb-4 p-4 bg-red-100 text-red-700 rounded-lg">
          {{ $page.props.flash.error }}
        </div>

        <div class="flex justify-between items-center mb-6">
          <h1 class="text-2xl font-bold">Категории</h1>
          <Link :href="route('admin.categories.create')" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            + Новая категория
          </Link>
        </div>

        <div class="bg-white shadow rounded-lg overflow-hidden">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Название</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Slug</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Действия</th>
            </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="cat in categories.data" :key="cat.id">
              <td class="px-6 py-4">{{ cat.name }}</td>
              <td class="px-6 py-4 text-gray-500 font-mono text-sm">{{ cat.slug }}</td>
              <td class="px-6 py-4 text-right space-x-2 text-sm">
                <Link :href="route('admin.categories.edit', cat.id)" class="text-blue-600 hover:text-blue-900 transition-colors">Редактировать</Link>
                <button
                    @click="destroy(cat.id)"
                    :disabled="deleteForm.processing"
                    class="text-red-600 hover:text-red-900 disabled:opacity-50"
                >
                  Удалить
                </button>
              </td>
            </tr>
            <tr v-if="!categories.data?.length">
              <td colspan="3" class="px-6 py-8 text-center text-gray-500">Категорий нет</td>
            </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>