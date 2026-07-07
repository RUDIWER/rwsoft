<?php

return [
    'default_database_mode' => env('TENANCY_DATABASE_MODE', 'separate'),

    'default_provisioning_mode' => env('TENANCY_PROVISIONING_MODE', 'create_database'),

    'shared_database' => env('TENANCY_SHARED_DATABASE', env('CENTRAL_DB_DATABASE', env('DB_DATABASE', 'laravel'))),

    'database_modes' => ['separate', 'shared_prefixed'],

    'provisioning_modes' => ['create_database', 'existing_database', 'shared_prefixed'],

    'table_prefix_pattern' => '/^[a-z][a-z0-9_]{1,30}_$/',
];
