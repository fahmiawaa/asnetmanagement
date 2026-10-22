<?php

return [
    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    */
    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | Do not use realpath() here. A fresh deployment may not have created the
    | directory yet, while Laravel can create compiled view files once the
    | configured path exists.
    |
    */
    'compiled' => env(
        'VIEW_COMPILED_PATH',
        storage_path('framework/views')
    ),
];
