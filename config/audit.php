<?php

return [
    'enabled' => env('AUDIT_ENABLED', true),
    'db_enabled' => env('AUDIT_DB_ENABLED', true),
    'file_enabled' => env('AUDIT_FILE_ENABLED', true),
    'channel' => env('AUDIT_LOG_CHANNEL', 'audit'),
    'max_meta_string_length' => (int) env('AUDIT_MAX_META_STRING_LENGTH', 4000),
    'redacted_keys' => [
        'password',
        'password_confirmation',
        'token',
        'api_token',
        'api_key',
        'access_token',
        'access_key_id',
        'access_key_secret',
        'refresh_token',
        'secret',
        'authorization',
        'cookie',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ],
];
