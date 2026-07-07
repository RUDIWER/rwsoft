<?php

return [
    'routes' => [
        'enabled' => true,
        'prefix' => 'admin',
        'name_prefix' => 'admin.',
        'middleware' => ['web', 'auth'],
    ],

    'field_aliases' => [
    ],

    'security' => [
        'allowed_field_pattern' => '/^[A-Za-z0-9_\.]+$/',
    ],
];
