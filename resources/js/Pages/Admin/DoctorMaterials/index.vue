<script setup>
import { ref } from 'vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  materials: { type: Array, default: () => [] },
  seoMeta: { type: Object, default: () => ({ title: '', description: '', keywords: '' }) },
})

const newId = () => (typeof crypto !== 'undefined' && crypto.randomUUID)
  ? crypto.randomUUID()
  : `cat-${Date.now()}-${Math.random().toString(16).slice(2)}`

const form = useForm({
  categories: props.categories.length
    ? props.categories
    : [{ id: newId(), name: 'Документы', slug: 'dokumenty', description: '' }],
  materials: props.materials.length
    ? props.materials
    : [{ title: '', file_path: '', date: '', description: '', category_id: '' }],
  meta_title: props.seoMeta?.title || '',
  meta_description: props.seoMeta?.description || '',
  meta_keywords: props.seoMeta?.keywords || '',
})

const isEditingSeo = ref(false)

const addCategory = () => {
  form.categories.push({ id: newId(), name: '', slug: '', description: '' })
}

const removeCategory = (index) => {
  const id = form.categories[index].id
  form.categories.splice(index, 1)
  form.materials.forEach((item) => {
    if (item.category_id === id) item.category_id = form.categories[0]?.id || ''
  })
}

const addMaterial = () => {
  form.materials.push({
    title: '',
    file_path: '',
    date: '',
    description: '',
    category_id: form.categories[0]?.id || '',
  })
}

const removeMaterial = (index) => {
  form.materials.splice(index, 1)
}

const handleFileUpload = async (event, index) => {
  const file = event.target.files[0]
  if (!file) return
  const formData = new FormData()
  formData.append('file', file)
  try {
    const response = await axios.post(route('admin.doctor-materials.upload'), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    form.materials[index].file_path = response.data.path
    if (!form.materials[index].title) {
      form.materials[index].title = response.data.original_name || file.name
    }
  } catch (error) {
    alert('Ошибка при загрузке файла. Проверьте формат и размер.')
  }
}

const submit = () => {
  form.put(route('admin.doctor-materials.update'), {
    preserveScroll: true,
    onSuccess: () => { isEditingSeo.value = false },
  })
}
</script>

<template>
  <Head title="Документы для врачей" />
  <AdminLayout>
    <div class="py-12">
      <div class="max-w-5xl mx-auto px-4 space-y-6">
        <div class="flex justify-between items-center">
          <h1 class="text-2xl font-bold text-gray-800">Документы для врачей</h1>
          <Link :href="route('admin.home')" class="text-sm text-blue-600 hover:underline">← Назад в админку</Link>
        </div>

        <div v-if="$page.props.flash?.success" class="p-4 bg-green-100 border border-green-300 text-green-700 rounded-lg">
          {{ $page.props.flash.success }}
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6 border">
          <div class="flex justify-between mb-4">
            <div>
              <h3 class="text-lg font-semibold">SEO</h3>
              <p class="text-xs text-gray-500">Мета-теги страницы документов на врачебном сайте</p>
            </div>
            <button type="button" class="text-sm text-blue-600" @click="isEditingSeo = !isEditingSeo">
              {{ isEditingSeo ? 'Отмена' : 'Редактировать' }}
            </button>
          </div>
          <div v-if="isEditingSeo" class="space-y-3">
            <input v-model="form.meta_title" class="w-full border rounded-md px-3 py-2 text-sm" placeholder="Meta Title" />
            <textarea v-model="form.meta_description" rows="2" class="w-full border rounded-md px-3 py-2 text-sm" placeholder="Meta Description" />
            <input v-model="form.meta_keywords" class="w-full border rounded-md px-3 py-2 text-sm" placeholder="Keywords" />
          </div>
          <div v-else class="text-sm space-y-1">
            <p><span class="text-gray-500">Title:</span> {{ form.meta_title || '—' }}</p>
            <p><span class="text-gray-500">Description:</span> {{ form.meta_description || '—' }}</p>
          </div>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
          <div class="bg-white p-6 rounded-lg border">
            <div class="flex justify-between mb-4">
              <h3 class="text-lg font-medium">Категории</h3>
              <button type="button" class="text-sm bg-blue-50 text-blue-600 px-3 py-1.5 rounded-md" @click="addCategory">
                + Категория
              </button>
            </div>
            <div v-for="(category, index) in form.categories" :key="category.id || index" class="mb-3 p-4 border rounded-lg bg-gray-50 space-y-3">
              <div class="grid md:grid-cols-2 gap-3">
                <input v-model="category.name" class="border rounded-md px-3 py-2 text-sm" placeholder="Название категории" />
                <input v-model="category.slug" class="border rounded-md px-3 py-2 text-sm" placeholder="slug (необязательно)" />
              </div>
              <input v-model="category.description" class="w-full border rounded-md px-3 py-2 text-sm" placeholder="Короткое описание" />
              <button type="button" class="text-xs text-red-500" @click="removeCategory(index)">Удалить категорию</button>
            </div>
          </div>

          <div class="bg-white p-6 rounded-lg border">
            <div class="flex justify-between mb-4">
              <h3 class="text-lg font-medium">Файлы</h3>
              <button type="button" class="text-sm bg-blue-50 text-blue-600 px-3 py-1.5 rounded-md" @click="addMaterial">
                + Документ
              </button>
            </div>
            <div v-for="(item, index) in form.materials" :key="index" class="mb-4 p-4 border rounded-lg bg-gray-50 space-y-3">
              <div class="grid md:grid-cols-2 gap-3">
                <input v-model="item.title" class="border rounded-md px-3 py-2 text-sm" placeholder="Название документа" />
                <select v-model="item.category_id" class="border rounded-md px-3 py-2 text-sm">
                  <option value="">Без категории</option>
                  <option v-for="category in form.categories" :key="category.id" :value="category.id">{{ category.name || 'Без названия' }}</option>
                </select>
                <input v-model="item.date" class="border rounded-md px-3 py-2 text-sm" placeholder="От 12.04.2025" />
                <input type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" @change="handleFileUpload($event, index)" />
              </div>
              <input v-model="item.description" class="w-full border rounded-md px-3 py-2 text-sm" placeholder="Короткое описание" />
              <p v-if="item.file_path" class="text-xs text-green-700">{{ item.file_path }}</p>
              <button type="button" class="text-xs text-red-500" @click="removeMaterial(index)">Удалить строку</button>
            </div>
          </div>

          <div class="flex justify-end">
            <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-blue-600 text-white rounded-md disabled:opacity-50">
              Сохранить изменения
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>
