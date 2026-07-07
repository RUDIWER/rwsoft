<?php

return [
    'disk' => env('CMS_DOWNLOADS_DISK', 'private'),

    'directory' => 'cms/downloads',

    'max_upload_kb' => (int) env('CMS_DOWNLOADS_MAX_UPLOAD_KB', 20480),

    'allowed_extensions' => [
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
        'odt',
        'ods',
        'odp',
        'txt',
        'csv',
    ],

    'allowed_mime_types' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.presentation',
        'text/plain',
        'text/csv',
    ],

    'default_access_mode' => 'authenticated',

    'folder_password_expires_minutes' => 120,
];
