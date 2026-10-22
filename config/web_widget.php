<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Website chat widget
    |--------------------------------------------------------------------------
    |
    | Limits protect the organization's AI quota from abuse on public sites.
    | They apply per visitor session unless stated otherwise.
    |
    */

    'limits' => [
        'message_min_interval_seconds' => (int) env('WEB_WIDGET_MESSAGE_MIN_INTERVAL', 3),
        'messages_per_hour' => (int) env('WEB_WIDGET_MESSAGES_PER_HOUR', 30),
        'sessions_per_ip_per_hour' => (int) env('WEB_WIDGET_SESSIONS_PER_IP_PER_HOUR', 10),
        'message_max_length' => 500,
    ],

    // How often an open widget asks for new team replies.
    'poll_interval_seconds' => 15,

    // Messages returned when a visitor reopens the widget.
    'history_limit' => 50,

    'max_domains' => 10,
    'max_suggestions' => 4,
    'max_suggestion_length' => 60,
    'max_welcome_length' => 300,

    'themes' => ['botzo', 'classic'],

    'defaults' => [
        // "botzo": dark with Botzo's brand icons; "classic": light in the customer's own colour.
        'theme' => 'botzo',
        'accent' => '#034737',
        'position' => 'right',
        'assistant_name' => '',
        'welcome_text' => '',
        'suggestions' => [],
    ],

];
