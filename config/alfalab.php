<?php

// Интеграция с ЛИС «АльфаЛАБ» (протокол v3.1, JSON веб-сервис). Логин, пароль и адрес —
// только в .env на сервере. Пока ALFALAB_ENABLED=false, команды ничего не отправляют.
// Драйвер fake — эмулятор ЛИС в нашей БД: работаем без стенда лаборатории.
return [
    'enabled' => (bool) env('ALFALAB_ENABLED', false),

    'driver' => env('ALFALAB_DRIVER', 'fake'), // fake | http

    'http' => [
        'base_url' => env('ALFALAB_BASE_URL'),
        'sender' => env('ALFALAB_SENDER'),
        'password' => env('ALFALAB_PASSWORD'),
        'connect_timeout' => (int) env('ALFALAB_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('ALFALAB_TIMEOUT', 30),
    ],

    // Код контрагента. Нужен, только если логин лаборатории привязан к нескольким заказчикам (§5.6).
    'hospital_code' => env('ALFALAB_HOSPITAL_CODE'),

    // Коды номенклатуры ЛИС: исследование ALEX2 и услуга взятия биоматериала. Выдаёт лаборатория.
    'services' => [
        'alex2' => env('ALFALAB_ALEX2_CODE'),
        'sampling' => env('ALFALAB_SAMPLING_CODE'),
    ],

    // Номер направления (§5.5): только цифры, чётная длина 8/10/12, без ведущего нуля.
    'referral_nr' => [
        'length' => (int) env('ALFALAB_NR_LENGTH', 10),
        'auto' => (bool) env('ALFALAB_AUTO_NR', false), // AutoNr: номер присваивает ЛИС
    ],

    // Сколько направлений забирать из очереди за один запуск опроса.
    'poll_batch' => (int) env('ALFALAB_POLL_BATCH', 50),

    // Журнал обмена без ПДн: сколько дней хранить.
    'log_retention_days' => (int) env('ALFALAB_LOG_RETENTION_DAYS', 90),

    // Эмулятор: коды и цены, которые он отдаёт в справочниках.
    'fake' => [
        'alex2_code' => 'ALEX2',
        'sampling_code' => 'VZ1',
        'alex2_price' => 21900,
        'sampling_price' => 300,
    ],
];
