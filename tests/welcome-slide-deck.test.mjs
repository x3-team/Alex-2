import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import {
    advantageStepTotal,
    getActiveMenuIndex,
    getPersistentStepGroup,
    getSlideDeckRanges,
    isDoctorSmokeIndex,
    isHowToPassIndex,
    isLargeMobileCloudIndex,
    isResultsCloudIndex,
    isStoryBackdropIndex,
    PATIENT_SLIDE_7_VIDEOS,
    resultsCloudClassForIndex,
    shouldIncludePatientSlide7,
} from '../resources/js/composables/welcomeSlideDeck.js';

const DECK_IDS_SHARED_HEAD = ['slide-1', 'slide-2', 'slide-3', 'slide-4', 'slide-5', 'slide-6'];
const DECK_IDS_TAIL = [
    'slide-8',
    'slide-9',
    'slide-10',
    'slide-11',
    'slide-16',
    'slide-17',
    'slide-18',
    'slide-19',
    'slide-20',
    'slide-21',
];

function buildDeck(isDoctorMode) {
    const ids = [...DECK_IDS_SHARED_HEAD];
    if (shouldIncludePatientSlide7(isDoctorMode)) {
        ids.push('slide-7');
    }
    ids.push(...DECK_IDS_TAIL);
    return ids.map((id) => ({ id }));
}

function expectedPatientRanges() {
    return {
        firstAdvantage: 2,
        lastAdvantage: 6,
        resultsIntro: 7,
        firstResult: 8,
        lastResult: 10,
        faq: 11,
        blog: 12,
        firstHowTo: 13,
        lastHowTo: 15,
        contacts: 16,
        advantageTotal: 5,
        resultsTotal: 3,
        storyEnd: 10,
    };
}

function expectedDoctorRanges() {
    return {
        firstAdvantage: 2,
        lastAdvantage: 5,
        resultsIntro: 6,
        firstResult: 7,
        lastResult: 9,
        faq: 10,
        blog: 11,
        firstHowTo: 12,
        lastHowTo: 14,
        contacts: 15,
        advantageTotal: 4,
        resultsTotal: 3,
        storyEnd: 9,
    };
}

assert.equal(advantageStepTotal(false), 5);
assert.equal(advantageStepTotal(true), 4);
assert.equal(shouldIncludePatientSlide7(false), true);
assert.equal(shouldIncludePatientSlide7(true), false);
assert.equal(PATIENT_SLIDE_7_VIDEOS.video, '/videos/PC/s6-v20.webm');
assert.equal(PATIENT_SLIDE_7_VIDEOS.reverseVideo, '/videos/PC/s6r-v20.webm');

const patientSlides = buildDeck(false);
const doctorSlides = buildDeck(true);
const patientRanges = getSlideDeckRanges(patientSlides, false);
const doctorRanges = getSlideDeckRanges(doctorSlides, true);

assert.deepEqual(
    patientSlides.filter((slide) => slide.id.startsWith('slide-') && Number(slide.id.slice(6)) <= 7).map((s) => s.id),
    ['slide-1', 'slide-2', 'slide-3', 'slide-4', 'slide-5', 'slide-6', 'slide-7'],
);
assert.equal(
    doctorSlides.some((slide) => slide.id === 'slide-7'),
    false,
);
assert.equal(patientSlides.length, 17);
assert.equal(doctorSlides.length, 16);

assert.deepEqual(patientRanges, expectedPatientRanges());
assert.deepEqual(doctorRanges, expectedDoctorRanges());

assert.deepEqual(getPersistentStepGroup(2, patientRanges), { type: 'advantages', total: 5, current: 1 });
assert.deepEqual(getPersistentStepGroup(6, patientRanges), { type: 'advantages', total: 5, current: 5 });
assert.equal(getPersistentStepGroup(7, patientRanges), null);
assert.deepEqual(getPersistentStepGroup(8, patientRanges), { type: 'results', total: 3, current: 1 });
assert.deepEqual(getPersistentStepGroup(10, patientRanges), { type: 'results', total: 3, current: 3 });

assert.deepEqual(getPersistentStepGroup(2, doctorRanges), { type: 'advantages', total: 4, current: 1 });
assert.deepEqual(getPersistentStepGroup(5, doctorRanges), { type: 'advantages', total: 4, current: 4 });
assert.equal(getPersistentStepGroup(6, doctorRanges), null);
assert.deepEqual(getPersistentStepGroup(7, doctorRanges), { type: 'results', total: 3, current: 1 });
assert.deepEqual(getPersistentStepGroup(9, doctorRanges), { type: 'results', total: 3, current: 3 });

assert.equal(getActiveMenuIndex(6, patientRanges), 0);
assert.equal(getActiveMenuIndex(7, patientRanges), 1);
assert.equal(getActiveMenuIndex(11, patientRanges), 2);
assert.equal(getActiveMenuIndex(5, doctorRanges), 0);
assert.equal(getActiveMenuIndex(6, doctorRanges), 1);
assert.equal(getActiveMenuIndex(10, doctorRanges), 2);

assert.equal(isStoryBackdropIndex(10, patientRanges), true);
assert.equal(isStoryBackdropIndex(11, patientRanges), false);
assert.equal(isStoryBackdropIndex(9, doctorRanges), true);
assert.equal(isStoryBackdropIndex(10, doctorRanges), false);

assert.equal(isDoctorSmokeIndex(5, doctorRanges), true);
assert.equal(isDoctorSmokeIndex(6, doctorRanges), true);
assert.equal(isDoctorSmokeIndex(9, doctorRanges), true);
assert.equal(isDoctorSmokeIndex(10, doctorRanges), false);

assert.equal(isResultsCloudIndex(8, patientRanges), true);
assert.equal(isResultsCloudIndex(7, patientRanges), false);
assert.equal(isResultsCloudIndex(7, doctorRanges), true);
assert.equal(resultsCloudClassForIndex(7, patientRanges), 'results-intro-cloud');
assert.equal(resultsCloudClassForIndex(6, doctorRanges), 'results-intro-cloud');
assert.equal(resultsCloudClassForIndex(8, patientRanges), 'results-cloud-primary');
assert.equal(resultsCloudClassForIndex(7, doctorRanges), 'results-cloud-primary');

assert.equal(isHowToPassIndex(13, patientRanges), true);
assert.equal(isHowToPassIndex(12, patientRanges), false);
assert.equal(isHowToPassIndex(12, doctorRanges), true);
assert.equal(isLargeMobileCloudIndex(6, patientRanges), true);
assert.equal(isLargeMobileCloudIndex(7, patientRanges), false);
assert.equal(isLargeMobileCloudIndex(5, doctorRanges), true);
assert.equal(isLargeMobileCloudIndex(6, doctorRanges), false);

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const welcome = readFileSync(join(root, 'resources/js/Pages/Public/Welcome.vue'), 'utf8');
const siteVersion = readFileSync(join(root, 'resources/js/siteVersion.js'), 'utf8');

assert.match(welcome, /shouldIncludePatientSlide7\(isDoctorMode\.value\)/);
assert.match(welcome, /advantageStepTotal\(isDoctorMode\.value\)/);
assert.match(welcome, /PATIENT_SLIDE_7_VIDEOS/);
assert.match(welcome, /s6-ccd-v1\.webm/);
assert.match(welcome, /s6r-ccd-v1\.webm/);
assert.match(welcome, /DOCTOR_DELAYED_TEXT_SLIDE_IDS = new Set\(\['slide-4', 'slide-5', 'slide-6'\]\)/);
assert.doesNotMatch(welcome, /totalSteps: '4'/);
assert.match(siteVersion, /1\.0\.94/);

console.log('welcome-slide-deck tests passed');
