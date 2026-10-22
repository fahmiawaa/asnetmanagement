<?php

return [
    'connect_timeout_seconds' => (int) env('GENIEACS_CONNECT_TIMEOUT_SECONDS', 3),
    'request_timeout_seconds' => (int) env('GENIEACS_REQUEST_TIMEOUT_SECONDS', 15),
    'sync_chunk_size' => (int) env('GENIEACS_SYNC_CHUNK_SIZE', 100),
    'missing_after_minutes' => (int) env('GENIEACS_MISSING_AFTER_MINUTES', 30),
    'sync_max_age_minutes' => (int) env('GENIEACS_SYNC_MAX_AGE_MINUTES', 10),
    'fallback_inform_interval_seconds' => (int) env(
        'GENIEACS_FALLBACK_INFORM_INTERVAL_SECONDS',
        300
    ),
    'ont_list_per_page' => (int) env('GENIEACS_ONT_LIST_PER_PAGE', 25),
    'history_retention_days' => (int) env('GENIEACS_HISTORY_RETENTION_DAYS', 60),
    'history_prune_batch' => (int) env('GENIEACS_HISTORY_PRUNE_BATCH', 5000),
    'alerts' => [
        'enabled' => filter_var(env('GENIEACS_ALERTS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'batch_limit' => (int) env('GENIEACS_ALERTS_BATCH_LIMIT', 50),
        'notify_offline' => filter_var(
            env('GENIEACS_ALERTS_NOTIFY_OFFLINE', true),
            FILTER_VALIDATE_BOOLEAN
        ),
        'notify_optical' => filter_var(
            env('GENIEACS_ALERTS_NOTIFY_OPTICAL', true),
            FILTER_VALIDATE_BOOLEAN
        ),
    ],
    'tasks' => [
        'enabled' => filter_var(env('GENIEACS_TASKS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'command_timeout_seconds' => (int) env('GENIEACS_TASK_TIMEOUT_SECONDS', 15),
        'max_attempts' => (int) env('GENIEACS_TASK_MAX_ATTEMPTS', 3),
        'retry_delay_seconds' => (int) env('GENIEACS_TASK_RETRY_DELAY_SECONDS', 30),
        'verify_attempts' => (int) env('GENIEACS_TASK_VERIFY_ATTEMPTS', 5),
        'verify_delay_seconds' => (int) env('GENIEACS_TASK_VERIFY_DELAY_SECONDS', 60),
        'expiry_minutes' => (int) env('GENIEACS_TASK_EXPIRY_MINUTES', 30),
        'batch_limit' => (int) env('GENIEACS_TASK_BATCH_LIMIT', 25),
        'endpoints' => [
            'command' => '/devices/{device}?commands=1',
            'task' => '/devices/{device}/tasks',
            'read' => '/devices/{device}',
        ],
        'commands' => [
            'summon' => ['name' => 'ConnectionRequest'],
            'reboot' => ['name' => 'Reboot'],
        ],
        'set_parameter_body' => [
            'name' => 'SetVirtualParameter',
            'objectName' => '{path}',
            'value' => '{value}',
        ],
        'read_denylist_fragments' => [
            'password',
            'keypassphrase',
            'presharedkey',
            'virtualparameters.superadmin',
            'virtualparameters.superpassword',
            'virtualparameters.userpassword',
            'virtualparameters.useradmin',
            'virtualparameters.pppoepassword',
        ],
    ],
    'rx_power' => [
        'min_valid_dbm' => -50.0,
        'max_valid_dbm' => 10.0,
        'sentinels' => [
            '', 'n/a', 'na', 'nan', 'null', 'none', 'inf', '-inf', '--', '-',
        ],
        'thresholds' => [
            'default' => [
                'overload_above' => -8.0,
                'good_min' => -24.0,
                'warning_min' => -27.0,
            ],
            'gpon' => [],
            'epon' => [],
        ],
    ],
    'denied_projection_fragments' => [
        'password',
        'keypassphrase',
        'presharedkey',
        'virtualparameters.superadmin',
        'virtualparameters.useradmin',
    ],
    'projection' => [
        '_id',
        '_lastInform',
        '_deviceId._Manufacturer',
        '_deviceId._OUI',
        '_deviceId._ProductClass',
        '_deviceId._SerialNumber',
        'VirtualParameters.IPTR069',
        'VirtualParameters.PonMac',
        'VirtualParameters.RXPower',
        'VirtualParameters.activedevices',
        'VirtualParameters.getSerialNumber',
        'VirtualParameters.getdeviceuptime',
        'VirtualParameters.getponmode',
        'VirtualParameters.getpppuptime',
        'VirtualParameters.gettemp',
        'VirtualParameters.pppoeIP',
        'VirtualParameters.pppoeMac',
        'InternetGatewayDevice.ManagementServer.PeriodicInformInterval',
        'Device.ManagementServer.PeriodicInformInterval',
    ],
];
