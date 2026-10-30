# Staging (test.alexallergotest.ru, doc.test.alexallergotest.ru)

Постоянный тестовый контур на том же VPS, что и прод. Здесь сначала проверяются ЮKassa, Medmis, ДНКОМ и всё, что идёт после PR #133. Заведено 30.10.2026.

## Что где

| Что | Где |
|---|---|
| Код | `/var/www/test.alexallergotest.ru` (git нет, код приходит rsync из workflow) |
| БД | MySQL `alex2_staging`, пользователь `alex2_staging`, доступ — `~/staging/db.cnf` (600) |
| Basic Auth | логин и пароль — `~/staging/basic-auth.txt` у alexadmin (600); хеш — `/etc/nginx/alex-staging.htpasswd` |
| Пароль админки staging | `~/staging/admin-password.txt` (у всех учёток staging один пароль, почта админов как на проде) |
| nginx | `/etc/nginx/sites-available/test.alexallergotest.ru.conf` (+ ссылка в `sites-enabled`) |
| php-fpm | отдельный мастер `/etc/alex-staging/php-fpm.conf`, сокет `/run/php/alex-staging-fpm.sock`, opcache видит новые файлы сам |
| supervisor | `/etc/supervisor/conf.d/alex-staging.conf`: группа `alex-staging` (`alex-staging-fpm`, `alex-staging-queue`) |
| cron | строка `schedule:run` в crontab alexadmin с пометкой `# alex-staging` |
| Сертификат | Let's Encrypt `/etc/letsencrypt/live/test.alexallergotest.ru/` (оба имени), продление — штатный certbot.timer |
| Что выложено | `storage/app/staging-release.txt` в каталоге staging (ref, sha, время, флаг ORDER_BRIDGE) |

Пароли в чат, PR и репозиторий не пишутся. Сказать, где лежат, — можно.

## Чем staging отличается от прода

- `APP_ENV=staging`, `APP_DEBUG=false`, свой `APP_KEY`, своя сессионная кука `alex2_staging_session`, `SESSION_DOMAIN=null`.
- Почта только в лог (`MAIL_MAILER=log`, `storage/logs/laravel.log`).
- Метрика и GA4 не грузятся (`ANALYTICS_CLIENT_ENABLED=false`), Measurement Protocol и офлайн-конверсии без ключей, экспорт заявок выключен.
- SSR выключен (`INERTIA_SSR_ENABLED=false`): `resources/js/ziggy.js` для SSR знает только боевые домены, ссылки вели бы на прод. Страницы отдаются через Blade-фолбэк, `X-SSR: off` здесь норма.
- Интеграции — только тестовые стенды. Ключи берутся из GitHub secrets `STAGING_YOOKASSA_SHOP_ID`, `STAGING_YOOKASSA_SECRET_KEY`, `STAGING_MEDMIS_*`, `STAGING_DNKOM_*`. Скрипт очищает секретный ключ ЮKassa без префикса `test_` и любые адреса/логины, совпадающие с продом. Пусто — наружу ничего не уходит (`config_incomplete`).
- Поисковики: Basic Auth на всём, `X-Robots-Tag: noindex, nofollow, noarchive`, `/robots.txt` — `Disallow: /` (отдаёт nginx, без пароля).
- Вебхук ЮKassa `POST /api/lab-order/payment` открыт без Basic Auth, но только для адресов ЮKassa.
- Эти ключи `.env` скрипт перезаписывает при каждом `setup` и `deploy`, руками их менять бесполезно.

## Обезличивание БД

`refresh-db` пересоздаёт `alex2_staging` из дампа прода (`~/.my.cnf`, только чтение) и сразу обезличивает:

- `users`: у всех один staging-пароль, `remember_token` пустой, телефон пустой; почта не-админов → `user<id>@staging.invalid`; у врачей (`is_doctor`) имя → «Врач <id>», slug → `doctor-<id>`, био и регалии пустые. Авторы блога (публичные данные) остаются.
- `orders`: имя «Тестовый клиент <id>», телефон `+7900…<id>`, почта `order<id>@staging.invalid`, комментарий пустой.
- Таблицы `lab_*` и `patient*` (карты пациентов, лабораторные заказы) очищаются целиком.
- Очищаются `sessions`, `password_reset_tokens`, `personal_access_tokens`, `jobs`, `failed_jobs`, `job_batches`, `cache`, `analytics_outbox`; `blog_ratings.visitor_id` заменяется случайным.
- В конце проверка: во всех колонках `*email*` и `*phone*` не осталось реальных значений (кроме почты админов). Итог печатается в лог workflow.

Файлы: `storage/app/public` и `public/videos` копируются с прода в каталог staging (прод только читается).

## Как запускать

GitHub → Actions → **Staging (manual)** → Run workflow (ветка `production`). Для всего, кроме `inspect`, в `confirm` вписать `I_CONFIRM_STAGING`.

| action | Что делает |
|---|---|
| `inspect` | Только читает: sudo, версии, nginx, supervisor, MySQL, cron, DNS, состояние staging |
| `setup` | Каталог, БД и первая обезличенная копия, файлы с прода, `.env`, Basic Auth, php-fpm и очередь под supervisor, cron, nginx (без сертификата — только :80), `nginx -t` и reload. Повторный запуск ничего не ломает |
| `refresh-db` | Свежая обезличенная копия прод-БД + `migrate` staging |
| `certs` | Проверяет, что оба имени смотрят на VPS, выпускает сертификат (webroot), включает HTTPS, reload nginx |
| `deploy` | Выкладывает `ref` в staging: `composer install`, `npm ci`, `npm run build`, `migrate --force` (только staging-БД), кеши, перезапуск только группы `alex-staging`, smoke |
| `smoke` | Повторить проверки: 401 без пароля, 200 с паролем, аналитика выключена, robots, noindex, doc определяется как врачебный |

`ref` для `deploy`:
- `pull/133` — PR поверх свежего `production` (merge-ref GitHub). Так и выкладывать PR до мержа: если PR конфликтует с production, деплой не соберётся — сначала обновить ветку PR.
- `production`, имя ветки или SHA — как есть. Ветка без флага `ANALYTICS_CLIENT_ENABLED` (старше этого PR) не выложится: скрипт останавливается, чтобы Метрика с GA не загрузились на staging.

`order_bridge`: `true`/`false` пишет `ORDER_BRIDGE_ENABLED` в `.env` staging, `keep` оставляет как было.

Скрипты staging (`scripts/staging/`) всегда берутся из ветки, с которой запущен workflow, а не из выкладываемого PR.

## Что не трогается

Каталог прода, его `.env`, БД, php-fpm, SSR и supervisor-программы прода. Из общего — только `nginx -t && systemctl reload nginx`; если `nginx -t` не прошёл, конфиг staging откатывается и reload не делается. `supervisorctl update alex-staging` касается только группы staging.

## Удалить staging целиком

```bash
sudo rm -f /etc/nginx/sites-enabled/test.alexallergotest.ru.conf /etc/nginx/sites-available/test.alexallergotest.ru.conf
sudo nginx -t && sudo systemctl reload nginx
sudo supervisorctl stop 'alex-staging:*' && sudo rm -f /etc/supervisor/conf.d/alex-staging.conf && sudo supervisorctl reread && sudo supervisorctl update alex-staging
crontab -l | grep -v '# alex-staging' | crontab -
sudo mysql -e "DROP DATABASE alex2_staging; DROP USER 'alex2_staging'@'localhost';"
sudo rm -rf /var/www/test.alexallergotest.ru /etc/alex-staging /etc/nginx/alex-staging.htpasswd ~/staging
sudo certbot delete --cert-name test.alexallergotest.ru
```
