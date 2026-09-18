<script setup>
import { reactive, ref } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  videos: { type: Array, default: () => [] },
  blogs: { type: Array, default: () => [] },
})

const emptyVideo = () => ({
  title: '',
  slug: '',
  description: '',
  seo_title: '',
  seo_description: '',
  seo_keywords: '',
  og_title: '',
  og_description: '',
  cover_path: '',
  embed_url: '',
  duration: '',
  published_at: new Date().toISOString().slice(0, 10),
  related_blog_id: '',
  is_active: true,
})

const createForm = useForm(emptyVideo())
const editingId = ref(null)
const editState = reactive({})

const editCoverInputs = {}
const setEditCoverInput = (id, el) => {
  if (el) editCoverInputs[id] = el
  else delete editCoverInputs[id]
}


const uploadCover = async (event, target) => {
  const file = event.target.files?.[0]
  if (!file) return
  const formData = new FormData()
  formData.append('file', file)
  try {
    const response = await axios.post(route('admin.doctor-videos.upload'), formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    target.cover_path = response.data.path
  } catch (error) {
    alert('Не удалось загрузить обложку.')
  }
}

const clearCover = (target, inputEl) => {
  target.cover_path = ''
  if (inputEl) inputEl.value = ''
}


const submitCreate = () => {
  createForm.post(route('admin.doctor-videos.store'), {
    preserveScroll: true,
    onSuccess: () => createForm.reset(),
  })
}

const startEdit = (video) => {
  editingId.value = video.id
  editState[video.id] = {
    title: video.title,
    slug: video.slug,
    description: video.description || '',
    seo_title: video.seo_title || '',
    seo_description: video.seo_description || '',
    seo_keywords: video.seo_keywords || '',
    og_title: video.og_title || '',
    og_description: video.og_description || '',
    cover_path: video.cover_path || '',
    embed_url: video.embed_url,
    duration: video.duration || '',
    published_at: video.published_at ? String(video.published_at).slice(0, 10) : '',
    related_blog_id: video.related_blog_id || '',
    is_active: !!video.is_active,
  }
}

const saveEdit = (video) => {
  router.put(route('admin.doctor-videos.update', video.id), editState[video.id], {
    preserveScroll: true,
    onSuccess: () => { editingId.value = null },
  })
}

const removeVideo = (video) => {
  if (!confirm(`Удалить «${video.title}»?`)) return
  router.delete(route('admin.doctor-videos.destroy', video.id), { preserveScroll: true })
}
</script>

<template>
  <Head title="Видео — админка" />
  <AdminLayout>
    <div class="py-12">
      <div class="max-w-5xl mx-auto px-4 space-y-6">
        <div class="flex justify-between items-center">
          <h1 class="text-2xl font-bold text-gray-800">Видео</h1>
          <Link :href="route('admin.home')" class="text-sm text-blue-600 hover:underline">← Назад в админку</Link>
        </div>

        <div v-if="$page.props.flash?.success" class="p-4 bg-green-100 border border-green-300 text-green-700 rounded-lg">
          {{ $page.props.flash.success }}
        </div>

        <form class="bg-white p-6 rounded-lg border space-y-4" @submit.prevent="submitCreate">
          <h2 class="text-lg font-semibold">Новое видео</h2>
          <p class="text-sm text-gray-500">Своя обложка обязательна для карточки. В поле ссылки — адрес YouTube или VK, не код iframe.</p>
          <div class="grid md:grid-cols-2 gap-4">
            <label class="text-sm">Название
              <input v-model="createForm.title" required class="mt-1 w-full border rounded-md px-3 py-2" />
            </label>
            <label class="text-sm">Ссылка на видео
              <input v-model="createForm.embed_url" required class="mt-1 w-full border rounded-md px-3 py-2" placeholder="https://youtu.be/..." />
            </label>
            <label class="text-sm md:col-span-2">Короткий анонс
              <textarea v-model="createForm.description" rows="2" class="mt-1 w-full border rounded-md px-3 py-2" />
            </label>
            <label class="text-sm">Обложка
              <input ref="createCoverInput" type="file" accept="image/*" class="mt-1 w-full text-sm" @change="uploadCover($event, createForm)" />
              <template v-if="createForm.cover_path">
                <img :src="createForm.cover_path" alt="" class="mt-2 max-h-28 rounded border object-cover" />
                <span class="block mt-1 text-xs text-green-700 break-all">{{ createForm.cover_path }}</span>
                <button type="button" class="mt-1 text-sm text-red-600" @click="clearCover(createForm, $refs.createCoverInput)">Удалить обложку</button>
              </template>
            </label>
            <label class="text-sm">Длительность
              <input v-model="createForm.duration" class="mt-1 w-full border rounded-md px-3 py-2" placeholder="24:10" />
            </label>
            <label class="text-sm">Дата публикации
              <input v-model="createForm.published_at" type="date" class="mt-1 w-full border rounded-md px-3 py-2" />
            </label>
            <label class="text-sm">Текстовая версия
              <select v-model="createForm.related_blog_id" class="mt-1 w-full border rounded-md px-3 py-2">
                <option value="">Нет</option>
                <option v-for="blog in blogs" :key="blog.id" :value="blog.id">{{ blog.title }}</option>
              </select>
            </label>
          </div>

          <details class="border border-gray-200 rounded-lg bg-gray-50">
            <summary class="px-4 py-3 cursor-pointer font-medium text-gray-700">SEO настройки</summary>
            <div class="px-4 pb-4 space-y-4">
              <label class="block text-sm">SEO Title
                <input v-model="createForm.seo_title" type="text" maxlength="255" class="mt-1 w-full border rounded-md px-3 py-2" :placeholder="createForm.title || 'По умолчанию = название + «— видео ALEX LAB»'" />
              </label>
              <label class="block text-sm">SEO Description
                <textarea v-model="createForm.seo_description" rows="2" maxlength="500" class="mt-1 w-full border rounded-md px-3 py-2" :placeholder="createForm.description || 'По умолчанию = анонс или название'" />
              </label>
              <label class="block text-sm">SEO Keywords
                <input v-model="createForm.seo_keywords" type="text" maxlength="500" class="mt-1 w-full border rounded-md px-3 py-2" />
              </label>
              <label class="block text-sm">OG Title
                <input v-model="createForm.og_title" type="text" maxlength="255" class="mt-1 w-full border rounded-md px-3 py-2" placeholder="Пусто = SEO Title" />
              </label>
              <label class="block text-sm">OG Description
                <textarea v-model="createForm.og_description" rows="2" maxlength="500" class="mt-1 w-full border rounded-md px-3 py-2" placeholder="Пусто = SEO Description" />
              </label>
            </div>
          </details>

          <div class="flex flex-wrap items-center gap-4 pt-1">
            <label class="inline-flex items-center gap-2 text-sm shrink-0">
              <input v-model="createForm.is_active" type="checkbox" /> Опубликовать
            </label>
            <button type="submit" :disabled="createForm.processing" class="ml-auto px-5 py-2 bg-blue-600 text-white rounded-md disabled:opacity-50">
              Добавить видео
            </button>
          </div>
        </form>

        <div v-for="video in videos" :key="video.id" class="bg-white p-5 rounded-lg border">
          <div v-if="editingId !== video.id" class="flex justify-between gap-4">
            <div>
              <h3 class="font-medium">{{ video.title }}</h3>
              <p class="text-sm text-gray-500">{{ video.source }} · {{ video.duration || 'без длительности' }} · /video/{{ video.slug }}</p>
            </div>
            <div class="flex gap-3 text-sm">
              <button type="button" class="text-blue-600" @click="startEdit(video)">Изменить</button>
              <button type="button" class="text-red-600" @click="removeVideo(video)">Удалить</button>
            </div>
          </div>
          <form v-else class="space-y-3" @submit.prevent="saveEdit(video)">
            <input v-model="editState[video.id].title" class="w-full border rounded-md px-3 py-2" />
            <input v-model="editState[video.id].embed_url" class="w-full border rounded-md px-3 py-2" />
            <textarea v-model="editState[video.id].description" rows="2" class="w-full border rounded-md px-3 py-2" />
            <div class="space-y-2">
              <label class="block text-sm text-gray-600">Обложка
                <input :ref="(el) => setEditCoverInput(video.id, el)" type="file" accept="image/*" class="mt-1 w-full text-sm" @change="uploadCover($event, editState[video.id])" />
              </label>
              <template v-if="editState[video.id].cover_path">
                <img :src="editState[video.id].cover_path" alt="" class="max-h-28 rounded border object-cover" />
                <p class="text-xs text-gray-500 break-all">{{ editState[video.id].cover_path }}</p>
                <button type="button" class="text-sm text-red-600" @click="clearCover(editState[video.id], editCoverInputs[video.id])">Удалить обложку</button>
              </template>
              <p v-else class="text-xs text-gray-400">Обложка не задана</p>
            </div>
            <div class="grid md:grid-cols-3 gap-3">
              <input v-model="editState[video.id].duration" class="border rounded-md px-3 py-2" placeholder="24:10" />
              <input v-model="editState[video.id].published_at" type="date" class="border rounded-md px-3 py-2" />
              <select v-model="editState[video.id].related_blog_id" class="border rounded-md px-3 py-2">
                <option value="">Нет статьи</option>
                <option v-for="blog in blogs" :key="blog.id" :value="blog.id">{{ blog.title }}</option>
              </select>
            </div>
            <details class="border border-gray-200 rounded-lg bg-gray-50">
              <summary class="px-4 py-3 cursor-pointer font-medium text-gray-700">SEO настройки</summary>
              <div class="px-4 pb-4 space-y-3">
                <input v-model="editState[video.id].seo_title" placeholder="SEO Title" class="w-full border rounded-md px-3 py-2" maxlength="255" />
                <textarea v-model="editState[video.id].seo_description" placeholder="SEO Description" rows="2" class="w-full border rounded-md px-3 py-2" maxlength="500" />
                <input v-model="editState[video.id].seo_keywords" placeholder="SEO Keywords" class="w-full border rounded-md px-3 py-2" maxlength="500" />
                <input v-model="editState[video.id].og_title" placeholder="OG Title" class="w-full border rounded-md px-3 py-2" maxlength="255" />
                <textarea v-model="editState[video.id].og_description" placeholder="OG Description" rows="2" class="w-full border rounded-md px-3 py-2" maxlength="500" />
              </div>
            </details>
            <div class="flex flex-wrap items-center gap-4">
              <label class="inline-flex items-center gap-2 text-sm shrink-0">
                <input v-model="editState[video.id].is_active" type="checkbox" /> Опубликовать
              </label>
              <div class="ml-auto flex gap-3">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md">Сохранить</button>
                <button type="button" class="text-sm text-gray-600" @click="editingId = null">Отмена</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
