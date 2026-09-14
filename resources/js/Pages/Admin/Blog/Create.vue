<script setup>
import { ref, watch, onBeforeUnmount, computed } from 'vue'
import { useForm, Head, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import TiptapEditor from '@/Components/TiptapEditor.vue'
import RelatedBlogsSelector from '@/Components/RelatedBlogsSelector.vue'
import AudienceField from '@/Pages/Admin/Blog/AudienceField.vue'
const page = usePage()
const form = useForm({
  title: '',
  slug: '',
  content: '',
  excerpt: '',
  preview_image: null,
  author_id: '',
  category_id: '',
  duration: '',
  sort_order: 0,
  table_of_contents: '',
  sources: [],
  faqs: [],
  cta_title: '',
  cta_description: '',
  cta_button_text: '',
  cta_button_url: '',
  related_posts: [],
  tag_ids: [],
  seo_title: '',
  seo_description: '',
  seo_keywords: '',
  is_active: true,
  canonical_url: '',
  noindex: false,
  og_title: '',
  og_description: '',
  audience: 'patients',
})

const editorMode = ref('visual') // 'visual' | 'html' | 'text'
const previewImageUrl = ref(null) // Отдельная переменная для превью

const slugManuallyEdited = ref(false)


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
    const text = Array.isArray(msg) ? msg.join(' ') : String(msg)
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


// 🔹 Получаем slug категории по ID
const getCategorySlug = (categoryId) => {
  if (!categoryId) return ''
  const category = page.props.categories?.find(c => c.id == categoryId)
  return category?.slug || ''
}

// 🔹 Генерация слага из заголовка + категории
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
  if (!form.title) return
  if (slugManuallyEdited.value) return // Не трогаем, если редактировали вручную

  const categorySlug = getCategorySlug(form.category_id)
  const titleSlug = slugifyTitle(form.title)

  form.slug = categorySlug ? `${categorySlug}/${titleSlug}` : titleSlug
}
const addFaq = () => {
  form.faqs.push({ question: '', answer: '' })
}

const removeFaq = (index) => {
  form.faqs.splice(index, 1)
}
// 🔹 Watch на заголовок — автогенерация slug
watch(() => form.title, () => {
  generateSlug()
})

// 🔹 Watch на категорию — перегенерация slug с новым префиксом
watch(() => form.category_id, () => {
  if (!slugManuallyEdited.value) {
    generateSlug()
  }
})

// 🔹 Watch на ручное редактирование slug
watch(() => form.slug, (newSlug, oldSlug) => {
  // Если slug изменился не из-за автогенерации — помечаем как ручной
  if (oldSlug && newSlug !== oldSlug) {
    const categorySlug = getCategorySlug(form.category_id)
    const expectedSlug = categorySlug
        ? `${categorySlug}/${slugifyTitle(form.title)}`
        : slugifyTitle(form.title)

    if (newSlug !== expectedSlug) {
      slugManuallyEdited.value = true
    }
  }
})

// 🔹 В <script setup> добавь переменную для отображения пропорций:
const previewRatio = ref('') // "1117×494px (пропорция 2.26:1)"

// 🔹 Новая функция обработки превью
const handlePreviewImageChange = async (event) => {
  const file = event.target.files?.[0]
  if (!file) return

  // 🔹 JPEG/PNG → WebP; GIF keep as GIF; already-WebP keep
  let processedFile = file
  const isGif = file.type === 'image/gif' || /\.gif$/i.test(file.name || '')
  if (file.type !== 'image/webp' && !isGif) {
    processedFile = await convertToWebP(file, 1117, 494)
  }

  form.preview_image = processedFile || null

  // Очищаем старый URL
  if (previewImageUrl.value && window.URL) {
    URL.revokeObjectURL(previewImageUrl.value)
  }

  // Создаём превью для отображения
  if (processedFile && typeof window !== 'undefined' && window.URL) {
    previewImageUrl.value = URL.createObjectURL(processedFile)

    // 🔹 Считаем и показываем пропорции
    const img = new Image()
    img.onload = () => {
      const ratio = (img.width / img.height).toFixed(2)
      previewRatio.value = `${img.width}×${img.height}px (пропорция ${ratio}:1)`
    }
    img.src = URL.createObjectURL(processedFile)
  } else {
    previewImageUrl.value = null
    previewRatio.value = ''
  }

  event.target.value = ''
}

// 🔹 Вспомогательная функция конвертации в WebP
const convertToWebP = (file, maxWidth, maxHeight) => {
  return new Promise((resolve) => {
    const img = new Image()
    const reader = new FileReader()

    reader.onload = (e) => {
      img.onload = () => {
        // Вычисляем пропорциональные размеры
        let width = img.width
        let height = img.height

        if (width > maxWidth || height > maxHeight) {
          const ratio = Math.min(maxWidth / width, maxHeight / height)
          width = Math.round(width * ratio)
          height = Math.round(height * ratio)
        }

        // Создаём canvas и рисуем
        const canvas = document.createElement('canvas')
        canvas.width = width
        canvas.height = height
        const ctx = canvas.getContext('2d')
        ctx.drawImage(img, 0, 0, width, height)

        // Конвертируем в WebP
        canvas.toBlob((blob) => {
          if (blob) {
            const webpFile = new File([blob], file.name.replace(/\.[^.]+$/, '') + '.webp', {
              type: 'image/webp',
              lastModified: Date.now()
            })
            resolve(webpFile)
          } else {
            resolve(file) // Если не получилось — отдаём оригинал
          }
        }, 'image/webp', 0.85) // Качество 85%
      }
      img.src = e.target.result
    }
    reader.readAsDataURL(file)
  })
}
const previewToc = computed(() => {
  if (!form.content) return []

  const parser = new DOMParser()
  const doc = parser.parseFromString(form.content, 'text/html')

  // 🔹 Ищем ТОЛЬКО h2
  const headings = doc.querySelectorAll('h2')

  return Array.from(headings).map(h => {
    // Берём только текстовые узлы (игнорируем вложенные теги вроде <span>, <strong>)
    const text = Array.from(h.childNodes)
        .filter(node => node.nodeType === Node.TEXT_NODE)
        .map(node => node.textContent.trim())
        .filter(text => text)
        .join(' ') || h.textContent?.trim() || ''

    if (!text) return null

    const anchor = text.toLowerCase()
        .replace(/[^\w\s-а-яА-ЯёЁ]/g, '')
        .replace(/[\s_]+/g, '-')
        .replace(/^-+|-+$/g, '')

    // Уровень всегда 2, так как мы берем только h2
    return { text, anchor, level: 2 }
  }).filter(item => item !== null)
})
const submit = () => {
  // 1. Подготавливаем контент в зависимости от режима
  let finalContent = form.content
  if (editorMode.value === 'text') {
    finalContent = `<p>${finalContent.replace(/\n/g, '<br>')}</p>`
  }

  // 2. Генерируем содержание (TOC)
  const tocResult = previewToc.value

  // 3. Записываем обработанные данные напрямую в объект form
  form.content = finalContent
  form.table_of_contents = JSON.stringify(tocResult, null, 2)

  dropEmptyRows()
  saveErrorLines.value = []

  // 4. Отправляем форму напрямую (Inertia сама применит FormData, так как есть файл)
  form.post(route('admin.blog.store'), {
    preserveScroll: true,
    onError: (errors) => {
      showSaveErrors(errors)
    },
    onSuccess: () => {
      saveErrorLines.value = []
    },
  })
}

// Логика переключения режимов редактора
watch(editorMode, (newMode, oldMode) => {
  // При переходе из визуального в текст — убираем HTML-теги
  if (oldMode === 'visual' && newMode === 'text') {
    form.content = form.content.replace(/<[^>]*>/g, '').trim()
  }
  // При переходе из текста в визуальный — оборачиваем в <p>, если нет тегов
  if (oldMode === 'text' && newMode === 'visual') {
    if (form.content && !form.content.includes('<')) {
      form.content = `<p>${form.content.replace(/\n/g, '<br>')}</p>`
    }
  }
})

// Очистка ресурсов при уходе со страницы
onBeforeUnmount(() => {
  // Очищаем ObjectURL, чтобы не было утечек памяти
  if (previewImageUrl.value && window.URL) {
    URL.revokeObjectURL(previewImageUrl.value)
  }
})


form.sources = []

// Добавить источник
const addSource = () => {
  form.sources.push({ title: '', url: '' })
}

// Удалить источник
const removeSource = (index) => {
  form.sources.splice(index, 1)
}
</script>

<template>
  <Head title="Новый пост" />

  <AdminLayout>
    <div class="py-12">
      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white shadow rounded-lg p-6">
          <h2 class="text-2xl font-bold mb-6 text-gray-800">Новый Блог</h2>

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



            <!-- 🔹 SEO настройки (аккордеон) -->
            <details class="mt-6 border border-gray-200 rounded-lg bg-gray-50">
              <summary class="px-4 py-3 cursor-pointer font-medium text-gray-700 hover:text-gray-900 flex items-center justify-between">
                <span>SEO настройки</span>
                <span class="text-sm text-gray-400">Нажмите, чтобы раскрыть</span>
              </summary>

              <div class="px-4 pb-4 space-y-4">

                <!-- SEO Title -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">
                    SEO Title <span class="text-xs font-normal text-gray-500">(заголовок для поисковиков)</span>
                  </label>
                  <input
                      v-model="form.seo_title"
                      type="text"
                      class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border"
                      :placeholder="form.title || 'По умолчанию = заголовок поста'"
                      maxlength="70"
                  />
                  <p class="text-xs text-gray-500 mt-1">
                    {{ form.seo_title?.length || 0 }}/70 символов. Рекомендуется 50-60.
                  </p>
                </div>

                <!-- SEO Description -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">
                    SEO Description <span class="text-xs font-normal text-gray-500">(описание для сниппета)</span>
                  </label>
                  <textarea
                      v-model="form.seo_description"
                      rows="3"
                      class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border"
                      placeholder="Краткое описание поста для поисковой выдачи..."
                      maxlength="160"
                  ></textarea>
                  <p class="text-xs text-gray-500 mt-1">
                    {{ form.seo_description?.length || 0 }}/160 символов. Рекомендуется 150-160.
                  </p>
                </div>

                <!-- SEO Keywords -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">
                    SEO Keywords <span class="text-xs font-normal text-gray-500">(ключевые слова)</span>
                  </label>
                  <input
                      v-model="form.seo_keywords"
                      type="text"
                      class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border"
                      placeholder="аллергия, тест, диагностика, здоровье (через запятую)"
                      maxlength="500"
                  />
                  <p class="text-xs text-gray-500 mt-1">
                    Вводите через запятую. Максимум 10-15 ключевых слов.
                  </p>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">OG Title</label>
                  <input
                      v-model="form.og_title"
                      type="text"
                      class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border"
                      placeholder="Пусто = взять SEO Title"
                      maxlength="255"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">OG Description</label>
                  <textarea
                      v-model="form.og_description"
                      rows="3"
                      class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border"
                      placeholder="Пусто = взять SEO Description"
                      maxlength="500"
                  ></textarea>
                </div>

              </div>
            </details>
            <div class="mt-6 p-4 bg-gray-50 rounded-lg border border-gray-200 space-y-4">
              <div class="flex items-center gap-2">
                <input
                    type="checkbox"
                    id="is_active"
                    v-model="form.is_active"
                    class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
                />
                <label for="is_active" class="text-sm font-medium text-gray-700">
                  Опубликовать сразу (Дата публикации будет установлена автоматически)
                </label>
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
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Canonical URL <span class="text-xs text-gray-500">(необязательно)</span>
                </label>
                <input
                    v-model="form.canonical_url"
                    type="url"
                    class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                    placeholder="https://example.com/original-article"
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
                    class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border"
                    placeholder="Введите заголовок"
                    required
                />
                <div v-if="form.errors.title" class="text-red-500 text-xs mt-1">{{ form.errors.title }}</div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Символьный код (ЧПУ)</label>
                <input
                    v-model="form.slug"
                    type="text"
                    class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border"
                    placeholder="auto-generate"
                />
                <div v-if="form.errors.slug" class="text-red-500 text-xs mt-1">{{ form.errors.slug }}</div>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Текст анонса</label>
              <textarea
                  v-model="form.excerpt"
                  rows="3"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border"
                  placeholder="Краткое описание поста для отображения в списке..."
                  maxlength="500"
              ></textarea>
              <p class="text-xs text-gray-500 mt-1">
                {{ form.excerpt?.length || 0 }}/500 символов. Отображается в превью поста.
              </p>
              <div v-if="form.errors.excerpt" class="text-red-500 text-xs mt-1">{{ form.errors.excerpt }}</div>
            </div>
            <!-- Выбор автора -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Автор</label>
              <select
                  v-model="form.author_id"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border"
              >
                <option :value="null">Я ({{ $page.props.auth.user?.name }})</option>
                <option v-for="author in $page.props.authors" :key="author.id" :value="author.id">
                  {{ author.name }}
                </option>
              </select>
              <p class="text-xs text-gray-500 mt-1">Если не выбран — пост будет за вами</p>
            </div>




            <!-- Категория -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Категория</label>
              <select
                  v-model="form.category_id"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border"
              >
                <option :value="null">— Без категории —</option>
                <option v-for="category in page.props.categories" :key="category.id" :value="category.id">
                  {{ category.name }}
                </option>
              </select>
              <p class="text-xs text-gray-500 mt-1">
                <Link :href="route('admin.categories.create')" class="text-blue-600 hover:underline">
                  + Создать новую категорию
                </Link>
              </p>
              <div v-if="form.errors.category_id" class="text-red-500 text-xs mt-1">{{ form.errors.category_id }}</div>
            </div>


            <!-- 🔹 Теги (множественный выбор) -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Теги</label>
              <select
                  v-model="form.tag_ids"
                  multiple
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border h-32"
              >
                <option
                    v-for="tag in $page.props.tags"
                    :key="tag.id"
                    :value="Number(tag.id)"
                >
                  {{ tag.name }}
                </option>
              </select>
              <p class="text-xs text-gray-500 mt-1">
                Удерживайте <b>Ctrl</b> (или <b>Cmd</b> на Mac) + клик для выбора нескольких тегов.
                <Link :href="route('admin.blog-tags.create')" class="text-blue-600 hover:underline ml-1">
                  + Создать новый тег
                </Link>
                <span v-if="form.tag_ids.length" class="block mt-1 text-blue-600">
      Выбрано тегов: {{ form.tag_ids.length }}
    </span>
              </p>
              <div v-if="form.errors.tag_ids" class="text-red-500 text-xs mt-1">{{ form.errors.tag_ids }}</div>
            </div>


            <!-- Превью изображение -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Картинка анонса</label>
              <input
                  @input="handlePreviewImageChange"
                  type="file"
                  accept="image/*"
                  class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-gray-300 rounded-md px-3 py-2"
              />
              <!-- Превью выбранного файла (через previewImageUrl) -->
              <div v-if="previewImageUrl" class="mt-2">
                <img :src="previewImageUrl" class="h-24 w-auto rounded-md object-cover border" />
              </div>
              <div v-if="previewRatio" class="text-xs text-gray-500 mt-1 flex items-center gap-1">
                {{ previewRatio }}
              </div>
              <div v-if="form.errors.preview_image" class="text-red-500 text-xs mt-1">{{ form.errors.preview_image }}</div>
            </div>
            <!-- 🔹 Содержание (авто-генерация) -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Содержание (оглавление)
                <span class="text-xs font-normal text-gray-500 ml-1">Генерируется автоматически из заголовков</span>
              </label>

              <div class="bg-gray-50 border border-gray-200 rounded-md p-3 min-h-[100px]">
                <p class="text-sm text-gray-600">
                  Якорные ссылки добавляются автоматически.<br>
                  Чтобы изменить — правь заголовки в контенте.
                </p>

                <!-- 🔹 Предпросмотр авто-содержания (если контент есть) -->
                <div v-if="form.content" class="mt-3 prose prose-sm max-w-none">
                  <ul class="list-disc pl-5 space-y-1 text-gray-700">
                    <li v-for="(item, idx) in previewToc" :key="idx" :style="{ marginLeft: (item.level - 2) * 16 + 'px' }">
                      <a :href="'#' + item.anchor" class="hover:text-emerald-700 transition-colors">
                        {{ item.text }}
                      </a>
                    </li>
                  </ul>
                </div>
              </div>

              <!-- 🔹 Скрытое поле для отправки данных на сервер -->
              <input type="hidden" v-model="form.table_of_contents" />

              <p class="text-xs text-gray-500 mt-1">
                Не редактируется вручную. Обновляется при сохранении поста.
              </p>
            </div>
            <!-- Переключатель режима редактора -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Режим редактирования контента</label>
              <div class="inline-flex rounded-md shadow-sm border border-gray-300" role="group">
                <button
                    type="button"
                    @click="editorMode = 'visual'"
                    :class="{
                    'bg-blue-600 text-white border-blue-600': editorMode === 'visual',
                    'bg-white text-gray-700 hover:bg-gray-50 border-transparent': editorMode !== 'visual'
                  }"
                    class="px-4 py-2 text-sm font-medium rounded-l-md border-r border-gray-200 focus:z-10 focus:ring-1 focus:ring-blue-500"
                >
                  Визуальный
                </button>
                <button
                    type="button"
                    @click="editorMode = 'html'"
                    :class="{
                    'bg-blue-600 text-white border-blue-600': editorMode === 'html',
                    'bg-white text-gray-700 hover:bg-gray-50 border-transparent': editorMode !== 'html'
                  }"
                    class="px-4 py-2 text-sm font-medium border-r border-gray-200 focus:z-10 focus:ring-1 focus:ring-blue-500"
                >
                  HTML
                </button>
                <button
                    type="button"
                    @click="editorMode = 'text'"
                    :class="{
                    'bg-blue-600 text-white border-blue-600': editorMode === 'text',
                    'bg-white text-gray-700 hover:bg-gray-50 border-transparent': editorMode !== 'text'
                  }"
                    class="px-4 py-2 text-sm font-medium rounded-r-md focus:z-10 focus:ring-1 focus:ring-blue-500"
                >
                  Текст
                </button>
              </div>
            </div>

            <!-- Область редактора (динамическая) -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Контент *</label>

              <!-- 1. Визуальный редактор (TipTap) -->
              <div v-if="editorMode === 'visual'" class="border border-gray-300 rounded-md overflow-hidden">
                <TiptapEditor v-model="form.content" />
              </div>

              <!-- 2. HTML редактор -->
              <textarea
                  v-else-if="editorMode === 'html'"
                  v-model="form.content"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 font-mono text-sm min-h-[400px]"
                  placeholder="<p>Введите ваш HTML код...</p>"
              ></textarea>

              <!-- 3. Простой текст -->
              <textarea
                  v-else
                  v-model="form.content"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 min-h-[400px]"
                  placeholder="Введите обычный текст..."
              ></textarea>

              <div v-if="form.errors.content" class="text-red-500 text-xs mt-1">{{ form.errors.content }}</div>
            </div>

            <!-- 🔹 ПОЛЕ "Вопросы и ответы (FAQ)" -->
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
                      title="Удалить вопрос"
                  >
                    ×
                  </button>

                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Вопрос №{{ index + 1 }}</label>
                    <input
                        v-model="faq.question"
                        type="text"
                        class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                        placeholder="Например: Как подготовиться к сдаче анализа?"
                    />
                  </div>

                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Ответ</label>
                    <textarea
                        v-model="faq.answer"
                        rows="2"
                        class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                        placeholder="Подробный ответ на вопрос..."
                    ></textarea>
                  </div>
                </div>
              </div>

              <button
                  type="button"
                  @click="addFaq"
                  class="mt-3 px-4 py-2 bg-emerald-50 text-emerald-700 rounded-md hover:bg-emerald-100 text-sm font-medium flex items-center gap-1"
              >
                + Добавить вопрос-ответ
              </button>

              <p class="text-xs text-gray-500 mt-2">
                Блок выводится на странице статьи в виде аккордеона и генерирует поисковую микроразметку FAQPage.
              </p>
            </div>
            <!-- 🔹 ПОЛЕ "ИСТОЧНИКИ" (динамический список) -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Источники</label>

              <div class="space-y-3">
                <div
                    v-for="(source, index) in form.sources"
                    :key="index"
                    class="flex gap-2 items-start"
                >
                  <div class="flex-1 space-y-2">
                    <input
                        v-model="source.title"
                        type="text"
                        class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                        placeholder="Название источника"
                    />
                    <input
                        v-model="source.url"
                        type="url"
                        class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                        placeholder="https://example.com (необязательно)"
                    />
                  </div>
                  <button
                      type="button"
                      @click="removeSource(index)"
                      class="mt-1 px-3 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 text-sm"
                  >
                    ×
                  </button>
                </div>
              </div>

              <button
                  type="button"
                  @click="addSource"
                  class="mt-3 px-4 py-2 bg-blue-50 text-blue-700 rounded-md hover:bg-blue-100 text-sm font-medium"
              >
                + Добавить источник
              </button>

              <p class="text-xs text-gray-500 mt-2">Добавьте источники с названиями и ссылками (если есть)</p>
            </div>



            <div class="mt-8">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">Также рекомендуем</h3>

              <RelatedBlogsSelector
                  v-model="form.related_posts"
                  :categories="page.props.categories || []"
                  :all-blogs="$page.props.allBlogs || []"
                  :current-blog-id="null"
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
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
              <a
                  :href="route('admin.blog.index')"
                  class="px-4 py-2 bg-white border border-gray-300 rounded-md text-gray-700 text-sm font-medium hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              >
                Отмена
              </a>
              <button
                  type="submit"
                  :disabled="form.processing"
                  class="px-4 py-2 bg-blue-600 border border-transparent rounded-md text-white text-sm font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                <span v-if="form.processing" class="flex items-center gap-2">
                  <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  Сохранение...
                </span>
                <span v-else>Опубликовать</span>
              </button>
            </div>

          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>