<?php

namespace Tests\Feature;

use App\Events\NewChatEvent;
use App\Jobs\ProcessWebhookJob;
use App\Jobs\ProcessWebhookMediaJob;
use App\Jobs\RunAutoReplyJob;
use App\Models\Chat;
use App\Models\Organization;
use App\Services\AutoReplyDispatchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

/**
 * Queue mode: the media download and the automatic reply run in separate jobs, so a voice
 * note is answered only after its file is in (or its download definitely failed).
 */
class VoiceNoteQueueModeTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['graph.webhook_processing' => 'queue', 'auto_replies.debounce_seconds' => 4]);
        Event::fake([NewChatEvent::class]);
        Queue::fake();
    }

    public function test_a_voice_note_waits_for_its_download_before_any_reply_is_scheduled(): void
    {
        $organization = $this->organization();

        $this->deliver($organization, [$this->voiceMessage('wamid.voice-1', now()->timestamp)]);

        Queue::assertPushed(ProcessWebhookMediaJob::class, fn (ProcessWebhookMediaJob $job) => $this->repliesAfterDownload($job));
        Queue::assertNotPushed(RunAutoReplyJob::class);

        $voice = Chat::where('wam_id', 'wamid.voice-1')->firstOrFail();
        $this->assertSame((int) $voice->id, $this->latestChatId($voice));
    }

    public function test_text_messages_are_still_scheduled_right_away(): void
    {
        $organization = $this->organization();

        $this->deliver($organization, [$this->textMessage('wamid.text-1', now()->timestamp)]);

        Queue::assertPushed(RunAutoReplyJob::class, 1);
    }

    public function test_an_older_text_in_the_same_delivery_does_not_take_over_from_a_newer_voice_note(): void
    {
        $organization = $this->organization();

        $this->deliver($organization, [
            $this->textMessage('wamid.text-older', now()->subSeconds(5)->timestamp),
            $this->voiceMessage('wamid.voice-newer', now()->timestamp),
        ]);

        Queue::assertNotPushed(RunAutoReplyJob::class);
        $voice = Chat::where('wam_id', 'wamid.voice-newer')->firstOrFail();
        $this->assertSame((int) $voice->id, $this->latestChatId($voice));
    }

    public function test_the_deferred_reply_is_skipped_when_a_newer_message_arrived_meanwhile(): void
    {
        $organization = $this->organization();
        $this->deliver($organization, [$this->voiceMessage('wamid.voice-2', now()->subSeconds(10)->timestamp)]);
        $voice = Chat::where('wam_id', 'wamid.voice-2')->firstOrFail();

        app(AutoReplyDispatchService::class)->dispatchDeferred($voice);
        Queue::assertPushed(RunAutoReplyJob::class, 1);

        $this->deliver($organization, [$this->textMessage('wamid.text-after', now()->timestamp)]);
        app(AutoReplyDispatchService::class)->dispatchDeferred($voice);

        // One reply for the voice note before the text arrived, one for the text; none for the voice note afterwards.
        Queue::assertPushed(RunAutoReplyJob::class, 2);
    }

    public function test_a_failed_download_still_schedules_the_reply_so_the_customer_is_asked_to_type(): void
    {
        $organization = $this->organization();
        $this->deliver($organization, [$this->voiceMessage('wamid.voice-3', now()->timestamp)]);
        $voice = Chat::where('wam_id', 'wamid.voice-3')->firstOrFail();

        (new ProcessWebhookMediaJob(['type' => 'audio', 'audio' => ['id' => 'm1']], (int) $voice->id, (int) $organization->id, true))
            ->failed(new RuntimeException('download failed'));
        Queue::assertPushed(RunAutoReplyJob::class, fn (RunAutoReplyJob $job) => true);

        Queue::fake();
        (new ProcessWebhookMediaJob(['type' => 'audio', 'audio' => ['id' => 'm1']], (int) $voice->id, (int) $organization->id, false))
            ->failed(new RuntimeException('download failed'));
        Queue::assertNotPushed(RunAutoReplyJob::class);
    }

    private function repliesAfterDownload(ProcessWebhookMediaJob $job): bool
    {
        return (fn () => $this->replyAfterDownload)->call($job) === true;
    }

    private function latestChatId(Chat $chat): ?int
    {
        $value = Cache::get(sprintf('auto-reply:latest:%d:%d', $chat->organization_id, $chat->contact_id));

        return $value === null ? null : (int) $value;
    }

    private function organization(): Organization
    {
        [, $organization] = $this->createOwnerContext([
            'whatsapp' => ['waba_id' => 'waba-q', 'phone_number_id' => 'phone-q'],
        ], true);

        return $organization;
    }

    private function deliver(Organization $organization, array $messages): void
    {
        (new ProcessWebhookJob([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'waba-q',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['phone_number_id' => 'phone-q'],
                        'contacts' => [['wa_id' => '966501112233', 'profile' => ['name' => 'Customer']]],
                        'messages' => $messages,
                    ],
                ]],
            ]],
        ], (int) $organization->id))->handle();
    }

    private function voiceMessage(string $wamId, int $timestamp): array
    {
        return [
            'from' => '966501112233',
            'id' => $wamId,
            'timestamp' => (string) $timestamp,
            'type' => 'audio',
            'audio' => ['id' => 'media-'.Str::random(6), 'mime_type' => 'audio/ogg; codecs=opus', 'voice' => true],
        ];
    }

    private function textMessage(string $wamId, int $timestamp): array
    {
        return [
            'from' => '966501112233',
            'id' => $wamId,
            'timestamp' => (string) $timestamp,
            'type' => 'text',
            'text' => ['body' => 'مرحبا'],
        ];
    }
}
