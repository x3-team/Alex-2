import { ssrRenderTeleport, ssrRenderStyle } from 'vue/server-renderer';
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick, useSSRContext } from 'vue';
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
  __name: 'DoctorBlogTransition',
  __ssrInlineRender: true,
  props: {
  active: {
    type: Boolean,
    default: false,
  },
  durationMs: {
    type: Number,
    default: 1100,
  },
},
  emits: ['complete'],
  setup(__props, { emit: __emit }) {

const props = __props;

const emit = __emit;

const overlay = ref(null);
const prefersReducedMotion = ref(false);

let completedForActivation = false;
let fallbackTimer = null;
let motionPreferenceQuery = null;
let previouslyFocusedElement = null;

const normalizedDurationMs = computed(() => (
    Number.isFinite(props.durationMs) && props.durationMs > 0
        ? props.durationMs
        : 1100
));

const effectiveDurationMs = computed(() => (
    prefersReducedMotion.value
        ? Math.min(normalizedDurationMs.value, 180)
        : normalizedDurationMs.value
));

const overlayStyle = computed(() => ({
  '--doctor-blog-transition-duration': `${effectiveDurationMs.value}ms`,
}));

const clearFallbackTimer = () => {
  if (fallbackTimer === null || typeof window === 'undefined') {
    return
  }

  window.clearTimeout(fallbackTimer);
  fallbackTimer = null;
};

const completeTransition = () => {
  if (!props.active || completedForActivation) {
    return
  }

  completedForActivation = true;
  clearFallbackTimer();
  emit('complete');
};

const scheduleFallback = () => {
  clearFallbackTimer();

  if (typeof window === 'undefined') {
    return
  }

  const bufferMs = prefersReducedMotion.value ? 80 : 180;
  fallbackTimer = window.setTimeout(
      completeTransition,
      effectiveDurationMs.value + bufferMs,
  );
};

const focusOverlay = async () => {
  if (typeof document === 'undefined') {
    return
  }

  previouslyFocusedElement = document.activeElement instanceof HTMLElement
      ? document.activeElement
      : null;

  await nextTick();

  if (props.active) {
    overlay.value?.focus({ preventScroll: true });
  }
};

const restorePreviousFocus = async () => {
  const elementToRestore = previouslyFocusedElement;
  previouslyFocusedElement = null;

  if (!elementToRestore || typeof document === 'undefined') {
    return
  }

  await nextTick();

  if (!props.active && document.contains(elementToRestore)) {
    elementToRestore.focus({ preventScroll: true });
  }
};

const syncMotionPreference = (event) => {
  prefersReducedMotion.value = event.matches;
};

watch(
    () => props.active,
    (isActive) => {
      clearFallbackTimer();

      if (isActive) {
        completedForActivation = false;
        focusOverlay();
        scheduleFallback();
        return
      }

      completedForActivation = false;
      restorePreviousFocus();
    },
    { immediate: true },
);

watch(effectiveDurationMs, () => {
  if (props.active && !completedForActivation) {
    scheduleFallback();
  }
});

onMounted(() => {
  motionPreferenceQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
  prefersReducedMotion.value = motionPreferenceQuery.matches;

  if (typeof motionPreferenceQuery.addEventListener === 'function') {
    motionPreferenceQuery.addEventListener('change', syncMotionPreference);
  } else {
    motionPreferenceQuery.addListener(syncMotionPreference);
  }
});

onBeforeUnmount(() => {
  clearFallbackTimer();

  if (!motionPreferenceQuery) {
    return
  }

  if (typeof motionPreferenceQuery.removeEventListener === 'function') {
    motionPreferenceQuery.removeEventListener('change', syncMotionPreference);
  } else {
    motionPreferenceQuery.removeListener(syncMotionPreference);
  }
});

return (_ctx, _push, _parent, _attrs) => {
  ssrRenderTeleport(_push, (_push) => {
    if (__props.active) {
      _push(`<div class="doctor-blog-transition" style="${ssrRenderStyle(overlayStyle.value)}" aria-busy="true" tabindex="-1" data-v-0642e346><span class="doctor-blog-transition__status" role="status" aria-live="polite" data-v-0642e346> Переходим в блог </span><div class="doctor-blog-transition__wash" aria-hidden="true" data-v-0642e346></div><svg class="doctor-blog-transition__mark" viewBox="0 0 240 260" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" data-v-0642e346><defs data-v-0642e346><clipPath id="doctor-blog-transition-flask-clip" data-v-0642e346><path d="M101 31h38v57l46 103c8 18-5 38-25 38H80c-20 0-33-20-25-38l46-103V31Z" data-v-0642e346></path></clipPath></defs><g class="doctor-blog-transition__flask" data-v-0642e346><g clip-path="url(#doctor-blog-transition-flask-clip)" data-v-0642e346><rect class="doctor-blog-transition__liquid" x="48" y="143" width="144" height="92" data-v-0642e346></rect><path class="doctor-blog-transition__liquid-surface" d="M47 150c17-12 31 9 49 0s31-8 48 0 31-8 49 0v12H47v-12Z" data-v-0642e346></path><circle class="doctor-blog-transition__bubble doctor-blog-transition__bubble--one" cx="91" cy="191" r="7" data-v-0642e346></circle><circle class="doctor-blog-transition__bubble doctor-blog-transition__bubble--two" cx="135" cy="204" r="5" data-v-0642e346></circle><circle class="doctor-blog-transition__bubble doctor-blog-transition__bubble--three" cx="158" cy="178" r="4" data-v-0642e346></circle></g><path class="doctor-blog-transition__outline" d="M101 31h38v57l46 103c8 18-5 38-25 38H80c-20 0-33-20-25-38l46-103V31Z" data-v-0642e346></path><path class="doctor-blog-transition__rim" d="M94 31h52" data-v-0642e346></path></g></svg></div>`);
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
  ;(ssrContext.modules || (ssrContext.modules = new Set())).add("resources/js/Components/DoctorBlogTransition.vue");
  return _sfc_setup ? _sfc_setup(props, ctx) : undefined
};
const DoctorBlogTransition = /*#__PURE__*/_export_sfc(_sfc_main, [['__scopeId',"data-v-0642e346"]]);

export { DoctorBlogTransition as default };
