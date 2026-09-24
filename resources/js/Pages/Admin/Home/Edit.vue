<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import TextInput from '@/Components/TextInput.vue'

const props = defineProps({
  patientSettings: { type: Object, default: () => ({}) },
  patientMeta: { type: Object, default: () => ({ title: '', description: '', keywords: '' }) },

  doctorSettings: { type: Object, default: () => ({}) },
  doctorMeta: { type: Object, default: () => ({ title: '', description: '', keywords: '' }) },

  blogs: { type: Array, default: () => [] },
})

// Активная вкладка
const activeTab = ref('patient')

// SEO режимы
const isEditingPatientSeo = ref(false)
const isEditingDoctorSeo = ref(false)

// Вспомогательная функция сборки полей
const buildFormData = (settings, meta, type) => {
  const defaultHowToPass = [
    { image: '', title: '', description: '' },
    { image: '', title: '', description: '' },
    { image: '', title: '', description: '' },
  ]

  const howToPassData = settings?.how_to_pass?.length === 3
      ? JSON.parse(JSON.stringify(settings.how_to_pass))
      : defaultHowToPass

  return {
    type,
    advantages: settings?.advantages?.length > 0
        ? JSON.parse(JSON.stringify(settings.advantages))
        : (type === 'patient' ? [{ title: '', description: '' }] : []),
    results: settings?.results?.length > 0
        ? JSON.parse(JSON.stringify(settings.results))
        : (type === 'patient' ? [{ title: '', description: '' }] : []),
    how_to_pass: howToPassData,
    faq: settings?.faq?.length > 0
        ? JSON.parse(JSON.stringify(settings.faq))
        : (type === 'patient' ? [{ question: '', answer: '' }] : []),
    featured_blog_ids: (settings?.featured_blog_ids || []).map(id => Number(id)),
    meta_title: meta?.title || settings?.meta_title || '',
    meta_description: meta?.description || settings?.meta_description || '',
    meta_keywords: meta?.keywords || settings?.meta_keywords || '',
    hero_title: settings?.hero_title || '',
    hero_subtitle: settings?.hero_subtitle || '',
    why_title: settings?.why_title || '',
    why_subtitle: settings?.why_subtitle || '',
    results_intro_title: settings?.results_intro_title || '',
    cta_text: settings?.cta_text || '',
    cta_url: settings?.cta_url || '',
  }
}

// Инициализация Inertia-форм
const patientForm = useForm(buildFormData(props.patientSettings, props.patientMeta, 'patient'))
const doctorForm = useForm(buildFormData(props.doctorSettings, props.doctorMeta, 'doctor'))

// Блог toggle
const toggleBlog = (form, blogId) => {
  const id = Number(blogId)
  const index = form.featured_blog_ids.indexOf(id)

  if (index > -1) {
    form.featured_blog_ids.splice(index, 1)
  } else if (form.featured_blog_ids.length < 2) {
    form.featured_blog_ids.push(id)
  }
}

// Вспомогательные функции
const addAdvantage = (form) => {
  if (form.advantages.length < 5) form.advantages.push({ title: '', description: '' })
}
const removeAdvantage = (form, index) => form.advantages.splice(index, 1)

const addResult = (form) => {
  if (form.results.length < 3) form.results.push({ title: '', description: '' })
}
const removeResult = (form, index) => form.results.splice(index, 1)

const addFaq = (form) => form.faq.push({ question: '', answer: '' })
const removeFaq = (form, index) => form.faq.splice(index, 1)

// Отправка формы
// Отправка формы (используем post c _method: 'put' для поддержки загрузки файлов)
const submit = (form, isDoctor = false) => {
  form.transform((data) => ({
    ...data,
    _method: 'put',
  })).post(route('admin.home.update'), {
    preserveScroll: true,
    preserveState: true,
    onSuccess: (page) => {
      if (isDoctor) {
        isEditingDoctorSeo.value = false
        activeTab.value = 'doctor'
        form.defaults(buildFormData(page.props.doctorSettings, page.props.doctorMeta, 'doctor'))
      } else {
        isEditingPatientSeo.value = false
        activeTab.value = 'patient'
        form.defaults(buildFormData(page.props.patientSettings, page.props.patientMeta, 'patient'))
      }
    }
  })
}

// Вспомогательный метод для выбора файла
const handleImageUpload = (form, index, event) => {
  const file = event.target.files[0]
  if (file) {
    form.how_to_pass[index].image = file
  }
}
</script>

<template>
  <Head title="Настройки страниц" />

  <AdminLayout>
    <div class="py-12">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <!-- Флэш-сообщение об успехе -->
        <div v-if="$page.props.flash?.success" class="p-4 bg-green-100 border border-green-300 text-green-700 rounded-lg">
          {{ $page.props.flash.success }}
        </div>

        <!-- 🔹 Вкладки (Tabs) -->
        <div class="flex border-b border-gray-200 bg-white rounded-t-lg px-6 pt-4 space-x-4">
          <button
              type="button"
              @click="activeTab = 'patient'"
              class="pb-3 px-3 font-medium text-sm border-b-2 transition-colors focus:outline-none"
              :class="activeTab === 'patient' ? 'border-blue-600 text-blue-600 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700'"
          >
            Пациентам (Главная)
          </button>
          <button
              type="button"
              @click="activeTab = 'doctor'"
              class="pb-3 px-3 font-medium text-sm border-b-2 transition-colors focus:outline-none"
              :class="activeTab === 'doctor' ? 'border-blue-600 text-blue-600 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700'"
          >
            Врачам
          </button>
        </div>

        <!-- ==================== ВКЛАДКА 1: ПАЦИЕНТАМ ==================== -->
        <div v-if="activeTab === 'patient'" class="space-y-6">

          <!-- SEO для пациентов -->
          <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
            <div class="flex items-start justify-between mb-4">
              <div>
                <h3 class="text-lg font-semibold text-gray-800">SEO настройки (Пациентам)</h3>
                <p class="text-xs text-gray-500">Управление отображением главной страницы в поисковых системах</p>
              </div>
              <button
                  v-if="!isEditingPatientSeo"
                  type="button"
                  @click="isEditingPatientSeo = true"
                  class="text-sm text-blue-600 hover:text-blue-800 font-medium"
              >
                Редактировать
              </button>
              <div v-else class="flex gap-2">
                <button
                    type="button"
                    @click="isEditingPatientSeo = false"
                    class="text-sm text-gray-600 hover:text-gray-800"
                >
                  Отмена
                </button>
              </div>
            </div>

            <div v-if="isEditingPatientSeo" class="space-y-4">
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Meta Title</label>
                <input
                    v-model="patientForm.meta_title"
                    type="text"
                    class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                    placeholder="Главная страница — ALEX LAB"
                    maxlength="255"
                />
                <p class="text-xs text-gray-500 mt-1">{{ patientForm.meta_title?.length || 0 }}/255</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Meta Description</label>
                <textarea
                    v-model="patientForm.meta_description"
                    rows="2"
                    class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                    placeholder="Описание главной страницы для поисковых систем..."
                    maxlength="500"
                ></textarea>
                <p class="text-xs text-gray-500 mt-1">{{ patientForm.meta_description?.length || 0 }}/500</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Meta Keywords</label>
                <input
                    v-model="patientForm.meta_keywords"
                    type="text"
                    class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                    placeholder="лаборатория, анализы, аллергия (через запятую)"
                    maxlength="500"
                />
                <p class="text-xs text-gray-500 mt-1">{{ patientForm.meta_keywords?.length || 0 }}/500</p>
              </div>
            </div>

            <div v-else class="space-y-2 text-sm">
              <div>
                <span class="font-medium text-gray-500 text-xs">Title: </span>
                <span class="text-gray-800 font-medium">{{ patientForm.meta_title || '—' }}</span>
              </div>
              <div>
                <span class="font-medium text-gray-500 text-xs">Description: </span>
                <span class="text-gray-700">{{ patientForm.meta_description || '—' }}</span>
              </div>
              <div>
                <span class="font-medium text-gray-500 text-xs">Keywords: </span>
                <span class="text-gray-700">{{ patientForm.meta_keywords || '—' }}</span>
              </div>
            </div>
          </div>

          <!-- Контент для пациентов -->
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center justify-between mb-6">
              <h2 class="text-2xl font-bold">Контент страницы пациентов</h2>
              <a
                  href="https://alexallergotest.ru/"
                  target="_blank"
                  rel="noopener"
                  class="text-sm text-blue-600 hover:text-blue-800 font-medium"
              >
                Открыть на сайте
              </a>
            </div>

            <form @submit.prevent="submit(patientForm, false)" class="space-y-8">

              <div class="border p-4 rounded-lg space-y-4">
                <h3 class="text-lg font-semibold">Первый экран и кнопка записи</h3>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Заголовок первого экрана</label>
                  <TextInput v-model="patientForm.hero_title" placeholder="Тест на аллергию ALEX² — один анализ, который даёт ответы" class="w-full" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Подзаголовок первого экрана</label>
                  <TextInput v-model="patientForm.hero_subtitle" placeholder="Необязательно" class="w-full" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Заголовок второго экрана</label>
                  <TextInput v-model="patientForm.why_title" placeholder="Почему ALEX2?" class="w-full" />
                  <p class="text-xs text-gray-500 mt-1">Экран сразу после первого. Пусто — вернётся «Почему ALEX2?».</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Подзаголовок второго экрана</label>
                  <TextInput v-model="patientForm.why_subtitle" placeholder="Почему Alex" class="w-full" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Заголовок блока «О результатах»</label>
                  <TextInput v-model="patientForm.results_intro_title" placeholder="Что вы получите по итогам теста на аллергию" class="w-full" />
                  <p class="text-xs text-gray-500 mt-1">Экран перед списком результатов.</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Текст кнопки записи</label>
                  <TextInput v-model="patientForm.cta_text" placeholder="Записаться на тест на аллергию" class="w-full" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Ссылка кнопки записи</label>
                  <TextInput v-model="patientForm.cta_url" placeholder="Пусто = открыть форму записи" class="w-full" />
                  <p class="text-xs text-gray-500 mt-1">Если пусто, кнопка открывает текущую форму записи.</p>
                </div>
              </div>

              <!-- 1. Преимущества -->
              <div class="border p-4 rounded-lg">
                <div class="flex justify-between items-center mb-1">
                  <h3 class="text-lg font-semibold">1. Преимущества (макс. 5)</h3>
                  <button type="button" @click="addAdvantage(patientForm)" :disabled="patientForm.advantages.length >= 5" class="text-sm bg-blue-50 text-blue-600 px-3 py-1 rounded disabled:opacity-50">
                    + Добавить
                  </button>
                </div>
                <p class="text-xs text-gray-500 mb-4">
                  Заголовки карточек выводятся как <code class="text-blue-600 bg-gray-100 px-1 py-0.5 rounded">&lt;h2&gt;</code> на слайдах преимуществ.
                </p>
                <div v-for="(item, index) in patientForm.advantages" :key="index" class="mb-4 p-3 bg-gray-50 rounded border">
                  <TextInput v-model="patientForm.advantages[index].title" placeholder="Название преимущества (Тег H2)" class="w-full mb-2" />
                  <textarea v-model="patientForm.advantages[index].description" placeholder="Описание" rows="2" class="w-full border-gray-300 rounded-md shadow-sm" />
                  <button type="button" @click="removeAdvantage(patientForm, index)" class="mt-2 text-xs text-red-500 hover:text-red-700">Удалить</button>
                </div>
              </div>

              <!-- 2. О результатах -->
              <div class="border p-4 rounded-lg">
                <div class="flex justify-between items-center mb-1">
                  <h3 class="text-lg font-semibold">2. О результатах (макс. 3)</h3>
                  <button type="button" @click="addResult(patientForm)" :disabled="patientForm.results.length >= 3" class="text-sm bg-blue-50 text-blue-600 px-3 py-1 rounded disabled:opacity-50">
                    + Добавить
                  </button>
                </div>
                <p class="text-xs text-gray-500 mb-4">
                  Заголовки этапов результатов выводятся как <code class="text-blue-600 bg-gray-100 px-1 py-0.5 rounded">&lt;h2&gt;</code> на публичном сайте.
                </p>
                <div v-for="(item, index) in patientForm.results" :key="index" class="mb-4 p-3 bg-gray-50 rounded border">
                  <TextInput v-model="patientForm.results[index].title" placeholder="Название результата (Тег H2)" class="w-full mb-2" />
                  <textarea v-model="patientForm.results[index].description" placeholder="Описание" rows="2" class="w-full border-gray-300 rounded-md shadow-sm" />
                  <button type="button" @click="removeResult(patientForm, index)" class="mt-2 text-xs text-red-500 hover:text-red-700">Удалить</button>
                </div>
              </div>

              <!-- 3. Как сдать тест -->
              <div class="border p-4 rounded-lg">
                <div class="flex justify-between items-center mb-1">
                  <h3 class="text-lg font-semibold">3. Как сдать тест (3 карточки)</h3>
                </div>
                <p class="text-xs text-gray-500 mb-4">
                  Заполните изображение, заголовок и описание для трех шагов.
                </p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div v-for="(card, index) in patientForm.how_to_pass" :key="index" class="p-3 bg-gray-50 rounded border space-y-3">
                    <div class="text-xs font-bold text-gray-500">Шаг {{ index + 1 }}</div>

                    <!-- Загрузка изображения -->
                    <div>
                      <label class="block text-xs font-medium text-gray-600 mb-1">Изображение</label>

                      <!-- Предпросмотр текущей картинки -->
                      <div v-if="typeof patientForm.how_to_pass[index].image === 'string' && patientForm.how_to_pass[index].image" class="mb-2 flex items-center gap-2">
                        <img :src="patientForm.how_to_pass[index].image" alt="Preview" class="w-12 h-12 object-cover rounded border bg-white" />
                        <span class="text-[10px] text-gray-500 truncate max-w-[150px]">{{ patientForm.how_to_pass[index].image }}</span>
                      </div>

                      <input
                          type="file"
                          accept="image/*"
                          @change="handleImageUpload(patientForm, index, $event)"
                          class="block w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer"
                      />
                    </div>

                    <div>
                      <label class="block text-xs font-medium text-gray-600 mb-1">Заголовок</label>
                      <TextInput v-model="patientForm.how_to_pass[index].title" placeholder="Заголовок шага" class="w-full text-xs" />
                    </div>

                    <div>
                      <label class="block text-xs font-medium text-gray-600 mb-1">Описание</label>
                      <textarea v-model="patientForm.how_to_pass[index].description" placeholder="Текст описания..." rows="3" class="w-full border-gray-300 rounded-md shadow-sm text-xs" />
                    </div>
                  </div>
                </div>
              </div>

              <!-- 4. FAQ -->
              <div class="border p-4 rounded-lg">
                <div class="flex justify-between items-center mb-1">
                  <h3 class="text-lg font-semibold">4. FAQ (Вопросы и ответы)</h3>
                  <button type="button" @click="addFaq(patientForm)" class="text-sm bg-blue-50 text-blue-600 px-3 py-1 rounded">
                    + Добавить вопрос
                  </button>
                </div>
                <p class="text-xs text-gray-500 mb-4">
                  Вопросы отображаются внутри интерактивного аккордеона на слайде FAQ.
                </p>
                <div v-for="(item, index) in patientForm.faq" :key="index" class="mb-4 p-3 bg-gray-50 rounded border">
                  <TextInput v-model="patientForm.faq[index].question" placeholder="Вопрос" class="w-full mb-2" />
                  <textarea v-model="patientForm.faq[index].answer" placeholder="Ответ" rows="3" class="w-full border-gray-300 rounded-md shadow-sm" />
                  <button type="button" @click="removeFaq(patientForm, index)" class="mt-2 text-xs text-red-500 hover:text-red-700">Удалить</button>
                </div>
              </div>

              <!-- 5. Блог про аллергию -->
              <div class="border p-4 rounded-lg">
                <div class="flex justify-between items-center mb-1">
                  <h3 class="text-lg font-semibold">5. Блог про аллергию (Выберите от 0 до 2 статей)</h3>
                  <span class="text-xs font-medium text-gray-500">Выбрано: {{ patientForm.featured_blog_ids.length }}/2</span>
                </div>
                <p class="text-xs text-gray-500 mb-4">
                  Заголовок секции блога <code class="text-blue-600 bg-gray-100 px-1 py-0.5 rounded">&lt;h2&gt;</code>, а названия карточек — <code class="text-blue-600 bg-gray-100 px-1 py-0.5 rounded">&lt;h3&gt;</code>.
                </p>

                <div class="max-h-60 overflow-y-auto border rounded p-3 bg-gray-50 space-y-2">
                  <label
                      v-for="blog in blogs"
                      :key="blog.id"
                      class="flex items-center space-x-3 p-2 hover:bg-gray-100 rounded cursor-pointer transition-colors"
                      :class="{ 'opacity-50 cursor-not-allowed': patientForm.featured_blog_ids.length >= 2 && !patientForm.featured_blog_ids.includes(Number(blog.id)) }"
                  >
                    <input
                        type="checkbox"
                        :value="blog.id"
                        :checked="patientForm.featured_blog_ids.includes(Number(blog.id))"
                        :disabled="patientForm.featured_blog_ids.length >= 2 && !patientForm.featured_blog_ids.includes(Number(blog.id))"
                        @change="toggleBlog(patientForm, blog.id)"
                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    />
                    <span class="text-sm">
                      {{ blog.title }}
                    </span>
                  </label>

                  <p v-if="blogs.length === 0" class="text-sm text-gray-500">Нет опубликованных статей.</p>
                </div>
              </div>

              <!-- Кнопка сохранения -->
              <div class="flex justify-end pt-4">
                <button
                    type="submit"
                    :disabled="patientForm.processing"
                    class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 transition-colors"
                >
                  {{ patientForm.processing ? 'Сохранение...' : 'Сохранить настройки пациентов' }}
                </button>
              </div>

            </form>
          </div>
        </div>


        <!-- ==================== ВКЛАДКА 2: ВРАЧАМ ==================== -->
        <div v-if="activeTab === 'doctor'" class="space-y-6">

          <!-- SEO для врачей -->
          <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
            <div class="flex items-start justify-between mb-4">
              <div>
                <h3 class="text-lg font-semibold text-gray-800">SEO настройки (Врачам)</h3>
                <p class="text-xs text-gray-500">Управление отображением страницы врачей в поисковых системах</p>
              </div>
              <button
                  v-if="!isEditingDoctorSeo"
                  type="button"
                  @click="isEditingDoctorSeo = true"
                  class="text-sm text-blue-600 hover:text-blue-800 font-medium"
              >
                Редактировать
              </button>
              <div v-else class="flex gap-2">
                <button
                    type="button"
                    @click="isEditingDoctorSeo = false"
                    class="text-sm text-gray-600 hover:text-gray-800"
                >
                  Отмена
                </button>
              </div>
            </div>

            <div v-if="isEditingDoctorSeo" class="space-y-4">
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Meta Title</label>
                <input
                    v-model="doctorForm.meta_title"
                    type="text"
                    class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                    placeholder="Информация для врачей — ALEX LAB"
                    maxlength="255"
                />
                <p class="text-xs text-gray-500 mt-1">{{ doctorForm.meta_title?.length || 0 }}/255</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Meta Description</label>
                <textarea
                    v-model="doctorForm.meta_description"
                    rows="2"
                    class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                    placeholder="Описание страницы для врачей..."
                    maxlength="500"
                ></textarea>
                <p class="text-xs text-gray-500 mt-1">{{ doctorForm.meta_description?.length || 0 }}/500</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Meta Keywords</label>
                <input
                    v-model="doctorForm.meta_keywords"
                    type="text"
                    class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                    placeholder="врачам, аллергодиагностика, alex lab"
                    maxlength="500"
                />
                <p class="text-xs text-gray-500 mt-1">{{ doctorForm.meta_keywords?.length || 0 }}/500</p>
              </div>
            </div>

            <div v-else class="space-y-2 text-sm">
              <div>
                <span class="font-medium text-gray-500 text-xs">Title: </span>
                <span class="text-gray-800 font-medium">{{ doctorForm.meta_title || '—' }}</span>
              </div>
              <div>
                <span class="font-medium text-gray-500 text-xs">Description: </span>
                <span class="text-gray-700">{{ doctorForm.meta_description || '—' }}</span>
              </div>
              <div>
                <span class="font-medium text-gray-500 text-xs">Keywords: </span>
                <span class="text-gray-700">{{ doctorForm.meta_keywords || '—' }}</span>
              </div>
            </div>
          </div>

          <!-- Контент для врачей -->
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <h2 class="text-2xl font-bold mb-6">Контент страницы для врачей</h2>

            <form @submit.prevent="submit(doctorForm, true)" class="space-y-8">

              <div class="border p-4 rounded-lg space-y-4">
                <h3 class="text-lg font-semibold">Первый экран и кнопка записи</h3>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Заголовок первого экрана</label>
                  <TextInput v-model="doctorForm.hero_title" placeholder="Аллергочип ALEX² — расширенный анализ на аллергию. 300+ аллергенов" class="w-full" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Подзаголовок первого экрана</label>
                  <TextInput v-model="doctorForm.hero_subtitle" placeholder="Необязательно" class="w-full" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Заголовок второго экрана</label>
                  <TextInput v-model="doctorForm.why_title" placeholder="ALEX² — лучший тест на аллергию, что есть на рынке." class="w-full" />
                  <p class="text-xs text-gray-500 mt-1">Экран сразу после первого на doc.alexallergotest.ru.</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Подзаголовок второго экрана</label>
                  <TextInput v-model="doctorForm.why_subtitle" placeholder="Почему ALEX2?" class="w-full" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Заголовок блока «О результатах»</label>
                  <TextInput v-model="doctorForm.results_intro_title" placeholder="Как назначать тест пациентам" class="w-full" />
                  <p class="text-xs text-gray-500 mt-1">Экран перед списком результатов.</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Текст кнопки записи</label>
                  <TextInput v-model="doctorForm.cta_text" placeholder="Записаться на тест на аллергию" class="w-full" />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Ссылка кнопки записи</label>
                  <TextInput v-model="doctorForm.cta_url" placeholder="Пусто = открыть форму записи" class="w-full" />
                </div>
              </div>

              <!-- 1. Преимущества -->
              <div class="border p-4 rounded-lg">
                <div class="flex justify-between items-center mb-1">
                  <h3 class="text-lg font-semibold">1. Преимущества (макс. 5)</h3>
                  <button type="button" @click="addAdvantage(doctorForm)" :disabled="doctorForm.advantages.length >= 5" class="text-sm bg-blue-50 text-blue-600 px-3 py-1 rounded disabled:opacity-50">
                    + Добавить
                  </button>
                </div>
                <p class="text-xs text-gray-500 mb-4">
                  Заголовки карточек выводятся как <code class="text-blue-600 bg-gray-100 px-1 py-0.5 rounded">&lt;h2&gt;</code> на слайдах преимуществ.
                </p>
                <div v-for="(item, index) in doctorForm.advantages" :key="index" class="mb-4 p-3 bg-gray-50 rounded border">
                  <TextInput v-model="doctorForm.advantages[index].title" placeholder="Название преимущества (Тег H2)" class="w-full mb-2" />
                  <textarea v-model="doctorForm.advantages[index].description" placeholder="Описание" rows="2" class="w-full border-gray-300 rounded-md shadow-sm" />
                  <button type="button" @click="removeAdvantage(doctorForm, index)" class="mt-2 text-xs text-red-500 hover:text-red-700">Удалить</button>
                </div>
                <p v-if="doctorForm.advantages.length === 0" class="text-xs text-gray-400 italic">Преимущества не добавлены.</p>
              </div>

              <!-- 2. О результатах -->
              <div class="border p-4 rounded-lg">
                <div class="flex justify-between items-center mb-1">
                  <h3 class="text-lg font-semibold">2. О результатах (макс. 3)</h3>
                  <button type="button" @click="addResult(doctorForm)" :disabled="doctorForm.results.length >= 3" class="text-sm bg-blue-50 text-blue-600 px-3 py-1 rounded disabled:opacity-50">
                    + Добавить
                  </button>
                </div>
                <p class="text-xs text-gray-500 mb-4">
                  Заголовки этапов результатов выводятся как <code class="text-blue-600 bg-gray-100 px-1 py-0.5 rounded">&lt;h2&gt;</code> на публичном сайте.
                </p>
                <div v-for="(item, index) in doctorForm.results" :key="index" class="mb-4 p-3 bg-gray-50 rounded border">
                  <TextInput v-model="doctorForm.results[index].title" placeholder="Название результата (Тег H2)" class="w-full mb-2" />
                  <textarea v-model="doctorForm.results[index].description" placeholder="Описание" rows="2" class="w-full border-gray-300 rounded-md shadow-sm" />
                  <button type="button" @click="removeResult(doctorForm, index)" class="mt-2 text-xs text-red-500 hover:text-red-700">Удалить</button>
                </div>
                <p v-if="doctorForm.results.length === 0" class="text-xs text-gray-400 italic">Информация о результатах не добавлена.</p>
              </div>

              <!-- 3. Как сдать тест -->

              <div class="border p-4 rounded-lg">
                <div class="flex justify-between items-center mb-1">
                  <h3 class="text-lg font-semibold">3. Как сдать тест (3 карточки)</h3>
                </div>
                <p class="text-xs text-gray-500 mb-4">
                  Загрузите изображение, укажите заголовок и описание для трех шагов.
                </p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div v-for="(card, index) in doctorForm.how_to_pass" :key="index" class="p-3 bg-gray-50 rounded border space-y-3">
                    <div class="text-xs font-bold text-gray-500">Шаг {{ index + 1 }}</div>

                    <!-- Загрузка изображения -->
                    <div>
                      <label class="block text-xs font-medium text-gray-600 mb-1">Изображение</label>

                      <!-- Предпросмотр текущей картинки -->
                      <div v-if="typeof doctorForm.how_to_pass[index].image === 'string' && doctorForm.how_to_pass[index].image" class="mb-2 flex items-center gap-2">
                        <img :src="doctorForm.how_to_pass[index].image" alt="Preview" class="w-12 h-12 object-cover rounded border bg-white" />
                        <span class="text-[10px] text-gray-500 truncate max-w-[150px]">{{ doctorForm.how_to_pass[index].image }}</span>
                      </div>

                      <input
                          type="file"
                          accept="image/*"
                          @change="handleImageUpload(doctorForm, index, $event)"
                          class="block w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer"
                      />
                    </div>

                    <div>
                      <label class="block text-xs font-medium text-gray-600 mb-1">Заголовок</label>
                      <TextInput v-model="doctorForm.how_to_pass[index].title" placeholder="Заголовок шага" class="w-full text-xs" />
                    </div>

                    <div>
                      <label class="block text-xs font-medium text-gray-600 mb-1">Описание</label>
                      <textarea v-model="doctorForm.how_to_pass[index].description" placeholder="Текст описания..." rows="3" class="w-full border-gray-300 rounded-md shadow-sm text-xs" />
                    </div>
                  </div>
                </div>
              </div>

              <!-- 4. FAQ -->
              <div class="border p-4 rounded-lg">
                <div class="flex justify-between items-center mb-1">
                  <h3 class="text-lg font-semibold">4. FAQ (Вопросы и ответы)</h3>
                  <button type="button" @click="addFaq(doctorForm)" class="text-sm bg-blue-50 text-blue-600 px-3 py-1 rounded">
                    + Добавить вопрос
                  </button>
                </div>
                <p class="text-xs text-gray-500 mb-4">
                  Вопросы отображаются внутри интерактивного аккордеона на слайде FAQ.
                </p>
                <div v-for="(item, index) in doctorForm.faq" :key="index" class="mb-4 p-3 bg-gray-50 rounded border">
                  <TextInput v-model="doctorForm.faq[index].question" placeholder="Вопрос" class="w-full mb-2" />
                  <textarea v-model="doctorForm.faq[index].answer" placeholder="Ответ" rows="3" class="w-full border-gray-300 rounded-md shadow-sm" />
                  <button type="button" @click="removeFaq(doctorForm, index)" class="mt-2 text-xs text-red-500 hover:text-red-700">Удалить</button>
                </div>
                <p v-if="doctorForm.faq.length === 0" class="text-xs text-gray-400 italic">Вопросы не добавлены.</p>
              </div>

              <!-- 5. Блог для врачей -->
              <div class="border p-4 rounded-lg">
                <div class="flex justify-between items-center mb-1">
                  <h3 class="text-lg font-semibold">5. Блог для врачей (Выберите от 0 до 2 статей)</h3>
                  <span class="text-xs font-medium text-gray-500">Выбрано: {{ doctorForm.featured_blog_ids.length }}/2</span>
                </div>
                <p class="text-xs text-gray-500 mb-4">
                  Заголовок секции блога <code class="text-blue-600 bg-gray-100 px-1 py-0.5 rounded">&lt;h2&gt;</code>, а названия карточек — <code class="text-blue-600 bg-gray-100 px-1 py-0.5 rounded">&lt;h3&gt;</code>.
                </p>

                <div class="max-h-60 overflow-y-auto border rounded p-3 bg-gray-50 space-y-2">
                  <label
                      v-for="blog in blogs"
                      :key="blog.id"
                      class="flex items-center space-x-3 p-2 hover:bg-gray-100 rounded cursor-pointer transition-colors"
                      :class="{ 'opacity-50 cursor-not-allowed': doctorForm.featured_blog_ids.length >= 2 && !doctorForm.featured_blog_ids.includes(Number(blog.id)) }"
                  >
                    <input
                        type="checkbox"
                        :value="blog.id"
                        :checked="doctorForm.featured_blog_ids.includes(Number(blog.id))"
                        :disabled="doctorForm.featured_blog_ids.length >= 2 && !doctorForm.featured_blog_ids.includes(Number(blog.id))"
                        @change="toggleBlog(doctorForm, blog.id)"
                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    />
                    <span class="text-sm">
                      {{ blog.title }}
                    </span>
                  </label>

                  <p v-if="blogs.length === 0" class="text-sm text-gray-500">Нет опубликованных статей.</p>
                </div>
              </div>

              <!-- Кнопка сохранения -->
              <div class="flex justify-end pt-4">
                <button
                    type="submit"
                    :disabled="doctorForm.processing"
                    class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 transition-colors"
                >
                  {{ doctorForm.processing ? 'Сохранение...' : 'Сохранить настройки врачей' }}
                </button>
              </div>

            </form>
          </div>
        </div>

      </div>
    </div>
  </AdminLayout>
</template>