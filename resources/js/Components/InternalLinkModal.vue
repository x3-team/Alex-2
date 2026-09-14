<template>
  <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="$emit('update:modelValue', false)">
    <div class="bg-white rounded-xl p-6 w-[500px] max-w-[90vw]">
      <h3 class="text-xl font-bold mb-4">Добавить перелинковку</h3>

      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">Текст</label>
          <input
              v-model="form.text"
              type="text"
              class="w-full border rounded px-3 py-2"
              placeholder="Например: Читайте также: Как выбрать тест на аллергию"
          />
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Ссылка</label>
          <input
              v-model="form.url"
              type="url"
              class="w-full border rounded px-3 py-2"
              placeholder="https://... или /blog/slug"
          />
        </div>
      </div>

      <div class="flex gap-3 mt-6">
        <button @click="$emit('update:modelValue', false)" class="px-4 py-2 border rounded hover:bg-gray-50">Отмена</button>
        <button @click="submit" class="px-4 py-2 bg-emerald-600 text-white rounded hover:bg-emerald-700">Добавить</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({ modelValue: Boolean })
const emit = defineEmits(['update:modelValue', 'insert'])

const form = ref({
  text: '',
  url: ''
})

watch(() => props.modelValue, (val) => {
  if (val) {
    form.value = { text: '', url: '' }
  }
})

const submit = () => {
  emit('insert', { ...form.value })
  emit('update:modelValue', false)
}
</script>