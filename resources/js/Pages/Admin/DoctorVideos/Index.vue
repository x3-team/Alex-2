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
  <Head title="Видеолекции для врачей" />
  <AdminLayout>
    <div class="py-12">
      <div class="max-w-5xl mx-auto px-4 space-y-6">
        <div class="flex justify-between items-center">
          <h1 class="text-2xl font-bold text-gray-800">Видеолекции</h1>
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
              <input type="file" accept="image/*" class="mt-1 w-full text-sm" @change="uploadCover($event, createForm)" />
              <span v-if="createForm.cover_path" class="block mt-1 text-xs text-green-700">{{ createForm.cover_path }}</span>
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
          <label class="inline-flex items-center gap-2 text-sm">
            <input v-model="createForm.is_active" type="checkbox" /> Опубликовать
          </label>
          <button type="submit" :disabled="createForm.processing" class="px-5 py-2 bg-blue-600 text-white rounded-md disabled:opacity-50">
            Добавить видео
          </button>
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
            <input type="file" accept="image/*" @change="uploadCover($event, editState[video.id])" />
            <div class="grid md:grid-cols-3 gap-3">
              <input v-model="editState[video.id].duration" class="border rounded-md px-3 py-2" placeholder="24:10" />
              <input v-model="editState[video.id].published_at" type="date" class="border rounded-md px-3 py-2" />
              <select v-model="editState[video.id].related_blog_id" class="border rounded-md px-3 py-2">
                <option value="">Нет статьи</option>
                <option v-for="blog in blogs" :key="blog.id" :value="blog.id">{{ blog.title }}</option>
              </select>
            </div>
            <label class="inline-flex items-center gap-2 text-sm">
              <input v-model="editState[video.id].is_active" type="checkbox" /> Опубликовать
            </label>
            <div class="flex gap-3">
              <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md">Сохранить</button>
              <button type="button" class="text-sm text-gray-600" @click="editingId = null">Отмена</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
