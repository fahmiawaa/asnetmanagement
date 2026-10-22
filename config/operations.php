<?php

return [
    'health_token' => env('OPERATIONS_HEALTH_TOKEN'),
    'scheduler_max_age_minutes' => (int) env('OPERATIONS_SCHEDULER_MAX_AGE_MINUTES', 10),
    'backup_max_age_hours' => (int) env('OPERATIONS_BACKUP_MAX_AGE_HOURS', 26),
    'mikrotik_sync_max_age_minutes' => (int) env('OPERATIONS_MIKROTIK_SYNC_MAX_AGE_MINUTES', 15),
    'queue_backlog_warning' => (int) env('OPERATIONS_QUEUE_BACKLOG_WARNING', 100),
    'disk_free_warning_percent' => (float) env('OPERATIONS_DISK_FREE_WARNING_PERCENT', 15),
    'processing_timeout_minutes' => (int) env(
        'OPERATIONS_PROCESSING_TIMEOUT_MINUTES',
        env('MIKROTIK_ISOLATION_PROCESSING_TIMEOUT_MINUTES', 10)
    ),
    'alert' => [
        'emails' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('OPERATIONS_ALERT_EMAILS', ''))
        ))),
        'webhook_url' => env('OPERATIONS_ALERT_WEBHOOK_URL'),
        'webhook_token' => env('OPERATIONS_ALERT_WEBHOOK_TOKEN'),
        'cooldown_minutes' => (int) env('OPERATIONS_ALERT_COOLDOWN_MINUTES', 30),
    ],
];
