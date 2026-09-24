export const QUIZ_LEAD_KEY = 'alex-quiz-lead'

export function saveQuizLead(payload) {
  if (typeof sessionStorage === 'undefined') return
  try {
    sessionStorage.setItem(QUIZ_LEAD_KEY, JSON.stringify(payload))
  } catch {
    // Приватный режим может запретить storage — заявка с квиза всё равно уйдёт из формы врача.
  }
}

export function readQuizLead() {
  if (typeof sessionStorage === 'undefined') return null
  try {
    const raw = sessionStorage.getItem(QUIZ_LEAD_KEY)
    if (!raw) return null
    const data = JSON.parse(raw)
    return data && typeof data === 'object' ? data : null
  } catch {
    return null
  }
}
