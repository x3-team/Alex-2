<script setup>
import { ref } from 'vue'

const props = defineProps({ modelValue: Boolean })
const emit = defineEmits(['update:modelValue', 'insert'])

const rows = ref(3)
const cols = ref(3)
const withHeader = ref(true)

const insert = () => {
  emit('insert', {
    rows: rows.value,
    cols: cols.value,
    withHeaderRow: withHeader.value
  })
  emit('update:modelValue', false)
}
</script>

<template>
  <Teleport to="body">
    <div v-if="modelValue" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50" @click="emit('update:modelValue', false)"></div>
        
        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6 z-10">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Вставить таблицу</h3>
          
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Строк</label>
              <input v-model.number="rows" type="number" min="1" max="20" class="w-full border rounded px-3 py-2" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Столбцов</label>
              <input v-model.number="cols" type="number" min="1" max="10" class="w-full border rounded px-3 py-2" />
            </div>
            <div class="flex items-center">
              <input v-model="withHeader" type="checkbox" id="withHeader" class="rounded text-blue-600" />
              <label for="withHeader" class="ml-2 text-sm text-gray-700">Заголовочная строка</label>
            </div>
          </div>
          
          <div class="mt-6 flex justify-end gap-3">
            <button @click="emit('update:modelValue', false)" class="px-4 py-2 border rounded hover:bg-gray-50">Отмена</button>
            <button @click="insert" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Вставить таблицу</button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>