<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AudienceSwitch from '@/Components/AudienceSwitch.vue'

const page = usePage()
const visible = computed(() => page.props.site?.switch?.visible === true)
</script>

<template>
  <AudienceSwitch v-if="visible" />
</template>
