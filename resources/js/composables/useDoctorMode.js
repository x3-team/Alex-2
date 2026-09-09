import { computed, ref, watch } from 'vue';
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

function pathLooksLikeDoctors(url) {
    const path = String(url || '').split('?')[0];

    return path === '/doctors' || path.startsWith('/doctors/');
}

/**
 * Site mode comes from Inertia `site.isDoctorsSite` (Host doc.* or /doctors).
 * The old alex-audience cookie is no longer authoritative — kept only so
 * production pages that still destructure audienceCookie / toggleAudienceMode
 * do not crash after this file is copied onto the VPS.
 */
export function useDoctorMode() {
    const page = usePage();
    const audienceCookie = ref('patients');

    const isDoctorMode = computed(() => {
        const site = page.props.site ?? {};

        if (typeof site.isDoctorsSite === 'boolean') {
            return site.isDoctorsSite;
        }

        return pathLooksLikeDoctors(page.url);
    });

    const themeColor = computed(() => {
        const site = page.props.site ?? {};

        return isDoctorMode.value ? site.themeColor || '#cba98e' : null;
    });

    const routePrefix = computed(() => {
        const site = page.props.site ?? {};

        if (typeof site.routePrefix === 'string') {
            return site.routePrefix;
        }

        return isDoctorMode.value && pathLooksLikeDoctors(page.url) ? '/doctors' : '';
    });

    const doctorsUrl = (path = '/') => {
        const normalized = path.startsWith('/') ? path : `/${path}`;

        if (page.props.site?.isDoctorsHost) {
            return normalized;
        }

        const prefix = String(routePrefix.value || (isDoctorMode.value ? '/doctors' : '')).replace(/\/$/, '');

        if (!prefix) {
            if (isDoctorMode.value || pathLooksLikeDoctors(page.url)) {
                return normalized === '/' ? '/doctors' : `/doctors${normalized}`;
            }

            return normalized;
        }

        return normalized === '/' ? prefix : `${prefix}${normalized}`;
    };

    const toggleAudienceMode = () => {
        // No-op: mode is Host/path, not a sticky cookie.
    };

    watch(
        isDoctorMode,
        (value) => {
            audienceCookie.value = value ? 'doctors' : 'patients';
            applyDoctorBodyClass(value);
        },
        { immediate: true },
    );

    return {
        audienceCookie,
        isDoctorMode,
        themeColor,
        routePrefix,
        doctorsUrl,
        toggleAudienceMode,
    };
}
