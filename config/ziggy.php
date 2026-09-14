<?php

return [
    // ZiggyVue in app.js already registers route(); do not inline route.umd.js (~21KB) into every HTML page.
    'skip-route-function' => true,

    'except' => [
        'debugbar.*',
        'horizon.*',
        'ignition.*',
        'storage.local',
        'storage.local.upload',
        'sanctum.csrf-cookie',
        'generated::*',
    ],

    'groups' => [
        'public' => [
            'home',
            'home.doctors',
            'alex-lab',
            'alex-lab.section',
            'blog.*',
            'cart.index',
            'consent',
            'search.index',
            'quiz.*',
            'public.demo-result',
            'doctor-materials.index',
            'login',
            'logout',
            'register',
            'password.*',
            'verification.*',
            'profile.*',
            'dashboard',
            'patient.*',
            'appointment.cancel',
        ],
    ],
];
