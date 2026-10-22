<?php

return [
    'mysqldump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
    'mysql_binary' => env('MYSQL_BINARY', 'mysql'),
    'timeout_seconds' => (int) env('BACKUP_TIMEOUT_SECONDS', 600),
    'minimum_bytes' => (int) env('BACKUP_MINIMUM_BYTES', 1024),
    'required_tables' => [
        'users',
        'customers',
        'invoices',
        'payments',
        'ont_devices',
        'mikrotik_devices',
    ],
    'local_disk' => env('BACKUP_LOCAL_DISK', 'private'),
    'local_path' => trim(env('BACKUP_LOCAL_PATH', 'backups'), '/'),
    'local_retention_days' => (int) env('BACKUP_LOCAL_RETENTION_DAYS', 14),
    'offsite_enabled' => (bool) env('BACKUP_OFFSITE_ENABLED', false),
    'offsite_disk' => env('BACKUP_OFFSITE_DISK', 'offsite'),
    'offsite_path' => trim(env('BACKUP_OFFSITE_PATH', 'asnet-backups'), '/'),
    'offsite_retention_days' => (int) env('BACKUP_OFFSITE_RETENTION_DAYS', 30),
    'encryption' => [
        'enabled' => filter_var(
            env('BACKUP_ENCRYPTION_ENABLED', false),
            FILTER_VALIDATE_BOOLEAN
        ),
        'key' => env('BACKUP_ENCRYPTION_KEY'),
    ],
];
