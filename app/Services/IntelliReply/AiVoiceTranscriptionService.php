<?php

namespace App\Services\IntelliReply;

use App\Events\NewChatEvent;
use App\Models\Chat;
use App\Models\ChatLog;
use App\Models\ChatMedia;
use App\Services\Ai\AiProviderManager;
use App\Services\Ai\Data\AiTranscriptionRequest;
use App\Support\AudioDuration;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns a customer's voice note into text once, and keeps the transcript on the message
 * (metadata.ai_transcript) for the reply context, agent suggestions and the chat view.
 *
 * The language is detected by the model (Arabic and French are often mixed in the same
 * note); the organization's dialect only adds a hint, except English which is pinned.
 */
class AiVoiceTranscriptionService
{
    public const STATUS_OK = 'ok';
    public const STATUS_NOT_READY = 'not_ready';
    public const STATUS_UNSUPPORTED = 'unsupported';
    public const STATUS_TOO_LONG = 'too_long';
    public const STATUS_EMPTY = 'empty';
    public const STATUS_FAILED = 'failed';

    /** Formats the transcription API accepts, by base MIME type. */
    private const FORMATS = [
        'audio/ogg' => 'ogg',
        'audio/opus' => 'ogg',
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/m4a' => 'm4a',
        'audio/x-m4a' => 'm4a',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/wave' => 'wav',
        'audio/webm' => 'webm',
        'audio/flac' => 'flac',
        'audio/x-flac' => 'flac',
    ];

    private const DIALECT_HINTS = [
        'auto' => 'The customer may speak Gulf, Egyptian, Levantine or Maghrebi Arabic, often mixed with French or English words.',
        'egyptian' => 'The customer speaks Egyptian Arabic, sometimes with English words.',
        'gulf' => 'The customer speaks Gulf Arabic, sometimes with English words.',
        'maghrebi' => 'The customer speaks Maghrebi Arabic (Moroccan, Algerian or Tunisian Darija), often mixed with French words.',
        'msa' => 'The customer speaks Arabic.',
        'english' => 'The customer speaks English.',
    ];

    /**
     * @return array{status:string,text:?string,model:?string,cached:bool,seconds:?float}
     */
    public function transcribe(Chat $chat, array $organizationMetadata, ?string $organizationName, string $apiKey, ?string $keySource = null): array
    {
        $chatMetadata = $this->chatMetadata($chat);
        $stored = $this->storedTranscript($chatMetadata);
        if ($stored !== null) {
            return $this->result(self::STATUS_OK, $stored, data_get($chatMetadata, 'ai_transcript.model'), true, data_get($chatMetadata, 'ai_transcript.seconds'));
        }

        $media = $chat->media_id ? ChatMedia::find($chat->media_id) : null;
        if (! $media) {
            return $this->fail($chat, self::STATUS_NOT_READY, 'media_missing');
        }

        $mimeType = self::baseMimeType((string) ($media->type ?: data_get($chatMetadata, 'audio.mime_type', '')));
        $extension = self::FORMATS[$mimeType] ?? null;
        if ($extension === null) {
            return $this->fail($chat, self::STATUS_UNSUPPORTED, 'format_unsupported', ['mime_type' => $mimeType]);
        }

        $audio = $this->readAudioBinary((string) $media->path);
        if ($audio === null || $audio === '') {
            return $this->fail($chat, self::STATUS_NOT_READY, 'media_unreadable');
        }

        // Checked before anything is sent, so an over-long note costs nothing.
        $seconds = AudioDuration::seconds($audio, $mimeType, (int) config('intellireply.transcription.fallback_bitrate_kbps', 64));
        $maxSeconds = (int) config('intellireply.transcription.max_seconds', 120);
        if ($maxSeconds > 0 && $seconds !== null && $seconds > $maxSeconds) {
            return $this->fail($chat, self::STATUS_TOO_LONG, 'too_long', ['seconds' => round($seconds, 1)], $seconds);
        }

        $persona = app(AiPersonaPromptBuilder::class)->persona($organizationMetadata, $organizationName);
        $provider = app(AiProviderManager::class)->transcription(data_get($organizationMetadata, 'ai.transcription_provider'), $apiKey);

        foreach ($this->modelCandidates() as $model) {
            try {
                $result = $provider->transcribe(new AiTranscriptionRequest(
                    audio: $audio,
                    fileName: 'voice.'.$extension,
                    mimeType: $mimeType,
                    model: $model,
                    language: $persona['dialect'] === 'english' ? 'en' : null,
                    prompt: $this->hint($persona),
                    timeoutSeconds: max(5, (int) config('intellireply.transcription.timeout_seconds', 30)),
                ));
            } catch (Throwable $exception) {
                Log::channel('intellireply')->notice('intellireply.audio.transcription_model_failed', [
                    'organization_id' => $chat->organization_id,
                    'chat_id' => $chat->id,
                    'model' => $model,
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }

            $seconds = $result->seconds ?? $seconds;
            $understood = mb_strlen($result->text) >= (int) config('intellireply.transcription.min_characters', 2);
            $this->recordUsage($chat, $result->provider, $model, $seconds, $understood ? 'transcribed' : 'empty', $keySource);

            if (! $understood) {
                return $this->fail($chat, self::STATUS_EMPTY, 'transcript_empty', ['model' => $model], $seconds);
            }

            $this->storeTranscript($chat, $chatMetadata, $result->text, $model, $seconds);

            return $this->result(self::STATUS_OK, $result->text, $model, false, $seconds);
        }

        return $this->fail($chat, self::STATUS_FAILED, 'transcription_failed', [], $seconds);
    }

    public function storedTranscript(array $chatMetadata): ?string
    {
        $text = data_get($chatMetadata, 'ai_transcript.text') ?? data_get($chatMetadata, 'transcript');

        return is_string($text) && trim($text) !== '' ? trim($text) : null;
    }

    /**
     * "audio/ogg; codecs=opus" -> "audio/ogg".
     */
    public static function baseMimeType(string $mimeType): string
    {
        return strtolower(trim(explode(';', $mimeType)[0]));
    }

    /**
     * @return list<string>
     */
    public function modelCandidates(): array
    {
        $models = array_merge(
            [(string) config('intellireply.transcription.model', 'gpt-4o-mini-transcribe')],
            (array) config('intellireply.transcription.fallback_models', [])
        );

        return array_values(array_unique(array_filter(array_map(fn ($model) => trim((string) $model), $models))));
    }

    /**
     * Estimated cost from the configured per-minute price of the model; null when unknown.
     */
    public function cost(string $model, ?float $seconds): ?float
    {
        $price = ((array) config('intellireply.transcription.price_per_minute_usd', []))[$model] ?? null;

        return $seconds !== null && is_numeric($price) ? $seconds / 60 * (float) $price : null;
    }

    private function recordUsage(Chat $chat, string $provider, string $model, ?float $seconds, string $outcome, ?string $keySource): void
    {
        app(AiUsageLogService::class)->record((int) $chat->organization_id, (int) $chat->contact_id, AiUsageLogService::PURPOSE_TRANSCRIPTION, $outcome, [
            'provider' => $provider,
            'model' => $model,
            'audio_seconds' => $seconds,
            'cost_usd' => $this->cost($model, $seconds),
        ], $keySource);
    }

    private function hint(array $persona): string
    {
        $business = $persona['business_name'] !== '' ? ' of '.$persona['business_name'] : '';

        return 'WhatsApp voice message from a customer'.$business.'. '
            .(self::DIALECT_HINTS[$persona['dialect']] ?? self::DIALECT_HINTS['auto']);
    }

    private function storeTranscript(Chat $chat, array $chatMetadata, string $text, string $model, ?float $seconds): void
    {
        $chatMetadata['ai_transcript'] = [
            'text' => $text,
            'model' => $model,
            'seconds' => $seconds !== null ? round($seconds, 1) : null,
            'created_at' => now()->toIso8601String(),
        ];

        Chat::where('id', $chat->id)->update(['metadata' => json_encode($chatMetadata, JSON_UNESCAPED_UNICODE)]);
        $chat->metadata = json_encode($chatMetadata, JSON_UNESCAPED_UNICODE);

        $this->broadcast($chat);
    }

    /**
     * Refresh the chat bubble so the team sees the transcript under the voice note.
     */
    private function broadcast(Chat $chat): void
    {
        try {
            $chatLog = ChatLog::where('entity_type', 'chat')
                ->where('entity_id', $chat->id)
                ->whereNull('deleted_at')
                ->latest('id')
                ->first();

            event(new NewChatEvent([[
                'type' => 'chat',
                'value' => $chatLog?->relatedEntities ?: Chat::with(['media', 'user', 'logs'])->find($chat->id),
            ]], (int) $chat->organization_id));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function chatMetadata(Chat $chat): array
    {
        $metadata = is_string($chat->metadata) ? json_decode($chat->metadata, true) : (array) $chat->metadata;

        return is_array($metadata) ? $metadata : [];
    }

    /**
     * @return array{status:string,text:?string,model:?string,cached:bool,seconds:?float}
     */
    private function result(string $status, ?string $text, ?string $model, bool $cached, mixed $seconds = null): array
    {
        return [
            'status' => $status,
            'text' => $text,
            'model' => $model,
            'cached' => $cached,
            'seconds' => is_numeric($seconds) ? (float) $seconds : null,
        ];
    }

    private function fail(Chat $chat, string $status, string $reason, array $context = [], ?float $seconds = null): array
    {
        Log::channel('intellireply')->warning('intellireply.audio.transcription_unavailable', array_merge([
            'organization_id' => $chat->organization_id,
            'chat_id' => $chat->id,
            'status' => $status,
            'reason' => $reason,
        ], $context));

        return $this->result($status, null, null, false, $seconds);
    }

    private function readAudioBinary(string $audioPath): ?string
    {
        $path = trim($audioPath);
        if ($path === '') {
            return null;
        }

        // Normalize malformed local paths that accidentally prefix storage path before an URL.
        $httpPos = strpos($path, 'http://');
        $httpsPos = strpos($path, 'https://');
        $urlPos = $httpPos !== false ? $httpPos : $httpsPos;
        if ($urlPos !== false && $urlPos > 0) {
            $path = substr($path, $urlPos);
        }

        // Resolve local media URL format: https://domain/media/public/<file>
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $resolvedLocal = $this->resolveStoragePathFromMediaUrl($path);
            if ($resolvedLocal !== null && file_exists($resolvedLocal)) {
                $contents = @file_get_contents($resolvedLocal);
                return $contents === false ? null : $contents;
            }

            if (! $this->isTrustedAudioUrl($path)) {
                Log::channel('intellireply')->warning('intellireply.audio_url_rejected', [
                    'host' => parse_url($path, PHP_URL_HOST),
                ]);

                return null;
            }

            $response = \Http::timeout(30)->retry(1, 250)->get($path);
            if ($response->successful()) {
                return $response->body();
            }

            return null;
        }

        // Absolute path
        if (file_exists($path)) {
            $contents = @file_get_contents($path);
            return $contents === false ? null : $contents;
        }

        // Relative storage path (e.g., public/..., uploads/...)
        $storagePath = storage_path('app/' . ltrim($path, '/'));
        if (file_exists($storagePath)) {
            $contents = @file_get_contents($storagePath);
            return $contents === false ? null : $contents;
        }

        return null;
    }

    private function resolveStoragePathFromMediaUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }

        // Local media controller serves files under /media/<storage-relative-path>
        if (str_starts_with($path, '/media/')) {
            $relative = ltrim(substr($path, strlen('/media/')), '/');
            if ($relative !== '') {
                return storage_path('app/' . $relative);
            }
        }

        return null;
    }

    private function isTrustedAudioUrl(string $url): bool
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https' || $host === '') {
            return false;
        }

        $trustedHosts = [];
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (is_string($appHost) && $appHost !== '') {
            $trustedHosts[] = strtolower($appHost);
        }

        foreach ([
            config('filesystems.disks.s3.url'),
            config('filesystems.disks.s3.endpoint'),
        ] as $configuredUrl) {
            $configuredHost = parse_url((string) $configuredUrl, PHP_URL_HOST);
            if (is_string($configuredHost) && $configuredHost !== '') {
                $trustedHosts[] = strtolower($configuredHost);
            }
        }

        $bucket = trim((string) config('filesystems.disks.s3.bucket'));
        $region = trim((string) config('filesystems.disks.s3.region'));
        if ($bucket !== '' && $region !== '') {
            $trustedHosts[] = strtolower($bucket . '.s3.' . $region . '.amazonaws.com');
        }
        if ($bucket !== '') {
            $trustedHosts[] = strtolower($bucket . '.s3.amazonaws.com');
        }

        return in_array($host, array_values(array_unique(array_filter($trustedHosts))), true);
    }
}
