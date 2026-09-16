<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ category: Object })
const form = useForm({ name: props.category?.name || '' })
const isEdit = !!props.category
</script>

<template>
  <Head :title="isEdit ? 'Редактировать категорию' : 'Новая категория'" />
  <AdminLayout>
    <div class="py-12">
      <div class="max-w-2xl mx-auto px-4 bg-white p-6 rounded-lg shadow">
        <h2 class="text-xl font-bold mb-4">{{ isEdit ? 'Редактировать' : 'Создать' }} категорию авторов</h2>
        <form @submit.prevent="isEdit ? form.put(route('admin.author-categories.update', category.id)) : form.post(route('admin.author-categories.store'))">
          <div class="mb-4">
            <label class="block text-sm font-medium mb-1">Название</label>
            <input v-model="form.name" type="text" class="w-full border rounded px-3 py-2" required />
            <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</p>
          </div>
          <div class="flex gap-3">
            <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50">
              {{ form.processing ? 'Сохранение...' : 'Сохранить' }}
            </button>
            <Link :href="route('admin.author-categories.index')" class="px-4 py-2 border rounded hover:bg-gray-50">Отмена</Link>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>