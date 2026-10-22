<?php

namespace App\Services\WebWidget;

use App\Models\Contact;
use App\Models\Organization;
use App\Models\WebWidget;
use App\Models\WebWidgetSession;
use App\Models\WebWidgetVoiceCall;
use App\Services\AutomationFlows\AutomationFlowConversationHandoffService;
use App\Services\IntelliReply\AiConversationPauseService;
use App\Services\IntelliReply\AiKeyResolver;
use App\Services\IntelliReply\AiKnowledgeRetriever;
use App\Services\IntelliReply\AiPersonaPromptBuilder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Voice calls of the website widget on the OpenAI Realtime API. The visitor's browser talks to
 * OpenAI over WebRTC with a short-lived client secret from start(); this service checks the
 * minutes (plan per month, per call, platform per day), counts them, stores what was said in the
 * inbox and answers the assistant's tool calls.
 */
class WebWidgetVoiceService
{
    public const UNAVAILABLE_OFF = 'voice_off';
    public const UNAVAILABLE_PLAN = 'plan_minutes_used';
    public const UNAVAILABLE_PLATFORM = 'platform_daily_cap';
    public const UNAVAILABLE_KEY = 'no_ai_key';
    public const UNAVAILABLE_PROVIDER = 'provider_error';

    public function __construct(
        private readonly WebWidgetEntitlements $entitlements,
        private readonly WebWidgetDefaults $defaults,
        private readonly WebWidgetAssistantContext $context,
    ) {
    }

    /**
     * @return array{available: bool, reason: ?string, remaining_seconds: int}
     */
    public function availability(WebWidget $widget): array
    {
        $settings = $widget->settingsWithDefaults();
        if (empty($settings['voice_enabled'])) {
            return ['available' => false, 'reason' => self::UNAVAILABLE_OFF, 'remaining_seconds' => 0];
        }

        $monthlyMinutes = $this->entitlements->for((int) $widget->organization_id)['voice_minutes_monthly'];
        if ($monthlyMinutes === 0) {
            return ['available' => false, 'reason' => self::UNAVAILABLE_OFF, 'remaining_seconds' => 0];
        }

        $limits = $this->defaults->voiceLimits();
        $platformLeft = $limits['daily_minutes_cap'] * 60 - $this->usedSeconds(null, now()->startOfDay());
        if ($platformLeft <= 0) {
            return ['available' => false, 'reason' => self::UNAVAILABLE_PLATFORM, 'remaining_seconds' => 0];
        }

        $planLeft = $monthlyMinutes < 0
            ? PHP_INT_MAX
            : $monthlyMinutes * 60 - $this->usedSeconds((int) $widget->organization_id, now()->startOfMonth());
        if ($planLeft <= 0) {
            return ['available' => false, 'reason' => self::UNAVAILABLE_PLAN, 'remaining_seconds' => 0];
        }

        return ['available' => true, 'reason' => null, 'remaining_seconds' => (int) min($planLeft, $platformLeft)];
    }

    /**
     * Open a call: a client secret for the browser plus the call's own limits.
     *
     * @return array{call: WebWidgetVoiceCall, client_secret: string, expires_at: ?int}|array{error: string}
     */
    public function start(WebWidget $widget, WebWidgetSession $session, string $locale): array
    {
        $availability = $this->availability($widget);
        if (! $availability['available']) {
            return ['error' => (string) $availability['reason']];
        }

        $organization = Organization::find($widget->organization_id);
        $metadata = json_decode((string) $organization?->metadata, true) ?: [];
        $apiKey = app(AiKeyResolver::class)->resolveForOrganization($metadata, data_get($metadata, 'ai.key_source', 'auto'), (int) $widget->organization_id)['key'] ?? null;
        if (! $apiKey) {
            return ['error' => self::UNAVAILABLE_KEY];
        }

        $settings = $widget->settingsWithDefaults();
        $contact = app(WebWidgetService::class)->contactFor($session);
        $maxSeconds = (int) min($this->defaults->voiceLimits()['call_max_minutes'] * 60, $availability['remaining_seconds']);
        $model = (string) config('web_widget.voice.model');
        $voice = in_array($settings['voice'] ?? null, (array) config('web_widget.voices', []), true) ? $settings['voice'] : 'alloy';

        $response = Http::withToken($apiKey)->timeout(20)->post((string) config('web_widget.voice.client_secrets_url'), [
            'expires_after' => ['anchor' => 'created_at', 'seconds' => (int) config('web_widget.voice.secret_ttl_seconds', 60)],
            'session' => [
                'type' => 'realtime',
                'model' => $model,
                'instructions' => $this->instructions($organization, $metadata, $contact, $apiKey),
                'audio' => [
                    'input' => [
                        'noise_reduction' => ['type' => 'near_field'],
                        'transcription' => array_filter([
                            'model' => (string) config('web_widget.voice.transcription_model'),
                            'language' => $locale === 'en' ? 'en' : 'ar',
                            'prompt' => $locale === 'en' ? null : (string) config('web_widget.voice.transcription_hint'),
                        ]),
                        'turn_detection' => [
                            'type' => 'server_vad',
                            'silence_duration_ms' => (int) config('web_widget.voice.silence_ms', 700),
                            'create_response' => true,
                            'interrupt_response' => true,
                        ],
                    ],
                    'output' => ['voice' => $voice, 'speed' => (float) config('web_widget.voice.speed', 1.05)],
                ],
                'tools' => $this->tools($settings),
                'tool_choice' => 'auto',
                'max_output_tokens' => (int) config('web_widget.voice.max_output_tokens', 400),
            ],
        ]);

        $secret = (string) ($response->json('value') ?? $response->json('client_secret.value') ?? '');
        if (! $response->successful() || $secret === '') {
            Log::channel('intellireply')->warning('intellireply.web_widget.voice_start_failed', [
                'organization_id' => $widget->organization_id,
                'status' => $response->status(),
                'error' => $response->json('error.message'),
            ]);

            return ['error' => self::UNAVAILABLE_PROVIDER];
        }

        $call = WebWidgetVoiceCall::create([
            'web_widget_id' => $widget->id,
            'organization_id' => $widget->organization_id,
            'web_widget_session_id' => $session->id,
            'contact_id' => $contact->id,
            'model' => $model,
            'voice' => $voice,
            'status' => WebWidgetVoiceCall::STATUS_ACTIVE,
            'max_seconds' => $maxSeconds,
            'started_at' => now(),
        ]);

        return ['call' => $call, 'client_secret' => $secret, 'expires_at' => $response->json('expires_at')];
    }

    /**
     * Close a call. The length is what the widget reports, never more than the time the server saw.
     */
    public function end(WebWidgetVoiceCall $call, int $reportedSeconds): WebWidgetVoiceCall
    {
        if ($call->status === WebWidgetVoiceCall::STATUS_ENDED) {
            return $call;
        }

        $elapsed = (int) $call->started_at->diffInSeconds(now(), true);
        $call->forceFill([
            'status' => WebWidgetVoiceCall::STATUS_ENDED,
            'seconds' => (int) min($call->max_seconds, max(0, $reportedSeconds), $elapsed + 5),
            'ended_at' => now(),
        ])->save();

        return $call;
    }

    /**
     * Store one finished line of the call in the inbox (no automatic text reply is triggered).
     */
    public function transcript(WebWidgetVoiceCall $call, string $role, string $text): bool
    {
        $text = trim(mb_substr($text, 0, 2000));
        $contact = Contact::find($call->contact_id);
        $lines = (int) \App\Models\Chat::where('contact_id', $call->contact_id)
            ->where('metadata', 'like', '%"voice_call":"'.$call->uuid.'"%')
            ->count();

        if ($text === '' || ! $contact || $lines >= (int) config('web_widget.voice.transcript_lines_per_call', 200)) {
            return false;
        }

        app(WebWidgetMessageStore::class)->storeText($contact, $text, $role === 'user' ? 'inbound' : 'outbound', null, ['voice_call' => $call->uuid]);

        return true;
    }

    /**
     * Answer a tool the assistant called during the call.
     */
    public function tool(WebWidgetVoiceCall $call, string $name, array $arguments): array
    {
        $organization = Organization::find($call->organization_id);
        $metadata = json_decode((string) $organization?->metadata, true) ?: [];

        if ($name === 'search_knowledge') {
            $apiKey = app(AiKeyResolver::class)->resolveForOrganization($metadata, data_get($metadata, 'ai.key_source', 'auto'), (int) $call->organization_id)['key'] ?? null;
            $found = app(AiKnowledgeRetriever::class)->retrieve((int) $call->organization_id, $metadata, $apiKey, mb_substr((string) ($arguments['query'] ?? ''), 0, 300));

            return ['result' => $found ? mb_substr($found, 0, 6000) : 'No matching information. Offer to connect the visitor with the team.'];
        }

        if ($name === 'request_human') {
            $contact = Contact::find($call->contact_id);
            if ($contact) {
                app(AiConversationPauseService::class)->pauseFor($contact, (int) config('intellireply.handoff.pause_minutes', 720));
                try {
                    app(AutomationFlowConversationHandoffService::class)->assignToAgent($contact, 'auto_assign', null, true, 'voice_call_handoff');
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }

            return ['result' => 'The team has been notified and will reply in this chat. Tell the visitor briefly and end the call politely.'];
        }

        return ['result' => 'Unknown tool.'];
    }

    public function usedSeconds(?int $organizationId, \DateTimeInterface $since): int
    {
        $query = WebWidgetVoiceCall::where('started_at', '>=', $since);
        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        return (int) $query->get(['status', 'seconds', 'max_seconds', 'started_at'])->sum(fn (WebWidgetVoiceCall $call) => $call->countedSeconds());
    }

    private function instructions(?Organization $organization, array $metadata, Contact $contact, string $apiKey): string
    {
        $faqs = app(AiKnowledgeRetriever::class)->retrieve((int) $organization?->id, $metadata, $apiKey, '');
        $parts = [
            app(AiPersonaPromptBuilder::class)->replySystemPrompt($metadata, $organization?->name, $faqs),
            (string) $this->context->instructions($contact),
            'This is a live voice call. When the call opens, you speak first with a short warm greeting and ask how you can help. Speak naturally and briefly: one or two short sentences, one question at a time. Never read out links, lists, prices tables or emojis; say numbers simply. If you need details you do not have, call search_knowledge before answering. If the visitor wants a person, call request_human. To open a page of the website, say one short line and call navigate_to with its key instead of writing a marker.',
        ];

        // The conversation so far (text and earlier calls), so a new call picks up where it was.
        $history = \App\Models\Chat::where('contact_id', $contact->id)->whereNull('deleted_at')->orderByDesc('id')->take(12)->get(['type', 'metadata'])
            ->reverse()
            ->map(function ($chat) {
                $text = trim((string) data_get(json_decode((string) $chat->metadata, true), 'text.body', ''));

                return $text === '' ? null : ($chat->type === 'inbound' ? 'Visitor: ' : 'You: ').mb_substr($text, 0, 300);
            })
            ->filter()
            ->implode("\n");
        if ($history !== '') {
            $parts[] = "Recent conversation with this visitor (continue from it, do not repeat what was already said):\n".$history;
        }

        // Brand names the voice tends to run together.
        $names = mb_strtolower((string) $organization?->name.' '.(string) data_get($metadata, 'ai.persona.business_name', ''));
        if (str_contains($names, 'botzo') || str_contains($names, 'بوتزو')) {
            $parts[] = 'Pronounce the name «بوتزو» / "Botzo" as two clear parts: «بوت زو» (Bot-Zo), never as one word.';
        }

        return trim(implode("\n\n", array_filter($parts)));
    }

    private function tools(array $settings): array
    {
        $tools = [
            [
                'type' => 'function',
                'name' => 'search_knowledge',
                'description' => 'Search the business knowledge (FAQs, documents, prices) to answer the visitor accurately.',
                'parameters' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string', 'description' => 'What to look up']], 'required' => ['query']],
            ],
            [
                'type' => 'function',
                'name' => 'request_human',
                'description' => 'Hand the conversation to a human team member when the visitor asks for a person or the answer is unknown.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
            ],
        ];

        $pages = WebWidgetPages::forWidget($settings);
        if ($pages !== []) {
            $tools[] = [
                'type' => 'function',
                'name' => 'navigate_to',
                'description' => 'Open one of the website pages for the visitor.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['page' => ['type' => 'string', 'enum' => array_column($pages, 'key'), 'description' => implode('; ', array_map(fn ($p) => $p['key'].' = '.$p['label'], $pages))]],
                    'required' => ['page'],
                ],
            ];
        }

        return $tools;
    }
}
