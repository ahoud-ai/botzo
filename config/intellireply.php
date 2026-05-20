<?php

return [
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

    // Speech-to-text transcription model and safe fallbacks.
    'default_transcription_model' => env('INTELLIREPLY_DEFAULT_TRANSCRIPTION_MODEL', 'gpt-4o-mini-transcribe'),
    'transcription_model_fallbacks' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('INTELLIREPLY_TRANSCRIPTION_MODEL_FALLBACKS', 'gpt-4o-mini-transcribe,whisper-1'))
    ))),
];
