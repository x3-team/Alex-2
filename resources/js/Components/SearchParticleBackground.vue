<template>
    <canvas
        ref="canvasRef"
        class="search-particle-background"
        aria-hidden="true"
    />
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

const canvasRef = ref(null);

const pointerTarget = { x: 0.62, y: 0.36 };
const textSafeRect = { x0: 200, x1: 840, y0: 210, y1: 480 };

const PARTICLE_TEXTURE_SIZE = 351;
const MOBILE_PARTICLE_COUNT = 351;
const DESKTOP_PARTICLE_COUNT = 351;
const MOBILE_BREAKPOINT = 768;
const POINTER_RADIUS = 210;
const POINTER_PUSH = 1.05;
const POINTER_SWIRL = 0.85;
const WRAP_PADDING = 40;

let hostEl = null;
let ctx = null;
let animationFrame = null;
let resizeObserver = null;
let intersectionObserver = null;
let reduceMotionQuery = null;
let finePointerQuery = null;
let width = 1243;
let height = 525;
let time = 0;
let lastFrame = 0;
let isVisible = true;
let pointer = { x: null, y: null };
let particles = [];
let textures = null;

const rand = (seed, salt) =>
    Math.abs(Math.sin(seed * 12.9898 + salt * 78.233) * 43758.5453) % 1;

const createTexture = (rgb, blur, fade) => {
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = PARTICLE_TEXTURE_SIZE;
    const context = canvas.getContext('2d');
    if (!context) {
        return canvas;
    }

    const gradient = context.createRadialGradient(
        PARTICLE_TEXTURE_SIZE / 2,
        PARTICLE_TEXTURE_SIZE / 2,
        0,
        PARTICLE_TEXTURE_SIZE / 2,
        PARTICLE_TEXTURE_SIZE / 2,
        PARTICLE_TEXTURE_SIZE / 2,
    );
    gradient.addColorStop(0, `rgba(${rgb}, 1)`);
    gradient.addColorStop(fade, `rgba(${rgb}, .55)`);
    gradient.addColorStop(1, `rgba(${rgb}, 0)`);
    context.filter = blur ? `blur(${blur}px)` : 'none';
    context.fillStyle = gradient;
    context.beginPath();
    context.arc(PARTICLE_TEXTURE_SIZE / 2, PARTICLE_TEXTURE_SIZE / 2, PARTICLE_TEXTURE_SIZE / 2 - blur * 2, 0, Math.PI * 2);
    context.fill();
    return canvas;
};

const createTextures = () => ({
    sage: [
        createTexture('150, 170, 134', 0, 0.42),
        createTexture('150, 170, 134', 4, 0.34),
        createTexture('150, 170, 134', 10, 0.28),
    ],
    deep: [
        createTexture('108, 132, 84', 0, 0.42),
        createTexture('108, 132, 84', 4, 0.34),
        createTexture('108, 132, 84', 10, 0.28),
    ],
});

const particleCount = () => (width < MOBILE_BREAKPOINT ? MOBILE_PARTICLE_COUNT : DESKTOP_PARTICLE_COUNT);

const initParticles = () => {
    particles = Array.from({ length: particleCount() }, (_, index) => {
        const depth = rand(index, 3);
        return {
            x: rand(index, 1) * width,
            y: rand(index, 2) * height,
            vx: 0,
            vy: 0,
            r: 1.2 + depth * 5.2,
            depth,
            tier: depth < 0.45 ? 0 : depth < 0.82 ? 1 : 2,
            speed: 0.22 + depth * 0.58,
            warm: rand(index, 8) > 0.8,
            wobble: 0.35 + rand(index, 9) * 0.5,
            phase: rand(index, 10) * Math.PI * 2,
            glow: 0,
        };
    });
};

const edgeFade = (x, y) =>
    Math.max(0, Math.min(1, Math.min(x, y, width - x, height - y) / 60));

const textFade = (x, y) => {
    const scaleX = width / 1243;
    const scaleY = height / 525;
    const left = textSafeRect.x0 * scaleX;
    const right = textSafeRect.x1 * scaleX;
    const top = textSafeRect.y0 * scaleY;
    const bottom = textSafeRect.y1 * scaleY;
    const dx = Math.min(x - (left - 50), right + 50 - x);
    const dy = Math.min(y - (top - 40), bottom + 40 - y);
    if (dx <= 0 || dy <= 0) {
        return 1;
    }
    return 1 - 0.82 * Math.min(1, Math.min(dx / 50, dy / 40));
};

const draw = () => {
    if (!ctx || !textures) {
        return;
    }

    ctx.clearRect(0, 0, width, height);
    for (const particle of particles) {
        const alphaScale = textFade(particle.x, particle.y) * edgeFade(particle.x, particle.y);
        if (alphaScale <= 0.01) {
            continue;
        }

        const base = 0.15 + particle.depth * 0.2;
        const alpha = Math.min(0.55, (base + particle.glow * 0.22) * alphaScale);
        if (alpha < 0.012) {
            continue;
        }

        const size = particle.r * (2.6 + particle.tier * 0.9) * (1 + particle.glow * 0.16);
        ctx.globalAlpha = alpha;
        ctx.drawImage(
            textures[particle.warm ? 'deep' : 'sage'][particle.tier],
            particle.x - size,
            particle.y - size,
            size * 2,
            size * 2,
        );
    }
    ctx.globalAlpha = 1;
};

const updateParticles = (delta) => {
    const pointerActive = finePointerQuery?.matches && pointer.x !== null && pointer.y !== null;

    for (const particle of particles) {
        const targetVx = (pointerTarget.x + Math.sin(time * particle.wobble + particle.phase) * 0.22) * particle.speed;
        const targetVy = (pointerTarget.y + Math.cos(time * particle.wobble * 0.7 + particle.phase) * 0.16) * particle.speed;
        particle.vx += (targetVx - particle.vx) * 0.035;
        particle.vy += (targetVy - particle.vy) * 0.035;

        let glow = 0;
        if (pointerActive) {
            const dx = particle.x - pointer.x;
            const dy = particle.y - pointer.y;
            const distance = Math.hypot(dx, dy);
            if (distance < POINTER_RADIUS && distance > 0.001) {
                const strength = 1 - distance / POINTER_RADIUS;
                glow = strength;
                const push = POINTER_PUSH * strength ** 3 * (0.4 + particle.depth);
                const swirl = POINTER_SWIRL * strength ** 3;
                particle.vx += (dx / distance) * push + (-dy / distance) * swirl;
                particle.vy += (dy / distance) * push + (dx / distance) * swirl;
            }
        }

        particle.glow += (glow - particle.glow) * 0.07;
        particle.x += particle.vx * delta * 60;
        particle.y += particle.vy * delta * 60;

        const wrapWidth = width + WRAP_PADDING * 2;
        const wrapHeight = height + WRAP_PADDING * 2;
        particle.x = ((((particle.x + WRAP_PADDING) % wrapWidth) + wrapWidth) % wrapWidth) - WRAP_PADDING;
        particle.y = ((((particle.y + WRAP_PADDING) % wrapHeight) + wrapHeight) % wrapHeight) - WRAP_PADDING;
    }
};

const shouldAnimate = () =>
    !reduceMotionQuery?.matches && isVisible && !document.hidden && width > 0 && height > 0;

const stopAnimation = () => {
    if (animationFrame !== null) {
        cancelAnimationFrame(animationFrame);
    }
    animationFrame = null;
    lastFrame = 0;
};

const tick = (timestamp) => {
    if (!shouldAnimate()) {
        stopAnimation();
        return;
    }

    const delta = Math.min((timestamp - (lastFrame || timestamp)) / 1000, 0.05);
    lastFrame = timestamp;
    time += delta;
    updateParticles(delta);
    draw();
    animationFrame = requestAnimationFrame(tick);
};

const startAnimation = () => {
    stopAnimation();
    draw();
    if (shouldAnimate()) {
        animationFrame = requestAnimationFrame(tick);
    }
};

const resize = () => {
    if (!canvasRef.value || !hostEl || !ctx) {
        return;
    }

    const rect = hostEl.getBoundingClientRect();
    const previousCount = particles.length;
    width = Math.max(0, rect.width);
    height = Math.max(0, rect.height);
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    canvasRef.value.width = Math.round(width * dpr);
    canvasRef.value.height = Math.round(height * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    if (!previousCount || previousCount !== particleCount()) {
        initParticles();
    }
    draw();
};

const onPointerMove = (event) => {
    if (!canvasRef.value || !finePointerQuery?.matches) {
        return;
    }
    const rect = canvasRef.value.getBoundingClientRect();
    pointer = {
        x: event.clientX - rect.left,
        y: event.clientY - rect.top,
    };
};

const onPointerLeave = () => {
    pointer = { x: null, y: null };
};

onMounted(() => {
    if (!canvasRef.value) {
        return;
    }

    hostEl = canvasRef.value.parentElement;
    ctx = canvasRef.value.getContext('2d');
    if (!hostEl || !ctx) {
        return;
    }

    reduceMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    finePointerQuery = window.matchMedia('(hover: hover) and (pointer: fine)');
    textures = createTextures();
    resize();

    hostEl.addEventListener('pointermove', onPointerMove);
    hostEl.addEventListener('pointerleave', onPointerLeave);
    window.addEventListener('resize', resize);
    document.addEventListener('visibilitychange', startAnimation);
    reduceMotionQuery.addEventListener('change', startAnimation);

    intersectionObserver = new IntersectionObserver(([entry]) => {
        isVisible = entry.isIntersecting;
        startAnimation();
    });
    intersectionObserver.observe(hostEl);

    resizeObserver = new ResizeObserver(resize);
    resizeObserver.observe(hostEl);
    startAnimation();
});

onUnmounted(() => {
    stopAnimation();
    intersectionObserver?.disconnect();
    resizeObserver?.disconnect();
    window.removeEventListener('resize', resize);
    document.removeEventListener('visibilitychange', startAnimation);
    reduceMotionQuery?.removeEventListener('change', startAnimation);
    hostEl?.removeEventListener('pointermove', onPointerMove);
    hostEl?.removeEventListener('pointerleave', onPointerLeave);
});
</script>

<style scoped>
.search-particle-background {
    position: absolute;
    inset: 0;
    display: block;
    width: 100%;
    height: 100%;
    pointer-events: none;
}
</style>
