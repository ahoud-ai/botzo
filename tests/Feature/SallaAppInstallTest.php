<?php

namespace Tests\Feature;

use App\Exceptions\SallaAuthorizeWithoutTokens;
use App\Models\ChatMedia;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\SallaStore;
use App\Models\SallaWebhookEvent;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

/**
 * Installing Botzo from the Salla app store: signed events create the account and keep the tokens,
 * refresh is careful with single-use tokens, and uninstalling removes access and later the data.
 */
class SallaAppInstallTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    private const SECRET = 'test-webhook-secret';

    private const MERCHANT = 1305146709;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'services.salla.webhook_secret' => self::SECRET,
            'services.salla.client_id' => 'client-id',
            'services.salla.client_secret' => 'client-secret',
        ]);

        OrganizationRole::firstOrCreate(
            ['organization_id' => null, 'name' => 'Owner'],
            ['description' => 'Owner', 'permissions' => ['*']]
        );

        $this->fakeSalla();
    }

    public function test_a_signed_authorize_event_creates_the_account_and_stores_encrypted_tokens(): void
    {
        $this->send($this->authorizeEvent())->assertOk();

        $store = SallaStore::where('merchant_id', self::MERCHANT)->sole();
        $this->assertSame('access-1', $store->access_token);
        $this->assertSame('refresh-1', $store->refresh_token);
        $this->assertSame(SallaStore::STATUS_INSTALLED, $store->status);
        $this->assertEqualsWithDelta(now()->addDays(14)->timestamp, $store->token_expires_at->timestamp, 5, 'The expiry keeps the moment Salla sent.');
        $this->assertSame(['متجر الاختبار', 'https://shop.test', 'owner@shop.test'], [$store->store_name, $store->store_domain, $store->store_email]);

        $raw = DB::table('salla_stores')->where('id', $store->id)->first();
        $this->assertStringNotContainsString('access-1', (string) $raw->access_token);
        $this->assertStringNotContainsString('refresh-1', (string) $raw->refresh_token);

        $organization = Organization::findOrFail($store->organization_id);
        $this->assertSame('متجر الاختبار', $organization->name);
        $this->assertSame('main', $organization->organization_type);
        $owner = Team::where('organization_id', $organization->id)->sole();
        $this->assertMatchesRegularExpression('/^salla-'.self::MERCHANT.'-[a-z0-9]{24}@merchants\.botzo\.invalid$/', User::findOrFail($owner->user_id)->email, 'A store\'s address cannot be guessed and claimed before it installs.');

        $this->assertArrayNotHasKey('access_token', $store->toArray());
    }

    public function test_webhook_log_never_keeps_tokens(): void
    {
        $this->send($this->authorizeEvent())->assertOk();

        $event = SallaWebhookEvent::where('merchant_id', self::MERCHANT)->sole();
        $this->assertSame(SallaWebhookEvent::STATUS_PROCESSED, $event->status);
        $this->assertNotNull($event->processed_at);

        $stored = (string) DB::table('salla_webhook_events')->where('id', $event->id)->value('payload');
        $this->assertStringNotContainsString('access-1', $stored);
        $this->assertStringNotContainsString('refresh-1', $stored);
        $this->assertStringContainsString('offline_access', $stored);
    }

    public function test_requests_without_valid_proof_are_refused_and_not_recorded(): void
    {
        $body = json_encode($this->authorizeEvent());

        $this->call('POST', '/api/salla/webhook', [], [], [], $this->server(['HTTP_X_SALLA_SIGNATURE' => str_repeat('a', 64)]), $body)->assertStatus(401);
        $this->call('POST', '/api/salla/webhook', [], [], [], $this->server(), $body)->assertStatus(401);
        $this->call('POST', '/api/salla/webhook', [], [], [], $this->server(['HTTP_AUTHORIZATION' => 'Bearer wrong']), $body)->assertStatus(401);

        // A signature for another body proves nothing about this one.
        $other = hash_hmac('sha256', '{"event":"other"}', self::SECRET);
        $this->call('POST', '/api/salla/webhook', [], [], [], $this->server(['HTTP_X_SALLA_SIGNATURE' => $other]), $body)->assertStatus(401);

        config(['services.salla.webhook_secret' => '']);
        $empty = hash_hmac('sha256', $body, '');
        $this->call('POST', '/api/salla/webhook', [], [], [], $this->server(['HTTP_X_SALLA_SIGNATURE' => $empty]), $body)->assertStatus(401);

        $this->assertSame(0, SallaStore::where('merchant_id', self::MERCHANT)->count());
        $this->assertSame(0, SallaWebhookEvent::where('merchant_id', self::MERCHANT)->count());
    }

    public function test_token_strategy_is_accepted_with_the_secret(): void
    {
        $body = json_encode($this->authorizeEvent());

        $this->call('POST', '/api/salla/webhook', [], [], [], $this->server(['HTTP_AUTHORIZATION' => 'Bearer '.self::SECRET]), $body)->assertOk();

        $this->assertSame(1, SallaStore::where('merchant_id', self::MERCHANT)->count());
    }

    public function test_the_same_event_delivered_twice_creates_one_account(): void
    {
        $users = User::count();
        $event = $this->authorizeEvent();

        $this->send($event)->assertOk();
        $this->send($event)->assertOk();

        $this->assertSame(1, SallaStore::where('merchant_id', self::MERCHANT)->count());
        $this->assertSame(1, SallaWebhookEvent::where('merchant_id', self::MERCHANT)->count());
        $this->assertSame($users + 1, User::count());
    }

    public function test_an_existing_botzo_user_with_the_same_email_is_not_linked(): void
    {
        [$existing, $organization] = $this->createOwnerContext();
        $this->fakeSalla(email: $existing->email);

        $this->send($this->authorizeEvent())->assertOk();

        $store = SallaStore::where('merchant_id', self::MERCHANT)->sole();
        $this->assertNotSame($organization->id, $store->organization_id);
        $this->assertFalse(Team::where('organization_id', $store->organization_id)->where('user_id', $existing->id)->exists());
        $this->assertSame($existing->email, $store->store_email);
    }

    public function test_install_survives_a_store_details_failure_and_details_are_filled_later(): void
    {
        $this->fakeSalla(userInfoStatus: 500);
        $this->send($this->authorizeEvent())->assertOk();

        $store = SallaStore::where('merchant_id', self::MERCHANT)->sole();
        $this->assertSame('access-1', $store->access_token);
        $this->assertNull($store->store_name);
        $this->assertSame('Salla store '.self::MERCHANT, Organization::find($store->organization_id)->name);

        $this->fakeSalla();
        $this->artisan('salla:refresh-tokens')->assertSuccessful();

        $this->assertSame('متجر الاختبار', $store->fresh()->store_name);
        $this->assertSame('متجر الاختبار', Organization::find($store->organization_id)->name);
        $user = User::findOrFail(Organization::find($store->organization_id)->created_by);
        $this->assertSame(['متجر الاختبار', ''], [$user->first_name, $user->last_name], 'The dashboard shows the store, not a placeholder or "null".');
    }

    public function test_a_failed_event_answers_500_and_stays_open_for_retries(): void
    {
        $event = $this->authorizeEvent();
        unset($event['data']['refresh_token']);

        $this->send($event)->assertStatus(500);
        $record = SallaWebhookEvent::where('merchant_id', self::MERCHANT)->sole();
        $this->assertSame(SallaWebhookEvent::STATUS_FAILED, $record->status);
        $this->assertSame(SallaAuthorizeWithoutTokens::class, $record->error);

        $this->send($event)->assertStatus(500);
        $this->assertSame(1, SallaWebhookEvent::where('merchant_id', self::MERCHANT)->count());

        // The record stays failed, not settled, so the delivery is still applied when it can be.
        $this->assertSame(SallaWebhookEvent::STATUS_FAILED, $record->fresh()->status);
        $this->assertSame(0, SallaStore::where('merchant_id', self::MERCHANT)->count());
    }

    public function test_other_events_are_recorded_without_their_content(): void
    {
        $this->send(['event' => 'order.created', 'merchant' => self::MERCHANT, 'created_at' => 'now', 'data' => ['customer' => ['name' => 'سارة']]])->assertOk();

        $record = SallaWebhookEvent::where('merchant_id', self::MERCHANT)->sole();
        $this->assertSame(SallaWebhookEvent::STATUS_IGNORED, $record->status);
        $this->assertNull($record->payload);
    }

    public function test_tokens_due_soon_are_refreshed_and_saved(): void
    {
        $this->send($this->authorizeEvent())->assertOk();
        $store = SallaStore::where('merchant_id', self::MERCHANT)->sole();
        $store->update(['token_expires_at' => now()->addDay()]);

        $this->artisan('salla:refresh-tokens')->assertSuccessful();

        $store->refresh();
        $this->assertSame('access-2', $store->access_token);
        $this->assertSame('refresh-2', $store->refresh_token);
        $this->assertTrue($store->token_expires_at->gt(now()->addDays(13)));

        Http::assertSent(fn (HttpRequest $request) => $request->url() === config('services.salla.token_url')
            && $request['grant_type'] === 'refresh_token'
            && $request['refresh_token'] === 'refresh-1'
            && $request['client_secret'] === 'client-secret');
    }

    public function test_a_refresh_token_is_used_only_once(): void
    {
        $this->send($this->authorizeEvent())->assertOk();
        SallaStore::where('merchant_id', self::MERCHANT)->sole()->update(['token_expires_at' => now()->addDay()]);

        $this->artisan('salla:refresh-tokens')->assertSuccessful();
        $this->artisan('salla:refresh-tokens')->assertSuccessful();

        Http::assertSentCount(2); // The store details lookup and one refresh.
    }

    public function test_a_refused_refresh_stops_further_attempts(): void
    {
        $this->send($this->authorizeEvent())->assertOk();
        $store = SallaStore::where('merchant_id', self::MERCHANT)->sole();
        $store->update(['token_expires_at' => now()->subDay()]);
        $this->fakeSalla(refreshStatus: 401);

        $this->artisan('salla:refresh-tokens')->assertSuccessful();
        $this->assertSame(SallaStore::STATUS_REAUTH_REQUIRED, $store->fresh()->status);
        $this->assertSame('refresh-1', $store->fresh()->refresh_token);

        $this->artisan('salla:refresh-tokens')->assertSuccessful();
        Http::assertSentCount(1);

        $this->fakeSalla();
        $this->send($this->authorizeEvent('Thu Nov 05 2026 10:00:00 GMT+0300'))->assertOk();
        $this->assertSame(SallaStore::STATUS_INSTALLED, $store->fresh()->status);
    }

    public function test_a_server_error_during_refresh_keeps_the_store_active_for_a_retry(): void
    {
        $this->send($this->authorizeEvent())->assertOk();
        $store = SallaStore::where('merchant_id', self::MERCHANT)->sole();
        $store->update(['token_expires_at' => now()->addDay()]);
        $this->fakeSalla(refreshStatus: 503);

        $this->artisan('salla:refresh-tokens')->assertSuccessful();

        $this->assertSame(SallaStore::STATUS_INSTALLED, $store->fresh()->status);
    }

    public function test_uninstall_removes_tokens_and_schedules_the_purge(): void
    {
        $this->send($this->authorizeEvent())->assertOk();

        $this->send($this->uninstallEvent())->assertOk();

        $store = SallaStore::where('merchant_id', self::MERCHANT)->sole();
        $this->assertNull($store->access_token);
        $this->assertNull($store->refresh_token);
        $this->assertSame(SallaStore::STATUS_UNINSTALLED, $store->status);
        $this->assertTrue($store->purge_after->between(now()->addDays(29), now()->addDays(31)));
    }

    public function test_late_retries_cannot_undo_a_newer_install_or_uninstall(): void
    {
        $install = $this->authorizeEvent('Wed Nov 04 2026 10:00:00 GMT+0300');
        $uninstall = $this->uninstallEvent('Wed Nov 04 2026 11:00:00 GMT+0300');
        $reinstall = $this->authorizeEvent('Wed Nov 04 2026 12:00:00 GMT+0300');

        // A retry of the first install arriving after the uninstall must not bring the tokens back.
        $this->send($uninstall)->assertOk(); // Nothing to uninstall yet.
        $this->send($install)->assertOk();
        $this->send($this->uninstallEvent('Wed Nov 04 2026 11:30:00 GMT+0300'))->assertOk();
        $retry = $this->authorizeEvent('Wed Nov 04 2026 11:15:00 GMT+0300');
        $this->send($retry)->assertOk();
        $store = SallaStore::where('merchant_id', self::MERCHANT)->sole();
        $this->assertSame(SallaStore::STATUS_UNINSTALLED, $store->status);
        $this->assertNull($store->access_token);

        // A retry of the old uninstall arriving after a reinstall must not remove the new tokens.
        $this->send($reinstall)->assertOk();
        $this->send($this->uninstallEvent('Wed Nov 04 2026 11:45:00 GMT+0300'))->assertOk();
        $store->refresh();
        $this->assertSame(SallaStore::STATUS_INSTALLED, $store->status);
        $this->assertSame('access-1', $store->access_token);
        $this->assertNull($store->purge_after);
    }

    public function test_salla_dates_without_an_offset_are_riyadh_time(): void
    {
        config(['app.timezone' => 'UTC']);
        date_default_timezone_set('UTC');

        try {
            $this->send($this->authorizeEvent('2026-11-04 19:40:19'))->assertOk();

            $installedAt = SallaStore::where('merchant_id', self::MERCHANT)->sole()->installed_at;
            $this->assertSame('2026-11-04 16:40:19', $installedAt->utc()->format('Y-m-d H:i:s'));
        } finally {
            date_default_timezone_set('Asia/Riyadh');
        }
    }

    public function test_reinstalling_returns_to_the_same_account(): void
    {
        $this->send($this->authorizeEvent('Wed Nov 04 2026 10:00:00 GMT+0300'))->assertOk();
        $organizationId = SallaStore::where('merchant_id', self::MERCHANT)->sole()->organization_id;
        $this->send($this->uninstallEvent('Wed Nov 04 2026 11:00:00 GMT+0300'))->assertOk();
        $users = User::count();

        $this->send($this->authorizeEvent('Wed Nov 04 2026 12:00:00 GMT+0300'))->assertOk();

        $store = SallaStore::where('merchant_id', self::MERCHANT)->sole();
        $this->assertSame($organizationId, $store->organization_id);
        $this->assertSame($users, User::count());
        $this->assertNull($store->uninstalled_at);
    }

    public function test_purge_deletes_only_the_uninstalled_stores_conversations_after_retention(): void
    {
        Storage::fake('local');
        $this->send($this->authorizeEvent('Wed Nov 04 2026 10:00:00 GMT+0300'))->assertOk();
        $this->send($this->uninstallEvent('Wed Nov 04 2026 11:00:00 GMT+0300'))->assertOk();
        $store = SallaStore::where('merchant_id', self::MERCHANT)->sole();

        Storage::disk('local')->put('private/received/customer.jpg', 'image');
        [$contact, $chat, $media] = $this->conversation($store->organization_id, 'private/received/customer.jpg');
        [, $other] = $this->createOwnerContext();
        [$otherContact, $otherChat] = $this->conversation($other->id);

        // Not due yet: nothing goes.
        $this->artisan('salla:purge')->assertSuccessful();
        $this->assertDatabaseHas('contacts', ['id' => $contact->id]);
        Storage::disk('local')->assertExists('private/received/customer.jpg');

        $store->update(['purge_after' => now()->subMinute()]);
        $this->artisan('salla:purge')->assertSuccessful();

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
        $this->assertDatabaseMissing('chats', ['id' => $chat]);
        $this->assertDatabaseMissing('chat_media', ['id' => $media->id]);
        Storage::disk('local')->assertMissing('private/received/customer.jpg');
        $this->assertDatabaseHas('contacts', ['id' => $otherContact->id]);
        $this->assertDatabaseHas('chats', ['id' => $otherChat]);

        $store->refresh();
        $this->assertNull($store->purge_after);
        $this->assertNull($store->store_email);
    }

    public function test_a_reinstalled_store_is_not_purged(): void
    {
        $this->send($this->authorizeEvent('Wed Nov 04 2026 10:00:00 GMT+0300'))->assertOk();
        $this->send($this->uninstallEvent('Wed Nov 04 2026 11:00:00 GMT+0300'))->assertOk();
        $store = SallaStore::where('merchant_id', self::MERCHANT)->sole();
        [$contact] = $this->conversation($store->organization_id);
        $this->send($this->authorizeEvent('Wed Nov 04 2026 12:00:00 GMT+0300'))->assertOk();

        $this->artisan('salla:purge')->assertSuccessful();

        $this->assertDatabaseHas('contacts', ['id' => $contact->id]);
    }

    public function test_old_webhook_events_are_pruned(): void
    {
        $this->send(['event' => 'order.created', 'merchant' => self::MERCHANT, 'created_at' => 'now', 'data' => []])->assertOk();
        $old = SallaWebhookEvent::where('merchant_id', self::MERCHANT)->sole();
        DB::table('salla_webhook_events')->where('id', $old->id)->update(['created_at' => now()->subDays(31)]);

        $this->artisan('salla:purge')->assertSuccessful();

        $this->assertDatabaseMissing('salla_webhook_events', ['id' => $old->id]);
    }

    private function send(array $event)
    {
        $body = json_encode($event);

        return $this->call('POST', '/api/salla/webhook', [], [], [], $this->server([
            'HTTP_X_SALLA_SIGNATURE' => hash_hmac('sha256', $body, self::SECRET),
            'HTTP_X_SALLA_SECURITY_STRATEGY' => 'Signature',
        ]), $body);
    }

    private function server(array $extra = []): array
    {
        return ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'] + $extra;
    }

    private function authorizeEvent(string $createdAt = 'Wed Nov 04 2026 10:00:00 GMT+0300'): array
    {
        return [
            'event' => 'app.store.authorize',
            'merchant' => self::MERCHANT,
            'created_at' => $createdAt,
            'data' => [
                'id' => 896353331,
                'app_name' => 'Botzo',
                'access_token' => 'access-1',
                'refresh_token' => 'refresh-1',
                'expires' => now()->addDays(14)->timestamp,
                'scope' => 'settings.read offline_access products.read',
                'token_type' => 'bearer',
            ],
        ];
    }

    private function uninstallEvent(string $createdAt = 'Wed Nov 04 2026 11:00:00 GMT+0300'): array
    {
        return ['event' => 'app.uninstalled', 'merchant' => self::MERCHANT, 'created_at' => $createdAt, 'data' => ['id' => 896353331]];
    }

    private function conversation(int $organizationId, ?string $mediaPath = null): array
    {
        $contact = Contact::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'first_name' => 'Visitor',
            'created_by' => 0,
        ]);

        $media = ChatMedia::create([
            'name' => 'customer.jpg',
            'path' => 'x',
            'storage_path' => $mediaPath,
            'visibility' => $mediaPath ? ChatMedia::VISIBILITY_PRIVATE : 'public',
            'location' => 'local',
            'type' => 'image/jpeg',
            'size' => '5',
        ]);

        $chat = DB::table('chats')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'contact_id' => $contact->id,
            'type' => 'inbound',
            'metadata' => '{}',
            'media_id' => $media->id,
            'status' => 'delivered',
        ]);

        DB::table('chat_logs')->insert(['contact_id' => $contact->id, 'entity_type' => 'chat', 'entity_id' => $chat]);

        return [$contact, $chat, $media];
    }

    private function fakeSalla(int $userInfoStatus = 200, int $refreshStatus = 200, string $email = 'owner@shop.test'): void
    {
        // Replaces earlier fakes and their recorded requests.
        $this->app->forgetInstance(\Illuminate\Http\Client\Factory::class);
        Http::clearResolvedInstances();

        Http::fake([
            'accounts.salla.sa/oauth2/user/info' => Http::response($userInfoStatus === 200 ? ['data' => [
                'id' => 1,
                'email' => $email,
                'merchant' => ['id' => self::MERCHANT, 'name' => 'متجر الاختبار', 'domain' => 'https://shop.test'],
            ]] : [], $userInfoStatus),
            'accounts.salla.sa/oauth2/token' => Http::response($refreshStatus === 200 ? [
                'access_token' => 'access-2',
                'refresh_token' => 'refresh-2',
                'expires_in' => 1209600,
                'scope' => 'settings.read offline_access products.read',
            ] : ['error' => 'invalid_grant'], $refreshStatus),
            '*' => Http::response([], 500),
        ]);
    }
}
