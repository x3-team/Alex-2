<script setup>
import { computed, ref } from 'vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  materials: { type: Array, default: () => [] },
  seoMeta: { type: Object, default: () => ({ title: '', description: '', keywords: '' }) },
})

const newId = () => (typeof crypto !== 'undefined' && crypto.randomUUID)
  ? crypto.randomUUID()
  : `id-${Date.now()}-${Math.random().toString(16).slice(2)}`

const normalizeMaterials = (rows) => rows.map((item, index) => ({
  id: item.id || newId(),
  title: item.title || '',
  file_path: item.file_path || '',
  date: item.date || '',
  description: item.description || '',
  category_id: item.category_id || '',
  sort_order: item.sort_order ?? index + 1,
}))

const form = useForm({
  categories: props.categories.length
    ? props.categories
    : [{ id: newId(), name: 'Документы', slug: 'dokumenty', description: '' }],
  materials: props.materials.length
    ? normalizeMaterials(props.materials)
    : [],
  meta_title: props.seoMeta?.title || '',
  meta_description: props.seoMeta?.description || '',
  meta_keywords: props.seoMeta?.keywords || '',
})

const isEditingSeo = ref(false)
const dragging = ref(null)

const MAX_CATEGORIES = 6
const canAddCategory = () => form.categories.length < MAX_CATEGORIES
const categoryIdOf = (item) => item?.category_id || ''

const filesInCategory = (categoryId) => form.materials.filter((item) => categoryIdOf(item) === (categoryId || ''))

const categoryGroups = computed(() => {
  const groups = form.categories.map((category) => ({
    id: category.id,
    name: category.name || 'Без названия',
    files: filesInCategory(category.id),
  }))
  const uncategorized = filesInCategory('')
  if (uncategorized.length) {
    groups.push({ id: '', name: 'Без категории', files: uncategorized })
  }
  return groups
})

const flattenMaterials = () => {
  const next = []
  form.categories.forEach((category) => {
    next.push(...filesInCategory(category.id))
  })
  next.push(...filesInCategory(''))
  return next
}

const replaceCategoryFiles = (categoryId, files) => {
  const cid = categoryId || ''
  const rest = form.materials.filter((item) => categoryIdOf(item) !== cid)
  const first = form.materials.findIndex((item) => categoryIdOf(item) === cid)
  if (first === -1) {
    form.materials = [...rest, ...files]
    return
  }
  form.materials = [
    ...form.materials.slice(0, first).filter((item) => categoryIdOf(item) !== cid),
    ...files,
    ...form.materials.slice(first).filter((item) => categoryIdOf(item) !== cid),
  ]
}

const moveInCategory = (categoryId, from, to) => {
  const files = [...filesInCategory(categoryId)]
  if (from === to || from < 0 || to < 0 || from >= files.length || to >= files.length) {
    return
  }
  const [row] = files.splice(from, 1)
  files.splice(to, 0, row)
  replaceCategoryFiles(categoryId, files)
}

const addCategory = () => {
  if (!canAddCategory()) return
  form.categories.push({ id: newId(), name: '', slug: '', description: '' })
}

const removeCategory = (index) => {
  const id = form.categories[index].id
  const fallback = form.categories.filter((_, i) => i !== index)[0]?.id || ''
  form.categories.splice(index, 1)
  form.materials.forEach((item) => {
    if (item.category_id === id) item.category_id = fallback
  })
}

const addMaterial = (categoryId = '') => {
  form.materials.push({
    id: newId(),
    title: '',
    file_path: '',
    date: '',
    description: '',
    category_id: categoryId || form.categories[0]?.id || '',
    sort_order: filesInCategory(categoryId || form.categories[0]?.id || '').length + 1,
  })
}

const removeMaterial = (id) => {
  form.materials = form.materials.filter((item) => item.id !== id)
}

const handleFileUpload = async (event, item) => {
  const file = event.target.files[0]
  if (!file) return
  const formData = new FormData()
  formData.append('file', file)
  try {
    const response = await axios.post(route('admin.doctor-materials.upload'), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    item.file_path = response.data.path
    if (!item.title) {
      item.title = response.data.original_name || file.name
    }
  } catch (error) {
    alert('Ошибка при загрузке файла. Проверьте формат и размер.')
  }
}

const onCategoryChange = (item) => {
  form.materials = [...form.materials.filter((row) => row.id !== item.id), item]
}

const onDragStart = (event, categoryId, index) => {
  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('text/plain', String(index))
  dragging.value = { categoryId: categoryId || '', index }
}

const onDrop = (categoryId, index) => {
  if (!dragging.value || (dragging.value.categoryId || '') !== (categoryId || '')) {
    dragging.value = null
    return
  }
  moveInCategory(categoryId, dragging.value.index, index)
  dragging.value = null
}

const submit = () => {
  form.materials = flattenMaterials()
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
              <div>
                <h3 class="text-lg font-medium">Категории</h3>
                <p class="text-xs text-gray-500">До {{ MAX_CATEGORIES }} плашек. На сайте рисуются только созданные категории.</p>
              </div>
              <button
                type="button"
                class="text-sm bg-blue-50 text-blue-600 px-3 py-1.5 rounded-md disabled:opacity-40"
                :disabled="!canAddCategory()"
                @click="addCategory"
              >
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

          <div class="bg-white p-6 rounded-lg border space-y-6">
            <div>
              <h3 class="text-lg font-medium">Файлы по категориям</h3>
              <p class="text-sm text-gray-500 mt-1">
                Порядок внутри категории — тот же, что на сайте. Перетащите строку за ⋮⋮ или сдвиньте стрелками.
              </p>
            </div>

            <section
              v-for="group in categoryGroups"
              :key="group.id || 'none'"
              class="rounded-xl border border-gray-200 overflow-hidden"
            >
              <div class="flex items-center justify-between gap-3 px-4 py-3 bg-gray-50 border-b">
                <div>
                  <h4 class="font-medium text-gray-900">{{ group.name }}</h4>
                  <p class="text-xs text-gray-500">{{ group.files.length }} в этом порядке</p>
                </div>
                <button
                  v-if="group.id"
                  type="button"
                  class="text-sm bg-white border px-3 py-1.5 rounded-md text-blue-600"
                  @click="addMaterial(group.id)"
                >
                  + Файл в категорию
                </button>
              </div>

              <div v-if="!group.files.length" class="px-4 py-6 text-sm text-gray-400">
                Пока пусто. Добавьте файл — он появится первым на сайте.
              </div>

              <div class="divide-y">
                <article
                  v-for="(item, index) in group.files"
                  :key="item.id"
                  class="p-4 bg-white space-y-3"
                  :class="{ 'opacity-50': dragging?.categoryId === (group.id || '') && dragging?.index === index }"
                  @dragover.prevent
                  @drop.prevent="onDrop(group.id, index)"
                >
                  <div class="flex items-start gap-3">
                    <div class="flex flex-col items-center gap-1 pt-1 select-none">
                      <span class="text-xs text-gray-400 font-medium w-6 text-center">{{ index + 1 }}</span>
                      <div
                        draggable="true"
                        class="text-gray-400 hover:text-gray-700 cursor-grab active:cursor-grabbing px-1"
                        title="Перетащить"
                        role="button"
                        tabindex="0"
                        aria-label="Перетащить файл"
                        @dragstart="onDragStart($event, group.id, index)"
                        @dragend="dragging = null"
                      >
                        <svg width="14" height="16" viewBox="0 0 14 16" fill="currentColor" aria-hidden="true">
                          <circle cx="4" cy="3" r="1.4" /><circle cx="10" cy="3" r="1.4" />
                          <circle cx="4" cy="8" r="1.4" /><circle cx="10" cy="8" r="1.4" />
                          <circle cx="4" cy="13" r="1.4" /><circle cx="10" cy="13" r="1.4" />
                        </svg>
                      </div>
                      <div class="flex flex-col">
                        <button
                          type="button"
                          class="text-gray-400 hover:text-gray-800 disabled:opacity-20 leading-none"
                          :disabled="index === 0"
                          title="Выше"
                          @click="moveInCategory(group.id, index, index - 1)"
                        >▲</button>
                        <button
                          type="button"
                          class="text-gray-400 hover:text-gray-800 disabled:opacity-20 leading-none"
                          :disabled="index === group.files.length - 1"
                          title="Ниже"
                          @click="moveInCategory(group.id, index, index + 1)"
                        >▼</button>
                      </div>
                    </div>

                    <div class="flex-1 space-y-3 min-w-0">
                      <div class="grid md:grid-cols-2 gap-3">
                        <input v-model="item.title" class="border rounded-md px-3 py-2 text-sm" placeholder="Название документа" />
                        <select v-model="item.category_id" class="border rounded-md px-3 py-2 text-sm" @change="onCategoryChange(item)">
                          <option value="">Без категории</option>
                          <option v-for="category in form.categories" :key="category.id" :value="category.id">{{ category.name || 'Без названия' }}</option>
                        </select>
                        <input v-model="item.date" class="border rounded-md px-3 py-2 text-sm" placeholder="От 12.04.2025" />
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" @change="handleFileUpload($event, item)" />
                      </div>
                      <input v-model="item.description" class="w-full border rounded-md px-3 py-2 text-sm" placeholder="Короткое описание" />
                      <p v-if="item.file_path" class="text-xs text-green-700 truncate">{{ item.file_path }}</p>
                      <button type="button" class="text-xs text-red-500" @click="removeMaterial(item.id)">Удалить строку</button>
                    </div>
                  </div>
                </article>
              </div>
            </section>

            <button
              v-if="!form.categories.length"
              type="button"
              class="text-sm bg-blue-50 text-blue-600 px-3 py-1.5 rounded-md"
              @click="addMaterial('')"
            >
              + Документ
            </button>
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
