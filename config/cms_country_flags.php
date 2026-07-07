<?php

return [
    'source' => [
        'name' => 'lipis/flag-icons',
        'version' => 'v7.5.0',
        'archive_url' => 'https://github.com/lipis/flag-icons/archive/refs/tags/v7.5.0.zip',
        'license_url' => 'https://raw.githubusercontent.com/lipis/flag-icons/v7.5.0/LICENSE',
    ],

    'storage' => [
        'disk' => 'local',
        'root' => 'system/countries',
        'format' => '4x3',
        'catalog' => 'catalog.json',
        'license' => 'LICENSE.flag-icons.txt',
    ],

    'tenant_media' => [
        'disk' => 'public',
        'root_folder_name' => 'Countries',
        'root_folder_slug' => 'countries',
        'asset_directory' => 'cms/media',
    ],
];
