// Маскировка для Вебвизора (ТЗ §8.2 п.5). Вебвизор включён только после согласия,
// а в кабинете выключено «Записывать все поля». Дополнительно:
// - ym-disable-keys на каждом input/textarea, в том числе в Welcome.vue, который не трогаем;
// - ym-hide-content на формах с контактами (телефон, email, имя), чтобы не писать и текст рядом с ними.
// Контейнеры квиза с вопросами, ответами и результатом помечены ym-hide-content прямо в шаблонах.
// ym-record-keys не используем нигде.
const FIELD = 'input, textarea'
const CONTACT_FIELD = 'input[type="tel"], input[type="email"], input[autocomplete="name"], input[autocomplete="tel"], input[autocomplete="email"], input[name*="phone"], input[name*="name"], input[name*="email"]'

function markField(el) {
    if (!el.classList.contains('ym-disable-keys')) el.classList.add('ym-disable-keys')
    if (el.matches(CONTACT_FIELD)) {
        const box = el.closest('form') || el.parentElement
        if (box && !box.classList.contains('ym-hide-content')) box.classList.add('ym-hide-content')
    }
}

function markTree(root) {
    if (!(root instanceof Element)) return
    if (root.matches(FIELD)) markField(root)
    root.querySelectorAll(FIELD).forEach(markField)
}

export function startWebvisorPrivacy() {
    if (typeof window === 'undefined' || typeof document === 'undefined' || !window.MutationObserver) return
    markTree(document.body)
    // Наблюдатель создаётся до загрузки Метрики, поэтому его колбэк срабатывает раньше,
    // чем Вебвизор успевает записать новый узел.
    new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            mutation.addedNodes.forEach(markTree)
        }
    }).observe(document.body, { childList: true, subtree: true })
}
