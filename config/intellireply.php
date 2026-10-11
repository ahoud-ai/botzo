<?php

return [
    // AI providers by name. Chat and embeddings are resolved separately because not every
    // chat provider offers embeddings (knowledge-base search can stay on OpenAI).
    'providers' => [
        'openai' => App\Services\Ai\Providers\OpenAiProvider::class,
    ],
    'default_chat_provider' => env('INTELLIREPLY_CHAT_PROVIDER', 'openai'),
    'default_embedding_provider' => env('INTELLIREPLY_EMBEDDING_PROVIDER', 'openai'),

    // The preferred embedding model for new organizations.
    'default_embedding_model' => env('INTELLIREPLY_DEFAULT_EMBEDDING_MODEL', 'text-embedding-3-small'),

    // Allowlist of embedding models used by IntelliReply.
    'embedding_models' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('INTELLIREPLY_EMBEDDING_MODELS', 'text-embedding-3-small,text-embedding-3-large'))
    ))),

    // Ordered fallback chain when model access is restricted.
    'embedding_model_fallbacks' => [
        'text-embedding-3-small' => ['text-embedding-3-large'],
        'text-embedding-3-large' => ['text-embedding-3-small'],
    ],

    // Audio-capable chat models that support generated voice responses.
    'audio_response_models' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('INTELLIREPLY_AUDIO_RESPONSE_MODELS', 'gpt-audio-1.5,gpt-audio,gpt-audio-mini,gpt-4o-audio-preview'))
    ))),

    // Bot disclosure, sent once at the start of each conversation before the first AI reply.
    // Each organization can override the text from the AI settings page. {business} = organization name.
    'disclosure' => [
        'default_text' => env('INTELLIREPLY_DISCLOSURE_TEXT', 'مرحباً 👋 أنا المساعد الآلي لـ {business}. لو حابب تتكلم مع حد من فريقنا اكتب «موظف» في أي وقت.'),
        'conversation_hours' => (int) env('INTELLIREPLY_DISCLOSURE_CONVERSATION_HOURS', 24),
    ],

    // A customer asking for a person (e.g. "موظف") pauses automatic replies and hands the chat to the team.
    'handoff' => [
        'keywords' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('INTELLIREPLY_HANDOFF_KEYWORDS', 'موظف,انسان,بشري,خدمة العملاء,agent,human'))
        ))),
        'confirmation_text' => env('INTELLIREPLY_HANDOFF_CONFIRMATION_TEXT', 'تمام، حوّلنا محادثتك لأحد أعضاء فريقنا وهيرد عليك في أقرب وقت.'),
        'pause_minutes' => (int) env('INTELLIREPLY_HANDOFF_PAUSE_MINUTES', 720),
    ],

    // When the AI model fails or times out: apologize once, hand the chat to the team, and pause briefly.
    // The model could not answer from the business information: hand the chat to the team.
    'unknown_answer' => [
        'text' => env('INTELLIREPLY_UNKNOWN_ANSWER_TEXT', 'سؤالك محتاج حد من فريقنا. حوّلنا محادثتك لزميل وهيرد عليك في أقرب وقت.'),
        'pause_minutes' => (int) env('INTELLIREPLY_UNKNOWN_ANSWER_PAUSE_MINUTES', 720),
    ],

    'fallback' => [
        'text' => env('INTELLIREPLY_FALLBACK_TEXT', 'عذراً، الرد الآلي غير متاح حالياً. وصلتنا رسالتك وفريقنا هيرد عليك في أقرب وقت.'),
        'cooldown_minutes' => (int) env('INTELLIREPLY_FALLBACK_COOLDOWN_MINUTES', 30),
        'pause_minutes' => (int) env('INTELLIREPLY_FALLBACK_PAUSE_MINUTES', 15),
    ],

    // Give up on the model after this many seconds (voice replies get 15 seconds more).
    'request_timeout_seconds' => (int) env('INTELLIREPLY_REQUEST_TIMEOUT_SECONDS', 20),

    // After a team member replies, automatic replies to that customer pause for this long, then resume. 0 disables.
    'human_takeover_minutes' => (int) env('INTELLIREPLY_HUMAN_TAKEOVER_MINUTES', 60),

    // Always-on cost and loop protection, independent of plan billing settings.
    'safety' => [
        // Hard ceiling for completion tokens, whatever an organization saved in its settings.
        'max_output_tokens' => (int) env('INTELLIREPLY_MAX_OUTPUT_TOKENS', 1024),
        // Automatic AI replies to one contact per rolling hour; stops bot-to-bot loops. 0 disables the cap.
        'max_replies_per_contact_per_hour' => (int) env('INTELLIREPLY_MAX_REPLIES_PER_CONTACT_PER_HOUR', 15),
    ],

    // Speech-to-text transcription model and safe fallbacks.
    'default_transcription_model' => env('INTELLIREPLY_DEFAULT_TRANSCRIPTION_MODEL', 'gpt-4o-mini-transcribe'),
    'transcription_model_fallbacks' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('INTELLIREPLY_TRANSCRIPTION_MODEL_FALLBACKS', 'gpt-4o-mini-transcribe,whisper-1'))
    ))),
];
