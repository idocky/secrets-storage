<?php

return [

    'max_upload_size' => 5 * 1024 * 1024 * 1024,

    'object_prefix' => 'tus-uploads/',

    'part_size' => 8 * 1024 * 1024,

    'hooks_user' => 'hooks',

    'hooks_secret' => env('TUSD_HOOKS_SECRET') ?: hash('sha256', (string) env('APP_KEY')),

    'lifecycle_days' => (int) env('TUSD_LIFECYCLE_DAYS', 7),

    'bin' => env('TUSD_BIN'),

    'host' => env('TUSD_HOST', '127.0.0.1'),

    'port' => (int) env('TUSD_PORT', 1080),

    'version' => env('TUSD_VERSION', '2.8.0'),

];
