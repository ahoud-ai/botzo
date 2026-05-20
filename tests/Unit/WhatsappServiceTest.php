<?php

namespace Tests\Unit;

use App\Models\Chat;
use App\Models\ChatMedia;
use App\Models\Contact;
use App\Models\Organization;
use App\Events\NewChatEvent;
use App\Services\OutboundMessageLimitGuardService;
use App\Services\WhatsappService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

class WhatsappServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_remote_media_metadata_uses_head_response_headers(): void
    {
        Http::fake([
            '*' => Http::response('', 200, [
                'Content-Length' => '12345',
                'Content-Type' => 'image/png',
            ]),
        ]);

        $service = $this->makeService();

        $this->assertSame(12345, $service->getMediaSizeInBytesFromUrl('https://example.com/media.png'));
        $this->assertSame('image/png', $service->getContentTypeFromUrl('https://example.com/media.png'));

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->method() === 'HEAD');
    }

    public function test_remote_media_metadata_has_safe_fallbacks_when_headers_are_missing(): void
    {
        Http::fake([
            '*' => Http::response('', 200),
        ]);

        $service = $this->makeService();

        $this->assertSame(0, $service->getMediaSizeInBytesFromUrl('https://example.com/media.mp3'));
        $this->assertSame('application/octet-stream', $service->getContentTypeFromUrl('https://example.com/media.mp3'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_send_media_uses_upload_metadata_when_remote_content_length_is_missing(): void
    {
        Event::fake([NewChatEvent::class]);
        Http::fake([
            '*' => Http::response('', 200),
        ]);

        $this->app->bind(OutboundMessageLimitGuardService::class, fn () => new class {
            public function blockedResponseForOrganization(int $organizationId): ?object
            {
                return null;
            }
        });

        $organization = Organization::create([
            'uuid' => (string) Str::uuid(),
            'identifier' => 'media-org',
            'name' => 'Media Org',
            'created_by' => 1,
        ]);

        $contact = Contact::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'first_name' => 'Audio',
            'phone' => '+966501000000',
            'created_by' => 1,
        ]);

        $mock = Mockery::mock('overload:GuzzleHttp\Client');
        $mock->shouldReceive('request')
            ->once()
            ->withArgs(fn ($method, $url, $options) => $method === 'POST'
                && str_contains($url, '/phone-id/messages')
                && ($options['json']['type'] ?? null) === 'audio'
                && ($options['json']['audio']['link'] ?? null) === 'https://example.com/audio.mp3')
            ->andReturn(new \GuzzleHttp\Psr7\Response(200, [], json_encode([
                'messages' => [
                    ['id' => 'wamid.media-test'],
                ],
            ])));

        $service = $this->makeService($organization->id);

        $response = $service->sendMedia(
            $contact->uuid,
            'audio',
            'audio-message.mp3',
            'public/audio-message.mp3',
            'https://example.com/audio.mp3',
            'local',
            null,
            null,
            [
                'content_type' => 'audio/mpeg',
                'size' => 3210,
            ]
        );

        $this->assertTrue($response->success);
        $this->assertDatabaseHas('chat_media', [
            'name' => 'audio-message.mp3',
            'path' => 'https://example.com/audio.mp3',
            'type' => 'audio/mpeg',
            'size' => 3210,
        ]);
        $this->assertDatabaseHas('chats', [
            'organization_id' => $organization->id,
            'wam_id' => 'wamid.media-test',
            'contact_id' => $contact->id,
            'type' => 'outbound',
            'status' => 'sent',
            'media_id' => ChatMedia::where('path', 'https://example.com/audio.mp3')->value('id'),
        ]);

        $chat = Chat::with('media')->where('wam_id', 'wamid.media-test')->firstOrFail();
        $this->assertSame('https://example.com/audio.mp3', $chat->media->path);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_send_message_persists_interactive_buttons_metadata_for_chat_preview(): void
    {
        Event::fake([NewChatEvent::class]);
        $this->disableOutboundMessageLimitGuard();

        [$organization, $contact] = $this->createOrganizationAndContact('buttons-org');

        $mock = Mockery::mock('overload:GuzzleHttp\Client');
        $mock->shouldReceive('request')
            ->once()
            ->withArgs(fn ($method, $url, $options) => $method === 'POST'
                && str_contains($url, '/phone-id/messages')
                && data_get($options, 'json.type') === 'interactive'
                && data_get($options, 'json.interactive.type') === 'button'
                && data_get($options, 'json.interactive.action.buttons.0.reply.title') === 'Sales')
            ->andReturn(new \GuzzleHttp\Psr7\Response(200, [], json_encode([
                'messages' => [
                    ['id' => 'wamid.buttons-test'],
                ],
            ])));

        $service = $this->makeService($organization->id);

        $response = $service->sendMessage(
            $contact->uuid,
            'Choose a service',
            null,
            'interactive buttons',
            [
                ['id' => 'sales', 'title' => 'Sales'],
            ],
            ['type' => 'text', 'text' => 'Support desk'],
            'Pick one'
        );

        $this->assertTrue($response->success);

        $chat = Chat::where('wam_id', 'wamid.buttons-test')->firstOrFail();
        $metadata = json_decode($chat->metadata, true);

        $this->assertSame('interactive', data_get($metadata, 'type'));
        $this->assertSame('Choose a service', data_get($metadata, 'text.body'));
        $this->assertSame('button', data_get($metadata, 'interactive.type'));
        $this->assertSame('Choose a service', data_get($metadata, 'interactive.body.text'));
        $this->assertSame('Support desk', data_get($metadata, 'interactive.header.text'));
        $this->assertSame('Pick one', data_get($metadata, 'interactive.footer.text'));
        $this->assertSame('Sales', data_get($metadata, 'interactive.action.buttons.0.reply.title'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_send_message_persists_interactive_list_metadata_for_chat_preview(): void
    {
        Event::fake([NewChatEvent::class]);
        $this->disableOutboundMessageLimitGuard();

        [$organization, $contact] = $this->createOrganizationAndContact('list-org');

        $mock = Mockery::mock('overload:GuzzleHttp\Client');
        $mock->shouldReceive('request')
            ->once()
            ->withArgs(fn ($method, $url, $options) => $method === 'POST'
                && str_contains($url, '/phone-id/messages')
                && data_get($options, 'json.type') === 'interactive'
                && data_get($options, 'json.interactive.type') === 'list'
                && data_get($options, 'json.interactive.action.button') === 'Open list'
                && data_get($options, 'json.interactive.action.sections.0.rows.0.title') === 'Tomorrow 10 AM')
            ->andReturn(new \GuzzleHttp\Psr7\Response(200, [], json_encode([
                'messages' => [
                    ['id' => 'wamid.list-test'],
                ],
            ])));

        $service = $this->makeService($organization->id);

        $response = $service->sendMessage(
            $contact->uuid,
            'Pick an appointment',
            null,
            'interactive list',
            [
                [
                    'title' => 'Available slots',
                    'rows' => [
                        ['id' => 'slot-1', 'title' => 'Tomorrow 10 AM', 'description' => 'First available slot'],
                    ],
                ],
            ],
            [],
            null,
            'Open list'
        );

        $this->assertTrue($response->success);

        $chat = Chat::where('wam_id', 'wamid.list-test')->firstOrFail();
        $metadata = json_decode($chat->metadata, true);

        $this->assertSame('interactive', data_get($metadata, 'type'));
        $this->assertSame('Pick an appointment', data_get($metadata, 'text.body'));
        $this->assertSame('list', data_get($metadata, 'interactive.type'));
        $this->assertSame('Pick an appointment', data_get($metadata, 'interactive.body.text'));
        $this->assertSame('Open list', data_get($metadata, 'interactive.action.button'));
        $this->assertSame('Tomorrow 10 AM', data_get($metadata, 'interactive.action.sections.0.rows.0.title'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_create_resumable_upload_session_handles_generic_exceptions_without_throwing(): void
    {
        $mock = Mockery::mock('overload:GuzzleHttp\Client');
        $mock->shouldReceive('request')
            ->once()
            ->andThrow(new \RuntimeException('upload session failed'));

        $service = $this->makeService();
        $file = UploadedFile::fake()->create('brochure.pdf', 10, 'application/pdf');

        $response = $service->createResumableUploadSession($file);

        $this->assertFalse($response->success);
        $this->assertSame('upload session failed', $response->data->error->message);
    }

    private function disableOutboundMessageLimitGuard(): void
    {
        $this->app->bind(OutboundMessageLimitGuardService::class, fn () => new class {
            public function blockedResponseForOrganization(int $organizationId): ?object
            {
                return null;
            }
        });
    }

    private function createOrganizationAndContact(string $identifier): array
    {
        $organization = Organization::create([
            'uuid' => (string) Str::uuid(),
            'identifier' => $identifier,
            'name' => ucwords(str_replace('-', ' ', $identifier)),
            'created_by' => 1,
        ]);

        $contact = Contact::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'first_name' => 'Preview',
            'phone' => '+966501000000',
            'created_by' => 1,
        ]);

        return [$organization, $contact];
    }

    private function makeService(int $organizationId = 1): WhatsappService
    {
        return new WhatsappService(
            'token',
            'v22.0',
            'app-id',
            'phone-id',
            'waba-id',
            $organizationId
        );
    }
}
