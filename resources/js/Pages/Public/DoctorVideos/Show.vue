<script setup>
import { computed, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import DoctorPublicShell from '@/Components/DoctorPublicShell.vue'
import DoctorVideoCard from '@/Components/DoctorVideoCard.vue'
import { useDoctorMode } from '@/composables/useDoctorMode'

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

const playSrc = computed(() => {
  const src = props.video.iframe_src
  if (!src) {
    return ''
  }

  try {
    const url = new URL(src)
    url.searchParams.set('autoplay', '1')
    if (url.hostname.includes('youtube')) {
      url.searchParams.set('playsinline', '1')
      url.searchParams.set('rel', '0')
    }
    return url.toString()
  } catch {
    return src.includes('?') ? `${src}&autoplay=1` : `${src}?autoplay=1`
  }
})

const startPlayback = () => {
  if (playSrc.value) {
    playing.value = true
  }
}

const pageTitle = computed(() => props.videosMeta.title || props.video.title)
const pageDescription = computed(() => props.videosMeta.description || props.video.description || props.video.title)
const ogTitle = computed(() => props.videosMeta.og_title?.trim() || pageTitle.value)
const ogDescription = computed(() => props.videosMeta.og_description?.trim() || pageDescription.value)
</script>

<template>
  <Head>
    <title>{{ pageTitle }}</title>
    <meta name="description" :content="pageDescription" />
    <meta v-if="videosMeta.keywords" name="keywords" :content="videosMeta.keywords" />
    <meta property="og:title" :content="ogTitle" />
    <meta property="og:description" :content="ogDescription" />
    <meta v-if="video.cover" property="og:image" :content="video.cover" />
  </Head>

  <DoctorPublicShell :back-href="doctorsUrl('/blog?type=videos')" back-label="К видео">
    <div
      class="breadcrumbs flex items-center gap-3 mb-6"
      style="display: flex; flex-direction: row; justify-content: flex-start; align-items: center; padding: 0px; gap: 12px; min-height: 48px;"
    >
      <Link :href="doctorsUrl('/')" class="flex-shrink-0 text-[14px] xl:text-[18px] text-black opacity-30">Главная</Link>
      <span class="text-black opacity-[0.3]">
        <svg width="6" height="9" viewBox="0 0 6 9" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M0.75 8.25L4.5 4.5L0.75 0.75" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </span>
      <Link :href="doctorsUrl('/blog')" class="flex-shrink-0 text-[14px] xl:text-[18px] text-black opacity-30">Материалы для врачей</Link>
      <span class="text-black opacity-[0.3]">
        <svg width="6" height="9" viewBox="0 0 6 9" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M0.75 8.25L4.5 4.5L0.75 0.75" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </span>
      <Link :href="doctorsUrl('/blog?type=videos')" class="flex-shrink-0 text-[14px] xl:text-[18px] text-black opacity-30">Видео</Link>
      <span class="text-black opacity-[0.3]">
        <svg width="6" height="9" viewBox="0 0 6 9" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M0.75 8.25L4.5 4.5L0.75 0.75" stroke="black" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </span>
      <span class="flex-1 w-0 text-[14px] xl:text-[18px] text-black truncate min-w-0" :title="video.title">{{ video.title }}</span>
    </div>

    <div class="badges">
      <span v-if="video.published_at">{{ formatDate(video.published_at) }}</span>
      <span v-if="video.duration">{{ video.duration }}</span>
      <span>{{ video.source_label }}</span>
    </div>

    <h1 class="font-400 text-[28px] sm:text-[34px] xl:text-[42px] mt-6 mb-8">{{ video.title }}</h1>

    <div class="player">
      <iframe
        v-if="playing && playSrc"
        :src="playSrc"
        title="Видеоплеер"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
        allowfullscreen
      />
      <button v-else type="button" class="player-cover" @click="startPlayback">
        <img v-if="video.cover" :src="video.cover" :alt="video.title" width="1115" height="627" />
        <span class="player-source">{{ video.source_label }}</span>
        <span v-if="video.duration" class="player-duration">{{ video.duration }}</span>
        <span class="player-play doctor-video-glass-play" aria-hidden="true">
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
      <img v-if="relatedArticle.cover" :src="relatedArticle.cover.startsWith('/') ? relatedArticle.cover : `/storage/${relatedArticle.cover}`" alt="" width="140" height="88" />
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
  </DoctorPublicShell>
</template>

<style scoped>
.badges { display: flex; flex-wrap: wrap; gap: 8px; }
.badges span {
  display: inline-flex; align-items: center; height: 45px; padding: 0 16px; border-radius: 8px;
  background: rgba(0,0,0,0.07); font-family: Roboto, Arial, sans-serif; font-size: 18px;
}
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
  display: flex; align-items: center; justify-content: center; border-radius: 44px;
}
.player-play img { width: 32px; height: 32px; }
.anons { margin: 24px 0 0; max-width: 916px; font-family: Roboto, Arial, sans-serif; font-size: 21px; line-height: 1.4; }
.related-article {
  display: flex; align-items: center; gap: 24px; padding: 24px; margin-top: 32px; background: #fff; border: 1px solid #dfdfdf;
  color: inherit; text-decoration: none;
}
.related-article img { width: 140px; height: 88px; object-fit: cover; }
.related-article p { margin: 0 0 8px; color: rgba(0,0,0,0.6); font-size: 16px; }
.related-article strong { font-size: 21px; font-weight: 400; }
.related { margin-top: 48px; }
.related h2 { margin: 0 0 32px; font-family: Roboto, Arial, sans-serif; font-size: 32px; font-weight: 400; }
.related-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 33px; }

@media (max-width: 1024px) {
  .related-grid { grid-template-columns: 1fr; }
  .player-play { width: 64px; height: 64px; margin: -32px 0 0 -32px; border-radius: 32px; }
  .player-play img { width: 24px; height: 24px; }
}
</style>
