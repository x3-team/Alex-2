<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import SiteSidebar from '@/Components/SiteSidebar.vue'
import DoctorVideoCard from '@/Components/DoctorVideoCard.vue'
import PublicFooter from '@/Components/PublicFooter.vue'
import { useDoctorMode } from '@/composables/useDoctorMode'
import '../../../../css/main.css'

const props = defineProps({
  video: { type: Object, required: true },
  relatedVideos: { type: Array, default: () => [] },
  relatedArticle: { type: Object, default: null },
  videosMeta: { type: Object, default: () => ({}) },
})

const { doctorsUrl } = useDoctorMode()
const playing = ref(false)

const formatDate = (value) => {
  if (!value) return ''
  return new Date(value).toLocaleDateString('ru-RU', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
}

const startPlayback = () => {
  if (props.video.iframe_src) {
    playing.value = true
  }
}

const goBack = () => {
  if (window.history.length > 1) {
    window.history.back()
    return
  }
  router.visit(doctorsUrl('/video'))
}
</script>

<template>
  <Head>
    <title>{{ videosMeta.title || video.title }}</title>
    <meta name="description" :content="videosMeta.description || video.description || video.title" />
  </Head>

  <main class="page-container site-sidebar-layout doctor-mode doctor-materials-page">
    <SiteSidebar class="doctor-materials-sidebar" :doctor-mode="true" />

    <section class="materials-shell">
      <header class="materials-back-bar">
        <button class="materials-back-button" type="button" aria-label="Назад" @click="goBack">
          <img src="/assets/figma-demo-back.svg" alt="" width="24" height="24" />
          <span>Назад</span>
        </button>
      </header>

      <div class="materials-content">
        <nav class="crumbs" aria-label="Навигация">
          <Link :href="doctorsUrl('/')">Главная</Link>
          <span>/</span>
          <Link :href="doctorsUrl('/video')">Видеолекции</Link>
          <span>/</span>
          <strong>Видеолекция</strong>
        </nav>

        <div class="badges">
          <span v-if="video.published_at">{{ formatDate(video.published_at) }}</span>
          <span v-if="video.duration">{{ video.duration }}</span>
          <span>{{ video.source_label }}</span>
        </div>

        <h1>{{ video.title }}</h1>

        <div class="player">
          <iframe
            v-if="playing && video.iframe_src"
            :src="video.iframe_src"
            title="Видеоплеер"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            allowfullscreen
          />
          <button v-else type="button" class="player-cover" @click="startPlayback">
            <img v-if="video.cover" :src="video.cover" :alt="video.title" width="1115" height="627" />
            <span class="player-source">{{ video.source_label }}</span>
            <span v-if="video.duration" class="player-duration">{{ video.duration }}</span>
            <span class="player-play" aria-hidden="true">
              <img src="/assets/figma-play-32.svg" alt="" width="32" height="32" />
            </span>
          </button>
        </div>

        <p v-if="video.description" class="anons">{{ video.description }}</p>

        <Link
          v-if="relatedArticle"
          :href="doctorsUrl(`/blog/${relatedArticle.slug}`)"
          class="related-article"
        >
          <img v-if="relatedArticle.cover" :src="relatedArticle.cover" alt="" width="140" height="88" />
          <div>
            <p>Статья{{ relatedArticle.duration ? ` · ${relatedArticle.duration}` : '' }}</p>
            <strong>{{ relatedArticle.title }}</strong>
          </div>
        </Link>

        <section v-if="relatedVideos.length" class="related">
          <h2>Материалы по теме</h2>
          <div class="related-grid">
            <DoctorVideoCard v-for="item in relatedVideos" :key="item.id" :video="item" />
          </div>
        </section>
      </div>
      <PublicFooter />
    </section>
  </main>
</template>

<style scoped>
.doctor-materials-page { background: #f5f5f5; }
.materials-shell { min-width: 0; height: 100dvh; overflow-x: hidden; overflow-y: auto; background: #f5f5f5; }
.materials-back-bar { width: 100%; height: 72px; padding: 24px 32px; background: #fff; border: 1px solid #dfdfdf; }
.materials-back-button {
  display: flex; align-items: center; gap: 8px; padding: 0; border: 0; background: transparent;
  color: #000; cursor: pointer; font-family: Helvetica, Arial, sans-serif; font-size: 17px;
}
.materials-back-button img { display: block; width: 24px; height: 24px; }
.materials-content { width: min(1115px, 100%); padding: 64px; display: flex; flex-direction: column; gap: 32px; }
.crumbs { display: flex; flex-wrap: wrap; gap: 8px; font-family: Roboto, Arial, sans-serif; font-size: 16px; color: rgba(0,0,0,0.4); }
.crumbs a { color: rgba(0,0,0,0.4); text-decoration: none; }
.crumbs strong { color: #000; font-weight: 400; }
.badges { display: flex; flex-wrap: wrap; gap: 8px; }
.badges span {
  display: inline-flex; align-items: center; height: 45px; padding: 0 16px; border-radius: 8px;
  background: rgba(0,0,0,0.07); font-family: Roboto, Arial, sans-serif; font-size: 18px;
}
.materials-content h1 { margin: 0; font-family: Roboto, Arial, sans-serif; font-size: 42px; font-weight: 400; line-height: 1.15; }
.player { position: relative; width: 100%; aspect-ratio: 1115 / 627; background: #111; overflow: hidden; }
.player iframe, .player-cover, .player-cover img { width: 100%; height: 100%; border: 0; display: block; object-fit: cover; }
.player-cover { position: relative; padding: 0; border: 0; cursor: pointer; background: #111; }
.player-source, .player-duration {
  position: absolute; display: inline-flex; align-items: center; height: 45px; padding: 0 16px;
  border-radius: 8px; background: #fff; font-family: Roboto, Arial, sans-serif; font-size: 16px;
  box-shadow: 0 2px 4px -2px rgba(0,0,0,0.1), 0 4px 6px -1px rgba(0,0,0,0.1);
}
.player-source { top: 24px; left: 24px; }
.player-duration { right: 24px; bottom: 24px; }
.player-play {
  position: absolute; top: 50%; left: 50%; width: 88px; height: 88px; margin: -44px 0 0 -44px;
  display: flex; align-items: center; justify-content: center; border-radius: 44px; background: #fff;
}
.player-play img { width: 32px; height: 32px; }
.anons { margin: 0; max-width: 916px; font-family: Roboto, Arial, sans-serif; font-size: 21px; line-height: 1.4; }
.related-article {
  display: flex; align-items: center; gap: 24px; padding: 24px; background: #fff; border: 1px solid #dfdfdf;
  color: inherit; text-decoration: none;
}
.related-article img { width: 140px; height: 88px; object-fit: cover; }
.related-article p { margin: 0 0 8px; color: rgba(0,0,0,0.6); font-size: 16px; }
.related-article strong { font-size: 21px; font-weight: 400; }
.related h2 { margin: 0 0 32px; font-family: Roboto, Arial, sans-serif; font-size: 32px; font-weight: 400; }
.related-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 33px; }

@media (max-width: 1024px) {
  .doctor-materials-sidebar { display: none !important; }
  .materials-content { width: 100%; padding: 32px 16px 48px; gap: 24px; }
  .materials-content h1 { font-size: 32px; }
  .related-grid { grid-template-columns: 1fr; }
  .player-play { width: 64px; height: 64px; margin: -32px 0 0 -32px; border-radius: 32px; }
  .player-play img { width: 24px; height: 24px; }
}
</style>
