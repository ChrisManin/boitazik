<?php

return [
    'admin' => [
        'name' => env('SEED_ADMIN_NAME', 'Admin Filament'),
        'email' => env('SEED_ADMIN_EMAIL'),
        'password' => env('SEED_ADMIN_PASSWORD'),
    ],

    'user' => [
        'name' => env('SEED_USER_NAME', 'User Vue'),
        'email' => env('SEED_USER_EMAIL'),
        'password' => env('SEED_USER_PASSWORD'),
    ],
];
