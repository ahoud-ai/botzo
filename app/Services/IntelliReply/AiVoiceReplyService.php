<?php

namespace App\Services\IntelliReply;

use App\Models\Contact;
use App\Services\Ai\AiProviderManager;
use App\Services\Ai\Data\AiSpeechRequest;
use App\Services\SettingValueService;
use App\Services\WhatsappService;
use App\Support\AudioDuration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends the assistant's text reply as a WhatsApp voice message (Ogg/Opus, voice: true).
 *
 * The reply itself is still written by the chat model, so persona, business-only rules and
 * handoffs are unchanged; this only turns the final text into speech. The text is kept on the
 * message so the team can read what the assistant said. Any failure returns false and the
 * caller sends the text instead: the customer always gets an answer.
 */
class AiVoiceReplyService
{
    private const ACCENTS = [
        'auto' => 'Speak naturally, with the accent that matches the language and dialect of the text.',
        'egyptian' => 'Speak in a natural Egyptian Arabic accent.',
        'gulf' => 'Speak in a natural Gulf (Saudi) Arabic accent.',
        'maghrebi' => 'Speak in a natural Maghrebi Arabic accent; pronounce French words in French.',
        'msa' => 'Speak in clear Modern Standard Arabic.',
        'english' => 'Speak in clear English.',
    ];

    public function send(Contact $contact, WhatsappService $whatsapp, array $organizationMetadata, string $apiKey, ?string $keySource, string $text): bool
    {
        $text = trim($text);
        $maxCharacters = (int) config('intellireply.speech.max_characters', 600);

        if ($text === '' || ($maxCharacters > 0 && mb_strlen($text) > $maxCharacters)) {
            $this->skip($contact, 'too_long');

            return false;
        }

        $persona = app(AiPersonaPromptBuilder::class)->persona($organizationMetadata);
        $model = (string) config('intellireply.speech.model', 'gpt-4o-mini-tts');

        try {
            $speech = app(AiProviderManager::class)
                ->speech(data_get($organizationMetadata, 'ai.speech_provider'), $apiKey)
                ->speak(new AiSpeechRequest(
                    text: $text,
                    model: $model,
                    voice: (string) (data_get($organizationMetadata, 'ai.voice') ?: config('intellireply.speech.default_voice', 'alloy')),
                    format: 'opus',
                    instructions: self::ACCENTS[$persona['dialect']] ?? self::ACCENTS['auto'],
                    timeoutSeconds: max(5, (int) config('intellireply.speech.timeout_seconds', 30)),
                ));

            $file = $this->store($speech->audio, (int) $contact->organization_id);
        } catch (Throwable $exception) {
            $this->skip($contact, 'speech_failed', ['error' => $exception->getMessage()]);

            return false;
        }

        $seconds = AudioDuration::seconds($speech->audio, $speech->mimeType, 0);

        // Upload first and send by id: WhatsApp then never has to download the file from our host
        // (shared hosting firewalls can block Meta, error 131053). Falls back to the public link.
        $mediaId = $whatsapp->uploadMedia($speech->audio, $speech->mimeType, $file['name']);

        $sent = $whatsapp->sendMedia(
            $contact->uuid,
            'audio',
            $file['name'],
            $file['path'],
            $file['url'],
            $file['location'],
            null,
            $text,
            array_filter(['voice' => true, 'content_type' => $speech->mimeType, 'size' => strlen($speech->audio), 'media_id' => $mediaId])
        );
        $delivered = ($sent->success ?? false) === true;

        app(AiUsageLogService::class)->record((int) $contact->organization_id, (int) $contact->id, AiUsageLogService::PURPOSE_SPEECH, $delivered ? 'sent' : 'send_failed', [
            'provider' => $speech->provider,
            'model' => $speech->model,
            'audio_seconds' => $seconds,
            'cost_usd' => $this->cost($speech->model, $seconds),
        ], $keySource);

        if (! $delivered) {
            $this->skip($contact, 'send_failed');
        }

        return $delivered;
    }

    private function cost(string $model, ?float $seconds): ?float
    {
        $price = ((array) config('intellireply.speech.price_per_minute_usd', []))[$model] ?? null;

        return $seconds !== null && is_numeric($price) ? $seconds / 60 * (float) $price : null;
    }

    /**
     * Voice replies are sent by the business, so WhatsApp downloads them by link: they are public,
     * like every other file the business sends.
     *
     * @return array{name:string,path:string,url:string,location:string}
     */
    private function store(string $audio, int $organizationId): array
    {
        $name = 'reply_'.Str::uuid().'.ogg';

        if (app(SettingValueService::class)->getString('storage_system', 'local') === 'aws') {
            $path = 'uploads/media/sent/'.$organizationId.'/'.$name;
            Storage::disk('s3')->put($path, $audio, ['ContentType' => 'audio/ogg', 'visibility' => 'public']);
            $url = Storage::disk('s3')->url($path);

            return ['name' => $name, 'path' => $url, 'url' => $url, 'location' => 'amazon'];
        }

        $path = 'public/audios/'.$name;
        if (! Storage::disk('local')->put($path, $audio)) {
            throw new \RuntimeException(__('Unable to store the voice reply.'));
        }

        return ['name' => $name, 'path' => $path, 'url' => rtrim((string) config('app.url'), '/').'/media/'.$path, 'location' => 'local'];
    }

    private function skip(Contact $contact, string $reason, array $context = []): void
    {
        Log::channel('intellireply')->info('intellireply.voice_reply.sent_as_text', array_merge([
            'organization_id' => $contact->organization_id,
            'contact_id' => $contact->id,
            'reason' => $reason,
        ], $context));
    }
}
