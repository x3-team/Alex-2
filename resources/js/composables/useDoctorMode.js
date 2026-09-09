import { computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

function applyDoctorBodyClass(isDoctorMode) {
    if (typeof document === 'undefined') {
        return;
    }

    if (isDoctorMode) {
        document.body.classList.add('doctor-mode');
        document.body.setAttribute('data-audience', 'doctors');
    } else {
        document.body.classList.remove('doctor-mode');
        document.body.setAttribute('data-audience', 'patients');
    }
}

export function useDoctorMode() {
    const page = usePage();

    const isDoctorMode = computed(() => {
        const site = page.props.site ?? {};

        if (typeof site.isDoctorsSite === 'boolean') {
            return site.isDoctorsSite;
        }

        // Fallback for pages that only expose URL (path preview without shared props yet).
        const path = String(page.url || '').split('?')[0];

        return path === '/doctors' || path.startsWith('/doctors/');
    });

    const themeColor = computed(() => {
        const site = page.props.site ?? {};

        return isDoctorMode.value
            ? site.themeColor || '#cba98e'
            : null;
    });

    const routePrefix = computed(() => {
        const site = page.props.site ?? {};

        return site.routePrefix || (isDoctorMode.value ? '/doctors' : '');
    });

    const doctorsUrl = (path = '/') => {
        const normalized = path.startsWith('/') ? path : `/${path}`;

        if (!isDoctorMode.value) {
            return normalized;
        }

        const prefix = routePrefix.value.replace(/\/$/, '');

        if (!prefix) {
            return normalized;
        }

        return normalized === '/'
            ? prefix
            : `${prefix}${normalized}`;
    };

    watch(
        isDoctorMode,
        (value) => applyDoctorBodyClass(value),
        { immediate: true },
    );

    return {
        isDoctorMode,
        themeColor,
        routePrefix,
        doctorsUrl,
    };
}
