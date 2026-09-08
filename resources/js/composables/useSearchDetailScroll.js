import { nextTick } from 'vue';

const DEFAULT_OFFSET_PX = 8;

/**
 * Scroll the search page's main column so the allergen detail header is visible.
 * `.search-main` is the scroll container on mobile; window scroll is not used.
 */
export function useSearchDetailScroll(searchMainRef, detailAnchorRef, offsetPx = DEFAULT_OFFSET_PX) {
    const scrollToDetail = () => {
        nextTick(() => {
            requestAnimationFrame(() => {
                const container = searchMainRef.value;
                const target = detailAnchorRef.value;
                if (!container || !target) {
                    return;
                }

                const containerTop = container.getBoundingClientRect().top;
                const targetTop = target.getBoundingClientRect().top;
                const nextTop = container.scrollTop + (targetTop - containerTop) - offsetPx;

                container.scrollTo({
                    top: Math.max(0, nextTop),
                    behavior: 'auto',
                });
            });
        });
    };

    return { scrollToDetail };
}
