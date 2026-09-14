<script setup>
import { ref, computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Head, useForm, router } from '@inertiajs/vue3'

const props = defineProps({
  allergensData: {
    type: Array,
    default: () => []
  }
})

const searchQuery = ref('')
const selectedCategory = ref('')
const isModalOpen = ref(false)
const editingId = ref(null)

// Ref для превью загружаемой/текущей иконки
const iconPreview = ref(null)

// Форма Inertia
const form = useForm({
  name: '',
  category: '',
  code: '',
  protein_family: '',
  type: 'E',
  description: '',
  included: true,
  related_ids: [],
  icon: null // 🔹 Добавили поле для файла
})

// Уникальные категории для фильтра
const categories = computed(() => {
  return [...new Set(props.allergensData.map(item => item.category))].filter(Boolean)
})

// Фильтрация
const filteredAllergens = computed(() => {
  return props.allergensData.filter(item => {
    const matchesSearch = item.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
        (item.code && item.code.toLowerCase().includes(searchQuery.value.toLowerCase()))
    const matchesCategory = !selectedCategory.value || item.category === selectedCategory.value
    return matchesSearch && matchesCategory
  })
})

// Обработка выбора файла иконки
const handleIconChange = (e) => {
  const file = e.target.files[0]
  if (file) {
    form.icon = file
    iconPreview.value = URL.createObjectURL(file)
  }
}

// Открыть модалку создания
const openCreateModal = () => {
  editingId.value = null
  iconPreview.value = null
  form.reset()
  form.clearErrors()
  isModalOpen.value = true
}

// Открыть модалку редактирования
const openEditModal = (item) => {
  editingId.value = item.id
  form.clearErrors()
  form.name = item.name
  form.category = item.category
  form.code = item.code || ''
  form.protein_family = item.protein_family || ''
  form.type = item.type || 'E'
  form.description = item.description || ''
  form.included = item.included
  form.related_ids = item.related_ids || []
  form.icon = null

  // 🔹 Если у объекта с сервера уже есть иконка (напр. item.icon_url или item.icon)
  iconPreview.value = item.icon_url ? item.icon_url : (item.icon ? `/storage/${item.icon}` : null)

  isModalOpen.value = true
}

// Закрыть модалку
const closeModal = () => {
  isModalOpen.value = false
  editingId.value = null
  iconPreview.value = null
  form.reset()
}

// Сохранение (Создание или Обновление)
const submitForm = () => {
  if (editingId.value) {
    // РЕДАКТИРОВАНИЕ: используем _method: 'put' для FormData
    form
        .transform((data) => ({
          ...data,
          _method: 'put',
        }))
        .post(route('admin.allergens.update', editingId.value), {
          forceFormData: true,
          onSuccess: () => closeModal(),
        })
  } else {
    // СОЗДАНИЕ: сбрасываем трансформер (чтобы убрать _method) и шлём обычный POST
    form
        .transform((data) => data)
        .post(route('admin.allergens.store'), {
          forceFormData: true,
          onSuccess: () => closeModal(),
        })
  }
}

// Удаление
const deleteItem = (item) => {
  if (confirm(`Вы уверены, что хотите удалить аллерген "${item.name}"?`)) {
    form.delete(route('admin.allergens.destroy', item.id))
  }
}
</script>

<template>
  <Head title="Управление аллергенами" />

  <AdminLayout>
    <div class="p-6 bg-white rounded-lg shadow-sm">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Список аллергенов ALEX2</h1>
        <button
            @click="openCreateModal"
            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition font-medium"
        >
          + Добавить аллерген
        </button>
      </div>

      <!-- Фильтры -->
      <div class="flex flex-col sm:flex-row gap-4 mb-6">
        <input
            v-model="searchQuery"
            type="text"
            placeholder="Поиск по названию или коду (Bet v 1)..."
            class="w-full sm:w-1/2 px-4 py-2 border rounded-md focus:ring focus:ring-blue-200 outline-none"
        />
        <select
            v-model="selectedCategory"
            class="w-full sm:w-1/3 px-4 py-2 border rounded-md focus:ring focus:ring-blue-200 outline-none bg-white"
        >
          <option value="">Все категории</option>
          <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
        </select>
      </div>

      <!-- Таблица -->
      <div class="overflow-x-auto border rounded-lg">
        <table class="w-full text-left text-sm text-gray-600">
          <thead class="bg-gray-50 border-b uppercase text-xs text-gray-500">
          <tr>
            <th class="px-4 py-3">Иконка</th> <!-- 🔹 Новая колонка -->
            <th class="px-4 py-3">Название</th>
            <th class="px-4 py-3">Категория</th>
            <th class="px-4 py-3">Код</th>
            <th class="px-4 py-3">Тип</th>
            <th class="px-4 py-3">Семейство белков</th>
            <th class="px-4 py-3">Связанные</th>
            <th class="px-4 py-3 text-right">Действия</th>
          </tr>
          </thead>
          <tbody>
          <tr v-for="item in filteredAllergens" :key="item.id" class="border-b hover:bg-gray-50">
            <!-- 🔹 Отображение иконки -->
            <td class="px-4 py-3">
              <img
                  v-if="item.icon || item.icon_url"
                  :src="item.icon_url || `/storage/${item.icon}`"
                  :alt="item.name"
                  class="w-8 h-8 object-contain rounded bg-gray-50 p-1 border"
              />
              <div v-else class="w-8 h-8 rounded bg-gray-100 flex items-center justify-center text-xs text-gray-400">
                —
              </div>
            </td>
            <td class="px-4 py-3 font-medium text-gray-900">{{ item.name }}</td>
            <td class="px-4 py-3">{{ item.category }}</td>
            <td class="px-4 py-3"><span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded">{{ item.code || '-' }}</span></td>
            <td class="px-4 py-3">
                <span :class="item.type === 'M' ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800'" class="px-2 py-0.5 rounded text-xs font-semibold">
                  {{ item.type }}
                </span>
            </td>
            <td class="px-4 py-3">{{ item.protein_family || '-' }}</td>
            <td class="px-4 py-3">
              <div class="flex flex-wrap gap-1">
                  <span v-for="rel in item.related" :key="rel" class="bg-blue-50 text-blue-700 text-xs px-2 py-0.5 rounded">
                    {{ rel }}
                  </span>
              </div>
            </td>
            <td class="px-4 py-3 text-right space-x-2">
              <button @click="openEditModal(item)" class="text-blue-600 hover:text-blue-900 font-medium">
                Изменить
              </button>
              <button @click="deleteItem(item)" class="text-red-600 hover:text-red-900 font-medium">
                Удалить
              </button>
            </td>
          </tr>
          <tr v-if="filteredAllergens.length === 0">
            <td colspan="8" class="text-center py-6 text-gray-400">Ничего не найдено</td>
          </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Модальное окно (Создание / Редактирование) -->
    <div v-if="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4 overflow-y-auto">
      <div class="bg-white rounded-lg w-full max-w-2xl p-6 shadow-lg max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center pb-3 border-b mb-4">
          <h3 class="text-lg font-bold text-gray-800">
            {{ editingId ? 'Редактировать аллерген' : 'Новый аллерген' }}
          </h3>
          <button @click="closeModal" class="text-gray-400 hover:text-gray-600 text-xl font-bold">&times;</button>
        </div>

        <form @submit.prevent="submitForm" class="space-y-4">

          <!-- 🔹 Блок загрузки иконки -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Иконка (SVG, PNG)</label>
            <div class="flex items-center gap-4">
              <div v-if="iconPreview" class="w-12 h-12 border rounded-md p-1 flex items-center justify-center bg-gray-50 shrink-0">
                <img :src="iconPreview" class="max-w-full max-h-full object-contain" />
              </div>
              <input
                  type="file"
                  accept="image/*,.svg"
                  @change="handleIconChange"
                  class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer"
              />
            </div>
            <span v-if="form.errors.icon" class="text-xs text-red-500 mt-1 block">{{ form.errors.icon }}</span>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Название *</label>
            <input v-model="form.name" type="text" required class="w-full px-3 py-2 border rounded-md outline-none focus:ring focus:ring-blue-200" />
            <span v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Категория *</label>
              <input v-model="form.category" type="text" required placeholder="Например: Пыльца деревьев" class="w-full px-3 py-2 border rounded-md outline-none focus:ring focus:ring-blue-200" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Код</label>
              <input v-model="form.code" type="text" placeholder="Bet v 1" class="w-full px-3 py-2 border rounded-md outline-none focus:ring focus:ring-blue-200" />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Тип *</label>
              <select v-model="form.type" class="w-full px-3 py-2 border rounded-md outline-none focus:ring focus:ring-blue-200 bg-white">
                <option value="E">Экстракт (E)</option>
                <option value="M">Молекулярный (M)</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Семейство белков</label>
              <input v-model="form.protein_family" type="text" placeholder="PR-10" class="w-full px-3 py-2 border rounded-md outline-none focus:ring focus:ring-blue-200" />
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Связанные аллергены</label>
            <select v-model="form.related_ids" multiple class="w-full px-3 py-2 border rounded-md outline-none focus:ring focus:ring-blue-200 bg-white h-32">
              <option
                  v-for="opt in props.allergensData.filter(a => a.id !== editingId)"
                  :key="opt.id"
                  :value="opt.id"
              >
                {{ opt.name }} ({{ opt.code || opt.category }})
              </option>
            </select>
            <span class="text-xs text-gray-400">Зажмите Ctrl (or Cmd) чтобы выбрать несколько</span>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Описание</label>
            <textarea v-model="form.description" rows="3" class="w-full px-3 py-2 border rounded-md outline-none focus:ring focus:ring-blue-200"></textarea>
          </div>

          <div class="flex items-center gap-2">
            <input v-model="form.included" id="included_check" type="checkbox" class="w-4 h-4 rounded text-blue-600" />
            <label for="included_check" class="text-sm text-gray-700">Входит в ALEX2</label>
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t">
            <button type="button" @click="closeModal" class="px-4 py-2 border rounded-md hover:bg-gray-100 transition">
              Отмена
            </button>
            <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition disabled:opacity-50">
              {{ editingId ? 'Сохранить изменения' : 'Создать' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>