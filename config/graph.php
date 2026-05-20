<?php

return [
    'api_version' => env('GRAPH_API_VERSION', 'v20.0'), // Default to v20.0
    'access_token_refresh_buffer_hours' => (int) env('WHATSAPP_TOKEN_REFRESH_BUFFER_HOURS', 168),
];
