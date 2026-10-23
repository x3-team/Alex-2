<?php

// Онлайн-оплата через ЮKassa (API v3) с чеком 54-ФЗ. Ключи магазина — только в .env на сервере.
// Пока PAYMENTS_ENABLED=false, платежи не создаются, а вебхук отвечает 404.
// Драйвер fake — эмулятор без ключей: для разработки и тестов.
return [
    'enabled' => (bool) env('PAYMENTS_ENABLED', false),

    'driver' => env('PAYMENTS_DRIVER', 'fake'), // fake | yookassa

    'yookassa' => [
        'api_url' => env('YOOKASSA_API_URL', 'https://api.yookassa.ru/v3'),
        'shop_id' => env('YOOKASSA_SHOP_ID'),
        'secret_key' => env('YOOKASSA_SECRET_KEY'),
        'timeout' => (int) env('YOOKASSA_TIMEOUT', 20),

        // Уведомления принимаем только с адресов ЮKassa (их список из документации ЮKassa).
        // Статус всё равно перепроверяем запросом к API: подписи у уведомлений нет.
        'webhook_ips' => array_filter(explode(',', (string) env('YOOKASSA_WEBHOOK_IPS',
            '185.71.76.0/27,185.71.77.0/27,77.75.153.0/25,77.75.156.11,77.75.156.35,77.75.154.128/25,2a02:5180::/32'))),
    ],

    // Реквизиты чека 54-ФЗ. Значения подтверждает бухгалтерия заказчика.
    'receipt' => [
        // Система налогообложения 1–6. Нужна, только если у магазина их несколько.
        'tax_system_code' => env('RECEIPT_TAX_SYSTEM_CODE') !== null && env('RECEIPT_TAX_SYSTEM_CODE') !== ''
            ? (int) env('RECEIPT_TAX_SYSTEM_CODE') : null,
        // Ставка НДС: 1 — без НДС (медуслуги по лицензии), 2 — 0%, 4 — 20/120 и т.д.
        'vat_code' => (int) env('RECEIPT_VAT_CODE', 1),
        'payment_subject' => env('RECEIPT_PAYMENT_SUBJECT', 'service'),
        // Оплата до оказания услуги — 100% предоплата; после оказания нужен второй чек «полный расчёт».
        'payment_mode' => env('RECEIPT_PAYMENT_MODE', 'full_prepayment'),
    ],

    // Сколько ждём оплату, прежде чем считать платёж брошенным (досверка payments:sync).
    'pending_ttl_minutes' => (int) env('PAYMENTS_PENDING_TTL_MINUTES', 60 * 24),
];
