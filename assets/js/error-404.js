import { ParticleField } from './particle-field.js';

const canvas = document.getElementById('particle-canvas');

if (canvas) {
  const field = new ParticleField(canvas);
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  const applyMotionPreference = () => {
    if (reducedMotion.matches) {
      field.stop();
      field.renderStaticFrame();
    } else {
      field.start();
    }
  };

  applyMotionPreference();
  reducedMotion.addEventListener('change', applyMotionPreference);
}
