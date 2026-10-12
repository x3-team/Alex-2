<script setup>
// Повторно открывает баннер согласия на cookie и веб-аналитику (resources/views/app.blade.php):
// так посетитель может отозвать или дать согласие в любой момент.
const openSettings = () => {
  window.alexAnalytics?.openSettings()
}
</script>

<template>
  <a href="#cookie-settings" @click.prevent="openSettings">Настройки cookie</a>
</template>
