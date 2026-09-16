import { ref, computed, mergeProps, useSSRContext } from 'vue';
import { ssrRenderAttrs, ssrRenderList, ssrRenderClass, ssrInterpolate, ssrRenderAttr } from 'vue/server-renderer';
import { _ as _export_sfc } from '../ssr.js';
import '@inertiajs/vue3';
import '@tiptap/vue-3';
import '@tiptap/core';
import '@tiptap/starter-kit';
import '@tiptap/extension-image';
import '@tiptap/extension-link';
import '@tiptap/extension-underline';
import '@tiptap/extension-text-align';
import '@tiptap/extension-task-list';
import '@tiptap/extension-task-item';
import '@tiptap/extension-code-block';
import '@tiptap/extension-horizontal-rule';
import '@tiptap/extension-subscript';
import '@tiptap/extension-superscript';
import '@tiptap/extension-highlight';
import '@tiptap/extension-text-style';
import '@tiptap/extension-color';
import '@tiptap/extension-table';
import '@tiptap/pm/state';
import '@tiptap/extension-table-row';
import '@tiptap/extension-table-cell';
import '@tiptap/extension-table-header';
import 'vue-advanced-cropper';
import 'minisearch';
import '@inertiajs/vue3/server';
import '@vue/server-renderer';

const _sfc_main = {
  __name: 'FigmaInfoSlide',
  __ssrInlineRender: true,
  props: {
  slide: {
    type: Object,
    required: true
  },
  doctorMode: {
    type: Boolean,
    default: false
  },
  blogHref: {
    type: String,
    default: 'https://alexallergotest.ru/blog'
  }
},
  emits: ['navigate', 'blog'],
  setup(__props, { emit: __emit }) {

const props = __props;

const openFaqIndex = ref(-1);

const patientFaqItems = [
  { question: 'Это больно?', answer: 'Забор крови из вены производится тонкой иглой, процедура быстрая и практически безболезненная.' },
  { question: 'Сколько аллергенов в тесте?', answer: 'Аллергочип ALEX2 проверяет более 300 аллергенов одновременно.' },
  { question: 'Через сколько будет результат?', answer: 'Готовность — через 4–7 рабочих дней.' },
  { question: 'У вас есть сертификаты?', answer: 'Да, тест ALEX2 сертифицирован и признан во всем мире.' }
];

const doctorFaqItems = [
  patientFaqItems[0],
  patientFaqItems[1],
  {
    question: 'Нужно ли сдавать на тощак?',
    answer: 'Нет. Анализ можно сдавать без голодания и специальной подготовки.'
  },
  patientFaqItems[3]
];

const faqItems = computed(() => (
    props.doctorMode ? doctorFaqItems : patientFaqItems
));

const blogCards = [
  {
    title: 'Я уволил джуна, нанял AI-агента, через месяц нанял джуна обратно',
    description: 'Про оптимизацию, которая выстрелила — но не так, как я думал'
  },
  {
    title: 'Я уволил джуна, нанял AI-агента, через месяц нанял джуна обратно',
    description: 'Про оптимизацию, которая выстрелила — но не так, как я думал'
  }
];

const howToAsset = computed(() => props.slide.step === '1'
    ? '/assets/figma-howto-step-1.webp'
    : `/assets/figma-howto-step-${props.slide.step}.webp`);

const howToSrcset = computed(() => {
  const step = props.slide.step === '1' ? '1' : String(props.slide.step);
  return `/assets/figma-howto-step-${step}-400w.webp 400w, /assets/figma-howto-step-${step}.webp 800w`
});

return (_ctx, _push, _parent, _attrs) => {
  if (__props.slide.type === 'faq') {
    _push(`<section${ssrRenderAttrs(mergeProps({ class: "figma-exact-info-slide exact-faq-slide" }, _attrs))} data-v-092c16f6><div class="exact-faq-list" data-v-092c16f6><!--[-->`);
    ssrRenderList(faqItems.value, (item, index) => {
      _push(`<div class="${
        ssrRenderClass([{ open: openFaqIndex.value === index }, "exact-faq-item"])
      }" data-v-092c16f6><button class="exact-faq-question" type="button" data-v-092c16f6><span data-v-092c16f6>${
        ssrInterpolate(item.question)
      }</span><img src="/assets/figma-faq-chevron.svg" alt="" class="exact-faq-chevron" data-v-092c16f6></button>`);
      if (openFaqIndex.value === index) {
        _push(`<p class="exact-faq-answer" data-v-092c16f6>${ssrInterpolate(item.answer)}</p>`);
      } else {
        _push(`<!---->`);
      }
      _push(`</div>`);
    });
    _push(`<!--]--></div><div class="exact-bottom-link exact-bottom-line" aria-hidden="true" data-v-092c16f6></div></section>`);
  } else if (__props.slide.type === 'blog') {
    _push(`<section${ssrRenderAttrs(mergeProps({ class: "figma-exact-info-slide exact-blog-slide" }, _attrs))} data-v-092c16f6><h2 class="exact-section-title" data-v-092c16f6>Блог про аллергию</h2><div class="exact-blog-grid" data-v-092c16f6><!--[-->`);
    ssrRenderList(blogCards, (card, index) => {
      _push(`<article class="exact-blog-card" data-v-092c16f6><div class="exact-blog-cover" data-v-092c16f6><img src="" alt="" data-v-092c16f6><div class="exact-blog-tags" data-v-092c16f6><span data-v-092c16f6>~16 минут</span><span data-v-092c16f6>Наука</span></div></div><div class="exact-blog-author" data-v-092c16f6><img src="" alt="Александра Ковальчук" data-v-092c16f6><div data-v-092c16f6><p class="exact-blog-author-name" data-v-092c16f6>Александра Ковальчук</p><p class="exact-blog-author-role" data-v-092c16f6>Профессор МГУ</p></div></div><div class="exact-blog-copy" data-v-092c16f6><h3 class="exact-blog-card-title" data-v-092c16f6>${
        ssrInterpolate(card.title)
      }</h3><p class="exact-blog-description" data-v-092c16f6>${
        ssrInterpolate(card.description)
      }</p></div></article>`);
    });
    _push(`<!--]--></div><a${ssrRenderAttr("href", __props.blogHref)} class="exact-bottom-link" data-v-092c16f6><span data-v-092c16f6>Все материалы</span><img src="/assets/figma-faq-link-arrow.svg" alt="" data-v-092c16f6></a></section>`);
  } else {
    _push(`<section${
      ssrRenderAttrs(mergeProps({
        class: ["figma-exact-info-slide exact-howto-slide", `exact-howto-step-${__props.slide.step}`]
      }, _attrs))
    } data-v-092c16f6><h2 class="exact-section-title" data-v-092c16f6>Как сдать тест: подготовка и процесс</h2><img class="exact-howto-image"${
      ssrRenderAttr("src", howToAsset.value)
    }${
      ssrRenderAttr("srcset", howToSrcset.value)
    } sizes="(max-width: 1024px) 88vw, 627px" width="800" height="460" alt="" loading="lazy" decoding="async" data-v-092c16f6><div class="exact-howto-copy" data-v-092c16f6><div class="exact-step-indicator" aria-label="Шаги" data-v-092c16f6><!--[-->`);
    ssrRenderList(3, (number) => {
      _push(`<button type="button" class="${
        ssrRenderClass({ active: Number(__props.slide.step) === number })
      }"${
        ssrRenderAttr("aria-label", `Перейти к шагу ${number}`)
      } data-v-092c16f6><i data-v-092c16f6>${
        ssrInterpolate(number)
      }</i></button>`);
    });
    _push(`<!--]--></div><div class="exact-howto-text" data-v-092c16f6>`);
    if (__props.slide.step === '3') {
      _push(`<h3 data-v-092c16f6> Отслеживайте результат<br data-v-092c16f6>в личном кабинете и на электронной почте </h3>`);
    } else {
      _push(`<h3 data-v-092c16f6>${ssrInterpolate(__props.slide.title)}</h3>`);
    }
    _push(`<p data-v-092c16f6>${ssrInterpolate(__props.slide.subtitle)}</p></div></div></section>`);
  }
}
}

};
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext()
  ;(ssrContext.modules || (ssrContext.modules = new Set())).add("resources/js/Components/FigmaInfoSlide.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : undefined
};
const FigmaInfoSlide = /*#__PURE__*/_export_sfc(_sfc_main, [['__scopeId',"data-v-092c16f6"]]);

export { FigmaInfoSlide as default };
