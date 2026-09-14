<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  modelValue: { type: Array, default: () => [] }, // массив ID
  categories: { type: Array, default: () => [] },
  allBlogs: { type: Array, default: () => [] }, // все статьи с категориями
  currentBlogId: { type: Number, default: null },
})

const emit = defineEmits(['update:modelValue'])

// Локальное состояние выбранных ID
const selectedIds = ref(new Set(props.modelValue || []))

// Следим за изменениями извне
watch(() => props.modelValue, (newVal) => {
  selectedIds.value = new Set(newVal || [])
}, { deep: true })

// Группируем статьи по категориям
const blogsByCategory = computed(() => {
  const groups = {}

  // Создаём группы для всех категорий
  props.categories.forEach(cat => {
    groups[cat.id] = {
      category: cat,
      blogs: [],
    }
  })

  // Добавляем группу "Без категории"
  groups['no_category'] = {
    category: { id: 'no_category', name: 'Без категории' },
    blogs: [],
  }

  // Распределяем статьи
  props.allBlogs.forEach(blog => {
    if (props.currentBlogId && blog.id === props.currentBlogId) return

    const categoryId = blog.category_id || 'no_category'
    if (groups[categoryId]) {
      groups[categoryId].blogs.push(blog)
    } else {
      groups['no_category'].blogs.push(blog)
    }
  })

  // Убираем пустые группы
  return Object.values(groups).filter(g => g.blogs.length > 0)
})

// Переключение чекбокса статьи
const toggleBlog = (blogId) => {
  const newSet = new Set(selectedIds.value)
  if (newSet.has(blogId)) {
    newSet.delete(blogId)
  } else {
    newSet.add(blogId)
  }
  selectedIds.value = newSet
  emit('update:modelValue', Array.from(newSet))
}

// Переключение всей категории
const toggleCategory = (categoryId) => {
  const group = blogsByCategory.value.find(g => g.category.id === categoryId)
  if (!group) return

  const newSet = new Set(selectedIds.value)
  const allSelected = group.blogs.every(b => newSet.has(b.id))

  group.blogs.forEach(blog => {
    if (allSelected) {
      newSet.delete(blog.id)
    } else {
      newSet.add(blog.id)
    }
  })

  selectedIds.value = newSet
  emit('update:modelValue', Array.from(newSet))
}

// Проверка, выбрана ли вся категория
const isCategorySelected = (categoryId) => {
  const group = blogsByCategory.value.find(g => g.category.id === categoryId)
  if (!group || group.blogs.length === 0) return false
  return group.blogs.every(b => selectedIds.value.has(b.id))
}

// Проверка, частично ли выбрана категория
const isCategoryPartial = (categoryId) => {
  const group = blogsByCategory.value.find(g => g.category.id === categoryId)
  if (!group || group.blogs.length === 0) return false
  const selectedCount = group.blogs.filter(b => selectedIds.value.has(b.id)).length
  return selectedCount > 0 && selectedCount < group.blogs.length
}

// Сбросить всё
const clearAll = () => {
  selectedIds.value = new Set()
  emit('update:modelValue', [])
}

// Количество выбранных
const selectedCount = computed(() => selectedIds.value.size)
</script>

<template>
  <div class="border border-gray-200 rounded-lg overflow-hidden">
    <!-- Заголовок с счётчиком -->
    <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-b border-gray-200">
      <div class="flex items-center gap-2">
        <span class="text-sm font-medium text-gray-700">Выбрано статей:</span>
        <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
          {{ selectedCount }}
        </span>
      </div>
      <button
          v-if="selectedCount > 0"
          type="button"
          @click="clearAll"
          class="text-xs text-red-600 hover:text-red-800 font-medium"
      >
        Очистить всё
      </button>
    </div>

    <!-- Пустое состояние -->
    <div v-if="blogsByCategory.length === 0" class="px-4 py-8 text-center text-gray-500 text-sm">
      Нет доступных статей для выбора
    </div>

    <!-- Таблица -->
    <table v-else class="w-full">
      <thead class="bg-gray-50 border-b border-gray-200">
      <tr>
        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase w-8">
          ✓
        </th>
        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
          Название статьи
        </th>
        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase w-48">
          Категория
        </th>
      </tr>
      </thead>
      <tbody>
      <template v-for="group in blogsByCategory" :key="group.category.id">
        <!-- Строка категории -->
        <tr class="bg-gray-100 border-b border-gray-200">
          <td class="px-4 py-2">
            <input
                type="checkbox"
                :checked="isCategorySelected(group.category.id)"
                :indeterminate="isCategoryPartial(group.category.id)"
                @change="toggleCategory(group.category.id)"
                class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
            />
          </td>
          <td class="px-4 py-2">
              <span class="text-sm font-semibold text-gray-700">
                {{ group.category.name }}
              </span>
            <span class="ml-2 text-xs text-gray-500">
                ({{ group.blogs.length }})
              </span>
          </td>
          <td class="px-4 py-2"></td>
        </tr>

        <!-- Строки статей -->
        <tr
            v-for="blog in group.blogs"
            :key="blog.id"
            :class="[
              'border-b border-gray-100 transition-colors',
              selectedIds.has(blog.id) ? 'bg-blue-50' : 'hover:bg-gray-50'
            ]"
        >
          <td class="px-4 py-2 pl-8">
            <input
                type="checkbox"
                :checked="selectedIds.has(blog.id)"
                @change="toggleBlog(blog.id)"
                class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
            />
          </td>
          <td class="px-4 py-2">
            <div class="text-sm text-gray-900">{{ blog.title }}</div>
            <div class="text-xs text-gray-500 font-mono truncate max-w-md">
              /{{ blog.slug }}
            </div>
          </td>
          <td class="px-4 py-2">
              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-700">
                {{ group.category.name }}
              </span>
          </td>
        </tr>
      </template>
      </tbody>
    </table>
  </div>
</template>