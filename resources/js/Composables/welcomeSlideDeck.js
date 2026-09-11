/**
 * Patient vs doctor home-deck geometry.
 *
 * Patients keep 5 advantage slides (slide-3…slide-7, including «Подходит детям»).
 * Doctors keep 4 (slide-3…slide-6). Index ranges after advantages shift by one
 * when slide-7 is omitted, so callers must use these helpers instead of a
 * single shared 4-step list.
 */

export function advantageStepTotal(isDoctorMode) {
    return isDoctorMode ? 4 : 5;
}

export function shouldIncludePatientSlide7(isDoctorMode) {
    return !isDoctorMode;
}

export const PATIENT_SLIDE_7_VIDEOS = Object.freeze({
    video: '/videos/PC/s6-v20.webm',
    reverseVideo: '/videos/PC/s6r-v20.webm',
});

export function indexBySlideId(slides) {
    const map = Object.create(null);
    slides.forEach((slide, index) => {
        if (slide?.id) {
            map[slide.id] = index;
        }
    });
    return map;
}

export function getSlideDeckRanges(slides, isDoctorMode) {
    const idx = indexBySlideId(slides);
    const firstAdvantage = idx['slide-3'];
    const lastAdvantage = isDoctorMode ? idx['slide-6'] : idx['slide-7'];
    const resultsIntro = idx['slide-8'];
    const firstResult = idx['slide-9'];
    const lastResult = idx['slide-11'];
    const faq = idx['slide-16'];
    const blog = idx['slide-17'];
    const firstHowTo = idx['slide-18'];
    const lastHowTo = idx['slide-20'];
    const contacts = idx['slide-21'];

    return {
        firstAdvantage,
        lastAdvantage,
        resultsIntro,
        firstResult,
        lastResult,
        faq,
        blog,
        firstHowTo,
        lastHowTo,
        contacts,
        advantageTotal: lastAdvantage - firstAdvantage + 1,
        resultsTotal: lastResult - firstResult + 1,
        storyEnd: lastResult,
    };
}

export function inRange(index, start, end) {
    return index >= start && index <= end;
}

export function getPersistentStepGroup(index, ranges) {
    if (inRange(index, ranges.firstAdvantage, ranges.lastAdvantage)) {
        return {
            type: 'advantages',
            total: ranges.advantageTotal,
            current: index - ranges.firstAdvantage + 1,
        };
    }
    if (inRange(index, ranges.firstResult, ranges.lastResult)) {
        return {
            type: 'results',
            total: ranges.resultsTotal,
            current: index - ranges.firstResult + 1,
        };
    }
    return null;
}

export function getActiveMenuIndex(index, ranges) {
    if (inRange(index, ranges.firstAdvantage, ranges.lastAdvantage)) {
        return 0;
    }
    if (inRange(index, ranges.resultsIntro, ranges.lastResult)) {
        return 1;
    }
    if (index === ranges.faq) {
        return 2;
    }
    if (index === ranges.blog) {
        return 3;
    }
    if (inRange(index, ranges.firstHowTo, ranges.lastHowTo)) {
        return 4;
    }
    if (index === ranges.contacts) {
        return 0;
    }
    return -1;
}

export function isHowToPassIndex(index, ranges) {
    return inRange(index, ranges.firstHowTo, ranges.lastHowTo);
}

export function isResultsCloudIndex(index, ranges) {
    return inRange(index, ranges.firstResult, ranges.lastResult);
}

export function resultsCloudClassForIndex(index, ranges) {
    if (index === ranges.resultsIntro) {
        return 'results-intro-cloud';
    }
    if (isResultsCloudIndex(index, ranges)) {
        return 'results-cloud-primary';
    }
    return '';
}

export function isLargeMobileCloudIndex(index, ranges) {
    return (
        index === 0 ||
        inRange(index, ranges.firstAdvantage, ranges.lastAdvantage) ||
        inRange(index, ranges.firstResult, ranges.lastResult)
    );
}

export function isStoryBackdropIndex(index, ranges) {
    return inRange(index, ranges.firstAdvantage, ranges.storyEnd);
}

export function isDoctorSmokeIndex(index, ranges) {
    return (
        inRange(index, ranges.firstAdvantage, ranges.lastAdvantage) ||
        inRange(index, ranges.resultsIntro, ranges.lastResult)
    );
}
