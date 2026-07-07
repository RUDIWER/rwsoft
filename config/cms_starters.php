<?php

return [
    'import' => [
        'manifest_type' => 'rwsoft-cms-starter',
        'allowed_paths' => [
            'manifest.json',
            'theme/',
            'layouts.json',
            'templates.json',
            'pages.json',
            'menus.json',
            'forms.json',
            'media/',
        ],
        'allowed_extensions' => ['json', 'css', 'png', 'jpg', 'jpeg', 'webp', 'avif'],
        'importable_modules' => ['theme', 'layouts', 'templates', 'pages', 'menus', 'forms', 'media'],
        'max_files' => 500,
        'max_file_bytes' => 5 * 1024 * 1024,
        'max_json_bytes' => 2 * 1024 * 1024,
        'allow_code_blocks_by_default' => false,
    ],
];
