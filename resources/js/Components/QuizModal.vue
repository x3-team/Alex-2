<script setup>
import { ref } from 'vue'

const props = defineProps({
  isOpen: { type: Boolean, default: false }
})

const emit = defineEmits(['close', 'openDemo', 'openRegister'])

const quizStep = ref(0)
const quizAnswers = ref([])

const quizQuestions = [
  { text: 'Испытываете ли вы зуд, насморк или слезотечение без простуды?', category: 'symptoms' },
  { text: 'Бывают ли у вас высыпания на коже после еды или контакта с животными?', category: 'skin' },
  { text: 'Есть ли у ваших близких родственников подтвержденная аллергия?', category: 'heredity' }
]

const startQuiz = () => {
  quizStep.value = 1
  quizAnswers.value = []
}

const answerQuiz = (answer) => {
  quizAnswers.value.push(answer)
  if (quizStep.value < quizQuestions.length) {
    quizStep.value++
  } else {
    quizStep.value = 4
  }
}

const getQuizRecommendation = () => {
  const yesCount = quizAnswers.value.filter(a => a === 'yes').length
  if (yesCount === 3) {
    return 'У вас высокая вероятность аллергических реакций. Рекомендуем пройти полный многокомпонентный тест на 300+ аллергенов для точной диагностики.'
  } else if (yesCount >= 1) {
    return 'У вас наблюдаются некоторые признаки предрасположенности к аллергии. Рекомендуем проконсультироваться с аллергологом ALEX LAB и сдать направленный тест.'
  } else {
    return 'Симптомы аллергии минимальны. Однако, если вы хотите убедиться в безопасности для здоровья или планируете завести питомца, превентивный тест не будет лишним.'
  }
}

const resetQuiz = () => {
  quizStep.value = 0
  quizAnswers.value = []
}

const handleClose = () => {
  emit('close')
}

const handleOpenDemo = () => {
  emit('close')
  emit('openDemo')
}

const handleOpenRegister = () => {
  emit('close')
  emit('openRegister')
}
</script>

<template>
  <Transition name="fade">
    <div class="modal-overlay" v-if="isOpen" @click.self="handleClose">
      <div class="modal-card">
        <button class="modal-close-btn" @click="handleClose">&times;</button>
        <h3 class="modal-title">Нужен ли вам тест?</h3>

        <div v-if="quizStep === 0" style="text-align: center; padding: 15px 0;">
          <p class="modal-desc">Ответьте на 3 простых вопроса, чтобы узнать, рекомендуется ли вам обследование на аллергены.</p>
          <button @click="startQuiz" class="quiz-btn-primary" style="margin-top: 15px;">Начать квиз</button>
        </div>

        <div v-else-if="quizStep >= 1 && quizStep <= 3" style="padding: 15px 0;">
          <div style="font-size: 14px; color: #999; margin-bottom: 10px;">Вопрос {{ quizStep }} из 3</div>
          <p style="font-size: 18px; font-weight: 500; line-height: 1.4; margin-bottom: 20px;">
            {{ quizQuestions[quizStep - 1].text }}
          </p>
          <div style="display: flex; gap: 12px;">
            <button @click="answerQuiz('yes')" class="quiz-btn-choice">Да</button>
            <button @click="answerQuiz('no')" class="quiz-btn-choice outline">Нет</button>
          </div>
        </div>

        <div v-else-if="quizStep === 4" style="text-align: center; padding: 15px 0;">
          <h4 style="font-size: 20px; font-weight: bold; color: #52c41a; margin-bottom: 15px;">Результат квиза</h4>
          <p style="font-size: 16px; line-height: 1.6; color: #333; margin-bottom: 20px;">
            {{ getQuizRecommendation() }}
          </p>
          <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 15px;">
            <button @click="handleOpenDemo" class="quiz-btn-primary">Посмотреть демо-отчет</button>
            <div style="display: flex; gap: 12px;">
              <button @click="resetQuiz" class="quiz-btn-choice outline" style="flex: 1;">Пройти заново</button>
              <button @click="handleOpenRegister" class="quiz-btn-choice" style="flex: 1;">Записаться на тест</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>