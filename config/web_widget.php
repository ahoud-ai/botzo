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
    'max_pages' => 10,
    'max_page_label_length' => 40,

    // "mochi": the Mochi mascot (default); "botzo": dark with Botzo's brand icons; "classic": light.
    'themes' => ['mochi', 'botzo', 'classic'],

    // OpenAI Realtime voices a customer may pick for voice calls.
    'voices' => ['alloy', 'marin', 'cedar', 'coral', 'sage', 'shimmer', 'ash', 'ballad', 'verse', 'echo'],

    // Platform defaults; the admin can override them (setting "web_widget_defaults").
    'defaults' => [
        'theme' => 'mochi',
        'accent' => '#22D366',
        'accent_secondary' => '#2F7BFF',
        'position' => 'left',
        'assistant_name' => 'موتشي',
        'business_label' => '',
        'welcome_text' => '',
        'suggestions' => [],
        'pages' => [],
        'hide_branding' => false,
        'voice_enabled' => false,
        'voice' => 'alloy',
    ],

    // Voice call limits shared by every organization; admin-editable.
    'voice_limits' => [
        'call_max_minutes' => 5,
        'daily_minutes_cap' => 300,
    ],

];
