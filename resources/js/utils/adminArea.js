// Чат Carrot Quest подтягивает Яндекс Метрика, а Метрика выводится только на публичных
// страницах (resources/views/app.blade.php, $isAdminArea). Но если человек пришёл в админку
// с сайта переходом Inertia, скрипт чата уже загружен. Поэтому на путях админки держим
// на <html> класс is-admin-area — по нему CSS прячет виджет (resources/css/main.css),
// а при уходе из админки класс снимается и чат возвращается.
import { router } from '@inertiajs/vue3'

// Тот же список, что request()->is(...) для $isAdminArea в app.blade.php.
const ADMIN_PATH = /^\/(admin|login|forgot-password|reset-password|confirm-password|verify-email|register)(\/|$)/

const ROOT_CLASS = 'is-admin-area'

export function isAdminPath(pathname) {
    return ADMIN_PATH.test(pathname || '/')
}

export function syncAdminAreaClass(pathname = window.location.pathname, root = document.documentElement) {
    const admin = isAdminPath(pathname)
    root.classList.toggle(ROOT_CLASS, admin)
    return admin
}

export function watchAdminArea() {
    if (typeof window === 'undefined' || typeof document === 'undefined') return
    syncAdminAreaClass()
    router.on('navigate', () => syncAdminAreaClass())
    window.addEventListener('popstate', () => syncAdminAreaClass())
}
