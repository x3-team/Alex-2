import { onBeforeUnmount, onMounted, shallowRef } from 'vue';

import { ParticleField } from '@/lib/particle-field.js';

/**
 * Drives a ParticleField over the lifetime of a component.
 *
 * The field is constructed in onMounted rather than at setup time so the engine
 * never touches `document` during server-side rendering.
 *
 * @param {import('vue').Ref<HTMLCanvasElement | null>} canvasRef
 * @param {object} [options] forwarded to the ParticleField constructor
 */
export function useParticleField(canvasRef, options = {}) {
  // shallowRef, not ref: the engine is a class with private fields and a deep
  // reactive proxy around it would break member access.
  const field = shallowRef(null);

  let motionQuery = null;
  let syncMotionPreference = null;

  onMounted(() => {
    if (!canvasRef.value) return;

    const instance = new ParticleField(canvasRef.value, options);
    field.value = instance;

    motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    syncMotionPreference = () => {
      if (motionQuery.matches) {
        instance.stop();
        // Still paint one frame, so the backdrop is composed rather than blank.
        instance.renderStaticFrame();
      } else {
        instance.start();
      }
    };

    syncMotionPreference();
    motionQuery.addEventListener('change', syncMotionPreference);
  });

  onBeforeUnmount(() => {
    if (motionQuery && syncMotionPreference) {
      motionQuery.removeEventListener('change', syncMotionPreference);
    }
    field.value?.destroy();
    field.value = null;
  });

  return { field };
}
