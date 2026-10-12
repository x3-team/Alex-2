// Цели и просмотры для Яндекс Метрики и GA4. Теги грузит inline-скрипт в
// resources/views/app.blade.php (window.alexAnalytics) и только после согласия посетителя.
// Здесь — единственный владелец просмотров страниц (Метрика defer:true, GA send_page_view:false)
// и отправка целей по справочнику из rules.js.
import { router } from '@inertiajs/vue3'
import { buildEvent, isExcludedPath, pageType, safeReferrer, safeUrl, siteMode } from './rules'

const REPEAT_GUARD_MS = 700
const PLACEMENT_MEMORY_MS = 3000

let lastPageUrl = null
let lastSent = { key: '', at: 0 }
let recentPlacement = { value: null, at: 0 }

function api() {
    return typeof window !== 'undefined' ? window.alexAnalytics : undefined
}

function allowedHere() {
    const a = api()
    return !!a && a.consent() === 'granted' && !isExcludedPath(window.location.pathname)
}

function context() {
    return {
        page_type: pageType(window.location.pathname),
        site_mode: siteMode(window.location.hostname),
    }
}

function sendPageView() {
    const a = api()
    if (!a || !a.isLoaded()) return
    const url = safeUrl(window.location.href)
    if (!url || url === lastPageUrl) return
    const referrer = lastPageUrl || safeReferrer(document.referrer)
    lastPageUrl = url
    const title = document.title
    window.ym?.(a.ymId, 'hit', url, { title, referer: referrer })
    window.gtag?.('event', 'page_view', { page_location: url, page_title: title, page_referrer: referrer })
}

// Отправляет событие из справочника rules.js. Без согласия, на исключённых путях
// и для неизвестных имён ничего не уходит.
export function track(name, params = {}) {
    if (!allowedHere()) return
    const event = buildEvent(name, params, context())
    if (!event) return

    // Двойной клик или touch+click по одной кнопке — одно действие.
    const key = event.name + JSON.stringify(event.params)
    const now = Date.now()
    if (key === lastSent.key && now - lastSent.at < REPEAT_GUARD_MS) return
    lastSent = { key, at: now }

    const a = api()
    a.load()
    window.ym?.(a.ymId, 'reachGoal', event.ymGoal, event.params)
    window.gtag?.('event', event.gaEvent, event.params)
}

// CTA записи запоминает своё место, чтобы следом открытый выбор лаборатории знал, откуда пришли.
export function rememberPlacement(placement) {
    recentPlacement = { value: placement, at: Date.now() }
}

export function recentPlacementOr(fallback) {
    return Date.now() - recentPlacement.at < PLACEMENT_MEMORY_MS && recentPlacement.value
        ? recentPlacement.value
        : fallback
}

const placementOf = (el) => el.closest('[data-analytics-placement]')?.getAttribute('data-analytics-placement')
    || (el.closest('footer') ? 'footer' : 'page')

// Клики, которые удобнее ловить делегированием: CTA с data-атрибутами (и на главной,
// где Welcome.vue не трогаем), ссылки tel:/mailto:, раскрытие вопросов FAQ.
function onDocumentClick(event) {
    const target = event.target instanceof Element ? event.target : null
    if (!target) return

    const cta = target.closest('[data-analytics-cta]')
        || target.closest('.mobile-home-dock .mobile-home-register')
        || target.closest('.blog-content .cta-block .cta-button')
    if (cta) {
        const placement = cta.getAttribute('data-analytics-placement')
            || (cta.closest('.mobile-home-dock') ? 'mobile_bar' : null)
            || (cta.closest('.blog-content') ? 'article' : 'page')
        const elementId = cta.getAttribute('data-analytics-cta')
            || (placement === 'article' ? 'article_cta' : 'booking_primary')
        rememberPlacement(placement)
        track('booking_cta_click', { placement, element_id: elementId })
        return
    }

    const contact = target.closest('a[href^="tel:"], a[href^="mailto:"]')
    if (contact) {
        const method = contact.getAttribute('href').startsWith('tel:') ? 'phone' : 'email'
        track('contact_click', { contact_method: method, placement: placementOf(contact) })
        return
    }

    const faq = target.closest('.exact-faq-question')
    if (faq) {
        const item = faq.closest('.exact-faq-item')
        // Клик до перерисовки: если вопрос уже открыт, это сворачивание — не считаем.
        if (!item || item.classList.contains('open')) return
        const index = Array.prototype.indexOf.call(item.parentElement?.children || [], item)
        track('faq_open', { placement: 'home', element_id: `faq_${index + 1}` })
    }
}

export function startAnalytics() {
    if (typeof window === 'undefined' || typeof document === 'undefined') return

    document.addEventListener('click', onDocumentClick, true)

    // Теги уже на странице, а человек уходит в квиз/поиск: открываем этот путь
    // полной загрузкой, где сервер теги не выводит, иначе их автосбор продолжится там.
    router.on('before', (event) => {
        const visit = event.detail.visit
        const a = api()
        if (!a || !a.isLoaded() || visit.prefetch || String(visit.method).toLowerCase() !== 'get') return
        if (!isExcludedPath(visit.url.pathname)) return
        event.preventDefault()
        window.location.assign(visit.url.href)
    })

    router.on('navigate', () => {
        const a = api()
        if (!a) return
        if (isExcludedPath(window.location.pathname)) {
            // Назад/вперёд браузера минуют 'before' — перезагружаемся без тегов.
            if (a.isLoaded()) window.location.reload()
            return
        }
        if (a.consent() !== 'granted') return
        if (!a.isLoaded()) a.load()
        sendPageView()
    })

    // Теги загрузились (после согласия или отложенной загрузки) — один просмотр текущей страницы.
    window.addEventListener('alex:analytics-ready', () => {
        if (!isExcludedPath(window.location.pathname)) sendPageView()
    })
    if (api()?.isLoaded() && !isExcludedPath(window.location.pathname)) sendPageView()
}
