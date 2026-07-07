<?php

return [
    'source_path' => resource_path('js/validation/extended_rules.js'),
    'build_command' => ['npm', 'run', 'build'],
    'build_timeout_seconds' => (int) env('CLIENT_RULES_BUILD_TIMEOUT', 900),
    'run_build_on_save' => (bool) env('CLIENT_RULES_BUILD_ON_SAVE', true),
    'run_build_on_publish' => (bool) env('CLIENT_RULES_BUILD_ON_PUBLISH', true),
    'run_syntax_check' => (bool) env('CLIENT_RULES_SYNTAX_CHECK', true),
];
