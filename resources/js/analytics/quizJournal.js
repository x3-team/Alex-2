// Внутренний журнал прохождения квиза (ТЗ по аналитике, §4.4–4.5, пакет А4-К).
// Пишется только в нашу базу (/api/quiz-events) и только при согласии на аналитику.
// В Метрику и GA4 события квиза не уходят. Ответы, варианты, текст результата, возраст
// и контакты в журнал не попадают: только этап, номер шага и служебные ключи.
import { newSubmissionId } from '@/utils/submissionId'

const ENDPOINT = '/api/quiz-events'
const FLUSH_DELAY_MS = 1000
const VISIBLE_DELAY_MS = 1000
const MAX_BATCH = 20

function consentGranted() {
    return typeof window !== 'undefined' && window.alexAnalytics?.consent() === 'granted'
}

function deviceCategory() {
    if (typeof window === 'undefined') return 'unknown'
    const width = window.innerWidth
    if (width < 768) return 'mobile'
    if (width <= 1024) return 'tablet'
    return 'desktop'
}

// Версия логики квиза: меняется, когда меняются вопросы, их порядок или ветвление.
export function quizVersion(questions = []) {
    const shape = questions.map((q) => [q.id, q.audience_target || 'all',
        (q.options || q.answers || []).map((o) => `${o.id}>${o.next_question_id || ''}`).join(',')].join(':')).join('|')
    let hash = 0
    for (let i = 0; i < shape.length; i += 1) hash = (hash * 31 + shape.charCodeAt(i)) | 0
    return `v${(hash >>> 0).toString(36)}`
}

export function createQuizJournal({ placement = 'page', version = 'v0' } = {}) {
    // Экземпляр открытия квиза; первая попытка получает его же ID, «Пройти заново» — новый.
    const instanceId = newSubmissionId()
    let attemptId = null
    let queue = []
    let timer = 0
    let stepVisitId = null
    let pendingVisible = 0

    function flush() {
        window.clearTimeout(timer)
        timer = 0
        if (!queue.length) return
        const batch = queue.splice(0, MAX_BATCH)
        const body = JSON.stringify({ events: batch })
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || ''
        fetch(ENDPOINT, {
            method: 'POST',
            keepalive: true,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body,
        }).catch(() => {})
        if (queue.length) flush()
    }

    function log(event, fields = {}) {
        if (!consentGranted()) return
        queue.push({
            event,
            attempt_id: attemptId || instanceId,
            quiz_version: version,
            placement,
            device: deviceCategory(),
            at: new Date().toISOString(),
            ...fields,
        })
        if (!timer) timer = window.setTimeout(flush, FLUSH_DELAY_MS)
    }

    // Экран засчитывается показанным, если пробыл на виду не меньше секунды при активной вкладке.
    function whenVisible(fn) {
        window.clearTimeout(pendingVisible)
        pendingVisible = window.setTimeout(() => {
            if (document.visibilityState === 'visible') fn()
        }, VISIBLE_DELAY_MS)
    }

    if (typeof document !== 'undefined') {
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') flush()
        })
    }

    return {
        introShown() {
            whenVisible(() => log('quiz_view'))
        },
        // Повторный старт сбрасывает ответы — это новая попытка.
        started() {
            attemptId = attemptId ? newSubmissionId() : instanceId
            log('quiz_start')
        },
        stepShown(position, questionId) {
            stepVisitId = newSubmissionId()
            const visit = stepVisitId
            whenVisible(() => {
                if (visit === stepVisitId) log('quiz_step_view', { step_position: position, step_visit_id: visit, question_key: `q${questionId}` })
            })
        },
        answered(position) {
            log('quiz_answer', { step_position: position, step_visit_id: stepVisitId })
        },
        back(fromPosition, toPosition) {
            log('quiz_back', { step_position: fromPosition, to_step_position: toPosition })
        },
        completed(resultId) {
            log('quiz_complete', { result_route_id: resultId ? `r${resultId}` : null })
        },
        resultShown(resultId) {
            whenVisible(() => log('quiz_result_view', { result_route_id: resultId ? `r${resultId}` : null }))
        },
        action(actionCode, role) {
            log(role === 'no_contact' ? 'no_contact_action_click' : 'primary_cta_click', { action_code: actionCode, action_role: role })
        },
        formOpened() {
            log('allergist_form_open', { form_id: 'allergist_request' })
        },
        failed(stage, code) {
            log('quiz_error', { error_stage: stage, error_code: code })
        },
        flush,
    }
}
