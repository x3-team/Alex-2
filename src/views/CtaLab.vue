<script setup>
import { ref } from 'vue';

import CtaButton from '@/components/CtaButton.vue';

const period = ref(14);
const cycles = ref(null);

const variants = [
  { effect: 'none', title: 'Сейчас', note: 'Без анимации' },
  { effect: 'shine', title: 'Блик', note: 'Полоса света проходит по кнопке' },
  { effect: 'glow', title: 'Подсветка', note: 'Кнопка один раз «дышит» тенью' },
  {
    effect: 'shine-nudge',
    title: 'Блик + стрелка',
    note: 'Свет проходит и слегка сдвигает стрелку',
  },
];
</script>

<template>
  <div class="lab">
    <header class="lab__head">
      <h1 class="lab__title">Периодический акцент на кнопке «Записаться на тест»</h1>
      <p class="lab__lead">
        Все четыре варианта запускаются одновременно и с одним интервалом, чтобы их можно
        было сравнивать. Сам жест всегда длится около 1,6 секунды — ползунком меняется
        только пауза между повторами.
      </p>

      <div class="lab__controls">
        <label class="control">
          <span class="control__label">Интервал: {{ period }} с</span>
          <input v-model.number="period" type="range" min="6" max="26" step="1" />
        </label>

        <label class="control control--inline">
          <input
            type="checkbox"
            :checked="cycles !== null"
            @change="cycles = cycles === null ? 3 : null"
          />
          <span class="control__label">Остановиться после 3 повторов</span>
        </label>
      </div>
    </header>

    <div class="lab__grid">
      <section v-for="v in variants" :key="v.effect" class="lab__item">
        <div class="lab__caption">
          <h2 class="lab__name">{{ v.title }}</h2>
          <p class="lab__note">{{ v.note }}</p>
        </div>

        <!-- The panel reproduces the sidebar backdrop the button actually sits on:
             the accent can only be judged against this green, not on white. -->
        <div class="panel">
          <div class="panel__lockup">
            <p class="panel__wordmark">ALEX<span class="panel__sup">²</span></p>
            <p class="panel__sub">ALLERGY XPLORER</p>
            <p class="panel__desc">
              Многокомпонентный анализ крови, который за один забор крови проверяет более
              300 аллергенов, выявляя истинные причины реакции.
            </p>
          </div>

          <CtaButton :effect="v.effect" :period="period" :cycles="cycles" />
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
}

.lab__head {
  max-width: 1040px;
  margin: 0 auto 28px;
}

.lab__title {
  margin: 0 0 10px;
  font-size: 26px;
  font-weight: 400;
  line-height: 1.2;
}

.lab__lead {
  margin: 0 0 20px;
  font-size: 16px;
  line-height: 1.45;
  color: #6b6b6b;
}

.lab__controls {
  display: flex;
  flex-wrap: wrap;
  gap: 32px;
  align-items: center;
  padding: 16px 24px;
  background: #ffffff;
  border: 1px solid #dfdfdf;
}

.control {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 260px;
}

.control--inline {
  flex-direction: row;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.control__label {
  font-size: 16px;
  color: #121212;
}

.control input[type='range'] {
  width: 100%;
  accent-color: #121212;
}

/* Two by two rather than four across: the button label does not wrap, which
   puts a hard floor of about 470px on a panel. Four of those overflow a 1920
   viewport, and all four variants fire at once so they must be on screen
   together. */
.lab__grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 28px 32px;
  max-width: 1040px;
  margin: 0 auto;
}

.lab__caption {
  margin-bottom: 10px;
}

.lab__name {
  margin: 0 0 4px;
  font-size: 24px;
  font-weight: 400;
}

.lab__note {
  margin: 0;
  font-size: 16px;
  color: #6b6b6b;
}

/* Sampled from the rendered comp: the panel is a soft vertical gradient. */
.panel {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 28px;
  min-height: 340px;
  padding: 24px;
  background: linear-gradient(180deg, #a3b2a2 0%, #90a08f 55%, #889887 100%);
}

.panel__lockup {
  color: #ffffff;
}

.panel__wordmark {
  margin: 0;
  font-size: 48px;
  line-height: 1;
  letter-spacing: 0.02em;
}

.panel__sup {
  font-size: 24px;
  vertical-align: super;
}

.panel__sub {
  margin: 6px 0 0;
  font-size: 15px;
  letter-spacing: 0.22em;
}

.panel__desc {
  margin: 18px 0 0;
  max-width: 420px;
  font-size: 16px;
  line-height: 1.45;
  color: rgba(255, 255, 255, 0.92);
}

@media (max-width: 900px) {
  .lab {
    padding: 32px 16px 56px;
  }

  .lab__grid {
    grid-template-columns: 1fr;
    max-width: 480px;
  }
}
</style>
