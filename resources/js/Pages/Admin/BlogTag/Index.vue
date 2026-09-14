<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
defineProps({ tags: Object })
const destroy = (id) => {
  if (confirm('Удалить этот тег?')) {
    router.delete(route('admin.blog-tags.destroy', id), { preserveScroll: true })
  }
}
</script>

<template>
  <Head title="Теги блога" />
  <AdminLayout>
    <div class="py-12">
      <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-2xl font-bold">Теги блога</h2>
          <Link :href="route('admin.blog-tags.create')" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            + Новый тег
          </Link>
        </div>
        <div class="bg-white shadow rounded-lg overflow-hidden">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Название</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Слаг</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Действия</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="tag in tags.data" :key="tag.id">
                <td class="px-6 py-4">{{ tag.name }}</td>
                <td class="px-6 py-4 text-gray-500">{{ tag.slug }}</td>
                <td class="px-6 py-4 text-right space-x-3">
                  <Link :href="route('admin.blog-tags.edit', tag.id)" class="text-blue-600">Ред.</Link>
                  <button @click="destroy(tag.id)" class="text-red-600">Удалить</button>
                </td>
              </tr>
              <tr v-if="!tags.data?.length">
                <td colspan="3" class="px-6 py-8 text-center text-gray-500">Тегов пока нет</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>