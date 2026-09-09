<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Doctors subdomain host
    |--------------------------------------------------------------------------
    |
    | Primary hostname for the public doctors site. Override locally with
    | APP_DOCTORS_HOST (e.g. doc.local.test) without changing production DNS.
    |
    */
    'host' => env('APP_DOCTORS_HOST', 'doc.alexallergotest.ru'),

    /*
    |--------------------------------------------------------------------------
    | Path preview on apex (temporary until doc.* DNS is live)
    |--------------------------------------------------------------------------
    */
    'path_preview' => env('DOCTORS_PATH_PREVIEW', true),

    'path_prefix' => 'doctors',

    /*
    |--------------------------------------------------------------------------
    | Hard redirect /doctors* → doc.* (enable only after DNS/TLS is ready)
    |--------------------------------------------------------------------------
    */
    'subdomain_redirect' => env('DOCTORS_SUBDOMAIN_REDIRECT', false),

    'theme_color' => '#cba98e',

    'default_audience' => 'patients',

    'audiences' => ['patients', 'doctors'],
];
