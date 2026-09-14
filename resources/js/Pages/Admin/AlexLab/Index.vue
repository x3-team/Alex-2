<script setup>
import { ref, computed } from 'vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
// Импортируем наш кастомный редактор, если он лежит рядом, или стандартный TiptapEditor
import TiptapEditor from '@/Components/TiptapEditor.vue'

const props = defineProps({
  // Данные приходят с бэкенда (см. контроллер ниже)
  settings: Object,
})

const activeTab = ref('about') // 'about', 'licenses', 'contacts'
const isEditingSeo = ref(false)
// Форма для сохранения всех настроек одним запросом
const form = useForm({
  about_content: props.settings?.about_content || '',
  licenses: props.settings?.licenses || [],
  contacts: {
    address: props.settings?.contacts?.address || '',
    phone: props.settings?.contacts?.phone || '',
    email: props.settings?.contacts?.email || '',
    work_hours: props.settings?.contacts?.work_hours || ['ПН-ПТ 9:00-19:00', 'СБ-ВС Выходной'],
    map_coords: props.settings?.contacts?.map_coords || '',
    inn: props.settings?.contacts?.inn || '',
    ogrn: props.settings?.contacts?.ogrn || '',
    kpp: props.settings?.contacts?.kpp || '',
    socials: props.settings?.contacts?.socials || [],
  },
  privacy_policy_content: props.settings?.privacy_policy_content || '',
  consent_content: props.settings?.consent_content || '',
  seo: {
    meta_title: props.settings?.seo?.meta_title || '',
    meta_description: props.settings?.seo?.meta_description || '',
    meta_keywords: props.settings?.seo?.meta_keywords || '',
  }
})
const saveSeoSettings = () => {
  form.put(route('admin.alex-lab.update'), {
    preserveScroll: true,
    onSuccess: () => {
      isEditingSeo.value = false
    }
  })
}
// Управление лицензиями
const addLicense = () => {
  form.licenses.push({ title: '', file_path: '' })
}

const removeLicense = (index) => {
  form.licenses.splice(index, 1)
}

const handleLicenseFileUpload = async (event, index) => {
  const file = event.target.files[0]
  if (!file) {
    alert('Файл не выбран')
    return
  }

  // 🔹 Проверка размера файла (максимум 10MB)
  const maxSize = 10 * 1024 * 1024
  if (file.size > maxSize) {
    alert(`Файл слишком большой. Максимум: 10MB, ваш файл: ${(file.size / 1024 / 1024).toFixed(2)}MB`)
    event.target.value = ''
    return
  }

  // 🔹 Проверка типа файла
  const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png']
  if (!allowedTypes.includes(file.type)) {
    alert(`Недопустимый тип файла. Разрешены: PDF, JPG, PNG`)
    event.target.value = ''
    return
  }

  const formData = new FormData()
  formData.append('file', file)

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
  if (!csrfToken) {
    alert('Ошибка: CSRF-токен не найден. Обновите страницу (Ctrl+F5).')
    return
  }

  try {
    // 🔹 НЕ указываем Content-Type — axios установит автоматически с boundary
    const response = await axios.post(
        route('admin.alex-lab.upload-license'),
        formData,
        {
          headers: {
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          }
        }
    )

    form.licenses[index].file_path = response.data.path
    form.licenses[index].title = response.data.original_name || file.name

    alert('✅ Файл успешно загружен!')

  } catch (error) {
    console.error('Ошибка загрузки:', error)

    if (error.response) {
      const status = error.response.status
      const data = error.response.data

      if (status === 422 && data.errors) {
        const messages = Object.values(data.errors).flat().join('\n')
        alert(`❌ Ошибка валидации:\n${messages}`)
      } else if (status === 413) {
        alert('❌ Файл слишком большой для сервера. Максимум: 10MB')
      } else if (status === 419) {
        alert('❌ Сессия истекла. Обновите страницу (Ctrl+F5) и попробуйте снова.')
      } else {
        alert(`❌ Ошибка ${status}: ${data.error || data.message || 'Неизвестная ошибка'}`)
      }
    } else if (error.request) {
      alert('❌ Нет ответа от сервера. Проверьте интернет-соединение.')
    } else {
      alert('❌ Ошибка: ' + error.message)
    }

    event.target.value = ''
  }
}

// Управление соцсетями
const addSocial = () => {
  form.contacts.socials.push({ name: '', url: '' })
}

const removeSocial = (index) => {
  form.contacts.socials.splice(index, 1)
}

const submit = () => {
  form.put(route('admin.alex-lab.update'), {
    onSuccess: () => {
      alert('Настройки сохранены!')
    },
    onError: (errors) => {
      console.error(errors)
      alert('Ошибка сохранения')
    }
  })
}
</script>

<template>
  <Head title="Управление лабораторией" />

  <AdminLayout>
    <div class="py-12">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

        <h1 class="text-2xl font-bold text-gray-800 mb-6">Управление страницей "Лаборатория"</h1>
        <!-- 🔹 SEO БЛОК (Стилизован под пример с авторами) -->
        <div class="mb-6 bg-white rounded-lg shadow p-4 border border-gray-200">
          <div class="flex items-start justify-between mb-2">
            <h3 class="text-lg font-semibold text-gray-800">SEO настройки страницы Лаборатории</h3>
            <button
                v-if="!isEditingSeo"
                @click="isEditingSeo = true"
                type="button"
                class="text-sm text-blue-600 hover:text-blue-800 font-medium"
            >
              Редактировать
            </button>
            <div v-else class="flex gap-2">
              <button
                  type="button"
                  @click="isEditingSeo = false"
                  class="text-sm text-gray-600 hover:text-gray-800"
              >
                Отмена
              </button>
              <button
                  type="button"
                  @click="saveSeoSettings"
                  :disabled="form.processing"
                  class="text-sm text-emerald-600 hover:text-emerald-800 font-medium disabled:opacity-50"
              >
                {{ form.processing ? 'Сохранение...' : 'Сохранить SEO' }}
              </button>
            </div>
          </div>

          <!-- Форма редактирования SEO -->
          <div v-if="isEditingSeo" class="space-y-3 mt-4 border-t pt-3 border-gray-100">
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Meta Title</label>
              <input
                  v-model="form.seo.meta_title"
                  type="text"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                  placeholder="Лаборатория — ALEX LAB"
                  maxlength="255"
              />
              <p class="text-xs text-gray-500 mt-1 text-right">{{ form.seo.meta_title?.length || 0 }}/255</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Meta Description</label>
              <textarea
                  v-model="form.seo.meta_description"
                  rows="2"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                  placeholder="Описание лаборатории для поисковой выдачи..."
                  maxlength="500"
              ></textarea>
              <p class="text-xs text-gray-500 mt-1 text-right">{{ form.seo.meta_description?.length || 0 }}/500</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1">Meta Keywords</label>
              <input
                  v-model="form.seo.meta_keywords"
                  type="text"
                  class="w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm px-3 py-2 border text-sm"
                  placeholder="лаборатория, анализы, аллергопробы (через запятую)"
                  maxlength="500"
              />
              <p class="text-xs text-gray-500 mt-1 text-right">{{ form.seo.meta_keywords?.length || 0 }}/500</p>
            </div>
          </div>

          <!-- Предпросмотр SEO -->
          <div v-else class="space-y-2 mt-2">
            <div>
              <span class="text-xs font-medium text-gray-500">Title: </span>
              <span class="text-sm text-gray-700">{{ form.seo.meta_title || '—' }}</span>
            </div>
            <div>
              <span class="text-xs font-medium text-gray-500">Description: </span>
              <span class="text-sm text-gray-700">{{ form.seo.meta_description || '—' }}</span>
            </div>
            <div>
              <span class="text-xs font-medium text-gray-500">Keywords: </span>
              <span class="text-sm text-gray-700">{{ form.seo.meta_keywords || '—' }}</span>
            </div>
          </div>
        </div>
        <!-- Табы навигации -->
        <div class="flex border-b border-gray-200 mb-6">
          <button
              @click="activeTab = 'about'"
              :class="{ 'border-blue-500 text-blue-600': activeTab === 'about', 'border-transparent text-gray-500 hover:text-gray-700': activeTab !== 'about' }"
              class="px-4 py-2 border-b-2 font-medium text-sm focus:outline-none"
          >
            О нас
          </button>
          <button
              @click="activeTab = 'licenses'"
              :class="{ 'border-blue-500 text-blue-600': activeTab === 'licenses', 'border-transparent text-gray-500 hover:text-gray-700': activeTab !== 'licenses' }"
              class="px-4 py-2 border-b-2 font-medium text-sm focus:outline-none"
          >
            Лицензии
          </button>
          <button
              @click="activeTab = 'contacts'"
              :class="{ 'border-blue-500 text-blue-600': activeTab === 'contacts', 'border-transparent text-gray-500 hover:text-gray-700': activeTab !== 'contacts' }"
              class="px-4 py-2 border-b-2 font-medium text-sm focus:outline-none"
          >
            Контакты
          </button>
          <button
              @click="activeTab = 'privacy'"
              :class="{ 'border-blue-500 text-blue-600': activeTab === 'privacy', 'border-transparent text-gray-500 hover:text-gray-700': activeTab !== 'privacy' }"
              class="px-4 py-2 border-b-2 font-medium text-sm focus:outline-none whitespace-nowrap"
          >
            Политика конфиденциальности
          </button>
          <button
              @click="activeTab = 'consent'"
              :class="{ 'border-blue-500 text-blue-600': activeTab === 'consent', 'border-transparent text-gray-500 hover:text-gray-700': activeTab !== 'consent' }"
              class="px-4 py-2 border-b-2 font-medium text-sm focus:outline-none whitespace-nowrap"
          >
            Согласие на обработку ПД
          </button>
        </div>

        <form @submit.prevent="submit">

          <!-- Раздел: О НАС -->
          <div v-if="activeTab === 'about'" class="space-y-6">
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Текст о лаборатории
              </label>
              <p class="text-xs text-gray-500 mb-2">Используйте редактор. Заголовки ограничены уровнем H2.</p>

              <!-- Используем тот же редактор, что и в блоге -->
              <TiptapEditor v-model="form.about_content" />
            </div>
          </div>

          <!-- Раздел: ЛИЦЕНЗИИ -->
          <div v-if="activeTab === 'licenses'" class="space-y-6">
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
              <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Список лицензий</h3>
                <button type="button" @click="addLicense" class="text-sm bg-blue-50 text-blue-600 px-3 py-1 rounded hover:bg-blue-100">
                  + Добавить лицензию
                </button>
              </div>

              <div v-for="(license, index) in form.licenses" :key="index" class="mb-4 p-4 border border-gray-200 rounded-lg bg-gray-50">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <!-- Название лицензии -->
                  <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Название документа</label>
                    <input
                        v-model="license.title"
                        type="text"
                        class="w-full border-gray-300 rounded-md shadow-sm text-sm"
                        placeholder="Например: Лицензия на мед. деятельность"
                    />
                  </div>

                  <!-- Загрузка файла -->
                  <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Файл (PDF/JPG)</label>
                    <input
                        type="file"
                        @change="handleLicenseFileUpload($event, index)"
                        accept=".pdf,.jpg,.jpeg,.png"
                        class="w-full text-sm text-gray-500 file:mr-4 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-blue-50 file:text-blue-700"
                    />
                    <div v-if="license.file_path" class="mt-1 text-xs text-green-600 flex items-center gap-1">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                      Файл загружен
                    </div>
                  </div>
                </div>

                <div class="mt-2 flex justify-end">
                  <button type="button" @click="removeLicense(index)" class="text-xs text-red-500 hover:text-red-700 underline">
                    Удалить строку
                  </button>
                </div>
              </div>

              <div v-if="!form.licenses.length" class="text-center py-8 text-gray-400 text-sm">
                Нет добавленных лицензий
              </div>
            </div>
          </div>

          <!-- Раздел: КОНТАКТЫ -->
          <div v-if="activeTab === 'contacts'" class="space-y-6">
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 space-y-4">
              <h3 class="text-lg font-medium text-gray-900 mb-4">Контактная информация</h3>

              <!-- Адрес -->
              <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Адрес лаборатории</label>
                <input
                    v-model="form.contacts.address"
                    type="text"
                    class="w-full border-gray-300 rounded-md shadow-sm"
                    placeholder="г. Москва, ул. Примерная, д. 1, оф. 101"
                />
              </div>

              <!-- Телефон и Email -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-medium text-gray-500 mb-1">Телефон</label>
                  <input
                      v-model="form.contacts.phone"
                      type="tel"
                      class="w-full border-gray-300 rounded-md shadow-sm"
                      placeholder="+7 (495) 123-45-67"
                  />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500 mb-1">Email</label>
                  <input
                      v-model="form.contacts.email"
                      type="email"
                      class="w-full border-gray-300 rounded-md shadow-sm"
                      placeholder="info@example.com"
                  />
                </div>
              </div>

              <!-- Режим работы -->
              <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Режим работы</label>
                <p class="text-xs text-gray-400 mb-2">Каждая строка — отдельный режим (например: "ПН-ПТ 9:00-19:00")</p>

                <div v-for="(line, index) in form.contacts.work_hours" :key="index" class="flex gap-2 mb-2">
                  <input
                      v-model="form.contacts.work_hours[index]"
                      type="text"
                      class="flex-1 border-gray-300 rounded-md shadow-sm text-sm"
                      placeholder="ПН-ПТ 9:00-19:00"
                  />
                  <button
                      type="button"
                      @click="form.contacts.work_hours.splice(index, 1)"
                      class="text-red-500 hover:text-red-700 px-2"
                  >×</button>
                </div>

                <button
                    type="button"
                    @click="form.contacts.work_hours.push('')"
                    class="text-xs bg-blue-50 text-blue-600 px-2 py-1 rounded hover:bg-blue-100 mt-1"
                >+ Добавить строку</button>
              </div>

              <!-- Координаты -->
              <div class="border-t border-gray-200 pt-4 mt-4">
                <label class="block text-xs font-medium text-gray-500 mb-1">Координаты Яндекс.Карты (Широта, Долгота)</label>
                <input
                    v-model="form.contacts.map_coords"
                    type="text"
                    placeholder="55.7558, 37.6173"
                    class="w-full border-gray-300 rounded-md shadow-sm"
                />
                <p class="text-xs text-gray-400 mt-1">
                  Введите координаты через запятую. Можно взять на
                  <a href="https://yandex.ru/maps/" target="_blank" class="text-blue-500 hover:underline">Яндекс.Картах</a>
                </p>
              </div>

              <!-- Реквизиты -->
              <div class="border-t border-gray-200 pt-4 mt-4">
                <h4 class="text-sm font-medium text-gray-900 mb-3">Реквизиты</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">ИНН</label>
                    <input v-model="form.contacts.inn" type="text" class="w-full border-gray-300 rounded-md shadow-sm" />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">ОГРН</label>
                    <input v-model="form.contacts.ogrn" type="text" class="w-full border-gray-300 rounded-md shadow-sm" />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">КПП</label>
                    <input v-model="form.contacts.kpp" type="text" class="w-full border-gray-300 rounded-md shadow-sm" />
                  </div>
                </div>
              </div>

              <!-- Соцсети -->
              <div class="border-t border-gray-200 pt-4 mt-4">
                <div class="flex justify-between items-center mb-4">
                  <h4 class="text-sm font-medium text-gray-900">Социальные сети</h4>
                  <button type="button" @click="addSocial" class="text-xs bg-blue-50 text-blue-600 px-2 py-1 rounded hover:bg-blue-100">
                    + Добавить ссылку
                  </button>
                </div>

                <div v-for="(social, index) in form.contacts.socials" :key="index" class="flex gap-2 mb-2">
                  <input v-model="social.name" type="text" placeholder="Название (напр. Telegram)" class="w-1/3 border-gray-300 rounded-md shadow-sm text-sm" />
                  <input v-model="social.url" type="url" placeholder="https://..." class="flex-1 border-gray-300 rounded-md shadow-sm text-sm" />
                  <button type="button" @click="removeSocial(index)" class="text-red-500 hover:text-red-700 px-2">×</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Раздел: ПОЛИТИКА КОНФИДЕНЦИАЛЬНОСТИ -->
          <div v-if="activeTab === 'privacy'" class="space-y-6">
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Политика конфиденциальности
              </label>
              <p class="text-xs text-gray-500 mb-2">Используйте редактор для форматирования текста.</p>
              <TiptapEditor v-model="form.privacy_policy_content" />
            </div>
          </div>

          <!-- Раздел: СОГЛАСИЕ НА ОБРАБОТКУ ПД -->
          <div v-if="activeTab === 'consent'" class="space-y-6">
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Согласие на обработку персональных данных
              </label>
              <p class="text-xs text-gray-500 mb-2">Используйте редактор для форматирования текста.</p>
              <TiptapEditor v-model="form.consent_content" />
            </div>
          </div>

          <!-- Кнопка сохранения (общая для всех табов) -->
          <div class="mt-8 flex justify-end">
            <button
                type="submit"
                :disabled="form.processing"
                class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
            >
              Сохранить изменения
            </button>
          </div>

        </form>
      </div>
    </div>
  </AdminLayout>
</template>