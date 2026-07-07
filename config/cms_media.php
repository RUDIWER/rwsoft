<?php

return [
    'default_max_image_upload_mb' => 20,
    'max_image_upload_mb_min' => 1,
    'max_image_upload_mb_max' => 100,
    'max_width' => 8000,
    'max_height' => 8000,
    'variants' => [320, 640, 960, 1280, 1920],
    'responsive_targets' => [
        'mobile' => 640,
        'tablet' => 960,
        'desktop' => 1280,
        'display' => 1920,
    ],
    'formats' => ['webp'],
    'webp_quality' => 80,
];
