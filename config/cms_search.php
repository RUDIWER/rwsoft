<?php

return [
    'engine' => env('CMS_SEARCH_ENGINE', 'mysql'),
    'default_limit' => 20,
    'max_limit' => 50,
    'chunk_size' => 3000,
    'source_types' => [
        'page',
        'post',
        'blog_index',
        'category_index',
        'category',
        'tag_index',
        'tag',
        'docs_index',
        'docs_collection',
        'docs_version',
        'doc_page',
    ],
];
