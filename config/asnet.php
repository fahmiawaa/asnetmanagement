<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public registration
    |--------------------------------------------------------------------------
    |
    | ASNet is an administrative workspace. Public registration is disabled
    | by default and should only be enabled temporarily for a controlled
    | bootstrap process.
    |
    */
    'public_registration' => filter_var(
        env('ASNET_PUBLIC_REGISTRATION', false),
        FILTER_VALIDATE_BOOLEAN
    ),
];
