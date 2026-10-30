<?php

// Серверная часть аналитики по ТЗ заказчика (этап А): факт принятой заявки после сохранения,
// журнал отправки (analytics_outbox) и доставка в GA4 Measurement Protocol и офлайн-конверсии Метрики.
// Ключи — только в .env на сервере. Пока ключа нет, отправка в платформу не выполняется.
return [
    // Теги Метрики и GA4 в браузере (resources/views/app.blade.php). На staging false.
    'client_enabled' => filter_var(env('ANALYTICS_CLIENT_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    'ga4' => [
        'measurement_id' => env('GA4_MEASUREMENT_ID', 'G-YZ7B5FSFW0'),
        'api_secret' => env('GA4_MP_API_SECRET'),
    ],

    'metrika' => [
        'counter_id' => env('METRIKA_COUNTER_ID', '110363549'),
        'oauth_token' => env('METRIKA_OAUTH_TOKEN'),
    ],

    // Какие принятые заявки можно отдавать в Метрику и GA4. По ТЗ (§4.1, §8) заявка к врачу
    // из квиза — клинический сценарий, наружу по умолчанию не уходит; корзина пока скрыта.
    // Внутренний журнал пишется всегда, флаг влияет только на внешнюю отправку.
    'export' => [
        'doctor_appointment' => (bool) env('ANALYTICS_EXPORT_DOCTOR_APPOINTMENT', false),
        'test_order' => (bool) env('ANALYTICS_EXPORT_TEST_ORDER', false),
    ],

    'max_attempts' => 5,
];
