import { ref, mergeProps, unref, withCtx, createTextVNode, createVNode, useSSRContext } from 'vue';
import { ssrRenderAttrs, ssrRenderComponent, ssrRenderClass, ssrRenderStyle } from 'vue/server-renderer';
import { Link } from '@inertiajs/vue3';
import { _ as _export_sfc } from '../ssr.js';
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
  __name: 'FigmaContactsSlide',
  __ssrInlineRender: true,
  props: {
  blogHref: {
    type: String,
    default: 'https://alexallergotest.ru/blog',
  },
},
  emits: ['register', 'quiz', 'navigate', 'blog'],
  setup(__props, { emit: __emit }) {
const activeTab = ref('contacts'); // 'contacts' | 'navigation'

return (_ctx, _push, _parent, _attrs) => {
  _push(`<section${ssrRenderAttrs(mergeProps({
    class: "figma-exact-contacts",
    "aria-labelledby": "contacts-title"
  }, _attrs))} data-v-9ea30107><div class="contacts-desktop-root desktop-only-container" data-v-9ea30107><div class="exact-contacts-body-desktop" data-v-9ea30107><div class="desktop-contacts-header" data-v-9ea30107><h2 id="contacts-title" data-v-9ea30107>Тест на аллергию ALEX² — один анализ, который даёт ответы</h2><button class="desktop-cta-banner" type="button" data-v-9ea30107><span class="cta-banner-text" data-v-9ea30107>Записаться на тест на аллергию</span><div class="cta-banner-icon" aria-hidden="true" data-v-9ea30107><svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" data-v-9ea30107><circle cx="16" cy="16" r="14" stroke="white" stroke-width="2" data-v-9ea30107></circle><path d="M14 11L19 16L14 21" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" data-v-9ea30107></path></svg></div></button></div><div class="desktop-contacts-columns" data-v-9ea30107><div class="desktop-col contacts-col" data-v-9ea30107><h3 class="col-heading" data-v-9ea30107>Контакты</h3><div class="col-rule" data-v-9ea30107></div><div class="contacts-info-group" data-v-9ea30107><a href="https://yandex.ru/maps/?text=Москва%2C%Таганская%3%2F2" target="_blank" rel="noopener noreferrer" class="contact-item-link" data-v-9ea30107> Москва, Таганская улица, 3 </a><a href="tel:+79774474907" class="contact-item-link" data-v-9ea30107> +7 (977) 447 49 07</a><a href="mailto:info@alexallergotest.ru" class="contact-item-link" data-v-9ea30107>info@alexallergotest.ru</a></div><div class="legal-info-group" data-v-9ea30107><span data-v-9ea30107>ИНН: 7733352084</span><span data-v-9ea30107>ОГРН: 1207700075032</span><span data-v-9ea30107>КПП: 773301001</span></div></div><div class="desktop-col navigation-col" data-v-9ea30107><h3 class="col-heading" data-v-9ea30107>Навигация по сайту</h3><div class="col-rule" data-v-9ea30107></div><div class="site-nav-links-grid" data-v-9ea30107>`);
  _push(ssrRenderComponent(unref(Link), {
    href: "/search",
    class: "desktop-nav-item"
  }, {
    default: withCtx((_, _push, _parent, _scopeId) => {
      if (_push) {
        _push(`Поиск аллергенов`);
      } else {
        return [
          createTextVNode("Поиск аллергенов")
        ]
      }
    }),
    _: 1
  }, _parent));
  _push(ssrRenderComponent(unref(Link), {
    href: "/demo-result",
    class: "desktop-nav-item"
  }, {
    default: withCtx((_, _push, _parent, _scopeId) => {
      if (_push) {
        _push(`Демо-результат`);
      } else {
        return [
          createTextVNode("Демо-результат")
        ]
      }
    }),
    _: 1
  }, _parent));
  _push(ssrRenderComponent(unref(Link), {
    href: "/quiz",
    class: "desktop-nav-item"
  }, {
    default: withCtx((_, _push, _parent, _scopeId) => {
      if (_push) {
        _push(`КВИЗ: Нужен ли вам тест`);
      } else {
        return [
          createTextVNode("КВИЗ: Нужен ли вам тест")
        ]
      }
    }),
    _: 1
  }, _parent));
  _push(ssrRenderComponent(unref(Link), {
    href: "/alex-lab",
    class: "desktop-nav-item"
  }, {
    default: withCtx((_, _push, _parent, _scopeId) => {
      if (_push) {
        _push(`О лаборатории ALEXLAB`);
      } else {
        return [
          createTextVNode("О лаборатории ALEXLAB")
        ]
      }
    }),
    _: 1
  }, _parent));
  _push(ssrRenderComponent(unref(Link), {
    href: "/blog",
    class: "desktop-nav-item"
  }, {
    default: withCtx((_, _push, _parent, _scopeId) => {
      if (_push) {
        _push(`Блог`);
      } else {
        return [
          createTextVNode("Блог")
        ]
      }
    }),
    _: 1
  }, _parent));
  _push(`</div></div></div><footer class="desktop-contacts-footer" data-v-9ea30107><div class="desktop-policy-links" data-v-9ea30107>`);
  _push(ssrRenderComponent(unref(Link), { href: "/alex-lab/privacy" }, {
    default: withCtx((_, _push, _parent, _scopeId) => {
      if (_push) {
        _push(`Политика конфиденциальности`);
      } else {
        return [
          createTextVNode("Политика конфиденциальности")
        ]
      }
    }),
    _: 1
  }, _parent));
  _push(ssrRenderComponent(unref(Link), { href: "/consent" }, {
    default: withCtx((_, _push, _parent, _scopeId) => {
      if (_push) {
        _push(`Условия использования материалов сайта`);
      } else {
        return [
          createTextVNode("Условия использования материалов сайта")
        ]
      }
    }),
    _: 1
  }, _parent));
  _push(`</div>`);
  _push(ssrRenderComponent(unref(Link), {
    href: "https://t.me/cartonasunsetbeach",
    class: "desktop-studio-credit"
  }, {
    default: withCtx((_, _push, _parent, _scopeId) => {
      if (_push) {
        _push(`<img src="/assets/figma-studio-logo.svg" alt="" data-v-9ea30107${
          _scopeId
        }><span data-v-9ea30107${
          _scopeId
        }>сайт сделан в студии «созерцание»</span>`);
      } else {
        return [
          createVNode("img", {
            src: "/assets/figma-studio-logo.svg",
            alt: ""
          }),
          createVNode("span", null, "сайт сделан в студии «созерцание»")
        ]
      }
    }),
    _: 1
  }, _parent));
  _push(`</footer></div></div><div class="contacts-mobile-root mobile-only-container" data-v-9ea30107><div class="mobile-contacts-body" data-v-9ea30107><header class="mobile-contacts-header" data-v-9ea30107><h2 data-v-9ea30107>Тест на аллергию ALEX² — один анализ, который даёт ответы</h2></header><div class="mobile-contacts-main" data-v-9ea30107><div class="mobile-tabs-row" data-v-9ea30107><button type="button" class="${
    ssrRenderClass([{ active: activeTab.value === 'contacts' }, "mobile-tab-btn"])
  }" data-v-9ea30107> Контакты </button><button type="button" class="${
    ssrRenderClass([{ active: activeTab.value === 'navigation' }, "mobile-tab-btn"])
  }" data-v-9ea30107> Навигация по сайту </button></div><div class="mobile-tab-rule" data-v-9ea30107></div><div class="mobile-tab-content contacts-tab-content" style="${
    ssrRenderStyle((activeTab.value === 'contacts') ? null : { display: "none" })
  }" data-v-9ea30107><div class="mobile-contacts-list" data-v-9ea30107><a href="https://yandex.ru/maps/?text=Москва%2C%20Таганская%203" target="_blank" rel="noopener noreferrer" data-v-9ea30107> Москва, Таганская улица, 3 </a><a href="tel:+79774474907" data-v-9ea30107>+7 (977) 447 49 07</a><a href="mailto:info@alexallergotest.ru" data-v-9ea30107>info@alexallergotest.ru</a></div><div class="mobile-legal-list" data-v-9ea30107><span data-v-9ea30107>ИНН: 7733352084</span><span data-v-9ea30107>ОГРН: 1207700075032</span><span data-v-9ea30107>КПП: 773301001</span></div><div class="mobile-policy-list" data-v-9ea30107>`);
  _push(ssrRenderComponent(unref(Link), { href: "/alex-lab/privacy" }, {
    default: withCtx((_, _push, _parent, _scopeId) => {
      if (_push) {
        _push(`Политика конфиденциальности`);
      } else {
        return [
          createTextVNode("Политика конфиденциальности")
        ]
      }
    }),
    _: 1
  }, _parent));
  _push(ssrRenderComponent(unref(Link), { href: "/consent" }, {
    default: withCtx((_, _push, _parent, _scopeId) => {
      if (_push) {
        _push(`Условия использования материалов сайта`);
      } else {
        return [
          createTextVNode("Условия использования материалов сайта")
        ]
      }
    }),
    _: 1
  }, _parent));
  _push(`</div></div><div class="mobile-tab-content navigation-tab-content" style="${ssrRenderStyle((activeTab.value === 'navigation') ? null : { display: "none" })}" data-v-9ea30107><div class="mobile-nav-list" data-v-9ea30107>`);
  _push(ssrRenderComponent(unref(Link), {
    href: "/search",
    class: "mobile-nav-link"
  }, {
    default: withCtx((_, _push, _parent, _scopeId) => {
      if (_push) {
        _push(`Поиск аллергенов`);
      } else {
        return [
          createTextVNode("Поиск аллергенов")
        ]
      }
    }),
    _: 1
  }, _parent));
  _push(ssrRenderComponent(unref(Link), {
    href: "/demo-result",
    class: "mobile-nav-link"
  }, {
    default: withCtx((_, _push, _parent, _scopeId) => {
      if (_push) {
        _push(`Демо-результат`);
      } else {
        return [
          createTextVNode("Демо-результат")
        ]
      }
    }),
    _: 1
  }, _parent));
  _push(ssrRenderComponent(unref(Link), {
    href: "/quiz",
    class: "mobile-nav-link"
  }, {
    default: withCtx((_, _push, _parent, _scopeId) => {
      if (_push) {
        _push(`КВИЗ: Нужен ли вам тест`);
      } else {
        return [
          createTextVNode("КВИЗ: Нужен ли вам тест")
        ]
      }
    }),
    _: 1
  }, _parent));
  _push(ssrRenderComponent(unref(Link), {
    href: "/alex-lab",
    class: "mobile-nav-link"
  }, {
    default: withCtx((_, _push, _parent, _scopeId) => {
      if (_push) {
        _push(`О лаборатории ALEXLAB`);
      } else {
        return [
          createTextVNode("О лаборатории ALEXLAB")
        ]
      }
    }),
    _: 1
  }, _parent));
  _push(ssrRenderComponent(unref(Link), {
    href: "/blog",
    class: "mobile-nav-link"
  }, {
    default: withCtx((_, _push, _parent, _scopeId) => {
      if (_push) {
        _push(`Блог`);
      } else {
        return [
          createTextVNode("Блог")
        ]
      }
    }),
    _: 1
  }, _parent));
  _push(`</div></div></div><footer class="mobile-contacts-footer" data-v-9ea30107><div class="mobile-studio-credit" data-v-9ea30107><img src="/assets/figma-studio-logo.svg" alt="" data-v-9ea30107><span data-v-9ea30107>сайт сделан в студии «созерцание»</span></div></footer></div></div></section>`);
}
}

};
const _sfc_setup = _sfc_main.setup;
_sfc_main.setup = (props, ctx) => {
  const ssrContext = useSSRContext()
  ;(ssrContext.modules || (ssrContext.modules = new Set())).add("resources/js/Components/FigmaContactsSlide.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : undefined
};
const FigmaContactsSlide = /*#__PURE__*/_export_sfc(_sfc_main, [['__scopeId',"data-v-9ea30107"]]);

export { FigmaContactsSlide as default };
