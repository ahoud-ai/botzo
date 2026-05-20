<?php

$scaleReadyProfile = [
    'connection' => env('QUEUE_PROFILE_CONNECTION', 'redis'),
    'cache_store' => env('QUEUE_PROFILE_CACHE_STORE', 'redis'),
    'session_driver' => env('QUEUE_PROFILE_SESSION_DRIVER', 'redis'),
    'queues' => [
        'default',
        'automation-flow-resume',
        'campaign-messages',
        'webhook-media',
    ],
    'workers' => [
        [
            'name' => 'default-worker',
            'queues' => ['default', 'automation-flow-resume'],
            'sleep' => 1,
            'tries' => 3,
            'timeout' => 120,
        ],
        [
            'name' => 'campaign-worker',
            'queues' => ['campaign-messages'],
            'sleep' => 1,
            'tries' => 3,
            'timeout' => 120,
        ],
        [
            'name' => 'webhook-media-worker',
            'queues' => ['webhook-media'],
            'sleep' => 1,
            'tries' => 3,
            'timeout' => 120,
        ],
    ],
];

return [
    /*
    |--------------------------------------------------------------------------
    | Queue Profiles
    |--------------------------------------------------------------------------
    |
    | Operational profiles used by runbooks, health checks, and CLI helpers.
    | The "production" profile is the scale-ready default for this project.
    |
    */
    'active' => env('QUEUE_PROFILE_NAME', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Readiness Scoring Profile
    |--------------------------------------------------------------------------
    |
    | Readiness and risk commands can score scalability against a dedicated
    | deployment profile even when local runtime uses "shared".
    |
    */
    'readiness_profile' => env('QUEUE_PROFILE_READINESS', 'production'),

    'production' => $scaleReadyProfile,

    'scale-ready' => $scaleReadyProfile,

    'shared' => [
        'connection' => env('QUEUE_PROFILE_SHARED_CONNECTION', 'database'),
        'cache_store' => env('QUEUE_PROFILE_SHARED_CACHE_STORE', 'file'),
        'session_driver' => env('QUEUE_PROFILE_SHARED_SESSION_DRIVER', 'file'),
        'queues' => [
            'default',
            'automation-flow-resume',
            'campaign-messages',
            'webhook-media',
        ],
        'workers' => [
            [
                'name' => 'shared-cron-worker',
                'queues' => [
                    'default',
                    'automation-flow-resume',
                    'campaign-messages',
                    'webhook-media',
                ],
                'sleep' => 1,
                'tries' => 3,
                'timeout' => 120,
            ],
        ],
    ],
];
