import { ref, mergeProps, useSSRContext } from 'vue';
import { ssrRenderAttrs, ssrRenderStyle, ssrInterpolate } from 'vue/server-renderer';

const _sfc_main = {
  __name: 'QuizModal',
  __ssrInlineRender: true,
  props: {
  isOpen: { type: Boolean, default: false }
},
  emits: ['close', 'openDemo', 'openRegister'],
  setup(__props, { emit: __emit }) {

const quizStep = ref(0);
const quizAnswers = ref([]);

const quizQuestions = [
  { text: 'Испытываете ли вы зуд, насморк или слезотечение без простуды?', category: 'symptoms' },
  { text: 'Бывают ли у вас высыпания на коже после еды или контакта с животными?', category: 'skin' },
  { text: 'Есть ли у ваших близких родственников подтвержденная аллергия?', category: 'heredity' }
];

const getQuizRecommendation = () => {
  const yesCount = quizAnswers.value.filter(a => a === 'yes').length;
  if (yesCount === 3) {
    return 'У вас высокая вероятность аллергических реакций. Рекомендуем пройти полный многокомпонентный тест на 300+ аллергенов для точной диагностики.'
  } else if (yesCount >= 1) {
    return 'У вас наблюдаются некоторые признаки предрасположенности к аллергии. Рекомендуем проконсультироваться с аллергологом ALEX LAB и сдать направленный тест.'
  } else {
    return 'Симптомы аллергии минимальны. Однако, если вы хотите убедиться в безопасности для здоровья или планируете завести питомца, превентивный тест не будет лишним.'
  }
};

return (_ctx, _push, _parent, _attrs) => {
  if (__props.isOpen) {
    _push(`<div${ssrRenderAttrs(mergeProps({ class: "modal-overlay" }, _attrs))}><div class="modal-card"><button class="modal-close-btn">×</button><h3 class="modal-title">Нужен ли вам тест?</h3>`);
    if (quizStep.value === 0) {
      _push(`<div style="${
        ssrRenderStyle({"text-align":"center","padding":"15px 0"})
      }"><p class="modal-desc">Ответьте на 3 простых вопроса, чтобы узнать, рекомендуется ли вам обследование на аллергены.</p><button class="quiz-btn-primary" style="${
        ssrRenderStyle({"margin-top":"15px"})
      }">Начать квиз</button></div>`);
    } else if (quizStep.value >= 1 && quizStep.value <= 3) {
      _push(`<div style="${
        ssrRenderStyle({"padding":"15px 0"})
      }"><div style="${
        ssrRenderStyle({"font-size":"14px","color":"#999","margin-bottom":"10px"})
      }">Вопрос ${
        ssrInterpolate(quizStep.value)
      } из 3</div><p style="${
        ssrRenderStyle({"font-size":"18px","font-weight":"500","line-height":"1.4","margin-bottom":"20px"})
      }">${
        ssrInterpolate(quizQuestions[quizStep.value - 1].text)
      }</p><div style="${
        ssrRenderStyle({"display":"flex","gap":"12px"})
      }"><button class="quiz-btn-choice">Да</button><button class="quiz-btn-choice outline">Нет</button></div></div>`);
    } else if (quizStep.value === 4) {
      _push(`<div style="${
        ssrRenderStyle({"text-align":"center","padding":"15px 0"})
      }"><h4 style="${
        ssrRenderStyle({"font-size":"20px","font-weight":"bold","color":"#52c41a","margin-bottom":"15px"})
      }">Результат квиза</h4><p style="${
        ssrRenderStyle({"font-size":"16px","line-height":"1.6","color":"#333","margin-bottom":"20px"})
      }">${
        ssrInterpolate(getQuizRecommendation())
      }</p><div style="${
        ssrRenderStyle({"display":"flex","flex-direction":"column","gap":"10px","margin-top":"15px"})
      }"><button class="quiz-btn-primary">Посмотреть демо-отчет</button><div style="${
        ssrRenderStyle({"display":"flex","gap":"12px"})
      }"><button class="quiz-btn-choice outline" style="${
        ssrRenderStyle({"flex":"1"})
      }">Пройти заново</button><button class="quiz-btn-choice" style="${
        ssrRenderStyle({"flex":"1"})
      }">Записаться на тест</button></div></div></div>`);
    } else {
      _push(`<!---->`);
    }
    _push(`</div></div>`);
  } else {
    _push(`<!---->`);
  }
}
}

};
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext()
  ;(ssrContext.modules || (ssrContext.modules = new Set())).add("resources/js/Components/QuizModal.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : undefined
};

export { _sfc_main as default };
