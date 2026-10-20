// Цели и просмотры для Яндекс Метрики и GA4. Теги грузит inline-скрипт в
// resources/views/app.blade.php (window.alexAnalytics) и только после согласия посетителя.
// Здесь — единственный владелец просмотров страниц (Метрика defer:true, GA send_page_view:false)
// и отправка целей по справочнику из rules.js.
import { router } from '@inertiajs/vue3'
import { buildEvent, isExcludedPath, isQuietPath, pageTitle, pageType, safeReferrer, safeUrl, siteMode } from './rules'
import { partnerUrl } from '@/data/partnerLabs'

const REPEAT_GUARD_MS = 700
const PLACEMENT_MEMORY_MS = 3000
const FULL_RELOAD_DELAY_MS = 300

let lastPageUrl = null
// Режим, в котором загружены теги: на квизе счётчик настроен без карты кликов.
let loadedQuiet = false
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

function sendPageView(href = window.location.href, title = document.title) {
    const a = api()
    if (!a || !a.isLoaded() || isQuietPath(window.location.pathname)) return
    const url = safeUrl(href)
    if (!url || url === lastPageUrl) return
    const referrer = lastPageUrl || safeReferrer(document.referrer)
    lastPageUrl = url
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

    // Теги уже на странице, а человек уходит в поиск или личный раздел: открываем этот путь
    // полной загрузкой, где сервер теги не выводит, иначе их автосбор продолжится там.
    // Вход в квиз и выход из него — тоже полной загрузкой: там счётчик без карты кликов.
    router.on('before', (event) => {
        const visit = event.detail.visit
        const a = api()
        if (!a || !a.isLoaded() || visit.prefetch || String(visit.method).toLowerCase() !== 'get') return
        const target = visit.url.pathname
        if (!isExcludedPath(target) && isQuietPath(target) === isQuietPath(window.location.pathname)) return
        event.preventDefault()
        // Небольшая пауза, чтобы цель, отправленная этим же кликом (например, действие
        // результата квиза), успела уйти до перезагрузки.
        window.setTimeout(() => window.location.assign(visit.url.href), FULL_RELOAD_DELAY_MS)
    })

    // Первый navigate приходит при загрузке документа: теги тогда грузит отложенно inline-скрипт.
    let firstNavigate = true
    router.on('navigate', (event) => {
        const initial = firstNavigate
        firstNavigate = false
        const a = api()
        if (!a) return
        // Назад/вперёд браузера минуют 'before': перезагружаемся, если теги здесь не нужны
        // или были настроены для другого режима (квиз без карты кликов).
        if (isExcludedPath(window.location.pathname) || (a.isLoaded() && loadedQuiet !== isQuietPath(window.location.pathname))) {
            if (a.isLoaded()) window.location.reload()
            return
        }
        if (a.consent() !== 'granted') return
        if (!a.isLoaded()) {
            // Пришли с пути без тегов (поиск, личные разделы) — грузим сразу; просмотр отправит 'alex:analytics-ready'.
            if (!initial) a.load()
            return
        }
        sendPageView(window.location.href, pageTitle(event.detail.page?.props, window.location.pathname, document.title))
    })

    // Теги загрузились (после согласия или отложенной загрузки) — один просмотр текущей страницы.
    window.addEventListener('alex:analytics-ready', () => {
        loadedQuiet = isQuietPath(window.location.pathname)
        if (!isExcludedPath(window.location.pathname)) sendPageView()
        watchChat()
    })
    if (api()?.isLoaded()) {
        loadedQuiet = isQuietPath(window.location.pathname)
        if (!isExcludedPath(window.location.pathname)) sendPageView()
        watchChat()
    }
}

// Чат Carrot Quest приходит через тег-менеджер Метрики, то есть только после согласия.
// Ждём его JS API и подписываемся: открытие чата — раз за загрузку страницы,
// сообщение посетителя — только первое в каждом диалоге. Текст сообщения не берём.
const CHAT_WAIT_MS = 60000
const CHAT_POLL_MS = 1000
let chatWatched = false

function watchChat() {
    if (chatWatched) return
    chatWatched = true
    const startedAt = Date.now()
    const poll = () => {
        const carrot = window.carrotquest
        if (carrot && typeof carrot.addCallback === 'function') {
            subscribeChat(carrot)
            return
        }
        if (Date.now() - startedAt < CHAT_WAIT_MS) window.setTimeout(poll, CHAT_POLL_MS)
    }
    poll()
}

function subscribeChat(carrot) {
    let opened = false
    const conversations = new Set()
    const messageSent = (data) => {
        const key = data && data.id != null ? String(data.id) : 'unknown'
        if (conversations.has(key)) return
        conversations.add(key)
        track('chat_message_sent')
    }
    carrot.addCallback('messenger_opened', () => {
        if (opened) return
        opened = true
        track('chat_open')
    })
    carrot.addCallback('conversation_started', messageSent)
    carrot.addCallback('user_replied', messageSent)
}

// Переход к сети-партнёру из любого списка лабораторий: выбор, затем исходящий переход.
// Без валидного адреса — техническая цель вместо перехода (раньше Ситилаб молча вёл на «#»).
export function openPartnerLab(lab, placement) {
    track('partner_select', { partner_id: lab.id, placement })
    const url = partnerUrl(lab)
    if (!url) {
        track('navigation_error', { partner_id: lab.id, placement, error_code: 'invalid_destination' })
        return
    }
    track('partner_outbound', { partner_id: lab.id, placement, destination_domain: new URL(url).hostname.replace(/^www\./, '') })
    window.open(url, '_blank')
}
