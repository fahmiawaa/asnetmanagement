<?php

return [
    'allow_real_actions' => filter_var(
        env('MIKROTIK_ALLOW_REAL_ACTIONS', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    'auto_isolation_enabled' => filter_var(
        env('MIKROTIK_AUTO_ISOLATION_ENABLED', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    'grace_period_days' => (int) env('MIKROTIK_GRACE_PERIOD_DAYS', 7),

    'isolation_batch_size' => min(
        100,
        max(1, (int) env('MIKROTIK_ISOLATION_BATCH_SIZE', 25))
    ),

    'isolation_max_attempts' => max(
        1,
        (int) env('MIKROTIK_ISOLATION_MAX_ATTEMPTS', 5)
    ),

    'isolation_retry_base_minutes' => max(
        1,
        (int) env('MIKROTIK_ISOLATION_RETRY_BASE_MINUTES', 2)
    ),

    'isolation_failure_threshold' => max(
        1,
        (int) env('MIKROTIK_ISOLATION_FAILURE_THRESHOLD', 3)
    ),

    'isolation_processing_timeout_minutes' => max(
        5,
        (int) env('MIKROTIK_ISOLATION_PROCESSING_TIMEOUT_MINUTES', 10)
    ),

    'restore_batch_size' => min(
        100,
        max(1, (int) env('MIKROTIK_RESTORE_BATCH_SIZE', 25))
    ),

    'restore_max_attempts' => max(
        1,
        (int) env('MIKROTIK_RESTORE_MAX_ATTEMPTS', 10)
    ),

    'restore_retry_base_minutes' => max(
        1,
        (int) env('MIKROTIK_RESTORE_RETRY_BASE_MINUTES', 2)
    ),

    'webhook_token' => env('MIKROTIK_WEBHOOK_TOKEN'),

    'webhook_allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MIKROTIK_WEBHOOK_ALLOWED_IPS', '192.168.27.1'))
    ))),
];
