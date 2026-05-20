<?php

return [
    'demo_mode' => env('APP_ENV') === 'demo',
    'enable_database_config' => (bool) env('ENABLE_DATABASE_CONFIG', false),
    'demo_number' => env('DEMO_NUMBER'),
];
