// Чистые правила аналитики без обращения к window: что считаем, куда и с какими полями.
// По ТЗ заказчика на аналитику (версия 1.2, этап А): во внешние счётчики уходят только
// перечисленные ниже события и только нейтральные значения из фиксированных справочников.

// Пути, где аналитики нет вовсе: поиск аллергенов, личные разделы пациента и врача, скрытая корзина
// и админка. Тот же список — в resources/views/app.blade.php ($analyticsExcluded).
export const EXCLUDED_PATH = /^\/(search|cart|dashboard|profile|recover|cabinet|admin|login|forgot-password|reset-password|confirm-password|verify-email|register)(\/|$)/

// Квиз: заказчик разрешил передавать события квиза (решение D1), но не просмотры страниц
// и не карту кликов — там идут только цели квиза. Тот же список — QUIET в app.blade.php.
export const QUIET_PATH = /^\/quiz(\/|$)/

// Из адреса страницы во внешние счётчики уходят только эти параметры (рекламная атрибуция).
const ALLOWED_QUERY = /^(utm_(source|medium|campaign|content|term)|yclid|gclid)$/

const QUIZ_PARAMS = ['quiz_id', 'quiz_version', 'quiz_placement']
const FORM_PARAMS = ['form_id', 'form_version', 'placement']

// Имя события → разрешённые параметры, имя JS-цели Метрики и имя события GA4.
// Цель Метрики может зависеть от параметров (контакт: телефон или почта).
export const EVENTS = Object.freeze({
    booking_cta_click: { params: ['placement', 'element_id'], ym: 'booking_cta_click', ga: 'booking_cta_click' },
    partner_selector_open: { params: ['placement'], ym: 'partner_selector_open', ga: 'partner_selector_open' },
    partner_select: { params: ['placement', 'partner_id'], ym: 'partner_select', ga: 'partner_select' },
    partner_outbound: { params: ['placement', 'partner_id', 'destination_domain'], ym: 'partner_outbound', ga: 'partner_outbound' },
    navigation_error: { params: ['placement', 'partner_id', 'error_code'], ym: 'diag_event', ga: 'navigation_error' },
    contact_click: { params: ['placement', 'contact_method'], ym: (p) => (p.contact_method === 'email' ? 'contact_email' : 'contact_phone'), ga: 'contact_click' },
    // form_start: в GA4 событие уже есть (enhanced measurement); из JS — только reachGoal Метрики exact match form_start.
    form_start: { params: FORM_PARAMS, ym: 'form_start', ga: false },
    faq_open: { params: ['placement', 'element_id'], ym: 'faq_open', ga: 'faq_open' },
    demo_download_click: { params: ['asset_id'], ym: 'demo_download_click', ga: 'demo_download_click' },
    site_mode_select: { params: ['placement', 'site_mode'], ym: 'site_mode_select', ga: 'site_mode_select' },

    // Квиз (ТЗ §4.4, D1): только служебные поля. Ответы, варианты, результат, номера вопросов,
    // ID попытки и код действия наружу не уходят — их нет в списках параметров ниже.
    quiz_view: { params: QUIZ_PARAMS, ym: 'quiz_view', ga: 'quiz_view' },
    quiz_start: { params: QUIZ_PARAMS, ym: 'quiz_start', ga: 'quiz_start' },
    quiz_step_view: { params: [...QUIZ_PARAMS, 'step_position'], ym: 'quiz_step_view', ga: 'quiz_step_view' },
    quiz_answer: { params: [...QUIZ_PARAMS, 'step_position'], ym: 'quiz_answer', ga: 'quiz_answer' },
    quiz_back: { params: [...QUIZ_PARAMS, 'step_position'], ym: 'quiz_back', ga: 'quiz_back' },
    quiz_complete: { params: QUIZ_PARAMS, ym: 'quiz_complete', ga: 'quiz_complete' },
    quiz_result_view: { params: QUIZ_PARAMS, ym: 'quiz_result_view', ga: 'quiz_result_view' },
    primary_cta_click: { params: QUIZ_PARAMS, ym: 'primary_cta_click', ga: 'primary_cta_click' },
    no_contact_action_click: { params: QUIZ_PARAMS, ym: 'no_contact_action_click', ga: 'no_contact_action_click' },
    allergist_form_open: { params: QUIZ_PARAMS, ym: 'allergist_form_open', ga: 'allergist_form_open' },
    quiz_error: { params: [...QUIZ_PARAMS, 'error_stage', 'error_code'], ym: 'diag_event', ga: 'quiz_error' },

    // Чат Carrot Quest: только факт, без текста сообщения и контактов.
    chat_open: { params: [], ym: 'chat_open', ga: 'chat_open' },
    chat_message_sent: { params: [], ym: 'chat_message_sent', ga: 'chat_message_sent' },
})

// Общие поля, которые добавляются к каждому событию.
const COMMON_PARAMS = ['page_type', 'site_mode']

// Значения — только короткие коды из справочников (латиница, цифры, _ . -), никакого текста.
const SAFE_VALUE = /^[a-z0-9_.-]{1,64}$/

export function isExcludedPath(pathname) {
    return EXCLUDED_PATH.test(pathname || '/')
}

export function isQuietPath(pathname) {
    return QUIET_PATH.test(pathname || '/')
}

export function pageType(pathname) {
    const path = pathname || '/'
    if (path === '/') return 'home'
    if (/^\/(blog|materials|video|demo-result)(\/|$)/.test(path)) return 'content'
    if (/^\/alex-lab(\/|$)/.test(path)) return 'information'
    if (QUIET_PATH.test(path)) return 'quiz'
    return 'other'
}

export function siteMode(hostname) {
    return /^doc\./.test(hostname || '') ? 'doctor' : 'patient'
}

// Адрес для счётчиков: без hash и без параметров, кроме рекламных меток.
export function safeUrl(href) {
    let url
    try {
        url = new URL(href)
    } catch {
        return null
    }
    if (!/^https?:$/.test(url.protocol)) return null
    const kept = new URLSearchParams()
    url.searchParams.forEach((value, key) => {
        if (ALLOWED_QUERY.test(key)) kept.append(key, value)
    })
    const query = kept.toString()
    return url.origin + url.pathname + (query ? `?${query}` : '')
}

// Источник перехода: только сайт и путь, без параметров (в них бывают поисковые запросы).
export function safeReferrer(href) {
    if (!href) return ''
    try {
        const url = new URL(href)
        return /^https?:$/.test(url.protocol) ? url.origin + url.pathname : ''
    } catch {
        return ''
    }
}

// Готовит событие к отправке: null, если имени нет в справочнике.
// Неразрешённые параметры и значения не по формату отбрасываются.
export function buildEvent(name, params = {}, context = {}) {
    const spec = EVENTS[name]
    if (!spec) return null
    const clean = {}
    for (const key of [...spec.params, ...COMMON_PARAMS]) {
        const raw = key in params ? params[key] : context[key]
        // Номер шага приходит числом: передаём только целые 1–99.
        const value = Number.isInteger(raw) && raw > 0 && raw < 100 ? String(raw) : raw
        if (typeof value === 'string' && SAFE_VALUE.test(value)) clean[key] = value
    }
    const ymGoal = typeof spec.ym === 'function' ? spec.ym(clean) : spec.ym
    return { name, ymGoal, gaEvent: spec.ga, params: clean }
}

// Заголовок страницы для просмотра при переходе внутри сайта. Не все страницы меняют
// <title> на клиенте, поэтому берём его из props так же, как app.blade.php ($documentTitle).
const TITLE_FALLBACKS = {
    '/': 'Тест на аллергию ALEX² — 300 аллергенов за один анализ',
    '/alex-lab': 'Лаборатория ALEX LAB',
    '/demo-result': 'Пример результата теста ALEX²',
    '/blog': 'Блог — ALEX LAB',
}

export function pageTitle(props, pathname, fallback = '') {
    const pick = (path) => path.split('.').reduce((value, key) => (value == null ? value : value[key]), props || {})
    const title = ['meta.title', 'pageTitle', 'blogMeta.title', 'authorMeta.title', 'authorsMeta.title', 'blog.seo_title', 'blog.title']
        .map(pick)
        .find((value) => typeof value === 'string' && value.trim() !== '')
    if (title) return title
    if (TITLE_FALLBACKS[pathname]) return TITLE_FALLBACKS[pathname]
    if (/^\/alex-lab(\/|$)/.test(pathname || '')) return TITLE_FALLBACKS['/alex-lab']
    return fallback
}
