/**
 * ParticleField — volumetric bokeh particles on a 2D canvas.
 *
 * Motion is split into two independent layers so the two behaviours never fight
 * each other:
 *   1. Ambient drift — a slow flow field, always active, never damped.
 *   2. Pointer wind — extra velocity injected by pointer movement that decays
 *      back to zero, which is what makes the field glide to a stop instead of
 *      snapping.
 *
 * Depth (`z`) drives size, focus and how strongly a particle answers the wind,
 * which is what reads as volume.
 */

const TAU = Math.PI * 2;

/** Depth that renders perfectly sharp; everything else defocuses towards bokeh. */
const FOCAL_PLANE = 0.62;

/** Pre-rendered blur steps. More steps = smoother focus falloff, more memory. */
const SOFTNESS_STEPS = 6;

/** Sprites are authored at this size and scaled down per particle. */
const SPRITE_SIZE = 128;

const PALETTE = [
  { rgb: [255, 237, 203], weight: 3 },
  { rgb: [246, 210, 150], weight: 3 },
  { rgb: [231, 187, 126], weight: 2 },
  { rgb: [215, 232, 246], weight: 2 },
  { rgb: [255, 253, 246], weight: 1 },
];

const DEFAULTS = {
  /** One particle per N css pixels of viewport area. */
  areaPerParticle: 9200,
  minParticles: 46,
  maxParticles: 190,
  /** Share of particles rendered as 4-point sparkles. */
  sparkleRatio: 0.05,
  /** Seconds for a particle to take up / shed pointer wind. Higher = more glide. */
  responseTau: 1.5,
  /** Seconds of smoothing on raw pointer velocity. Higher = softer onset. */
  pointerTau: 0.35,
  /** Fraction of pointer speed handed to the field. */
  coupling: 0.16,
  /** Ceiling on wind speed (css px/s) so fast flicks stay graceful. */
  maxWind: 260,
  /** Radius (css px) inside which the pointer also swirls particles locally. */
  swirlRadius: 260,
  dprCap: 2,
};

const lerp = (a, b, t) => a + (b - a) * t;
const clamp = (v, min, max) => (v < min ? min : v > max ? max : v);

/**
 * Frame-rate independent exponential smoothing: pulls `current` towards
 * `target` with time constant `tau`.
 */
const smooth = (current, target, tau, dt) =>
  current + (target - current) * (1 - Math.exp(-dt / tau));

const randomIn = (min, max) => min + Math.random() * (max - min);

function pickPaletteIndex() {
  const total = PALETTE.reduce((sum, entry) => sum + entry.weight, 0);
  let roll = Math.random() * total;
  for (let i = 0; i < PALETTE.length; i += 1) {
    roll -= PALETTE[i].weight;
    if (roll <= 0) return i;
  }
  return PALETTE.length - 1;
}

/**
 * Soft round blob. `softness` 0 keeps a bright core with a tight halo, 1 spreads
 * it into a wide diffuse disc.
 */
function renderBlobSprite(rgb, softness) {
  const canvas = document.createElement('canvas');
  canvas.width = SPRITE_SIZE;
  canvas.height = SPRITE_SIZE;
  const ctx = canvas.getContext('2d');
  const mid = SPRITE_SIZE / 2;
  const [r, g, b] = rgb;

  const gradient = ctx.createRadialGradient(mid, mid, 0, mid, mid, mid);
  const core = lerp(0.34, 0.015, softness);
  const shoulder = lerp(0.62, 0.4, softness);

  gradient.addColorStop(0, `rgba(${r},${g},${b},1)`);
  gradient.addColorStop(core, `rgba(${r},${g},${b},${lerp(0.92, 0.5, softness)})`);
  gradient.addColorStop(shoulder, `rgba(${r},${g},${b},${lerp(0.32, 0.2, softness)})`);
  gradient.addColorStop(0.85, `rgba(${r},${g},${b},${lerp(0.06, 0.05, softness)})`);
  gradient.addColorStop(1, `rgba(${r},${g},${b},0)`);

  ctx.fillStyle = gradient;
  ctx.fillRect(0, 0, SPRITE_SIZE, SPRITE_SIZE);

  // Out-of-focus highlights keep a faintly brighter rim, like real bokeh.
  if (softness > 0.45) {
    const rim = ctx.createRadialGradient(mid, mid, mid * 0.52, mid, mid, mid * 0.84);
    rim.addColorStop(0, `rgba(${r},${g},${b},0)`);
    rim.addColorStop(0.72, `rgba(${r},${g},${b},${0.05 * softness})`);
    rim.addColorStop(1, `rgba(${r},${g},${b},0)`);
    ctx.fillStyle = rim;
    ctx.fillRect(0, 0, SPRITE_SIZE, SPRITE_SIZE);
  }

  return canvas;
}

/** Four-point star accent, matching the sparkle in the original comp. */
function renderSparkleSprite(rgb) {
  const canvas = document.createElement('canvas');
  canvas.width = SPRITE_SIZE;
  canvas.height = SPRITE_SIZE;
  const ctx = canvas.getContext('2d');
  const mid = SPRITE_SIZE / 2;
  const [r, g, b] = rgb;

  const glow = ctx.createRadialGradient(mid, mid, 0, mid, mid, mid * 0.42);
  glow.addColorStop(0, `rgba(${r},${g},${b},0.85)`);
  glow.addColorStop(0.45, `rgba(${r},${g},${b},0.16)`);
  glow.addColorStop(1, `rgba(${r},${g},${b},0)`);
  ctx.fillStyle = glow;
  ctx.fillRect(0, 0, SPRITE_SIZE, SPRITE_SIZE);

  ctx.translate(mid, mid);
  for (let i = 0; i < 2; i += 1) {
    const spike = ctx.createLinearGradient(-mid, 0, mid, 0);
    spike.addColorStop(0, `rgba(${r},${g},${b},0)`);
    spike.addColorStop(0.5, `rgba(${r},${g},${b},0.9)`);
    spike.addColorStop(1, `rgba(${r},${g},${b},0)`);
    ctx.fillStyle = spike;
    ctx.beginPath();
    ctx.moveTo(-mid, 0);
    ctx.quadraticCurveTo(0, -mid * 0.12, mid, 0);
    ctx.quadraticCurveTo(0, mid * 0.12, -mid, 0);
    ctx.fill();
    ctx.rotate(Math.PI / 2);
  }

  return canvas;
}

export class ParticleField {
  #canvas;
  #ctx;
  #options;
  #particles = [];
  #blobSprites = [];
  #sparkleSprites = [];
  #width = 0;
  #height = 0;
  #dpr = 1;
  #rafId = null;
  #lastFrame = 0;
  #elapsed = 0;
  #running = false;

  // Raw pointer sample, its smoothed velocity, and the resulting wind field.
  #pointer = { x: 0, y: 0, active: false, hasSample: false };
  #pointerVelocity = { x: 0, y: 0 };
  #wind = { x: 0, y: 0 };

  #resizeObserver = null;
  #boundHandlers = {};

  constructor(canvas, options = {}) {
    this.#canvas = canvas;
    this.#ctx = canvas.getContext('2d', { alpha: true });
    this.#options = { ...DEFAULTS, ...options };

    this.#buildSprites();
    this.#resize();
    this.#bindEvents();
  }

  #buildSprites() {
    this.#blobSprites = PALETTE.map(({ rgb }) => {
      const steps = [];
      for (let i = 0; i < SOFTNESS_STEPS; i += 1) {
        steps.push(renderBlobSprite(rgb, i / (SOFTNESS_STEPS - 1)));
      }
      return steps;
    });
    this.#sparkleSprites = PALETTE.map(({ rgb }) => renderSparkleSprite(rgb));
  }

  #bindEvents() {
    const onPointerMove = (event) => {
      const rect = this.#canvas.getBoundingClientRect();
      this.#samplePointer(event.clientX - rect.left, event.clientY - rect.top);
    };

    const onTouchMove = (event) => {
      const touch = event.touches[0];
      if (!touch) return;
      const rect = this.#canvas.getBoundingClientRect();
      this.#samplePointer(touch.clientX - rect.left, touch.clientY - rect.top);
    };

    const onPointerLeave = () => {
      this.#pointer.active = false;
      this.#pointer.hasSample = false;
    };

    const onVisibilityChange = () => {
      if (document.hidden) this.stop();
      else if (this.#running) this.start();
    };

    this.#boundHandlers = { onPointerMove, onTouchMove, onPointerLeave, onVisibilityChange };

    window.addEventListener('pointermove', onPointerMove, { passive: true });
    window.addEventListener('touchmove', onTouchMove, { passive: true });
    window.addEventListener('pointerleave', onPointerLeave);
    window.addEventListener('blur', onPointerLeave);
    document.addEventListener('visibilitychange', onVisibilityChange);

    if (typeof ResizeObserver === 'function') {
      this.#resizeObserver = new ResizeObserver(() => this.#resize());
      this.#resizeObserver.observe(this.#canvas);
    } else {
      window.addEventListener('resize', this.#resize.bind(this));
    }
  }

  /**
   * Records a pointer position and converts the delta into a velocity estimate.
   * Velocity is clamped before smoothing so a single fast flick cannot spike the
   * whole field.
   */
  #samplePointer(x, y) {
    if (this.#pointer.hasSample) {
      const dx = x - this.#pointer.x;
      const dy = y - this.#pointer.y;
      // Per-event delta, converted to px/s using a nominal 60fps interval. The
      // smoothing pass below is what actually shapes the response curve.
      this.#pointerVelocity.x = clamp(dx * 60, -2400, 2400);
      this.#pointerVelocity.y = clamp(dy * 60, -2400, 2400);
    }
    this.#pointer.x = x;
    this.#pointer.y = y;
    this.#pointer.active = true;
    this.#pointer.hasSample = true;
  }

  #resize() {
    const rect = this.#canvas.getBoundingClientRect();
    const width = Math.max(1, Math.round(rect.width || window.innerWidth));
    const height = Math.max(1, Math.round(rect.height || window.innerHeight));
    const dpr = Math.min(window.devicePixelRatio || 1, this.#options.dprCap);

    if (width === this.#width && height === this.#height && dpr === this.#dpr) return;

    const isFirstLayout = this.#width === 0;
    const scaleX = isFirstLayout ? 1 : width / this.#width;
    const scaleY = isFirstLayout ? 1 : height / this.#height;

    this.#width = width;
    this.#height = height;
    this.#dpr = dpr;

    this.#canvas.width = Math.round(width * dpr);
    this.#canvas.height = Math.round(height * dpr);
    this.#ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    if (isFirstLayout) {
      this.#spawn();
    } else {
      // Keep the existing field and rescale it, so a resize never restarts the
      // animation from a fresh random layout.
      for (const particle of this.#particles) {
        particle.x *= scaleX;
        particle.y *= scaleY;
      }
      this.#reconcileCount();
    }

    if (!this.#running) this.#draw();
  }

  #targetCount() {
    const { areaPerParticle, minParticles, maxParticles } = this.#options;
    const raw = Math.round((this.#width * this.#height) / areaPerParticle);
    return clamp(raw, minParticles, maxParticles);
  }

  #createParticle() {
    const paletteIndex = pickPaletteIndex();
    const z = Math.random();
    return {
      x: Math.random() * this.#width,
      y: Math.random() * this.#height,
      z,
      paletteIndex,
      // Base radius in css px before depth scaling.
      radius: randomIn(9, 30),
      alpha: randomIn(0.55, 1),
      // Constant personal drift so particles never move in lockstep. Tuned so
      // the field is legibly alive with the pointer idle — much slower than
      // this and it reads as a still image.
      driftX: randomIn(-26, 26),
      driftY: randomIn(-28, 14),
      // Depth drift. Moving through the focal plane changes size, blur and
      // opacity at once, so it registers far more than lateral travel does and
      // it is what makes the field feel like a volume rather than a plane.
      driftZ: randomIn(-0.04, 0.04),
      // Wind-induced velocity, decays back to zero.
      windX: 0,
      windY: 0,
      phase: Math.random() * TAU,
      wobbleRate: randomIn(0.05, 0.16),
      isSparkle: Math.random() < this.#options.sparkleRatio,
      twinkleRate: randomIn(0.25, 0.6),
    };
  }

  #spawn() {
    const count = this.#targetCount();
    this.#particles = Array.from({ length: count }, () => this.#createParticle());
    this.#sortByDepth();
  }

  #reconcileCount() {
    const target = this.#targetCount();
    while (this.#particles.length < target) this.#particles.push(this.#createParticle());
    if (this.#particles.length > target) this.#particles.length = target;
    this.#sortByDepth();
  }

  /** Painter's algorithm: distant particles first so near ones layer over them. */
  #sortByDepth() {
    this.#particles.sort((a, b) => a.z - b.z);
  }

  #update(dt) {
    const opts = this.#options;
    this.#elapsed += dt;

    // Pointer velocity decays whenever no fresh sample arrives, so simply
    // holding the cursor still lets the field settle.
    this.#pointerVelocity.x = smooth(this.#pointerVelocity.x, 0, opts.pointerTau, dt);
    this.#pointerVelocity.y = smooth(this.#pointerVelocity.y, 0, opts.pointerTau, dt);

    const targetWindX = clamp(this.#pointerVelocity.x * opts.coupling, -opts.maxWind, opts.maxWind);
    const targetWindY = clamp(this.#pointerVelocity.y * opts.coupling, -opts.maxWind, opts.maxWind);
    this.#wind.x = smooth(this.#wind.x, targetWindX, opts.pointerTau, dt);
    this.#wind.y = smooth(this.#wind.y, targetWindY, opts.pointerTau, dt);

    const swirlRadius = opts.swirlRadius;
    const swirlRadiusSq = swirlRadius * swirlRadius;
    const pointerSpeed = Math.hypot(this.#wind.x, this.#wind.y);
    const margin = 140;
    const t = this.#elapsed;

    for (const p of this.#particles) {
      // Near particles answer the wind more than distant ones, which sells depth.
      const depthResponse = 0.35 + p.z * 1.15;
      const targetWX = this.#wind.x * depthResponse;
      const targetWY = this.#wind.y * depthResponse;

      p.windX = smooth(p.windX, targetWX, opts.responseTau, dt);
      p.windY = smooth(p.windY, targetWY, opts.responseTau, dt);

      // Local swirl: pushes particles out of the cursor's path and adds a slight
      // tangential curl so the disturbance rolls rather than shoves.
      if (this.#pointer.active && pointerSpeed > 6) {
        const dx = p.x - this.#pointer.x;
        const dy = p.y - this.#pointer.y;
        const distSq = dx * dx + dy * dy;
        if (distSq < swirlRadiusSq && distSq > 0.01) {
          const dist = Math.sqrt(distSq);
          const falloff = (1 - dist / swirlRadius) ** 2;
          const push = falloff * pointerSpeed * 0.5 * depthResponse;
          p.windX += ((dx / dist) * push - (dy / dist) * push * 0.35) * dt;
          p.windY += ((dy / dist) * push + (dx / dist) * push * 0.35) * dt;
        }
      }

      // Ambient flow field — large, slow sinusoids for organic wandering.
      const flowX = Math.sin(p.y * 0.0016 + t * 0.09 + p.phase) * 18;
      const flowY = Math.cos(p.x * 0.0014 - t * 0.07 + p.phase) * 15;
      const wobble = Math.sin(t * p.wobbleRate + p.phase) * 6;

      p.x += (p.driftX + flowX + wobble + p.windX) * dt;
      p.y += (p.driftY + flowY + p.windY) * dt;

      // Bounce off the depth limits so particles never pop between planes.
      p.z += p.driftZ * dt;
      if (p.z < 0.02) {
        p.z = 0.02;
        p.driftZ = Math.abs(p.driftZ);
      } else if (p.z > 0.99) {
        p.z = 0.99;
        p.driftZ = -Math.abs(p.driftZ);
      }

      // Wrap with a margin so particles fade in off-screen rather than popping.
      if (p.x < -margin) p.x = this.#width + margin;
      else if (p.x > this.#width + margin) p.x = -margin;
      if (p.y < -margin) p.y = this.#height + margin;
      else if (p.y > this.#height + margin) p.y = -margin;
    }

    // Depth drift invalidates the paint order. The array stays nearly sorted
    // between frames, so this is cheap at these counts.
    this.#sortByDepth();
  }

  #draw() {
    const ctx = this.#ctx;
    ctx.clearRect(0, 0, this.#width, this.#height);
    // `screen` keeps overlapping glows luminous without clipping to pure white
    // the way additive blending would.
    ctx.globalCompositeOperation = 'screen';

    const t = this.#elapsed;

    for (const p of this.#particles) {
      const focusDistance = Math.abs(p.z - FOCAL_PLANE) / FOCAL_PLANE;
      const sharpness = 1 - clamp(focusDistance, 0, 1);
      const softness = 1 - sharpness;

      // Quadratic depth scaling reads as stronger perspective than linear.
      const size = p.radius * (0.3 + p.z * p.z * 2.4) * (softness * 1.6 + 1);
      let alpha = p.alpha * (0.14 + 0.74 * sharpness);

      // Slow opacity breathing. Small in amplitude, but it keeps the field
      // reading as alive even where lateral travel is barely perceptible.
      alpha *= 0.82 + 0.18 * Math.sin(t * p.wobbleRate * 2.4 + p.phase);

      if (p.isSparkle) {
        alpha *= 0.55 + 0.45 * Math.sin(t * p.twinkleRate + p.phase);
      }

      if (alpha <= 0.004 || size < 0.6) continue;

      const sprite = p.isSparkle
        ? this.#sparkleSprites[p.paletteIndex]
        : this.#blobSprites[p.paletteIndex][
            Math.min(SOFTNESS_STEPS - 1, Math.round(softness * (SOFTNESS_STEPS - 1)))
          ];

      const drawSize = p.isSparkle ? size * 2.1 : size * 2;
      ctx.globalAlpha = clamp(alpha, 0, 1);
      ctx.drawImage(sprite, p.x - drawSize / 2, p.y - drawSize / 2, drawSize, drawSize);
    }

    ctx.globalAlpha = 1;
    ctx.globalCompositeOperation = 'source-over';
  }

  #tick = (now) => {
    if (!this.#running) return;
    // Clamp dt so a backgrounded tab does not teleport the whole field.
    const dt = Math.min((now - this.#lastFrame) / 1000, 1 / 20);
    this.#lastFrame = now;

    if (dt > 0) {
      this.#update(dt);
      this.#draw();
    }
    this.#rafId = requestAnimationFrame(this.#tick);
  };

  start() {
    if (this.#rafId !== null) return;
    this.#running = true;
    this.#lastFrame = performance.now();
    this.#rafId = requestAnimationFrame(this.#tick);
  }

  stop() {
    if (this.#rafId !== null) {
      cancelAnimationFrame(this.#rafId);
      this.#rafId = null;
    }
  }

  /** Single static frame, for users who asked for reduced motion. */
  renderStaticFrame() {
    this.#update(0.016);
    this.#draw();
  }

  destroy() {
    this.stop();
    this.#running = false;
    const h = this.#boundHandlers;
    window.removeEventListener('pointermove', h.onPointerMove);
    window.removeEventListener('touchmove', h.onTouchMove);
    window.removeEventListener('pointerleave', h.onPointerLeave);
    window.removeEventListener('blur', h.onPointerLeave);
    document.removeEventListener('visibilitychange', h.onVisibilityChange);
    this.#resizeObserver?.disconnect();
    this.#particles = [];
  }
}
