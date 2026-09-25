import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Absolute site origin for the current contour (patient apex or doc subdomain).
 */
export function usePublicSiteUrl() {
    const page = usePage();

    return computed(() => {
        const site = page.props.site ?? {};
        const fromShare = typeof site.publicOrigin === 'string' ? site.publicOrigin.trim() : '';
        if (fromShare !== '') {
            return fromShare.replace(/\/$/, '');
        }

        if (site.isDoctorsSite && typeof site.doctorsOrigin === 'string' && site.doctorsOrigin !== '') {
            return site.doctorsOrigin.replace(/\/$/, '');
        }

        const ziggyUrl = page.props.ziggy?.url;
        if (typeof ziggyUrl === 'string' && ziggyUrl !== '') {
            return ziggyUrl.replace(/\/$/, '');
        }

        return 'https://alexallergotest.ru';
    });
}
