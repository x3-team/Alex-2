<script setup>
import { ref } from 'vue'
import { useForm, Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ImageCropperModal from '@/Components/ImageCropperModal.vue'

const props = defineProps({
  author: { type: Object, required: true },
  authorCategories: Array,
  selectedCategories: { type: Array, default: () => [] }
})

const form = useForm({
  name: props.author.name ?? '',
  email: props.author.email ?? '',
  password: '',
  phone: props.author.phone ?? '',
  password_confirmation: '',
  avatar: null,
  delete_avatar: false,
  bio: props.author.bio ?? '',
  is_admin: props.author.is_admin ?? false,
  career_history: (() => {
    const raw = props.author.career_history
    if (!raw) return [{ year_from: '', year_to: '', place: '' }]
    if (typeof raw === 'string') {
      try {
        const parsed = JSON.parse(raw)
        return Array.isArray(parsed) && parsed.length ? parsed : [{ year_from: '', year_to: '', place: '' }]
      } catch {
        return [{ year_from: '', year_to: '', place: '' }]
      }
    }
    return Array.isArray(raw) ? raw : [{ year_from: '', year_to: '', place: '' }]
  })(),
  education: (() => {
    const raw = props.author.education
    if (!raw) return [{ year_from: '', year_to: '', place: '' }]
    if (typeof raw === 'string') {
      try {
        const parsed = JSON.parse(raw)
        return Array.isArray(parsed) && parsed.length ? parsed : [{ year_from: '', year_to: '', place: '' }]
      } catch {
        return [{ year_from: '', year_to: '', place: '' }]
      }
    }
    return Array.isArray(raw) ? raw : [{ year_from: '', year_to: '', place: '' }]
  })(),
  author_categories: props.selectedCategories || [], // 🔹 Массив выбранных категорий
  seo_title: props.author.seo_title ?? '',
  seo_description: props.author.seo_description ?? '',
  seo_keywords: props.author.seo_keywords ?? '',
  credentials: props.author.credentials ?? '',
  profile_url: props.author.profile_url ?? '',
})

const previewUrl = ref(props.author?.avatar ? `/storage/${props.author.avatar}` : null)
const showCropper = ref(false)
const rawImageSrc = ref(null)

const handleAvatarChange = (e) => {
  const file = e.target.files?.[0]
  if (file) {
    const reader = new FileReader()
    reader.onload = (e) => {
      rawImageSrc.value = e.target.result
      showCropper.value = true
    }
    reader.readAsDataURL(file)
  }
}

const handleCropped = (croppedFile) => {
  form.avatar = croppedFile
  form.delete_avatar = false
  if (previewUrl.value && previewUrl.value.startsWith('blob:')) {
    URL.revokeObjectURL(previewUrl.value)
  }
  previewUrl.value = URL.createObjectURL(croppedFile)
}

const removeAvatar = () => {
  form.avatar = null
  form.delete_avatar = true
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  previewUrl.value = null
}

const addCareerEntry = () => {
  form.career_history.push({ year_from: '', year_to: '', place: '' })
}

const removeCareerEntry = (index) => {
  form.career_history.splice(index, 1)
  if (form.career_history.length === 0) {
    form.career_history.push({ year_from: '', year_to: '', place: '' })
  }
}
const addEducationEntry = () => {
  form.education.push({ year_from: '', year_to: '', place: '' })
}
const removeEducationEntry = (index) => {
  form.education.splice(index, 1)
  if (form.education.length === 0) {
    form.education.push({ year_from: '', year_to: '', place: '' })
  }
}
const submit = () => {
  if (!form.name?.trim() || !form.email?.trim()) {
    alert('Имя и Email обязательны!')
    return
  }

  const formData = new FormData()
  formData.append('name', form.name.trim())
  formData.append('email', form.email.trim())
  formData.append('bio', form.bio ?? '')

  const cleanCareer = form.career_history.filter(
      entry => (entry.year_from || entry.place?.trim())
  )
  formData.append('career_history', JSON.stringify(cleanCareer))
  formData.append('phone', form.phone ?? '')
  formData.append('seo_title', form.seo_title ?? '')
  formData.append('seo_description', form.seo_description ?? '')
  formData.append('seo_keywords', form.seo_keywords ?? '')
  formData.append('credentials', form.credentials ?? '')
  formData.append('profile_url', form.profile_url ?? '')
  formData.append('is_admin', form.is_admin ? '1' : '0')
  // 🔹 Отправляем массив категорий
  if (form.author_categories?.length) {
    form.author_categories.forEach(id => {
      formData.append('author_categories[]', id)
    })
  }

  if (form.password?.trim() && form.password_confirmation?.trim()) {
    formData.append('password', form.password.trim())
    formData.append('password_confirmation', form.password_confirmation.trim())
  }

  if (form.avatar instanceof File) {
    formData.append('avatar', form.avatar)
  } else if (form.delete_avatar) {
    formData.append('delete_avatar', '1')
  }
  const cleanEducation = form.education.filter(
      entry => (entry.year_from || entry.place?.trim())
  )
  formData.append('education', JSON.stringify(cleanEducation))
  formData.append('_method', 'PUT')

  axios.post(route('admin.authors.update', { author: props.author.id }), formData, {
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
      .then(() => {
        router.visit(route('admin.authors.index'), {
          method: 'get',
          preserveState: false,
          preserveScroll: false
        })
      })
      .catch((error) => {
        console.error('❌ Ошибка:', error.response?.data)
        alert('Ошибка: ' + JSON.stringify(error.response?.data?.errors || error.message))
      })
}
</script>

<template>
  <Head :title="`Редактировать: ${author.name}`" />

  <AdminLayout>
    <div class="max-w-2xl">
      <h1 class="text-2xl font-bold mb-6">Редактировать автора</h1>

      <form @submit.prevent="submit" class="bg-white shadow rounded-lg p-6 space-y-6">

        <!-- 🔹 SEO Блок -->
        <div class="mt-8 pt-6 border-t border-gray-200">
          <h3 class="text-lg font-medium text-gray-900 mb-4">SEO настройки</h3>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">SEO заголовок (title)</label>
              <input v-model="form.seo_title" type="text" class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border" placeholder="Заголовок для поисковиков" maxlength="255" />
              <p class="text-xs text-gray-500 mt-1">{{ form.seo_title?.length || 0 }}/255 символов</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">SEO описание (description)</label>
              <textarea v-model="form.seo_description" rows="3" class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border" placeholder="Краткое описание для поисковиков" maxlength="500"></textarea>
              <p class="text-xs text-gray-500 mt-1">{{ form.seo_description?.length || 0 }}/500 символов</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Ключевые слова (keywords)</label>
              <input v-model="form.seo_keywords" type="text" class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border" placeholder="врач, гинеколог, Москва" maxlength="255" />
              <p class="text-xs text-gray-500 mt-1">{{ form.seo_keywords?.length || 0 }}/255 символов</p>
            </div>
          </div>
        </div>

        <!-- 🔹 Аватар с кадрированием -->
        <div>
          <label class="block text-sm font-medium mb-1">Аватар</label>
          <div class="flex items-center gap-4">
            <img v-if="previewUrl" :src="previewUrl" class="w-24 h-24 rounded-full object-cover border-2 border-gray-200" />
            <div v-else class="w-24 h-24 rounded-full bg-gray-200 flex items-center justify-center text-3xl text-gray-400">
              {{ form.name?.charAt(0)?.toUpperCase() || '?' }}
            </div>
            <div class="flex-1">
              <input @input="handleAvatarChange" type="file" accept="image/*" class="text-sm" />
              <p class="text-xs text-gray-500 mt-1">После выбора откроется окно кадрирования.</p>
              <button v-if="previewUrl" type="button" @click="removeAvatar" class="text-xs text-red-600 hover:text-red-800 mt-1">
                Удалить аватар
              </button>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Имя *</label>
            <input v-model="form.name" type="text" class="w-full border rounded px-3 py-2" required />
            <div v-if="form.errors.name" class="text-red-500 text-xs">{{ form.errors.name }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Email *</label>
            <input v-model="form.email" type="email" class="w-full border rounded px-3 py-2" required />
            <div v-if="form.errors.email" class="text-red-500 text-xs">{{ form.errors.email }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Телефон</label>
            <input
                v-model="form.phone"
                type="tel"
                class="w-full border rounded px-3 py-2"
                placeholder="+7 (999) 000-00-00"
            />
            <div v-if="form.errors.phone" class="text-red-500 text-xs">{{ form.errors.phone }}</div>
          </div>
        </div>

        <!-- Пароль -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Новый пароль</label>
            <input v-model="form.password" type="password" class="w-full border rounded px-3 py-2" placeholder="••••••••" />
            <div v-if="form.errors.password" class="text-red-500 text-xs">{{ form.errors.password }}</div>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Подтвердите пароль</label>
            <input v-model="form.password_confirmation" type="password" class="w-full border rounded px-3 py-2" placeholder="••••••••" />
          </div>
        </div>
        <p class="text-xs text-gray-500 -mt-3">Оставьте пустым, чтобы не менять пароль</p>

        <div class="flex items-center gap-2 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
          <input
              v-model="form.is_admin"
              type="checkbox"
              id="is_admin"
              class="w-4 h-4 text-yellow-600 rounded focus:ring-yellow-500"
          />
          <label for="is_admin" class="text-sm font-medium text-gray-700">
            Админский аккаунт
          </label>
        </div>
        <p class="text-xs text-gray-500 -mt-3">
          Отмеченные авторы <strong>не будут</strong> отображаться в публичном списке на сайте
        </p>
        <div>
          <label class="block text-sm font-medium mb-1">Регалии</label>
          <input
              v-model="form.credentials"
              type="text"
              class="w-full border rounded px-3 py-2"
              placeholder="к.м.н., аллерголог"
              maxlength="255"
          />
          <p class="text-xs text-gray-500 mt-1">Например: к.м.н., аллерголог. Показывается рядом с именем на сайте, если заполнено.</p>
        <div class="mt-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Ссылка (Instagram и др.)</label>
          <input
              v-model="form.profile_url"
              type="text"
              inputmode="url"
              class="w-full border rounded px-3 py-2"
              placeholder="https://instagram.com/..."
              maxlength="500"
          />
          <p class="text-xs text-gray-500 mt-1">Необязательно. На странице автора показывается под специальностями, открывается в новом окне.</p>
        </div>
        </div>

        <!-- Био -->
        <div>
          <label class="block text-sm font-medium mb-1">Описание</label>
          <textarea v-model="form.bio" rows="4" class="w-full border rounded px-3 py-2" placeholder="Кратко об авторе..."></textarea>
          <div v-if="form.errors.bio" class="text-red-500 text-xs">{{ form.errors.bio }}</div>
        </div>

        <!-- 🔹 История карьеры (на Vue!) -->
        <div class="mt-6">
          <div class="flex items-center justify-between mb-2">
            <label class="block text-sm font-medium text-gray-700">Опыт работы</label>
            <button type="button" @click="addCareerEntry" class="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
              Добавить запись
            </button>
          </div>

          <div class="space-y-3">
            <div v-for="(entry, index) in form.career_history" :key="index" class="flex gap-3 items-start p-3 bg-gray-50 rounded-lg border border-gray-200">
              <div class="flex-1">
                <label class="block text-xs font-medium text-gray-500 mb-1">Год начала *</label>
                <input v-model="entry.year_from" type="number" class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm" placeholder="2020" min="1950" :max="new Date().getFullYear()" />
              </div>
              <div class="flex-1">
                <label class="block text-xs font-medium text-gray-500 mb-1">Год окончания</label>
                <input v-model="entry.year_to" type="number" class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm" placeholder="2023 или пусто" min="1950" :max="new Date().getFullYear()" />
              </div>
              <div class="flex-[2]">
                <label class="block text-xs font-medium text-gray-500 mb-1">Место работы / Должность</label>
                <input v-model="entry.place" type="text" class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm" placeholder="Врач в клинике Х" />
              </div>
              <button v-if="form.career_history.length > 1" type="button" @click="removeCareerEntry(index)" class="mt-6 p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
              </button>
            </div>
          </div>

          <p class="text-xs text-gray-500 mt-2">Подсказка: оставьте поле "Год окончания" пустым, если работаете до сих пор</p>
        </div>


        <!-- 🔹 Образование -->
        <div class="mt-6">
          <div class="flex items-center justify-between mb-2">
            <label class="block text-sm font-medium text-gray-700">Образование</label>
            <button type="button" @click="addEducationEntry" class="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
              Добавить запись
            </button>
          </div>

          <div class="space-y-3">
            <div v-for="(entry, index) in form.education" :key="'edu-' + index" class="flex gap-3 items-start p-3 bg-gray-50 rounded-lg border border-gray-200">
              <div class="flex-1">
                <label class="block text-xs font-medium text-gray-500 mb-1">Год начала *</label>
                <input v-model="entry.year_from" type="number" class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm" placeholder="2015" min="1950" :max="new Date().getFullYear()" />
              </div>
              <div class="flex-1">
                <label class="block text-xs font-medium text-gray-500 mb-1">Год окончания</label>
                <input v-model="entry.year_to" type="number" class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm" placeholder="2020 или пусто" min="1950" :max="new Date().getFullYear()" />
              </div>
              <div class="flex-[2]">
                <label class="block text-xs font-medium text-gray-500 mb-1">Учебное заведение / Специальность</label>
                <input v-model="entry.place" type="text" class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm" placeholder="МГМУ им. Сеченова, лечебное дело" />
              </div>
              <button v-if="form.education.length > 1" type="button" @click="removeEducationEntry(index)" class="mt-6 p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
              </button>
            </div>
          </div>

          <p class="text-xs text-gray-500 mt-2">Подсказка: оставьте поле "Год окончания" пустым, если учёба продолжается</p>
        </div>

        <!-- 🔹 Категории автора (множественный выбор) -->
        <div class="mt-6">
          <label class="block text-sm font-medium text-gray-700 mb-2">Категории автора</label>
          <select
              v-model="form.author_categories"
              multiple
              class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border min-h-[120px]"
          >
            <option v-for="cat in authorCategories" :key="cat.id" :value="cat.id">
              {{ cat.name }}
            </option>
          </select>
          <p class="text-xs text-gray-500 mt-1">Удерживайте Ctrl (Cmd на Mac) для выбора нескольких категорий</p>
          <p v-if="form.errors.author_categories" class="text-red-500 text-xs mt-1">{{ form.errors.author_categories }}</p>
        </div>

        <!-- Кнопки -->
        <div class="flex gap-3 pt-4">
          <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50">
            {{ form.processing ? 'Сохранение...' : 'Сохранить изменения' }}
          </button>
          <a :href="route('admin.authors.index')" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">
            Отмена
          </a>
        </div>
      </form>
    </div>

    <!-- 🔹 Модальное окно кадрирования -->
    <ImageCropperModal
        :show="showCropper"
        :image-src="rawImageSrc"
        @close="showCropper = false"
        @crop="handleCropped"
    />
  </AdminLayout>
</template>