<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useDoctorMode } from '@/composables/useDoctorMode'

const props = defineProps({
  category: { type: Object, required: true },
})

const { doctorsUrl } = useDoctorMode()

const categoryLink = computed(() => (props.category.link_url || '').trim())
const hasCustomLink = computed(() => categoryLink.value !== '')
const isAbsoluteUrl = computed(() => /^(https?:)?\/\//i.test(categoryLink.value))
const href = computed(() => {
  if (!hasCustomLink.value) {
    return doctorsUrl(`/materials/${props.category.slug}`)
  }
  if (categoryLink.value.startsWith('/') && !categoryLink.value.startsWith('//')) {
    return doctorsUrl(categoryLink.value)
  }
  return categoryLink.value
})
</script>

<template>
  <a
    v-if="hasCustomLink"
    :href="href"
    class="doctor-doc-cat"
    :target="isAbsoluteUrl ? '_blank' : undefined"
    :rel="isAbsoluteUrl ? 'noopener noreferrer' : undefined"
  >
    <div class="doctor-doc-cat-copy">
      <h3>{{ category.name }}</h3>
      <p v-if="category.description">{{ category.description }}</p>
    </div>
    <div class="doctor-doc-cat-meta">
      <img src="/assets/figma-arrow-right.svg" alt="" width="24" height="24" />
    </div>
  </a>
  <Link v-else :href="href" class="doctor-doc-cat">
    <div class="doctor-doc-cat-copy">
      <h3>{{ category.name }}</h3>
      <p v-if="category.description">{{ category.description }}</p>
    </div>
    <div class="doctor-doc-cat-meta">
      <img src="/assets/figma-arrow-right.svg" alt="" width="24" height="24" />
    </div>
  </Link>
</template>

<style scoped>
.doctor-doc-cat {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  min-height: 184px;
  padding: 32px;
  background: #fff;
  border: 1px solid #dfdfdf;
  color: inherit;
  text-decoration: none;
}

.doctor-doc-cat-copy h3 {
  margin: 0 0 12px;
  font-family: Roboto, Arial, sans-serif;
  font-size: 24px;
  font-weight: 400;
}

.doctor-doc-cat-copy p {
  margin: 0;
  font-family: Roboto, Arial, sans-serif;
  font-size: 16px;
  color: rgba(0, 0, 0, 0.6);
}

.doctor-doc-cat-meta {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  margin-top: 24px;
}

.doctor-doc-cat-meta img {
  width: 24px;
  height: 24px;
}
</style>
