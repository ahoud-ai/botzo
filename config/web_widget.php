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

    // Voice calls run on the OpenAI Realtime API (WebRTC from the visitor's browser). The server
    // only hands out short-lived client secrets; the API key never leaves it.
    'voice' => [
        'model' => env('WEB_WIDGET_VOICE_MODEL', 'gpt-realtime-2.1-mini'),
        'transcription_model' => env('WEB_WIDGET_VOICE_TRANSCRIPTION_MODEL', 'gpt-4o-mini-transcribe'),
        'calls_url' => 'https://api.openai.com/v1/realtime/calls',
        'client_secrets_url' => 'https://api.openai.com/v1/realtime/client_secrets',
        'secret_ttl_seconds' => 60,
        'silence_ms' => 700,
        'speed' => 1.05,
        // Spoken audio counts toward the output tokens; a low cap cuts replies off before their end.
        'max_output_tokens' => 4096,
        'transcript_lines_per_call' => 200,
        // Words that help the transcription hear product names right.
        'transcription_hint' => 'بوتزو، واتساب، باقة، ربط الرقم، عرض توضيحي، أتمتة، موتشي',
    ],

];
