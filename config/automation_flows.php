<?php

return [
    'enabled' => (bool) env('FLOW_BUILDER_V2_ENABLED', true),
    'default_goal_preset' => env('FLOW_BUILDER_V2_DEFAULT_GOAL', 'sales_qualification'),
    'max_nodes' => (int) env('FLOW_BUILDER_V2_MAX_NODES', 80),
    'max_edges' => (int) env('FLOW_BUILDER_V2_MAX_EDGES', 160),
    'autosave_debounce_ms' => (int) env('FLOW_BUILDER_V2_AUTOSAVE_DEBOUNCE_MS', 1200),
    'ui_enhancements_enabled' => (bool) env('FLOW_BUILDER_V2_UI_ENHANCEMENTS_ENABLED', true),
    'preview_default_scenario' => env('FLOW_BUILDER_V2_PREVIEW_SCENARIO', 'main'),
    'max_execution_steps' => (int) env('FLOW_BUILDER_V2_MAX_EXECUTION_STEPS', 60),
    'resume_queue' => env('FLOW_BUILDER_V2_RESUME_QUEUE', 'automation-flow-resume'),
    'asset_url_ttl_minutes' => (int) env('FLOW_BUILDER_V2_ASSET_URL_TTL_MINUTES', 1440),
    'runtime' => [
        'active_run_stale_minutes' => (int) env('FLOW_BUILDER_V2_ACTIVE_RUN_STALE_MINUTES', 30),
        'waiting_input_stale_minutes' => (int) env('FLOW_BUILDER_V2_WAITING_INPUT_STALE_MINUTES', 1440),
        'waiting_handoff_stale_minutes' => (int) env('FLOW_BUILDER_V2_WAITING_HANDOFF_STALE_MINUTES', 10080),
        'contact_lock_ttl_seconds' => (int) env('FLOW_BUILDER_V2_CONTACT_LOCK_TTL_SECONDS', 10),
        'contact_lock_wait_seconds' => (int) env('FLOW_BUILDER_V2_CONTACT_LOCK_WAIT_SECONDS', 3),
        'invalid_reply_default_behavior' => env('FLOW_BUILDER_V2_INVALID_REPLY_DEFAULT_BEHAVIOR', 'release_to_fallback'),
        'invalid_reply_behaviors' => [
            'release_to_fallback',
            'repeat_prompt',
            'end_run',
        ],
    ],
    'whatsapp' => [
        'customer_care_window_hours' => (int) env('FLOW_BUILDER_V2_CUSTOMER_CARE_WINDOW_HOURS', 24),
        'enforce_customer_care_window' => (bool) env('FLOW_BUILDER_V2_ENFORCE_CUSTOMER_CARE_WINDOW', true),
        'preview_customer_care_window_open' => (bool) env('FLOW_BUILDER_V2_PREVIEW_CUSTOMER_CARE_WINDOW_OPEN', true),
        // Supported actions: fail_run | release_to_fallback
        'on_window_closed' => env('FLOW_BUILDER_V2_ON_WINDOW_CLOSED', 'fail_run'),
    ],
    'builder_policy' => [
        'channel' => env('FLOW_BUILDER_V2_CHANNEL', 'whatsapp'),
        'whatsapp_only_mode' => (bool) env('FLOW_BUILDER_V2_WHATSAPP_ONLY_MODE', true),
        'allow_external_actions' => (bool) env('FLOW_BUILDER_V2_ALLOW_EXTERNAL_ACTIONS', false),
        'allow_crm_actions' => (bool) env('FLOW_BUILDER_V2_ALLOW_CRM_ACTIONS', true),
    ],
];
