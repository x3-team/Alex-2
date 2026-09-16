<script setup>
import { ref, watch, onBeforeUnmount, onMounted, computed } from 'vue'
import { useForm, Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import TiptapEditor from '@/Components/TiptapEditor.vue'
import axios from 'axios'
import RelatedBlogsSelector from '@/Components/RelatedBlogsSelector.vue'
import AudienceField from '@/Pages/Admin/Blog/AudienceField.vue'

// Получаем пост, авторов и категории из пропсов
const props = defineProps({
  blog: { type: Object, required: true },
  authors: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  tags: Array,
  selectedTags: { type: Array, default: () => [] }
})

// 🔹 Дебаг в консоли
onMounted(() => {
  console.log('🔍 Edit.vue debug:', {
    blog_id: props.blog?.id,
    author: props.blog?.author,
    category: props.blog?.category,
    form_author_id: props.blog?.author?.id ? Number(props.blog.author.id) : null,
    form_category_id: props.blog?.category?.id ? Number(props.blog.category.id) : null,
  })
})

const toDatetimeLocalValue = (iso) => {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

const parseSources = (sourcesData) => {
  if (!sourcesData) return []
  if (Array.isArray(sourcesData)) {
    return sourcesData.map(item =>
        typeof item === 'string' ? { title: item, url: '' } : { title: item.title || '', url: item.url || '' }
    )
  }
  try {
    const parsed = JSON.parse(sourcesData)
    if (Array.isArray(parsed)) {
      return parsed.map(item =>
          typeof item === 'string' ? { title: item, url: '' } : { title: item.title || '', url: item.url || '' }
      )
    }
  } catch (e) {
    return [{ title: sourcesData, url: '' }]
  }
  return []
}

// 🔹 Задаем form
const form = useForm({
  duration: props.blog?.duration || '',
  seo_title: props.blog?.seo_title || '',
  seo_description: props.blog?.seo_description || '',
  seo_keywords: props.blog?.seo_keywords || '',
  title: props.blog.title ?? '',
  slug: props.blog.slug ?? '',
  content: props.blog.content ?? '',
  preview_image: null,
  excerpt: props.blog.excerpt ?? '',
  author_id: props.blog.author?.id ? Number(props.blog.author.id) : null,
  category_id: props.blog.category?.id ? Number(props.blog.category.id) : null,
  sort_order: props.blog.sort_order ?? 0,
  table_of_contents: props.blog.table_of_contents ?? '',
  sources: parseSources(props.blog.sources),
  cta_title: props.blog.cta_title ?? '',
  cta_description: props.blog.cta_description ?? '',
  cta_button_text: props.blog.cta_button_text ?? '',
  cta_button_url: props.blog.cta_button_url ?? '',
  related_posts: Array.isArray(props.blog.related_posts)
      ? props.blog.related_posts.map(p => typeof p === 'object' ? p.id : p)
      : [],
  tag_ids: props.blog?.tags ? props.blog.tags.map(t => Number(t.id)) : [],
  is_active: props.blog.is_active ?? true,
  canonical_url: props.blog.canonical_url ?? '',
  noindex: !!props.blog.noindex,
  og_title: props.blog.og_title ?? '',
  og_description: props.blog.og_description ?? '',
  faqs: props.blog.faqs || [],
  audience: props.blog.audience || 'patients',
  published_at: toDatetimeLocalValue(props.blog.published_at),
})

watch(() => form.is_active, (active, prev) => {
  if (active && !prev && !form.published_at) {
    form.published_at = toDatetimeLocalValue(new Date().toISOString())
  }
})

// 🔹 Исправлено: работаем строго с form.faqs
const addFaq = () => {
  if (!form.faqs) form.faqs = []
  form.faqs.push({ question: '', answer: '' })
}

const removeFaq = (index) => {
  form.faqs.splice(index, 1)
}

// 🔹 Превью оглавления — ТОЛЬКО H2
const previewToc = computed(() => {
  if (!form.content) return []

  try {
    const parser = new DOMParser()
    const doc = parser.parseFromString(form.content, 'text/html')
    const headings = doc.querySelectorAll('h2')

    return Array.from(headings).map(h => {
      const text = h.textContent?.trim() || ''
      if (!text) return null

      const anchor = text.toLowerCase()
          .replace(/[^\w\s-а-яА-ЯёЁ]/g, '')
          .replace(/[\s_]+/g, '-')
          .replace(/^-+|-+$/g, '')

      return { text, anchor, level: 2 }
    }).filter(item => item !== null)
  } catch (e) {
    console.error('TOC parse error:', e)
    return []
  }
})

// 🔹 Watch для гарантии, что значения подгрузятся
watch(() => props.blog, (newBlog) => {
  if (newBlog?.author?.id) {
    form.author_id = Number(newBlog.author.id)
  }
  if (newBlog?.category?.id) {
    form.category_id = Number(newBlog.category.id)
  }
}, { immediate: true, deep: true })

const editorMode = ref('visual')
const previewImageUrl = ref(null)
const existingPreviewUrl = ref(props.blog.preview_image ? `/storage/${props.blog.preview_image}` : null)

const slugManuallyEdited = ref(true)


const saveErrorLines = ref([])

const errorLabels = {
  title: 'Заголовок',
  slug: 'Ссылка',
  content: 'Текст',
  excerpt: 'Краткое описание',
  preview_image: 'Обложка',
  category_id: 'Категория',
  author_id: 'Автор',
  tag_ids: 'Теги',
  audience: 'Аудитория',
}

const humanizeValidationMessage = (message) => {
  const text = Array.isArray(message) ? message.join(' ') : String(message || '')
  if (text === 'validation.required' || text.endsWith('.required')) {
    return 'обязательное поле'
  }
  return text
}

const formatSaveErrors = (errors) => {
  const entries = Object.entries(errors || {})
  if (!entries.length) {
    return ['Не получилось сохранить. Попробуйте ещё раз.']
  }
  return entries.map(([key, msg]) => {
    let label = errorLabels[key]
    if (!label && String(key).startsWith('faqs')) label = 'FAQ'
    if (!label && String(key).startsWith('sources')) label = 'Источник'
    const text = humanizeValidationMessage(msg)
    return label ? `${label}: ${text}` : text
  })
}

const showSaveErrors = (errors) => {
  saveErrorLines.value = formatSaveErrors(errors)
  if (typeof window !== 'undefined') {
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

const dropEmptyRows = () => {
  form.faqs = (form.faqs || []).filter((row) => String(row?.question || '').trim() && String(row?.answer || '').trim())
  form.sources = (form.sources || []).filter((row) => String(row?.title || '').trim())
}


const getCategorySlug = (categoryId) => {
  if (!categoryId) return ''
  const category = props.categories?.find(c => c.id == categoryId)
  return category?.slug || ''
}

const RU_TO_LAT = {
  а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'z', з: 'z',
  и: 'i', й: 'i', к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r',
  с: 's', т: 't', у: 'u', ф: 'f', х: 'x', ц: 'c', ч: 'c', ш: 's', щ: 'shh',
  ъ: '', ы: 'y', ь: '', э: 'e', ю: 'iu', я: 'ia',
}

const slugifyTitle = (title) => {
  const mapped = String(title || '').toLowerCase().split('').map((ch) => (
    Object.prototype.hasOwnProperty.call(RU_TO_LAT, ch) ? RU_TO_LAT[ch] : ch
  )).join('')
  return mapped
    .replace(/[^a-z0-9\s-]/g, '')
    .replace(/[\s_-]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

const generateSlug = () => {
  if (!form.title || slugManuallyEdited.value) return

  const categorySlug = getCategorySlug(form.category_id)
  const titleSlug = slugifyTitle(form.title)

  form.slug = categorySlug ? `${categorySlug}/${titleSlug}` : titleSlug
}

watch(() => form.title, () => {
  if (!slugManuallyEdited.value) generateSlug()
})

watch(() => form.category_id, () => {
  if (!slugManuallyEdited.value) generateSlug()
})

const handlePreviewImageChange = (event) => {
  const file = event.target.files?.[0]
  form.preview_image = file || null

  if (previewImageUrl.value && window.URL) {
    URL.revokeObjectURL(previewImageUrl.value)
  }

  if (file && typeof window !== 'undefined' && window.URL) {
    previewImageUrl.value = URL.createObjectURL(file)
  } else {
    previewImageUrl.value = null
  }
}

const removePreviewImage = () => {
  form.preview_image = null
  if (previewImageUrl.value && window.URL) {
    URL.revokeObjectURL(previewImageUrl.value)
  }
  previewImageUrl.value = null

  const fileInput = document.querySelector('input[type="file"]')
  if (fileInput) fileInput.value = ''
}

const submit = () => {
  if (editorMode.value === 'text') {
    form.content = `<p>${(form.content || '').replace(/\n/g, '<br>')}</p>`
  }

  if (!form.title?.trim() || !form.content?.trim()) {
    alert('Заголовок и контент обязательны!')
    return
  }

  form.table_of_contents = JSON.stringify(previewToc.value, null, 2)
  dropEmptyRows()
  saveErrorLines.value = []

  // Передаем форму через Inertia Router с spoofing метода PUT
  router.post(route('admin.blog.update', props.blog.id), {
    _method: 'PUT',
    audience: form.audience || 'patients',
    title: form.title,
    content: form.content,
    slug: form.slug || '',
    excerpt: form.excerpt || '',
    author_id: form.author_id ?? '',
    category_id: form.category_id ?? '',
    tag_ids: form.tag_ids || [],
    duration: form.duration || '',
    sort_order: form.sort_order || 0,
    table_of_contents: form.table_of_contents || '[]',
    sources: JSON.stringify(form.sources || []),
    faqs: JSON.stringify(form.faqs || []),
    cta_title: form.cta_title || '',
    cta_description: form.cta_description || '',
    cta_button_text: form.cta_button_text || '',
    cta_button_url: form.cta_button_url || '',
    seo_title: form.seo_title || '',
    seo_description: form.seo_description || '',
    seo_keywords: form.seo_keywords || '',
    is_active: form.is_active ? 1 : 0,
    published_at: form.is_active && form.published_at ? form.published_at : '',
    noindex: form.noindex ? 1 : 0,
    og_title: form.og_title || '',
    og_description: form.og_description || '',
    canonical_url: form.canonical_url || '',
    related_posts: form.related_posts || [],
    preview_image: form.preview_image instanceof File ? form.preview_image : null
  }, {
    forceFormData: true,
    preserveScroll: true,
    onError: (errors) => {
      showSaveErrors(errors)
    },
    onSuccess: () => {
      saveErrorLines.value = []
    },
  })
}

watch(editorMode, (newMode, oldMode) => {
  if (oldMode === 'visual' && newMode === 'text') {
    form.content = form.content.replace(/<[^>]*>/g, '').trim()
  }
  if (oldMode === 'text' && newMode === 'visual') {
    if (form.content && !form.content.includes('<')) {
      form.content = `<p>${form.content.replace(/\n/g, '<br>')}</p>`
    }
  }
})

onBeforeUnmount(() => {
  if (previewImageUrl.value && window.URL) {
    URL.revokeObjectURL(previewImageUrl.value)
  }
})

const destroy = () => {
  if (confirm('Удалить этот пост?')) {
    router.delete(route('admin.blog.destroy', props.blog.id), { preserveScroll: true })
  }
}

const addSource = () => {
  form.sources.push({ title: '', url: '' })
}

const removeSource = (index) => {
  form.sources.splice(index, 1)
}
</script>

<template>
  <Head :title="`Редактировать: ${blog.title}`" />

  <AdminLayout>
    <div class="py-12">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white shadow rounded-lg p-6">
          <h2 class="text-2xl font-bold mb-6 text-gray-800">Редактировать пост</h2>

            <div
                v-if="saveErrorLines.length"
                class="mb-6 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800"
                role="alert"
            >
              <p class="font-medium">Статья не сохранилась</p>
              <ul class="mt-2 list-disc pl-5 space-y-1">
                <li v-for="(line, i) in saveErrorLines" :key="i">{{ line }}</li>
              </ul>
            </div>

          <form @submit.prevent="submit" class="space-y-6">
      <AudienceField v-model="form.audience" />


            <!-- SEO настройки -->
            <details class="mt-6 border border-gray-200 rounded-lg bg-gray-50">
              <summary class="px-4 py-3 cursor-pointer font-medium text-gray-700 hover:text-gray-900 flex items-center justify-between">
                <span>SEO настройки</span>
                <span class="text-sm text-gray-400">Нажмите, чтобы раскрыть</span>
              </summary>

              <div class="px-4 pb-4 space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">SEO Title</label>
                  <input
                      v-model="form.seo_title"
                      type="text"
                      class="w-full border-gray-300 rounded-md shadow-sm px-3 py-2 border"
                      :placeholder="form.title || 'По умолчанию = заголовок поста'"
                      maxlength="70"
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">SEO Description</label>
                  <textarea
                      v-model="form.seo_description"
                      rows="3"
                      class="w-full border-gray-300 rounded-md shadow-sm px-3 py-2 border"
                      maxlength="160"
                  ></textarea>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">SEO Keywords</label>
                  <input
                      v-model="form.seo_keywords"
                      type="text"
                      class="w-full border-gray-300 rounded-md shadow-sm px-3 py-2 border"
                      maxlength="500"
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">OG Title</label>
                  <input
                      v-model="form.og_title"
                      type="text"
                      class="w-full border-gray-300 rounded-md shadow-sm px-3 py-2 border"
                      placeholder="Пусто = взять SEO Title"
                      maxlength="255"
                  />
                  <p class="text-xs text-gray-500 mt-1">Пусто = взять seo_title / заголовок</p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">OG Description</label>
                  <textarea
                      v-model="form.og_description"
                      rows="3"
                      class="w-full border-gray-300 rounded-md shadow-sm px-3 py-2 border"
                      placeholder="Пусто = взять SEO Description"
                      maxlength="500"
                  ></textarea>
                  <p class="text-xs text-gray-500 mt-1">Пусто = взять seo_description / excerpt</p>
                </div>
              </div>
            </details>

            <!-- Активность -->
            <div class="mt-6 p-4 bg-gray-50 rounded-lg border border-gray-200 space-y-4">
              <div class="flex items-center justify-between border-b border-gray-200 pb-3">
                <div class="flex items-center gap-2">
                  <input
                      type="checkbox"
                      id="is_active"
                      v-model="form.is_active"
                      class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
                  />
                  <label for="is_active" class="text-sm font-medium text-gray-700">
                    Активен (опубликован на сайте)
                  </label>
                </div>
                <p class="text-xs text-gray-500">
                  Черновик: снимите галочку. При первой публикации дата на сайте ставится автоматически (сегодня).
                </p>
              </div>

              <div v-if="form.is_active" class="space-y-1">
                <label for="published_at" class="block text-sm font-medium text-gray-700">
                  Дата публикации на сайте
                </label>
                <input
                    id="published_at"
                    v-model="form.published_at"
                    type="datetime-local"
                    class="w-full max-w-xs border-gray-300 rounded-md shadow-sm px-3 py-2 border text-sm"
                />
                <p class="text-xs text-gray-500">
                  Можно поправить вручную. При включении «Активен» после черновика подставится текущее время.
                </p>
              </div>

              <div class="flex items-center gap-2">
                <input
                    type="checkbox"
                    id="noindex"
                    v-model="form.noindex"
                    class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
                />
                <label for="noindex" class="text-sm font-medium text-gray-700">
                  Не индексировать (страница на сайте, но noindex)
                </label>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Canonical URL</label>
                <input
                    v-model="form.canonical_url"
                    type="url"
                    class="w-full border-gray-300 rounded-md shadow-sm px-3 py-2 border text-sm"
                />
              </div>
            </div>

            <!-- Заголовок + Слаг -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Заголовок *</label>
                <input
                    v-model="form.title"
                    @blur="generateSlug"
                    type="text"
                    class="w-full border-gray-300 rounded-md shadow-sm px-3 py-2 border"
                    required
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Символьный код (ЧПУ)</label>
                <input
                    v-model="form.slug"
                    type="text"
                    class="w-full border-gray-300 rounded-md shadow-sm px-3 py-2 border"
                />
              </div>
            </div>

            <!-- Текст анонса -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Текст анонса</label>
              <textarea
                  v-model="form.excerpt"
                  rows="3"
                  class="w-full border-gray-300 rounded-md shadow-sm px-3 py-2 border"
                  maxlength="500"
              ></textarea>
            </div>

            <!-- Выбор автора -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Автор</label>
              <select
                  v-model="form.author_id"
                  class="w-full border-gray-300 rounded-md shadow-sm px-3 py-2 border"
              >
                <option :value="null">— Выбрать автора —</option>
                <option v-for="author in authors" :key="author.id" :value="Number(author.id)">
                  {{ author.name }}
                </option>
              </select>
            </div>

            <!-- Категория -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Категория</label>
              <select
                  v-model="form.category_id"
                  class="w-full border-gray-300 rounded-md shadow-sm px-3 py-2 border"
              >
                <option :value="null">— Без категории —</option>
                <option v-for="category in categories" :key="category.id" :value="Number(category.id)">
                  {{ category.name }}
                </option>
              </select>
            </div>

            <!-- Теги -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Теги</label>
              <select
                  v-model="form.tag_ids"
                  multiple
                  class="w-full border-gray-300 rounded-md shadow-sm px-3 py-2 border h-32"
              >
                <option v-for="tag in $page.props.tags" :key="tag.id" :value="Number(tag.id)">
                  {{ tag.name }}
                </option>
              </select>
            </div>

            <!-- Длительность -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Длительность</label>
              <input
                  v-model="form.duration"
                  type="text"
                  class="w-full border-gray-300 rounded-md shadow-sm px-3 py-2 border"
              />
            </div>

            <!-- Превью картинка -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Картинка анонса</label>
              <div v-if="existingPreviewUrl && !previewImageUrl" class="mb-2">
                <img :src="existingPreviewUrl" class="h-24 w-auto rounded-md object-cover border" />
              </div>
              <div v-if="previewImageUrl" class="mb-2 relative inline-block">
                <img :src="previewImageUrl" class="h-24 w-auto rounded-md object-cover border" />
                <button
                    type="button"
                    @click="removePreviewImage"
                    class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs"
                >
                  ×
                </button>
              </div>
              <input
                  @input="handlePreviewImageChange"
                  type="file"
                  accept="image/*"
                  class="w-full text-sm text-gray-500 border border-gray-300 rounded-md px-3 py-2"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Содержание (оглавление)
                <span class="text-xs font-normal text-gray-500 ml-1">Генерируется автоматически</span>
              </label>

              <div class="bg-gray-50 border border-gray-200 rounded-md p-3 min-h-[100px]">

                <!-- 🔹 Предпросмотр авто-содержания -->
                <div v-if="previewToc.length" class="prose prose-sm max-w-none">
                  <ul class="list-disc pl-5 space-y-1">
                    <li
                        v-for="(item, idx) in previewToc"
                        :key="idx"
                        class="text-gray-700"
                    >
                      <a :href="'#' + item.anchor" class="hover:text-emerald-700 transition-colors">
                        {{ item.text }}
                      </a>
                    </li>
                  </ul>
                </div>

                <!-- Пустое состояние -->
                <p v-else class="text-sm text-gray-400 italic">
                  Нет заголовков (h2-h5) в контенте
                </p>
              </div>
            </div>

            <!-- Редактор контента -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Режим редактирования</label>
              <div class="inline-flex rounded-md shadow-sm border border-gray-300">
                <button type="button" @click="editorMode = 'visual'" :class="{'bg-blue-600 text-white': editorMode === 'visual'}" class="px-4 py-2 text-sm">Визуальный</button>
                <button type="button" @click="editorMode = 'html'" :class="{'bg-blue-600 text-white': editorMode === 'html'}" class="px-4 py-2 text-sm">HTML</button>
                <button type="button" @click="editorMode = 'text'" :class="{'bg-blue-600 text-white': editorMode === 'text'}" class="px-4 py-2 text-sm">Текст</button>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Контент *</label>
              <div v-if="editorMode === 'visual'" class="border border-gray-300 rounded-md overflow-hidden">
                <TiptapEditor v-model="form.content" />
              </div>
              <textarea v-else-if="editorMode === 'html'" v-model="form.content" class="w-full border-gray-300 rounded-md px-3 py-2 font-mono text-sm min-h-[400px]"></textarea>
              <textarea v-else v-model="form.content" class="w-full border-gray-300 rounded-md px-3 py-2 min-h-[400px]"></textarea>
            </div>

            <!-- FAQ -->
            <div class="mt-6 border-t border-gray-200 pt-6">
              <label class="block text-sm font-medium text-gray-700 mb-2">Вопросы и ответы (FAQ)</label>
              <div class="space-y-4">
                <div
                    v-for="(faq, index) in form.faqs"
                    :key="index"
                    class="p-4 bg-gray-50 border border-gray-200 rounded-md relative space-y-3"
                >
                  <button
                      type="button"
                      @click="removeFaq(index)"
                      class="absolute top-2 right-2 text-red-500 hover:text-red-700 font-bold text-lg"
                  >
                    ×
                  </button>
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Вопрос №{{ index + 1 }}</label>
                    <input v-model="faq.question" type="text" class="w-full border-gray-300 rounded-md px-3 py-2 border text-sm" />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Ответ</label>
                    <textarea v-model="faq.answer" rows="2" class="w-full border-gray-300 rounded-md px-3 py-2 border text-sm"></textarea>
                  </div>
                </div>
              </div>
              <button type="button" @click="addFaq" class="mt-3 px-4 py-2 bg-emerald-50 text-emerald-700 rounded-md hover:bg-emerald-100 text-sm font-medium">
                + Добавить вопрос-ответ
              </button>
            </div>

            <!-- Источники -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Источники</label>
              <div class="space-y-3">
                <div v-for="(source, index) in form.sources" :key="index" class="flex gap-2 items-start">
                  <div class="flex-1 space-y-2">
                    <input v-model="source.title" type="text" class="w-full border-gray-300 rounded-md px-3 py-2 border text-sm" placeholder="Название" />
                    <input v-model="source.url" type="url" class="w-full border-gray-300 rounded-md px-3 py-2 border text-sm" placeholder="https://..." />
                  </div>
                  <button type="button" @click="removeSource(index)" class="mt-1 px-3 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 text-sm">×</button>
                </div>
              </div>
              <button type="button" @click="addSource" class="mt-3 px-4 py-2 bg-blue-50 text-blue-700 rounded-md hover:bg-blue-100 text-sm font-medium">
                + Добавить источник
              </button>
            </div>

            <!-- Рекомендуемые посты -->
            <div class="mt-8">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">Также рекомендуем</h3>
              <RelatedBlogsSelector
                  v-model="form.related_posts"
                  :categories="categories"
                  :all-blogs="$page.props.allBlogs || []"
                  :current-blog-id="props.blog.id"
              />
            </div>

            <div
                v-if="saveErrorLines.length"
                class="rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800"
                role="alert"
            >
              Статья не сохранилась. {{ saveErrorLines[0] }}<span v-if="saveErrorLines.length > 1"> (ещё {{ saveErrorLines.length - 1 }})</span>
            </div>

            <!-- Кнопки управления -->
            <div class="flex items-center justify-between pt-4 border-t border-gray-100">
              <button type="button" @click="destroy" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700">
                Удалить пост
              </button>
              <div class="flex gap-3">
                <Link :href="route('admin.blog.index')" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-gray-700 text-sm font-medium hover:bg-gray-50">
                  Отмена
                </Link>
                <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                  Сохранить
                </button>
              </div>
            </div>

          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>