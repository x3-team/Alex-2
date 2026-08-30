<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import CtaLab from '@/views/CtaLab.vue';
import SwipeLab from '@/views/SwipeLab.vue';
import Error404 from '@/views/Error404.vue';

// Standalone harness. In the real app the 404 view belongs on a catch-all route
// with linkComponent set to RouterLink; the CTA lab is a review page only.
const hash = ref(window.location.hash);
const onHashChange = () => {
  hash.value = window.location.hash;
};

onMounted(() => window.addEventListener('hashchange', onHashChange));
onBeforeUnmount(() => window.removeEventListener('hashchange', onHashChange));

const views = { '#cta': CtaLab, '#swipe': SwipeLab };
const view = computed(() => views[hash.value] ?? Error404);
</script>

<template>
  <component :is="view" />
</template>
