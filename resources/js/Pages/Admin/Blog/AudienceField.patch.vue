<template>
    <fieldset class="admin-audience-field">
        <legend>Аудитория</legend>
        <label>
            <input v-model="modelValueProxy" type="radio" value="patients" />
            Пациенты
        </label>
        <label>
            <input v-model="modelValueProxy" type="radio" value="doctors" />
            Врачи
        </label>
        <p class="admin-audience-hint">
            Публикация видна только на соответствующем контуре (alexallergotest.ru или doc.alexallergotest.ru / /doctors).
        </p>
    </fieldset>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: 'patients' },
});

const emit = defineEmits(['update:modelValue']);

const modelValueProxy = computed({
    get: () => props.modelValue || 'patients',
    set: (value) => emit('update:modelValue', value),
});
</script>
