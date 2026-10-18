<?php

namespace App\Services\IntelliReply;

use App\Models\AiUsageLog;
use Throwable;

/**
 * Records the tokens (or audio minutes and cost, for transcription) of every AI model request. Logging must never break a reply,
 * so failures are reported and swallowed.
 */
class AiUsageLogService
{
    public const PURPOSE_AUTO_REPLY = 'auto_reply';
    public const PURPOSE_SUGGESTION = 'suggestion';
    public const PURPOSE_TRANSCRIPTION = 'transcription';
    public const PURPOSE_SPEECH = 'speech';

    /**
     * @param  array{provider?:string,model?:string,input_tokens?:int,output_tokens?:int,audio_seconds?:float|null,cost_usd?:float|null}  $usage
     */
    public function record(int $organizationId, ?int $contactId, string $purpose, ?string $outcome, array $usage, ?string $keySource): void
    {
        $audioSeconds = $usage['audio_seconds'] ?? null;
        $cost = $usage['cost_usd'] ?? null;

        try {
            AiUsageLog::create([
                'organization_id' => $organizationId,
                'contact_id' => $contactId,
                'purpose' => $purpose,
                'outcome' => $outcome,
                'provider' => (string) ($usage['provider'] ?? 'unknown'),
                'model' => mb_substr((string) ($usage['model'] ?? 'unknown'), 0, 128),
                'key_source' => $keySource,
                'input_tokens' => max(0, (int) ($usage['input_tokens'] ?? 0)),
                'output_tokens' => max(0, (int) ($usage['output_tokens'] ?? 0)),
                'audio_seconds' => is_numeric($audioSeconds) ? round(max(0, (float) $audioSeconds), 1) : null,
                'cost_usd' => is_numeric($cost) ? round(max(0, (float) $cost), 6) : null,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Token, audio-minute and cost totals per model for an organization between two dates.
     *
     * @return list<array{provider:string,model:string,key_source:?string,requests:int,input_tokens:int,output_tokens:int,audio_seconds:float,cost_usd:float}>
     */
    public function totals(int $organizationId, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return AiUsageLog::query()
            ->where('organization_id', $organizationId)
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('provider', 'model', 'key_source')
            ->selectRaw('provider, model, key_source, COUNT(*) as requests, SUM(input_tokens) as input_tokens, SUM(output_tokens) as output_tokens, SUM(COALESCE(audio_seconds, 0)) as audio_seconds, SUM(COALESCE(cost_usd, 0)) as cost_usd')
            ->get()
            ->map(fn ($row) => [
                'provider' => (string) $row->provider,
                'model' => (string) $row->model,
                'key_source' => $row->key_source,
                'requests' => (int) $row->requests,
                'input_tokens' => (int) $row->input_tokens,
                'output_tokens' => (int) $row->output_tokens,
                'audio_seconds' => (float) $row->audio_seconds,
                'cost_usd' => (float) $row->cost_usd,
            ])
            ->all();
    }
}
