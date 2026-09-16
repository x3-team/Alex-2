<script setup>
import { Link } from '@inertiajs/vue3'
import { useDoctorMode } from '@/composables/useDoctorMode'

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
      <div class="doctor-video-card__badges">
        <span v-if="video.published_at">{{ formatDate(video.published_at) }}</span>
        <span v-if="video.duration">{{ video.duration }}</span>
        <span>{{ video.source_label || 'Видео' }}</span>
      </div>
      <span class="doctor-video-card__play doctor-video-glass-play" aria-hidden="true">
        <img src="/assets/figma-play-20.svg" alt="" width="20" height="20" />
      </span>
    </div>
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
  height: 250px;
  overflow: hidden;
  background: #111;
}

.doctor-video-card__cover {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.doctor-video-card__fallback {
  width: 100%;
  height: 100%;
  background: #1a1a1a;
}

.doctor-video-card__badges {
  position: absolute;
  top: 8px;
  left: 8px;
  right: 8px;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.doctor-video-card__badges span {
  display: inline-flex;
  align-items: center;
  height: 32px;
  padding: 0 12px;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 2px 4px -2px rgba(0, 0, 0, 0.1), 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  font-family: Roboto, Arial, sans-serif;
  font-size: 14px;
  line-height: 1;
  color: #000;
}

.doctor-video-card__play {
  position: absolute;
  top: 50%;
  left: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  margin: -24px 0 0 -24px;
  border-radius: 28px;
}

.doctor-video-card__play img {
  display: block;
  width: 20px;
  height: 20px;
}

.doctor-video-card__body {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 32px 0 0;
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
