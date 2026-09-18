<script setup>
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { useDoctorMode } from '@/composables/useDoctorMode'

const props = defineProps({
  item: { type: Object, required: true },
})

const { doctorsUrl, articleUrl } = useDoctorMode()

const isVideo = computed(() => props.item.type === 'video')

const href = computed(() => (
  isVideo.value
    ? doctorsUrl(`/video/${props.item.slug}`)
    : articleUrl(props.item.slug)
))

const cover = computed(() => props.item.cover || null)

const formattedDate = computed(() => {
  if (!props.item.published_at) return ''
  return new Date(props.item.published_at).toLocaleDateString('ru-RU', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
})

const durationLabel = computed(() => {
  const value = props.item.duration
  if (!value) return ''
  if (!isVideo.value && !String(value).startsWith('~')) {
    return `~${value}`
  }
  return value
})
</script>

<template>
  <article class="bg-[transparent] overflow-hidden">
    <Link :href="href" class="block h-[250px] sm:h-[350px] xl:h-[494px] overflow-hidden relative">
      <img
        v-if="cover"
        :src="cover"
        :alt="item.title"
        class="w-full h-full object-cover"
        style="background-color: rgb(247, 247, 247);"
        loading="lazy"
        decoding="async"
      />
      <div v-else class="w-full h-full bg-gradient-to-br from-gray-400 to-gray-500" />

      <div class="absolute top-2 left-2 xl:top-4 xl:left-4 flex flex-wrap gap-2">
        <div v-if="formattedDate" class="bg-white h-[32px] xl:h-[45px] px-3 xl:px-4 flex items-center shadow-md" style="border-radius: 8px">
          <span class="text-[14px] xl:text-[18px] font-[400] text-gray-900">{{ formattedDate }}</span>
        </div>
        <div v-if="durationLabel" class="bg-white h-[32px] xl:h-[45px] px-3 xl:px-4 flex items-center shadow-md" style="border-radius: 8px">
          <span class="text-[14px] xl:text-[18px] font-[400] text-gray-900">{{ durationLabel }}</span>
        </div>
        <div v-if="isVideo && item.source_label" class="bg-white h-[32px] xl:h-[45px] px-3 xl:px-4 flex items-center shadow-md" style="border-radius: 8px">
          <span class="text-[14px] xl:text-[18px] font-[400] text-gray-900">{{ item.source_label }}</span>
        </div>
        <div v-if="item.category" class="bg-white h-[32px] xl:h-[45px] px-3 xl:px-4 flex items-center shadow-md" style="border-radius: 8px">
          <span class="text-[14px] xl:text-[18px] font-[400] text-gray-900">{{ item.category }}</span>
        </div>
        <div v-if="item.tag" class="bg-white h-[32px] xl:h-[45px] px-3 xl:px-4 flex items-center shadow-md" style="border-radius: 8px">
          <span class="text-[14px] xl:text-[18px] font-[400] text-gray-900">{{ item.tag }}</span>
        </div>
      </div>

      <span v-if="isVideo" class="doctor-feed-play" aria-hidden="true">
        <img src="/assets/figma-play-20.svg" alt="" width="20" height="20" />
      </span>
    </Link>

    <div class="py-[2rem] space-y-3 xl:space-y-4" style="padding-bottom: 64px">
      <component
        :is="item.author?.id ? Link : 'div'"
        v-if="item.author?.id"
        v-bind="item.author?.id ? { href: `/blog/author/${item.author.id}` } : {}"
        class="flex items-center gap-3 xl:gap-4 text-sm text-gray-600"
      >
        <div class="flex items-center gap-2 xl:gap-3">
          <div class="w-[45px] h-[45px] xl:w-[60px] xl:h-[60px] rounded-[8px] xl:rounded-[10px] overflow-hidden bg-white flex-shrink-0">
            <img
              v-if="item.author?.avatar"
              :src="item.author.avatar"
              class="w-full h-full object-cover"
              :alt="item.author?.name"
              loading="lazy"
              decoding="async"
            />
            <div v-else class="w-full h-full bg-emerald-100 flex items-center justify-center text-xl font-semibold text-emerald-700">
              {{ item.author?.name?.charAt(0) || '?' }}
            </div>
          </div>
          <div class="gap-[5px]" :style="{ display: 'flex', flexFlow: 'column' }">
            <span class="font-[400] text-[18px] xl:text-[24px] text-gray-900 block">{{ item.author.name }}</span>
            <span v-if="item.author.role" class="font-[400] text-[16px] xl:text-[18px] text-black opacity-50 block">
              {{ item.author.role }}
            </span>
          </div>
        </div>
      </component>

      <Link :href="href" class="block">
        <h2 class="text-[22px] sm:text-[26px] xl:text-[32px] font-[400] text-gray-900 line-clamp-2" style="line-height: 1">
          {{ item.title }}
        </h2>
        <p v-if="item.description" class="text-black opacity-[0.6] text-[16px] xl:text-[21px] font-[400]" style="margin-top: 8px; line-height: 1.2">
          {{ item.description }}
        </p>
      </Link>
    </div>
  </article>
</template>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.doctor-feed-play {
  position: absolute;
  top: 50%;
  left: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 37px;
  height: 37px;
  margin: -18.5px 0 0 -18.5px;
  border-radius: 18.5px;
  background: #fff;
}

.doctor-feed-play img {
  display: block;
  width: 13px;
  height: 13px;
}
</style>
