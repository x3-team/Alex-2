<script setup>
import { Link } from '@inertiajs/vue3'
import { useDoctorMode } from '@/composables/useDoctorMode'
import BlogFeedMeta from '@/Components/BlogFeedMeta.vue'

const props = defineProps({
  video: { type: Object, required: true },
})

const { doctorsUrl } = useDoctorMode()

const formatDate = (value) => {
  if (!value) return ''
  return new Date(value).toLocaleDateString('ru-RU', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}
</script>

<template>
  <Link :href="doctorsUrl(`/video/${video.slug}`)" class="doctor-video-card">
    <div class="doctor-video-card__preview">
      <img
        v-if="video.cover"
        class="doctor-video-card__cover"
        :src="video.cover"
        :alt="video.title"
        width="541"
        height="250"
      />
      <div v-else class="doctor-video-card__fallback" />
      <span class="doctor-video-card__play doctor-video-glass-play" aria-hidden="true">
        <img src="/assets/figma-play-20.svg" alt="" width="13" height="13" />
      </span>
    </div>
    <BlogFeedMeta
      :date="formatDate(video.published_at)"
      :duration="video.duration || ''"
      :source="video.source_label || 'Видео'"
    />
    <div class="doctor-video-card__body">
      <h2>{{ video.title }}</h2>
      <p v-if="video.description">{{ video.description }}</p>
    </div>
  </Link>
</template>

<style scoped>
.doctor-video-card {
  display: flex;
  flex-direction: column;
  min-width: 0;
  color: inherit;
  text-decoration: none;
}

.doctor-video-card__preview {
  position: relative;
  width: 100%;
  height: auto;
  aspect-ratio: 2 / 1;
  overflow: hidden;
  background: #f7f7f7;
}

.doctor-video-card__cover {
  width: 100%;
  height: 100%;
  object-fit: contain;
  object-position: center;
  display: block;
  background: #f7f7f7;
}

.doctor-video-card__fallback {
  width: 100%;
  height: 100%;
  background: #1a1a1a;
}

.doctor-video-card__play {
  position: absolute;
  top: 50%;
  left: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  margin: -16px 0 0 -16px;
  border-radius: 16px;
}

.doctor-video-card__play img {
  display: block;
  width: 13px;
  height: 13px;
}

.doctor-video-card__body {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px 0 0;
}

.doctor-video-card__body h2 {
  margin: 0;
  font-family: Roboto, Arial, sans-serif;
  font-size: 21px;
  font-weight: 400;
  line-height: 1.2;
  color: #000;
}

.doctor-video-card__body p {
  margin: 0;
  font-family: Roboto, Arial, sans-serif;
  font-size: 16px;
  line-height: 1.4;
  color: rgba(0, 0, 0, 0.6);
}
</style>
