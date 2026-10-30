<?php

// Тестовая связка заказа: Medmis и ДНКОМ. Флаг по умолчанию выключен.
// Секреты и коды только из env. Пустые коды услуг — HTTP наружу нет.
return [
    'enabled' => filter_var(env('ORDER_BRIDGE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    // Секретная ссылка /cart/preview/{ключ}: связка включается только в браузере, открывшем её.
    // Пусто или короче 32 символов — доступа по ссылке нет.
    'preview_key' => env('ORDER_BRIDGE_PREVIEW_KEY'),

    'consent_text' => 'Я даю согласие на передачу моих персональных данных в медицинскую информационную систему и лабораторию ДНКОМ для оформления и выполнения заказа на исследование. Текст согласия будет заменён.',
    'consent_text_version' => 'placeholder-1',

    'blood_draw_title' => 'Взятие крови',
    'blood_draw_price' => 0,

    'visit_duration_minutes' => 30,
    'poll_limit' => 20,
    'max_attempts' => 5,

    'medmis' => [
        'base_url' => env('MEDMIS_BASE_URL'),
        'login' => env('MEDMIS_LOGIN'),
        'password' => env('MEDMIS_PASSWORD'),
        'system_name' => env('MEDMIS_SYSTEM_NAME'),
        'branch_id' => env('MEDMIS_BRANCH_ID'),
        'schedule_id' => env('MEDMIS_SCHEDULE_ID'),
        'employee_specialization_id' => env('MEDMIS_EMPLOYEE_SPECIALIZATION_ID'),
        'service_test_id' => env('MEDMIS_SERVICE_TEST_ID'),
        'service_blood_id' => env('MEDMIS_SERVICE_BLOOD_ID'),
        'pause_ms' => 200,
        'login_pause_ms' => 500,
        // Пусто — статус в Medmis не пишем, пока справочник статусов сам не совпадёт по имени.
        'status_ids' => [
            'created' => env('MEDMIS_STATUS_CREATED'),
            'in_progress' => env('MEDMIS_STATUS_IN_PROGRESS'),
            'done' => env('MEDMIS_STATUS_DONE'),
        ],
    ],

    'dnkom' => [
        'url' => env('DNKOM_URL'),
        'sender_paid' => env('DNKOM_SENDER_PAID'),
        'sender_unpaid' => env('DNKOM_SENDER_UNPAID'),
        'receiver' => env('DNKOM_RECEIVER'),
        'password' => env('DNKOM_PASSWORD'),
        'code_test' => env('DNKOM_CODE_TEST'),
        'code_blood' => env('DNKOM_CODE_BLOOD'),
    ],

    'yookassa' => [
        'shop_id' => env('YOOKASSA_SHOP_ID'),
        'secret_key' => env('YOOKASSA_SECRET_KEY'),
    ],
];
