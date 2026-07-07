<?php

return [
    'backup_storage_path' => env('DATABASE_BACKUP_STORAGE_PATH', 'DB-Backup'),
    'backup_retention_days' => (int) env('DATABASE_BACKUP_RETENTION_DAYS', 7),

    'view_blocked_tables' => [
        'cache',
        'cache_locks',
    ],

    'edit_blocked_tables' => [
        'database_editor_logs',
        'database_logs',
        'password_reset_tokens',
        'sessions',
        'jobs',
        'failed_jobs',
        'cache',
        'cache_locks',
        'personal_access_tokens',
        'migrations',
    ],

    'non_editable_columns_by_table' => [
        'database_editor_logs' => ['*'],
        'users' => ['password', 'remember_token'],
        'personal_access_tokens' => ['token'],
    ],
];
