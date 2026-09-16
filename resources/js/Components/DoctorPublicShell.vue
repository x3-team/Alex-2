<script setup>
import { Link } from '@inertiajs/vue3'
import SiteSidebar from '@/Components/SiteSidebar.vue'
import PublicFooter from '@/Components/PublicFooter.vue'
import { useDoctorMode } from '@/composables/useDoctorMode'
import '../../css/main.css'

defineProps({
  backHref: { type: String, default: '/' },
  backLabel: { type: String, default: 'На главную' },
})

const { isDoctorMode } = useDoctorMode()
</script>

<template>
  <div
    class="page-container search-page-container site-sidebar-layout"
    :class="{ 'doctor-mode': isDoctorMode }"
  >
    <SiteSidebar />

    <div class="flex-1 flex flex-col" style="background-color: #f7f7f7; width: 100%; overflow: auto">
      <header class="hidden xl:block bg-white border-b border-gray-200 sticky top-0 z-10">
        <div class="px-9 py-4 flex align-center height-[72px]">
          <Link
            :href="backHref"
            class="inline-flex items-center gap-2 text-gray-700 hover:text-emerald-700 font-medium transition-colors w-full"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            {{ backLabel }}
          </Link>
        </div>
      </header>

      <main class="main-content p-8 xl:p-16 flex-1">
        <slot />
      </main>

      <PublicFooter />
    </div>
  </div>
</template>

<style scoped>
.main-content {
  padding: 24px;
  background-color: rgb(247, 247, 247);
  width: 100%;
  padding-bottom: 80px;
  box-sizing: border-box;
}
</style>
