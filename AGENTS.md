# Alex-2 — инструкции для агентов

Полные правила: **[docs/AGENT_WORKFLOW.md](docs/AGENT_WORKFLOW.md)**.  
Always-on в Cursor: **[.cursor/rules/alex2-workflow.mdc](.cursor/rules/alex2-workflow.mdc)**.

## Кратко

| Тема | Правило |
|------|---------|
| Git | `cursor/<имя>-2397` от **`production`**, PR только в **`production`**. **`main`** и прямой push в **`production`** — нет. |
| Версия | Правки сайта → bump **`SITE_VERSION`** в **том же PR**. Догонять версию отдельно — нет. |
| Релиз | CI green → merge → деплой **только** по «залей» / «деплой» от Виталия. |
| Деплой | **Только SSH** с VM (секреты Alex-2 env, см. [AGENT_WORKFLOW.md](docs/AGENT_WORKFLOW.md)): backup → rsync (excludes в [DEPLOY.md](docs/DEPLOY.md), без `--delete`) → `deploy-vps.sh apply`. **Не** `gh workflow run` / Actions. |
| Перед rsync | Нет **`public/hot`**. Проверки — **`npm run build`**, не `dev`. |
| Код | **`Welcome.vue`** / сторителлинг не откатывать. Перед merge — актуальный **`production`**. |
| Запреты | `.env`, `storage/`, `public/storage`, `public/videos`, nginx, лишние migrate. |
| Заявки | Не на `vitalynagay@gmail.com`; **`info@alexallergotest.ru`**, пока в админке не задан менеджер. |
| Язык | С Виталием — **русский**. |

Перед merge или деплоем — чек-лист в **AGENT_WORKFLOW.md**.
