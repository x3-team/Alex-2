<?php

return [

    /*
    | Адреса менеджеров, если в админке поле «Почта для заявок» пустое.
    | Несколько адресов — через запятую. Пустой env тоже уходит в fallback.
    */
    'mail_to' => env('LEADS_MAIL_TO'),

    'fallback' => 'info@alexallergotest.ru',

];
