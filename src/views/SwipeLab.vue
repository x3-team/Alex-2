<script setup>
import ScrollHint from '@/components/ScrollHint.vue';

const variants = [
  { glyph: 'mouse', title: 'Сейчас', note: 'Мышь и колёсико — на телефоне бессмысленно' },
  { glyph: 'chevrons', title: 'Только шевроны', note: 'Выровнены по левому краю заголовка' },
  { glyph: 'dot', title: 'Точка со шлейфом', note: 'Точка уходит снизу вверх, шлейф тянется за ней' },
  { glyph: 'dot-chevrons', title: 'Точка и шевроны', note: 'Шлейф плюс пульсация вверх' },
];
</script>

<template>
  <div class="lab">
    <header class="lab__head">
      <h1 class="lab__title">Подсказка прокрутки на телефоне</h1>
      <p class="lab__lead">
        Слева — то, что на сайте сейчас: мышь и шевроны вниз. Остальные — свайп снизу
        вверх. Тонкая красная линия отмечает левый край заголовка: у мыши шевроны
        сдвинуты от неё на 21px, потому что центрируются под глифом, у остальных
        вариантов они выровнены по тексту.
      </p>
    </header>

    <div class="lab__grid">
      <section v-for="v in variants" :key="v.glyph" class="lab__item">
        <div class="lab__caption">
          <h2 class="lab__name">{{ v.title }}</h2>
          <p class="lab__note">{{ v.note }}</p>
        </div>

        <!-- Reproduces the mobile hero: the gradient is sampled from a phone
             screenshot of the live site, so contrast is judged for real. -->
        <div class="phone">
          <div class="phone__hero">
            <span class="phone__guide" aria-hidden="true" />
            <ScrollHint class="phone__hint" :glyph="v.glyph" />
            <h3 class="phone__title">
              Тест на аллергию ALEX² — один анализ, который даёт ответы
            </h3>
          </div>
          <div class="phone__cta">
            <span>Записаться на тест на аллергию</span>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
              <path
                d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z"
                stroke="black"
                stroke-width="2"
              />
              <path
                d="M10 8L14 12L10 16"
                stroke="black"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.lab {
  min-height: 100%;
  padding: 32px 40px 48px;
  background: #f5f5f5;
  color: #121212;
  font-family: var(--font-sans);
}

.lab__head {
  max-width: 1040px;
  margin: 0 auto 28px;
}

.lab__title {
  margin: 0 0 10px;
  font-size: 26px;
  font-weight: 400;
}

.lab__lead {
  margin: 0;
  font-size: 16px;
  line-height: 1.45;
  color: #6b6b6b;
}

.lab__grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 32px;
  max-width: 1600px;
  margin: 0 auto;
}

.lab__caption {
  margin-bottom: 10px;
  min-height: 56px;
}

.lab__name {
  margin: 0 0 4px;
  font-size: 21px;
  font-weight: 400;
}

.lab__note {
  margin: 0;
  font-size: 14px;
  color: #6b6b6b;
}

.phone {
  display: flex;
  flex-direction: column;
  border: 1px solid #dfdfdf;
  overflow: hidden;
}

/* Sampled from the phone screenshot of the live hero. */
.phone__hero {
  position: relative;
  height: 470px;
  background: linear-gradient(180deg, #c5c8ca 0%, #c9c8c0 45%, #c2bfb3 78%, #bfbbac 100%);
}

.phone__hint {
  position: absolute;
  left: 16px;
  bottom: 132px;
}

/* Guide at the headline's left edge, to check the hint lines up with it. */
.phone__guide {
  position: absolute;
  left: 16px;
  top: 0;
  bottom: 0;
  width: 1px;
  background: rgba(200, 0, 0, 0.45);
}

.phone__title {
  position: absolute;
  left: 16px;
  right: 16px;
  bottom: 24px;
  margin: 0;
  font-size: 24px;
  font-weight: 400;
  line-height: 1.18;
  color: #000;
}

.phone__cta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 18px 16px;
  background: #ffffff;
  font-size: 15px;
}

@media (max-width: 1400px) {
  .lab__grid {
    grid-template-columns: repeat(2, 1fr);
    max-width: 900px;
  }
}
</style>
