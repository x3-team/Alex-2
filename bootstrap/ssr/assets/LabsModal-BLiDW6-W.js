import { ssrRenderTeleport, ssrRenderClass, ssrRenderStyle, ssrRenderList, ssrRenderAttr } from 'vue/server-renderer';
import { ref, useSSRContext } from 'vue';
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
  __name: 'LabsModal',
  __ssrInlineRender: true,
  props: {
  isOpen: { type: Boolean, default: false }
},
  emits: ['close'],
  setup(__props, { emit: __emit }) {

const isSheetDragging = ref(false);
const sheetDragOffset = ref(0);

const labs = [
  { name: 'Гемотест', logo: '/assets/figma-lab-gemotest.webp', logoClass: 'test-location-modal__logo--gemotest', href: 'https://gemotest.ru/moskva/catalog/allergii/pervichnye-testy/obshchaya-allergodiagnostika/allergochip-alex-2/allergochip-alex-2-300-allergokomponentov-allergy-explorer-2-19ddaf/' },
  { name: 'ДНКОМ', logo: '/assets/figma-lab-dncom.webp', logoClass: 'test-location-modal__logo--dncom', href: 'https://dnkom.ru/analizy-i-tseny/allergochip-alex2-allergy-explorer/allergochip-alex2-allergy-explorer-2-do-300-allergokomponentov-i-obshchiy-ige/' },
  { name: 'Ситилаб', logo: '/assets/figma-lab-citilab.webp', logoClass: 'test-location-modal__logo--citilab', href: '#' },
  { name: 'KDL', logo: '/assets/figma-lab-kdl.webp', logoClass: 'test-location-modal__logo--kdl', href: 'https://kdl.ru/analizy-i-tseny/msk/allergochip-alex2-300-komponentov' },
  { name: 'CMD', logo: '/assets/figma-lab-cmd.webp', logoClass: 'test-location-modal__logo--cmd', href: 'https://www.cmd-online.ru/analizy-i-tseny/katalog-analizov/msk/allergochip_alex_2_300_allergokomponentov_i_ig_e_obshhij_ig_e/' },
  { name: 'CHROMOLAB', logo: '/assets/figma-lab-chromolab.webp', logoClass: 'test-location-modal__logo--chromolab', href: 'https://www.chromolab.ru/issledovaniya/al866_allergochip_alex2_300_komponentov_vklyuchaet_opredelenie_obshchego_ige/' }
];

return (_ctx, _push, _parent, _attrs) => {
  ssrRenderTeleport(_push, (_push) => {
    if (__props.isOpen) {
      _push(`<section class="test-location-modal" aria-label="Выбор лаборатории для сдачи теста" role="dialog" aria-modal="true" data-v-e72de53e><div class="${
        ssrRenderClass([{ 'test-location-modal__sheet--dragging': isSheetDragging.value }, "test-location-modal__sheet"])
      }" style="${
        ssrRenderStyle({ '--sheet-drag-offset': `${sheetDragOffset.value}px` })
      }" data-v-e72de53e><div class="test-location-modal__grabber-area" role="button" tabindex="0" aria-label="Потяните вниз, чтобы закрыть" data-v-e72de53e><div class="test-location-modal__grabber" aria-hidden="true" data-v-e72de53e></div></div><header class="test-location-modal__header" data-v-e72de53e><h2 data-v-e72de53e>Где сдать тест?</h2><button class="test-location-modal__close" type="button" aria-label="Закрыть" data-v-e72de53e>✕</button></header><div class="test-location-modal__labs" data-v-e72de53e><!--[-->`);
      ssrRenderList(labs, (lab) => {
        _push(`<article class="test-location-modal__lab-card" data-v-e72de53e><div class="test-location-modal__logo-box" data-v-e72de53e><img${
          ssrRenderAttr("src", lab.logo)
        } class="${
          ssrRenderClass(lab.logoClass)
        }"${
          ssrRenderAttr("alt", `Логотип ${lab.name}`)
        } loading="lazy" data-v-e72de53e></div><button class="test-location-modal__lab-action" type="button"${
          ssrRenderAttr("aria-label", `Записаться на тест в ${lab.name}`)
        } data-v-e72de53e><span data-v-e72de53e>Записаться на тест</span><span class="test-location-modal__arrow" aria-hidden="true" data-v-e72de53e><svg viewBox="0 0 24 24" fill="none" data-v-e72de53e><path d="M9 5L15 12L9 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-v-e72de53e></path></svg></span></button></article>`);
      });
      _push(`<!--]--></div></div></section>`);
    } else {
      _push(`<!---->`);
    }
  }, "body", false, _parent);
}
}

};
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext()
  ;(ssrContext.modules || (ssrContext.modules = new Set())).add("resources/js/Components/LabsModal.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : undefined
};
const LabsModal = /*#__PURE__*/_export_sfc(_sfc_main, [['__scopeId',"data-v-e72de53e"]]);

export { LabsModal as default };
