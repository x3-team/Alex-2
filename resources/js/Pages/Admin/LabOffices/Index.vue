<script setup>
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  offices: Array,
  cities: Array,
  filters: Object,
  importErrors: Array,
})

const empty = () => ({
  city: '', name: '', address: '', metro: '', phone: '', hours_weekdays: '', hours_weekend: '',
  lat: '', lng: '', lis_code: '', accepts_alex2: true, is_active: true, badge: '', note: '', sort: 0,
})

const editingId = ref(null)
const showForm = ref(false)
const form = useForm(empty())

const filterCity = ref(props.filters?.city || '')
const filterStatus = ref(props.filters?.status || '')

const applyFilters = () => {
  router.get(route('admin.lab-offices.index'), { city: filterCity.value || undefined, status: filterStatus.value || undefined }, { preserveState: true, replace: true })
}

const startCreate = () => {
  editingId.value = null
  form.defaults(empty())
  form.reset()
  form.clearErrors()
  showForm.value = true
}

const startEdit = (office) => {
  editingId.value = office.id
  const data = empty()
  Object.keys(data).forEach((key) => { data[key] = office[key] ?? data[key] })
  form.defaults(data)
  form.reset()
  form.clearErrors()
  showForm.value = true
}

const submit = () => {
  const options = { preserveScroll: true, onSuccess: () => { showForm.value = false } }
  if (editingId.value) {
    form.put(route('admin.lab-offices.update', editingId.value), options)
  } else {
    form.post(route('admin.lab-offices.store'), options)
  }
}

const toggle = (office) => {
  router.post(route('admin.lab-offices.toggle', office.id), {}, { preserveScroll: true })
}

const importForm = useForm({ file: null })
const fileInput = ref(null)
const submitImport = () => {
  importForm.post(route('admin.lab-offices.import'), {
    preserveScroll: true,
    forceFormData: true,
    onFinish: () => {
      importForm.reset()
      if (fileInput.value) fileInput.value.value = ''
    },
  })
}

const fields = [
  { key: 'city', label: 'Город *' },
  { key: 'name', label: 'Название *' },
  { key: 'address', label: 'Адрес *' },
  { key: 'metro', label: 'Метро' },
  { key: 'phone', label: 'Телефон' },
  { key: 'hours_weekdays', label: 'Часы в будни' },
  { key: 'hours_weekend', label: 'Часы в выходные' },
  { key: 'lat', label: 'Широта' },
  { key: 'lng', label: 'Долгота' },
  { key: 'lis_code', label: 'Код в ЛИС' },
  { key: 'badge', label: 'Бейдж (акция)' },
  { key: 'sort', label: 'Порядок' },
]
</script>

<template>
  <Head title="Офисы лаборатории" />
  <AdminLayout>
    <div class="py-12">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <div v-if="$page.props.flash?.success" class="p-4 bg-green-100 text-green-700 rounded-lg">
          {{ $page.props.flash.success }}
        </div>
        <div v-if="$page.props.flash?.error" class="p-4 bg-red-100 text-red-700 rounded-lg">
          {{ $page.props.flash.error }}
          <ul v-if="importErrors?.length" class="mt-2 list-disc list-inside text-sm">
            <li v-for="(err, i) in importErrors" :key="i">{{ err }}</li>
          </ul>
        </div>

        <div class="flex flex-wrap justify-between items-center gap-4">
          <div>
            <h1 class="text-2xl font-bold">Офисы лаборатории</h1>
            <p class="text-sm text-gray-500">Список для выбора офиса в корзине. Офис не удаляется, а выключается.</p>
          </div>
          <button type="button" @click="startCreate" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            + Добавить офис
          </button>
        </div>

        <!-- Импорт -->
        <div class="bg-white shadow rounded-lg p-4 flex flex-wrap items-center gap-4">
          <form @submit.prevent="submitImport" class="flex flex-wrap items-center gap-3">
            <input ref="fileInput" type="file" accept=".csv,text/csv" @input="importForm.file = $event.target.files[0]" class="text-sm" />
            <button type="submit" :disabled="!importForm.file || importForm.processing" class="px-3 py-2 bg-gray-800 text-white rounded text-sm disabled:opacity-50">
              Импортировать CSV
            </button>
          </form>
          <a :href="route('admin.lab-offices.template')" class="text-sm text-blue-600 hover:underline">Скачать шаблон</a>
          <span class="text-xs text-gray-500">Офис с тем же кодом в ЛИС или тем же городом и адресом обновится, остальные не трогаются.</span>
          <p v-if="importForm.errors.file" class="w-full text-sm text-red-600">{{ importForm.errors.file }}</p>
        </div>

        <!-- Форма -->
        <div v-if="showForm" class="bg-white shadow rounded-lg p-6">
          <h2 class="text-lg font-semibold mb-4">{{ editingId ? 'Редактировать офис' : 'Новый офис' }}</h2>
          <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <label v-for="field in fields" :key="field.key" class="block text-sm">
              <span class="text-gray-700">{{ field.label }}</span>
              <input v-model="form[field.key]" type="text" class="mt-1 w-full border-gray-300 rounded" />
              <span v-if="form.errors[field.key]" class="text-red-600 text-xs">{{ form.errors[field.key] }}</span>
            </label>
            <label class="block text-sm md:col-span-3">
              <span class="text-gray-700">Примечание для пациента</span>
              <textarea v-model="form.note" rows="2" class="mt-1 w-full border-gray-300 rounded"></textarea>
              <span v-if="form.errors.note" class="text-red-600 text-xs">{{ form.errors.note }}</span>
            </label>
            <label class="flex items-center gap-2 text-sm">
              <input v-model="form.accepts_alex2" type="checkbox" /> Принимает ALEX2
            </label>
            <label class="flex items-center gap-2 text-sm">
              <input v-model="form.is_active" type="checkbox" /> Активен
            </label>
            <div class="md:col-span-3 flex gap-3">
              <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white rounded disabled:opacity-50">Сохранить</button>
              <button type="button" @click="showForm = false" class="px-4 py-2 border rounded">Отмена</button>
            </div>
          </form>
        </div>

        <!-- Фильтры -->
        <div class="flex flex-wrap gap-3">
          <select v-model="filterCity" @change="applyFilters" class="border-gray-300 rounded text-sm">
            <option value="">Все города</option>
            <option v-for="city in cities" :key="city" :value="city">{{ city }}</option>
          </select>
          <select v-model="filterStatus" @change="applyFilters" class="border-gray-300 rounded text-sm">
            <option value="">Все</option>
            <option value="active">Активные</option>
            <option value="inactive">Выключенные</option>
          </select>
        </div>

        <div class="bg-white shadow rounded-lg overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Город</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Офис</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Часы</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Код ЛИС</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Статус</th>
              <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Действия</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
            <tr v-for="office in offices" :key="office.id" :class="{ 'opacity-50': !office.is_active }">
              <td class="px-4 py-3">{{ office.city }}</td>
              <td class="px-4 py-3">
                <div class="font-medium">{{ office.name }}</div>
                <div class="text-gray-500">{{ office.address }}<span v-if="office.metro"> · м. {{ office.metro }}</span></div>
                <a :href="office.map_url" target="_blank" rel="noopener" class="text-blue-600 hover:underline text-xs">На карте</a>
              </td>
              <td class="px-4 py-3 text-gray-600">
                <div v-if="office.hours_weekdays">Будни: {{ office.hours_weekdays }}</div>
                <div v-if="office.hours_weekend">Выходные: {{ office.hours_weekend }}</div>
              </td>
              <td class="px-4 py-3 font-mono text-xs">{{ office.lis_code || '—' }}</td>
              <td class="px-4 py-3">
                <span v-if="!office.is_active" class="text-gray-500">Выключен</span>
                <span v-else-if="!office.accepts_alex2" class="text-amber-600">Не принимает ALEX2</span>
                <span v-else class="text-green-700">В корзине</span>
              </td>
              <td class="px-4 py-3 text-right space-x-3 whitespace-nowrap">
                <button type="button" @click="startEdit(office)" class="text-blue-600 hover:text-blue-900">Изменить</button>
                <button type="button" @click="toggle(office)" class="text-gray-600 hover:text-gray-900">
                  {{ office.is_active ? 'Выключить' : 'Включить' }}
                </button>
              </td>
            </tr>
            <tr v-if="!offices.length">
              <td colspan="6" class="px-4 py-8 text-center text-gray-500">Офисов пока нет</td>
            </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
