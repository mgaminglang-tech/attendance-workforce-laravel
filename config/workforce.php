<?php

return [
    'invitations' => [
        'expiration_minutes' => (int) env('EMPLOYEE_INVITATION_EXPIRATION_MINUTES', 2880),
        'resend_cooldown_seconds' => (int) env('EMPLOYEE_INVITATION_RESEND_COOLDOWN_SECONDS', 60),
    ],

    'local_admin' => [
        'name' => env('LOCAL_ADMIN_NAME', 'Local Administrator'),
        'email' => env('LOCAL_ADMIN_EMAIL'),
        'password' => env('LOCAL_ADMIN_PASSWORD'),
    ],
];
