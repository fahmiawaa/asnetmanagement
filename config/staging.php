<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Password Akun Staging
    |--------------------------------------------------------------------------
    |
    | Dipakai oleh StagingSeeder dan perintah masking untuk menyetel ulang
    | password akun pada environment staging. Ganti nilai ini sebelum dibagikan
    | ke peserta pilot.
    |
    */

    'password' => env('STAGING_SEED_PASSWORD', 'Staging!1234'),

    'email_domain' => env('STAGING_EMAIL_DOMAIN', 'staging.test'),

    'customer_count' => (int) env('STAGING_CUSTOMER_COUNT', 10),

];
