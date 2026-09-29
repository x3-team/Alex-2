<script setup>
defineProps({
  date: { type: String, default: '' },
  duration: { type: String, default: '' },
  source: { type: String, default: '' },
  category: { type: String, default: '' },
  tag: { type: String, default: '' },
})
</script>

<template>
  <div
    v-if="date || duration || source || category || tag"
    class="blog-feed-meta"
  >
    <div v-if="date" class="bg-white h-[32px] xl:h-[45px] px-3 xl:px-4 flex items-center shadow-md" style="border-radius: 8px">
      <span class="text-[14px] xl:text-[18px] font-[400] text-gray-900">{{ date }}</span>
    </div>
    <div v-if="duration" class="bg-white h-[32px] xl:h-[45px] px-3 xl:px-4 flex items-center shadow-md" style="border-radius: 8px">
      <span class="text-[14px] xl:text-[18px] font-[400] text-gray-900">{{ duration }}</span>
    </div>
    <div v-if="source" class="bg-white h-[32px] xl:h-[45px] px-3 xl:px-4 flex items-center shadow-md" style="border-radius: 8px">
      <span class="text-[14px] xl:text-[18px] font-[400] text-gray-900">{{ source }}</span>
    </div>
    <div v-if="category" class="bg-white h-[32px] xl:h-[45px] px-3 xl:px-4 flex items-center shadow-md" style="border-radius: 8px">
      <span class="text-[14px] xl:text-[18px] font-[400] text-gray-900">{{ category }}</span>
    </div>
    <div v-if="tag" class="bg-white h-[32px] xl:h-[45px] px-3 xl:px-4 flex items-center shadow-md" style="border-radius: 8px">
      <span class="text-[14px] xl:text-[18px] font-[400] text-gray-900">{{ tag }}</span>
    </div>
  </div>
</template>
