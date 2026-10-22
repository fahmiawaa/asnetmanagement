<?php

return [
    'qris' => [
        /*
        |--------------------------------------------------------------------------
        | Static QRIS configuration
        |--------------------------------------------------------------------------
        |
        | Static QRIS does not expire at the QR-code level. The application
        | creates a 24-hour payment request window and still requires manual
        | verification by an admin after the customer pays.
        |
        */
        'enabled' => filter_var(env('QRIS_ENABLED', false), FILTER_VALIDATE_BOOL),
        'image_path' => env(
            'QRIS_STATIC_IMAGE_PATH',
            'images/qris/asnet-qris.png'
        ),
        'merchant_name' => env('QRIS_MERCHANT_NAME', 'ASNet'),
        'expiration_hours' => (int) env('QRIS_EXPIRATION_HOURS', 24),
    ],
];
