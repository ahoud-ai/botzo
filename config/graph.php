<?php

return [
    'api_version' => env('GRAPH_API_VERSION', 'v20.0'), // Default to v20.0
    'access_token_refresh_buffer_hours' => (int) env('WHATSAPP_TOKEN_REFRESH_BUFFER_HOURS', 168),

    // How verified inbound webhooks are processed:
    //  - after_response: acknowledge Meta immediately, then process in the same PHP process (no worker needed)
    //  - queue: push to the queue below (requires a running queue worker)
    //  - sync: process before responding (legacy; slow AI replies can trigger Meta retries)
    'webhook_processing' => env('WHATSAPP_WEBHOOK_PROCESSING', 'after_response'),
    'webhook_queue' => env('WHATSAPP_WEBHOOK_QUEUE', 'whatsapp-webhooks'),
];
