<?php

return [
    'default_install_profile' => env('RWSOFT_INSTALL_PROFILE', 'docker'),

    'install_profiles' => [
        'docker' => [
            'default_tenant_storage' => 'create_database',
        ],
        'lerd' => [
            'default_tenant_storage' => 'create_database',
        ],
        'herd' => [
            'default_tenant_storage' => 'create_database',
        ],
        'laravel-cloud' => [
            'default_tenant_storage' => 'shared_prefixed',
        ],
    ],
];
