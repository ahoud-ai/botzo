<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckSubscriptionStatus;
use App\Models\Setting;
use App\Services\Whatsapp\WhatsappTokenVault;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class WhatsappBusinessProfileUpdateTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    private const TOKEN = 'secret-test-token';

    /** @var array<int, array<string, mixed>> */
    private array $sentToMeta = [];

    /**
     * Mirrors what the customer hit: Meta refused the save while the sector was
     * UNDEFINED and accepted the same form once it was OTHER.
     */
    public function test_saving_with_the_undefined_sector_does_not_send_it_to_meta(): void
    {
        $this->fakeMetaRejectingUndefinedVertical();
        [$user, $organization] = $this->createOwnerContext($this->whatsappMetadata(['industry' => 'UNDEFINED']));

        $response = $this->saveProfile($user, $organization, [
            'email' => 'owner@example.com',
            'industry' => 'UNDEFINED',
        ]);

        $response->assertSessionHas('status.type', 'success');
        $this->assertArrayNotHasKey('vertical', $this->sentToMeta[0]);
        $this->assertSame('owner@example.com', $this->sentToMeta[0]['email']);
        $this->assertSame('UNDEFINED', $this->savedProfile($organization)['industry']);
        $this->assertSame('owner@example.com', $this->savedProfile($organization)['email']);
    }

    public function test_choosing_undefined_keeps_the_sector_already_saved(): void
    {
        $this->fakeMetaRejectingUndefinedVertical();
        [$user, $organization] = $this->createOwnerContext($this->whatsappMetadata(['industry' => 'RETAIL']));

        $this->saveProfile($user, $organization, ['industry' => 'UNDEFINED'])
            ->assertSessionHas('status.type', 'success');

        $this->assertArrayNotHasKey('vertical', $this->sentToMeta[0]);
        $this->assertSame('RETAIL', $this->savedProfile($organization)['industry']);
    }

    public function test_a_real_sector_is_sent_and_saved(): void
    {
        $this->fakeMetaRejectingUndefinedVertical();
        [$user, $organization] = $this->createOwnerContext($this->whatsappMetadata(['industry' => 'UNDEFINED']));

        $this->saveProfile($user, $organization, ['industry' => 'OTHER'])
            ->assertSessionHas('status.type', 'success');

        $this->assertSame('OTHER', $this->sentToMeta[0]['vertical']);
        $this->assertSame('OTHER', $this->savedProfile($organization)['industry']);
    }

    public function test_a_form_without_about_neither_sends_nor_wipes_it(): void
    {
        $this->fakeMetaRejectingUndefinedVertical();
        [$user, $organization] = $this->createOwnerContext($this->whatsappMetadata(['about' => 'Saved about text']));

        $this->saveProfile($user, $organization, ['industry' => 'OTHER'])
            ->assertSessionHas('status.type', 'success');

        $this->assertArrayNotHasKey('about', $this->sentToMeta[0]);
        $this->assertSame('Saved about text', $this->savedProfile($organization)['about']);
    }

    public function test_a_refusal_from_meta_is_logged_without_the_token_and_shown_to_the_user(): void
    {
        $this->fakeMeta(fn () => new Response(400, [], json_encode([
            'error' => [
                'message' => '(#100) Invalid parameter',
                'type' => 'OAuthException',
                'code' => 100,
                'error_subcode' => 2494010,
                'error_user_msg' => 'The email address is not valid.',
                'fbtrace_id' => 'trace-1',
            ],
        ])));
        Log::spy();
        [$user, $organization] = $this->createOwnerContext($this->whatsappMetadata(['email' => 'old@example.com']));

        $response = $this->saveProfile($user, $organization, ['email' => 'new@example.com', 'industry' => 'OTHER']);

        $response->assertSessionHas('status.type', 'error');
        $this->assertSame(
            'WhatsApp (Meta) refused to update your business profile. The email address is not valid. Meta error code: 100',
            session('status.message')
        );
        $this->assertSame('old@example.com', $this->savedProfile($organization)['email']);

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context) use ($organization): bool {
            return $message === 'WhatsApp business profile update failed.'
                && $context['organization_id'] === $organization->id
                && $context['code'] === 100
                && $context['subcode'] === 2494010
                && $context['error'] === '(#100) Invalid parameter'
                && ! str_contains(json_encode($context), self::TOKEN);
        })->once();
    }

    public function test_a_refusal_without_a_user_message_shows_the_source_and_metas_description(): void
    {
        $this->fakeMeta(fn () => new Response(400, [], json_encode([
            'error' => ['message' => '(#100) Param email is invalid', 'code' => 100],
        ])));
        [$user, $organization] = $this->createOwnerContext($this->whatsappMetadata());

        $this->saveProfile($user, $organization, ['industry' => 'OTHER'])
            ->assertSessionHas('status.type', 'error');

        $this->assertSame(
            'WhatsApp (Meta) refused to update your business profile. (#100) Param email is invalid Meta error code: 100',
            session('status.message')
        );
    }

    public function test_an_expired_connection_is_explained_in_plain_words(): void
    {
        $this->fakeMeta(fn () => new Response(401, [], json_encode([
            'error' => ['message' => 'Error validating access token', 'code' => 190],
        ])));
        [$user, $organization] = $this->createOwnerContext($this->whatsappMetadata());

        $this->saveProfile($user, $organization, ['industry' => 'OTHER'])
            ->assertSessionHas('status.type', 'error');

        $this->assertSame(
            'WhatsApp (Meta) refused to update your business profile. The WhatsApp connection is no longer valid. Please reconnect your WhatsApp account. Meta error code: 190',
            session('status.message')
        );
    }

    public function test_a_connection_failure_says_meta_could_not_be_reached_without_technical_details(): void
    {
        $this->fakeMeta(function (array $body) {
            throw new ConnectException('cURL error 28 for phone-1', new GuzzleRequest('POST', 'https://graph.facebook.com'));
        });
        [$user, $organization] = $this->createOwnerContext($this->whatsappMetadata());

        $this->saveProfile($user, $organization, ['industry' => 'OTHER'])
            ->assertSessionHas('status.type', 'error');

        $this->assertSame(
            'Could not reach WhatsApp (Meta) to update your business profile. Please try again shortly.',
            session('status.message')
        );
    }

    public function test_a_network_failure_without_any_response_does_not_crash_the_page(): void
    {
        $this->fakeMeta(function (array $body) {
            throw new RequestException('cURL error 60: SSL certificate problem', new GuzzleRequest('POST', 'https://graph.facebook.com'));
        });
        [$user, $organization] = $this->createOwnerContext($this->whatsappMetadata());

        $this->saveProfile($user, $organization, ['industry' => 'OTHER'])
            ->assertRedirect('/settings/whatsapp')
            ->assertSessionHas('status.type', 'error');

        $this->assertSame(
            'Could not reach WhatsApp (Meta) to update your business profile. Please try again shortly.',
            session('status.message')
        );
    }

    public function test_the_messages_are_translated_to_arabic(): void
    {
        $this->fakeMeta(fn () => new Response(400, [], json_encode([
            'error' => ['message' => '(#100) Param email is invalid', 'code' => 100],
        ])));
        [$user, $organization] = $this->createOwnerContext($this->whatsappMetadata());
        $translations = json_decode(file_get_contents(lang_path('ar.json')), true);

        $this->assertSame('رفضت واتساب (ميتا) تحديث ملف نشاطك التجاري.', $translations['WhatsApp (Meta) refused to update your business profile.']);
        $this->assertSame('كود خطأ ميتا: :code', $translations['Meta error code: :code']);
        $this->assertArrayHasKey('Could not reach WhatsApp (Meta) to update your business profile. Please try again shortly.', $translations);
        $this->assertArrayHasKey('The WhatsApp connection is no longer valid. Please reconnect your WhatsApp account.', $translations);
        $this->assertArrayHasKey('The WhatsApp connection does not have permission to change this profile.', $translations);
        $this->assertArrayHasKey('Too many requests were sent to WhatsApp. Please try again later.', $translations);
    }

    public function test_about_and_websites_are_sent_and_saved(): void
    {
        $this->fakeMetaRejectingUndefinedVertical();
        [$user, $organization] = $this->createOwnerContext($this->whatsappMetadata());

        $this->saveProfile($user, $organization, [
            'about' => 'Real estate in Riyadh',
            'websites' => ['https://example.com', null],
            'industry' => 'OTHER',
        ])->assertSessionHas('status.type', 'success');

        $this->assertSame('Real estate in Riyadh', $this->sentToMeta[0]['about']);
        $this->assertSame(['https://example.com'], $this->sentToMeta[0]['websites']);
        $this->assertSame('Real estate in Riyadh', $this->savedProfile($organization)['about']);
        $this->assertSame(['https://example.com'], $this->savedProfile($organization)['websites']);
    }

    public function test_emptying_a_saved_value_asks_meta_to_clear_it(): void
    {
        $this->fakeMetaRejectingUndefinedVertical();
        [$user, $organization] = $this->createOwnerContext($this->whatsappMetadata([
            'address' => 'Old address',
            'description' => 'Old description',
            'email' => 'old@example.com',
            'websites' => ['https://old.example.com'],
        ]));

        $this->saveProfile($user, $organization, [
            'address' => '',
            'description' => '',
            'email' => '',
            'websites' => [null, null],
        ])->assertSessionHas('status.type', 'success');

        $this->assertSame('', $this->sentToMeta[0]['address']);
        $this->assertSame('', $this->sentToMeta[0]['description']);
        $this->assertSame('', $this->sentToMeta[0]['email']);
        $this->assertSame([], $this->sentToMeta[0]['websites']);

        $saved = $this->savedProfile($organization);
        $this->assertNull($saved['address']);
        $this->assertNull($saved['email']);
        $this->assertSame([], $saved['websites']);
    }

    public function test_fields_with_nothing_saved_and_nothing_entered_are_not_sent(): void
    {
        $this->fakeMetaRejectingUndefinedVertical();
        [$user, $organization] = $this->createOwnerContext($this->whatsappMetadata());

        $this->saveProfile($user, $organization, [
            'about' => '',
            'address' => '',
            'description' => '',
            'email' => 'owner@example.com',
            'websites' => [null, null],
            'industry' => 'UNDEFINED',
        ])->assertSessionHas('status.type', 'success');

        $this->assertSame(['messaging_product', 'email'], array_keys($this->sentToMeta[0]));
    }

    public function test_emptying_a_saved_about_is_refused_because_meta_does_not_allow_it(): void
    {
        $this->fakeMetaRejectingUndefinedVertical();
        [$user, $organization] = $this->createOwnerContext($this->whatsappMetadata(['about' => 'Saved about text']));

        $this->saveProfile($user, $organization, ['about' => '', 'industry' => 'OTHER'])
            ->assertSessionHasErrors('about');

        $this->assertSame([], $this->sentToMeta);
        $this->assertSame('Saved about text', $this->savedProfile($organization)['about']);
    }

    public function test_values_beyond_metas_limits_are_refused_next_to_their_fields(): void
    {
        $this->fakeMetaRejectingUndefinedVertical();
        [$user, $organization] = $this->createOwnerContext($this->whatsappMetadata());

        $this->saveProfile($user, $organization, [
            'about' => str_repeat('a', 140),
            'address' => str_repeat('a', 257),
            'description' => str_repeat('a', 513),
            'email' => 'not-an-email',
            'websites' => ['example.com', 'https://two.example.com', 'https://three.example.com'],
        ])->assertSessionHasErrors(['about', 'address', 'description', 'email', 'websites', 'websites.0']);

        $this->assertSame([], $this->sentToMeta);
    }

    public function test_a_new_picture_is_uploaded_to_meta_and_its_handle_is_sent(): void
    {
        Storage::fake('local');
        $requests = [];
        $this->fakeMetaSequence($requests, [
            new Response(200, [], json_encode(['id' => 'upload:session-1'])),
            new Response(200, [], json_encode(['h' => 'handle-1'])),
            new Response(200, [], json_encode(['success' => true])),
        ]);
        [$user, $organization] = $this->createOwnerContext($this->whatsappMetadata());

        $this->saveProfile($user, $organization, [
            'profile_picture_url' => UploadedFile::fake()->image('logo.png', 192, 192),
            'industry' => 'OTHER',
        ])->assertSessionHas('status.type', 'success');

        $this->assertStringEndsWith('/app-1/uploads', $requests[0]->getUri()->getPath());
        $this->assertStringContainsString('file_name=logo.png', (string) $requests[0]->getBody());
        $this->assertStringEndsWith('/upload:session-1', urldecode($requests[1]->getUri()->getPath()));
        $this->assertSame('OAuth '.self::TOKEN, $requests[1]->getHeaderLine('Authorization'));
        $this->assertSame('handle-1', json_decode((string) $requests[2]->getBody(), true)['profile_picture_handle']);
        $this->assertNotEmpty($this->savedProfile($organization)['profile_picture_url']);
    }

    public function test_the_app_id_falls_back_to_the_platform_setting_for_the_upload(): void
    {
        Storage::fake('local');
        Setting::updateOrCreate(['key' => 'whatsapp_client_id'], ['value' => 'platform-app']);
        $requests = [];
        $this->fakeMetaSequence($requests, [
            new Response(200, [], json_encode(['id' => 'upload:session-1'])),
            new Response(200, [], json_encode(['h' => 'handle-1'])),
            new Response(200, [], json_encode(['success' => true])),
        ]);
        $metadata = $this->whatsappMetadata();
        unset($metadata['whatsapp']['app_id']);
        [$user, $organization] = $this->createOwnerContext($metadata);

        $this->saveProfile($user, $organization, [
            'profile_picture_url' => UploadedFile::fake()->image('logo.png', 192, 192),
            'industry' => 'OTHER',
        ])->assertSessionHas('status.type', 'success');

        $this->assertStringEndsWith('/platform-app/uploads', $requests[0]->getUri()->getPath());
    }

    public function test_a_failed_picture_upload_stops_the_save_and_tells_the_customer(): void
    {
        Storage::fake('local');
        Log::spy();
        $requests = [];
        $this->fakeMetaSequence($requests, [
            new Response(400, [], json_encode(['error' => ['message' => '(#100) Bad file', 'code' => 100]])),
        ]);
        [$user, $organization] = $this->createOwnerContext($this->whatsappMetadata(['email' => 'old@example.com']));

        $this->saveProfile($user, $organization, [
            'profile_picture_url' => UploadedFile::fake()->image('logo.png', 192, 192),
            'email' => 'new@example.com',
            'industry' => 'OTHER',
        ])->assertSessionHas('status.type', 'error');

        $this->assertCount(1, $requests);
        $this->assertSame(
            'WhatsApp (Meta) refused to update your business profile. (#100) Bad file Meta error code: 100',
            session('status.message')
        );
        $saved = $this->savedProfile($organization);
        $this->assertSame('old@example.com', $saved['email']);
        $this->assertArrayNotHasKey('profile_picture_url', $saved);
        Storage::disk('local')->assertDirectoryEmpty('public');

        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $message, array $context): bool => $message === 'WhatsApp business profile update failed.'
                && $context['step'] === 'picture_upload'
                && ! str_contains(json_encode($context), self::TOKEN)
        )->once();
    }

    public function test_a_picture_smaller_than_metas_minimum_is_refused_next_to_the_field(): void
    {
        $this->fakeMetaRejectingUndefinedVertical();
        [$user, $organization] = $this->createOwnerContext($this->whatsappMetadata());

        $this->saveProfile($user, $organization, [
            'profile_picture_url' => UploadedFile::fake()->image('logo.png', 100, 100),
        ])->assertSessionHasErrors('profile_picture_url');

        $this->assertSame([], $this->sentToMeta);
    }

    private function fakeMetaSequence(array &$requests, array $responses): void
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::mapRequest(function (RequestInterface $request) use (&$requests) {
            $requests[] = $request;

            return $request;
        }));

        $this->app->instance(Client::class, new Client(['handler' => $stack]));
    }

    private function saveProfile($user, $organization, array $fields)
    {
        return $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->from('/settings/whatsapp')
            ->post('/settings/whatsapp/business-profile', $fields);
    }

    private function whatsappMetadata(array $profile = []): array
    {
        return [
            'whatsapp' => [
                'access_token_encrypted' => (new WhatsappTokenVault())->encryptToken(self::TOKEN),
                'app_id' => 'app-1',
                'phone_number_id' => 'phone-1',
                'waba_id' => 'waba-1',
                'business_profile' => $profile,
            ],
        ];
    }

    private function savedProfile($organization): array
    {
        return json_decode($organization->fresh()->metadata, true)['whatsapp']['business_profile'];
    }

    private function fakeMetaRejectingUndefinedVertical(): void
    {
        $this->fakeMeta(function (array $body): Response {
            if (($body['vertical'] ?? null) === 'UNDEFINED') {
                return new Response(400, [], json_encode([
                    'error' => ['message' => '(#100) Param vertical is invalid', 'code' => 100],
                ]));
            }

            return new Response(200, [], json_encode(['success' => true]));
        });
    }

    private function fakeMeta(callable $reply): void
    {
        $handler = new MockHandler([function (RequestInterface $request) use ($reply) {
            $body = json_decode((string) $request->getBody(), true);
            $this->sentToMeta[] = $body;

            return $reply($body);
        }]);

        $this->app->instance(Client::class, new Client(['handler' => HandlerStack::create($handler)]));
    }
}
