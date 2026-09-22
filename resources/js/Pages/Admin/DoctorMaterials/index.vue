<script setup>
import { computed, ref } from 'vue'
import { Head, useForm, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { SITE_VERSION } from '@/siteVersion.js'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  materials: { type: Array, default: () => [] },
  seoMeta: { type: Object, default: () => ({ title: '', description: '', keywords: '' }) },
})

const newId = () => (typeof crypto !== 'undefined' && crypto.randomUUID)
  ? crypto.randomUUID()
  : `id-${Date.now()}-${Math.random().toString(16).slice(2)}`

const normalizeMaterials = (rows) => rows.map((item, index) => {
  const link_url = item.link_url || ''
  const file_path = link_url ? '' : (item.file_path || '')
  return {
    id: item.id || newId(),
    title: item.title || '',
    file_path,
    link_url: file_path ? '' : link_url,
    source_type: link_url ? 'link' : 'file',
    date: item.date || '',
    description: item.description || '',
    category_id: item.category_id || '',
    sort_order: item.sort_order ?? index + 1,
  }
})

const form = useForm({
  categories: props.categories.length
    ? props.categories.map((category) => ({ ...category, link_url: category.link_url || '' }))
    : [{ id: newId(), name: 'Документы', slug: 'dokumenty', description: '', link_url: '' }],
  materials: props.materials.length
    ? normalizeMaterials(props.materials)
    : [],
  meta_title: props.seoMeta?.title || '',
  meta_description: props.seoMeta?.description || '',
  meta_keywords: props.seoMeta?.keywords || '',
})

const isEditingSeo = ref(false)
const dragging = ref(null)
const isSaving = ref(false)
const saveFeedback = ref(null)

const clearSaveFeedback = () => {
  saveFeedback.value = null
}

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
  form.categories.push({ id: newId(), name: '', slug: '', description: '', link_url: '' })
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
    link_url: '',
    source_type: 'file',
    date: '',
    description: '',
    category_id: categoryId || form.categories[0]?.id || '',
    sort_order: filesInCategory(categoryId || form.categories[0]?.id || '').length + 1,
  })
}

const setSourceType = (item, type) => {
  item.source_type = type
  if (type === 'file') {
    item.link_url = ''
  } else {
    item.file_path = ''
  }
}

const normalizeLinkUrl = (raw) => {
  let url = (raw || '').trim()
  if (!url) return ''
  if (!/^https?:\/\//i.test(url)) {
    url = `https://${url}`
  }
  return url
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
    item.link_url = ''
    item.source_type = 'file'
    if (!item.title) {
      item.title = response.data.original_name || file.name
    }
  } catch (error) {
    alert('Ошибка при загрузке файла. Нужны pdf/jpg/png/doc/docx до 20 МБ.')
  }
}

const onCategoryChange = (item) => {
  form.materials = [...form.materials.filter((row) => row.id !== item.id), item]
}

const onDragStart = (event, categoryId, index) => {
  event.dataTransfer.effectAllowed = 'move'
  event.dataTransfer.setData('text/plain', String(index))
  const row = event.currentTarget.closest('.file-row')
  if (row) {
    event.dataTransfer.setDragImage(row, 24, 24)
  }
  dragging.value = { categoryId: categoryId || '', index }
}

const onDragOver = (categoryId, index) => {
  if (!dragging.value || (dragging.value.categoryId || '') !== (categoryId || '')) {
    return
  }
  if (dragging.value.index === index) {
    return
  }
  moveInCategory(categoryId, dragging.value.index, index)
  dragging.value = { categoryId: categoryId || '', index }
}

const onDragEnd = () => {
  dragging.value = null
}

const buildMaterialsPayload = () => {
  const rows = flattenMaterials()
    .filter((item) => (item.title || '').trim() !== '')
    .map((item) => {
      const source = item.source_type === 'link' ? 'link' : 'file'
      const file_path = source === 'file' ? (item.file_path || '') : ''
      const link_url = source === 'link' ? normalizeLinkUrl(item.link_url) : ''
      return {
        id: item.id,
        title: item.title.trim(),
        file_path,
        link_url,
        date: item.date || '',
        description: item.description || '',
        category_id: item.category_id || '',
        sort_order: item.sort_order,
      }
    })

  for (const row of rows) {
    const hasFile = !!row.file_path
    const hasLink = !!row.link_url
    if (hasFile === hasLink) {
      return {
        error: hasFile
          ? `«${row.title}»: у документа можно указать либо файл, либо ссылку — не оба сразу.`
          : `«${row.title}»: добавьте файл или ссылку.`,
      }
    }
  }

  return { rows }
}

const submit = () => {
  clearSaveFeedback()

  const { rows, error } = buildMaterialsPayload()
  if (error) {
    saveFeedback.value = { type: 'error', text: error }
    return
  }

  const categoriesPayload = form.categories.map((category) => {
    const raw = (category.link_url || '').trim()
    const link_url = !raw || raw.startsWith('/') ? raw : normalizeLinkUrl(raw)
    return {
      id: category.id,
      name: category.name,
      slug: category.slug,
      description: category.description || '',
      link_url,
    }
  })

  const payload = {
    categories: categoriesPayload,
    materials: rows,
    meta_title: form.meta_title,
    meta_description: form.meta_description,
    meta_keywords: form.meta_keywords,
  }

  isSaving.value = true
  saveFeedback.value = { type: 'saving', text: 'Сохраняем…' }

  router.put(route('admin.doctor-materials.update'), payload, {
    preserveScroll: true,
    onSuccess: () => {
      isEditingSeo.value = false
      saveFeedback.value = { type: 'success', text: 'Сохранено' }
    },
    onError: (errors) => {
      const first = Object.values(errors || {})[0]
      saveFeedback.value = {
        type: 'error',
        text: Array.isArray(first) ? first[0] : (first || 'Не удалось сохранить. Проверьте данные.'),
      }
    },
    onFinish: () => {
      isSaving.value = false
    },
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

        <form @submit.prevent="submit" class="space-y-6 doctor-materials-form">
          <div class="bg-white p-6 rounded-lg border">
            <div class="flex justify-between mb-4">
              <div>
                <h3 class="text-lg font-medium">Категории</h3>
                <p class="text-xs text-gray-500">До {{ MAX_CATEGORIES }} плашек. Ссылка необязательна: если она есть, клик ведёт по ней и не открывает категорию.</p>
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
              <input v-model="category.link_url" type="text" inputmode="url" class="w-full border rounded-md px-3 py-2 text-sm" placeholder="Ссылка категории (необязательно) — https://… или /путь" />
              <p class="text-xs text-gray-500">Пусто — плашка открывает категорию с документами. Заполнена — клик сразу переходит по ссылке.</p>
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

              <TransitionGroup name="file-sort" tag="div" class="file-list">
                <article
                  v-for="(item, index) in group.files"
                  :key="item.id"
                  class="file-row p-4 bg-white space-y-3"
                  :class="{ 'is-dragging': dragging?.categoryId === (group.id || '') && dragging?.index === index }"
                  @dragenter.prevent
                  @dragover.prevent="onDragOver(group.id, index)"
                  @drop.prevent
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
                        @dragend="onDragEnd"
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
                        <div class="flex flex-wrap items-center gap-4 text-sm">
                          <label class="inline-flex items-center gap-2">
                            <input type="radio" :name="`source-${item.id}`" value="file" :checked="item.source_type !== 'link'" @change="setSourceType(item, 'file')" />
                            Файл
                          </label>
                          <label class="inline-flex items-center gap-2">
                            <input type="radio" :name="`source-${item.id}`" value="link" :checked="item.source_type === 'link'" @change="setSourceType(item, 'link')" />
                            Ссылка
                          </label>
                        </div>
                      </div>
                      <div v-if="item.source_type !== 'link'" class="space-y-1">
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" @change="handleFileUpload($event, item)" />
                        <p v-if="item.file_path" class="text-xs text-green-700 truncate">{{ item.file_path }}</p>
                      </div>
                      <div v-else class="space-y-1">
                        <input v-model="item.link_url" type="text" inputmode="url" class="w-full border rounded-md px-3 py-2 text-sm" placeholder="https://… ссылка на скачивание" />
                        <p class="text-xs text-gray-500">При клике на плашку откроется эта ссылка. Файл на сайт не загружается.</p>
                      </div>
                      <input v-model="item.description" class="w-full border rounded-md px-3 py-2 text-sm" placeholder="Короткое описание" />
                      <button type="button" class="text-xs text-red-500" @click="removeMaterial(item.id)">Удалить строку</button>
                    </div>
                  </div>
                </article>
              </TransitionGroup>
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

          <div class="doctor-materials-form-spacer" aria-hidden="true" />
        </form>

        <div class="doctor-materials-save-dock" role="region" aria-label="Сохранение">
          <div class="doctor-materials-save-dock-inner">
            <div class="doctor-materials-save-meta">
              <span class="text-xs text-gray-500 tabular-nums">Админ · v{{ SITE_VERSION }}</span>
              <p
                v-if="saveFeedback"
                class="doctor-materials-save-status"
                :class="`is-${saveFeedback.type}`"
                role="status"
              >
                {{ saveFeedback.text }}
              </p>
              <p v-else-if="$page.props.flash?.success" class="doctor-materials-save-status is-success" role="status">
                {{ $page.props.flash.success }}
              </p>
            </div>
            <button
              type="button"
              class="doctor-materials-save-btn"
              :disabled="isSaving"
              @click="submit"
            >
              {{ isSaving ? 'Сохраняем…' : 'Сохранить изменения' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
.file-list {
  position: relative;
}
.file-row {
  border-top: 1px solid #eee;
  transition: box-shadow 0.2s ease, background-color 0.2s ease;
}
.file-row.is-dragging {
  background: #f7f7f7;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
  opacity: 0.72;
  position: relative;
  z-index: 2;
}
.file-sort-move {
  transition: transform 0.32s cubic-bezier(0.22, 1, 0.36, 1);
  will-change: transform;
}

.doctor-materials-form-spacer {
  height: 5rem;
}

.doctor-materials-save-dock {
  position: fixed;
  z-index: 40;
  left: 0;
  right: 0;
  bottom: 0;
  border-top: 1px solid #e5e7eb;
  background: rgba(249, 250, 251, 0.96);
  backdrop-filter: blur(8px);
  box-shadow: 0 -8px 24px rgba(0, 0, 0, 0.06);
}

@media (min-width: 1024px) {
  .doctor-materials-save-dock {
    left: 20%;
  }
}

.doctor-materials-save-dock-inner {
  max-width: 64rem;
  margin: 0 auto;
  padding: 0.75rem 1rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.doctor-materials-save-meta {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}

.doctor-materials-save-status {
  margin: 0;
  font-size: 0.875rem;
  font-weight: 500;
}

.doctor-materials-save-status.is-saving {
  color: #2563eb;
}

.doctor-materials-save-status.is-success {
  color: #15803d;
}

.doctor-materials-save-status.is-error {
  color: #b91c1c;
}

.doctor-materials-save-btn {
  flex-shrink: 0;
  padding: 0.625rem 1.5rem;
  border-radius: 0.375rem;
  background: #2563eb;
  color: #fff;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
}

.doctor-materials-save-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.doctor-materials-save-btn:not(:disabled):hover {
  background: #1d4ed8;
}
</style>
