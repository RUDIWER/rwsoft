<?php

return [
    'sources' => [
        'dynamic_prompts' => [
            'label' => 'Dynamic prompts',
            'path_template' => 'lang/{locale}/dynamic_prompts.php',
        ],
        'translation_editor_ui' => [
            'label' => 'Translation editor UI',
            'path_template' => 'lang/{locale}/translation_editor_ui.php',
        ],
        'auth_ui' => [
            'label' => 'Authentication UI',
            'path_template' => 'lang/{locale}/auth_ui.php',
        ],
        'admin_common_ui' => [
            'label' => 'Admin common UI',
            'path_template' => 'lang/{locale}/admin_common_ui.php',
        ],
        'admin_security_ui' => [
            'label' => 'Admin security UI',
            'path_template' => 'lang/{locale}/admin_security_ui.php',
        ],
        'query_builder_ui' => [
            'label' => 'Query builder UI',
            'path_template' => 'lang/{locale}/query_builder_ui.php',
        ],
        'db_diagram_ui' => [
            'label' => 'DB diagram UI',
            'path_template' => 'lang/{locale}/db_diagram_ui.php',
        ],
        'cms_admin_ui' => [
            'label' => 'CMS admin UI',
            'path_template' => 'lang/{locale}/cms_admin_ui.php',
        ],
        'cms_validation' => [
            'label' => 'CMS validation',
            'path_template' => 'lang/{locale}/cms_validation.php',
        ],
        'rwtable' => [
            'label' => 'RWTable',
            'path_template' => 'lang/vendor/rwtable/{locale}/rwtable.php',
        ],
    ],
    'source_locale' => env('TRANSLATION_EDITOR_SOURCE_LOCALE', 'en'),
    'backup_directory' => env(
        'TRANSLATION_EDITOR_BACKUP_DIRECTORY',
        storage_path('app/private/translation-backups')
    ),
    'app_config_path' => env('TRANSLATION_EDITOR_APP_CONFIG_PATH', ''),
    'ai' => [
        'defaults' => [
            'provider' => env('TRANSLATION_EDITOR_AI_PROVIDER', 'gemini'),
            'model' => env('TRANSLATION_EDITOR_AI_MODEL', 'gemini-2.5-flash'),
        ],
        'fill_limit_default' => (int) env('TRANSLATION_EDITOR_AI_FILL_LIMIT_DEFAULT', 100),
        'fill_limit_max' => (int) env('TRANSLATION_EDITOR_AI_FILL_LIMIT_MAX', 500),
        'providers' => [
            'gemini' => [
                'label' => 'Google Gemini',
                'default_model' => 'gemini-2.5-flash',
                'models' => [
                    ['value' => 'gemini-2.5-flash', 'label' => 'Gemini 2.5 Flash'],
                    ['value' => 'gemini-2.0-flash', 'label' => 'Gemini 2.0 Flash'],
                ],
            ],
            'openai' => [
                'label' => 'OpenAI',
                'default_model' => 'gpt-4.1-mini',
                'models' => [
                    ['value' => 'gpt-4.1-mini', 'label' => 'GPT-4.1 Mini'],
                    ['value' => 'gpt-4.1', 'label' => 'GPT-4.1'],
                ],
            ],
        ],
    ],
];
