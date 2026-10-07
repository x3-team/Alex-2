// Кнопка чата Carrot Quest (подключается через Яндекс Метрику, не из кода сайта) стоит
// в правом нижнем углу и на мобилке накрывает нижнюю плашку «Записаться на тест…».
// Меряем видимую плашку и отдаём её реальную высоту (вместе с safe-area) в CSS:
// html.has-mobile-dock + --mobile-dock-offset. Правило для чата — в resources/css/main.css.
const DOCK_SELECTOR = '.mobile-home-dock, .mobile-site-dock'
const ROOT_CLASS = 'has-mobile-dock'
const OFFSET_VAR = '--mobile-dock-offset'

function findVisibleDock() {
    for (const el of document.querySelectorAll(DOCK_SELECTOR)) {
        const style = window.getComputedStyle(el)
        if (style.display === 'none' || style.visibility === 'hidden' || style.position !== 'fixed') continue
        const rect = el.getBoundingClientRect()
        if (rect.width > 0 && rect.height > 0) return { el, rect }
    }
    return null
}

export function syncChatDockOffset(root = document.documentElement) {
    const dock = findVisibleDock()
    if (!dock) {
        root.classList.remove(ROOT_CLASS)
        root.style.removeProperty(OFFSET_VAR)
        return null
    }
    const offset = Math.round(dock.rect.height)
    root.style.setProperty(OFFSET_VAR, `${offset}px`)
    root.classList.add(ROOT_CLASS)
    return { el: dock.el, offset }
}

export function watchChatDockOffset() {
    if (typeof window === 'undefined' || typeof document === 'undefined' || !document.body) return

    let frame = 0
    let observedDock = null
    const resizeObserver = typeof ResizeObserver !== 'undefined'
        ? new ResizeObserver(() => schedule())
        : null

    function run() {
        frame = 0
        const dock = syncChatDockOffset()
        const el = dock ? dock.el : null
        if (resizeObserver && el !== observedDock) {
            if (observedDock) resizeObserver.unobserve(observedDock)
            if (el) resizeObserver.observe(el, { box: 'border-box' })
            observedDock = el
        }
    }

    function schedule() {
        if (!frame) frame = window.requestAnimationFrame(run)
    }

    // Плашка появляется/исчезает при смене страницы Inertia и при открытии меню (Teleport в body).
    new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true })
    window.addEventListener('resize', schedule, { passive: true })
    window.addEventListener('orientationchange', schedule, { passive: true })
    schedule()
}
