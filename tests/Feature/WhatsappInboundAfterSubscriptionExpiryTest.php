<?php

namespace Tests\Feature;

use App\Events\NewChatEvent;
use App\Models\Chat;
use App\Models\Organization;
use App\Services\AutoReplyService;
use App\Services\Whatsapp\WhatsappTokenVault;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class WhatsappInboundAfterSubscriptionExpiryTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([NewChatEvent::class]);
    }

    public function test_messages_are_stored_but_not_auto_replied_when_the_subscription_has_expired(): void
    {
        $organization = $this->organization(withActiveSubscription: false);
        $this->expectAutoReplyCalls(0);

        $this->postMessage($organization, 'wamid.EXPIRED1')->assertOk();

        $this->assertTrue(Chat::where('organization_id', $organization->id)->where('wam_id', 'wamid.EXPIRED1')->exists());
    }

    public function test_messages_are_stored_and_auto_replied_while_the_subscription_is_active(): void
    {
        $organization = $this->organization(withActiveSubscription: true);
        $this->expectAutoReplyCalls(1);

        $this->postMessage($organization, 'wamid.ACTIVE1')->assertOk();

        $this->assertTrue(Chat::where('organization_id', $organization->id)->where('wam_id', 'wamid.ACTIVE1')->exists());
    }

    public function test_messages_from_a_new_contact_over_the_contact_limit_are_stored_without_auto_reply(): void
    {
        $organization = $this->organization(withActiveSubscription: false);
        $this->createActiveSubscription($organization->id, ['contacts_limit' => 0]);
        $this->expectAutoReplyCalls(0);

        $this->postMessage($organization, 'wamid.OVERCAP1')->assertOk();

        $this->assertTrue(Chat::where('organization_id', $organization->id)->where('wam_id', 'wamid.OVERCAP1')->exists());
    }

    private function expectAutoReplyCalls(int $times): void
    {
        $autoReplies = Mockery::mock(AutoReplyService::class);
        $autoReplies->shouldReceive('checkAutoReply')->times($times);
        $this->app->instance(AutoReplyService::class, $autoReplies);
    }

    private function organization(bool $withActiveSubscription): Organization
    {
        [, $organization] = $this->createOwnerContext([
            'whatsapp' => [
                'is_embedded_signup' => 0,
                'waba_id' => 'waba-expiry',
                'phone_number_id' => 'phone-expiry',
                'app_secret_encrypted' => (new WhatsappTokenVault())->encryptAppSecret('expiry-secret'),
            ],
        ], $withActiveSubscription);

        return $organization;
    }

    private function postMessage(Organization $organization, string $wamId)
    {
        $payload = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'waba-expiry',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['phone_number_id' => 'phone-expiry'],
                        'contacts' => [['wa_id' => '201001234567', 'profile' => ['name' => 'Customer']]],
                        'messages' => [[
                            'from' => '201001234567',
                            'id' => $wamId,
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'text',
                            'text' => ['body' => 'مرحبا'],
                        ]],
                    ],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return $this->call('POST', '/webhook/whatsapp/'.$organization->identifier, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $payload, 'expiry-secret'),
        ], $payload);
    }
}
