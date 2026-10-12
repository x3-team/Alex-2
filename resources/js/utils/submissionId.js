// Ключ одной отправки формы заявки. Повтор с тем же ключом (двойной клик, повтор после
// ошибки сети) сервер не превращает во вторую заявку — см. app/Support/LeadIntake.php.
export function newSubmissionId() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID()
    }
    return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`
}
