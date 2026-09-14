<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({
  categories: Object
})

const destroy = (id) => {
  if (confirm('Удалить эту категорию?')) {
    router.delete(route('admin.author-categories.destroy', id), { preserveScroll: true })
  }
}
</script>

<template>
  <Head title="Категории авторов" />
  <AdminLayout>
    <div class="py-12">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-2xl font-bold text-gray-800">Категории авторов</h2>
          <Link :href="route('admin.author-categories.create')" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
            + Новая категория
          </Link>
        </div>

        <div class="bg-white shadow rounded-lg overflow-hidden">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Название</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Действия</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="cat in categories.data" :key="cat.id">
                <td class="px-6 py-4">{{ cat.name }}</td>
                <td class="px-6 py-4 text-right space-x-3">
                  <Link :href="route('admin.author-categories.edit', cat.id)" class="text-blue-600">Ред.</Link>
                  <button @click="destroy(cat.id)" class="text-red-600">Удалить</button>
                </td>
              </tr>
              <tr v-if="!categories.data?.length">
                <td colspan="2" class="px-6 py-8 text-center text-gray-500">Категорий пока нет</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>