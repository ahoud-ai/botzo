<?php

namespace Tests\Feature;

use App\Events\NewChatEvent;
use App\Models\Chat;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Setting;
use App\Services\Whatsapp\WhatsappTokenVault;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class WhatsappWebhookProcessingTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([NewChatEvent::class]);
    }

    public function test_redelivered_message_is_stored_only_once(): void
    {
        $organization = $this->manualOrganization();
        $payload = $this->payload([
            $this->entry('waba-manual', [$this->messageChange('phone-manual', [$this->textMessage('wamid.DUP1', 'السلام عليكم')])]),
        ]);

        $this->postManual($organization, $payload)->assertOk();
        $this->postManual($organization, $payload)->assertOk();

        $this->assertSame(1, Chat::where('organization_id', $organization->id)->where('wam_id', 'wamid.DUP1')->count());
    }

    public function test_every_entry_and_change_in_a_batched_delivery_is_processed(): void
    {
        $organization = $this->manualOrganization();
        $payload = $this->payload([
            $this->entry('waba-manual', [
                $this->messageChange('phone-manual', [
                    $this->textMessage('wamid.E1C1M1', 'first'),
                    $this->textMessage('wamid.E1C1M2', 'second'),
                ]),
                $this->messageChange('phone-manual', [$this->textMessage('wamid.E1C2M1', 'third')]),
            ]),
            $this->entry('waba-manual', [
                $this->messageChange('phone-manual', [$this->textMessage('wamid.E2C1M1', 'fourth')]),
            ]),
        ]);

        $this->postManual($organization, $payload)->assertOk();

        $this->assertEqualsCanonicalizing(
            ['wamid.E1C1M1', 'wamid.E1C1M2', 'wamid.E1C2M1', 'wamid.E2C1M1'],
            Chat::where('organization_id', $organization->id)->pluck('wam_id')->all()
        );
    }

    public function test_statuses_and_messages_in_the_same_change_are_both_handled(): void
    {
        $organization = $this->manualOrganization();
        $contact = Contact::create([
            'first_name' => 'Existing',
            'phone' => '+201001234567',
            'organization_id' => $organization->id,
            'created_by' => 0,
        ]);
        $outbound = Chat::create([
            'organization_id' => $organization->id,
            'wam_id' => 'wamid.OUT1',
            'contact_id' => $contact->id,
            'type' => 'outbound',
            'metadata' => json_encode(['type' => 'text', 'text' => ['body' => 'hi']]),
            'status' => 'sent',
        ]);

        $change = $this->messageChange('phone-manual', [$this->textMessage('wamid.IN1', 'reply')]);
        $change['value']['statuses'] = [['id' => 'wamid.OUT1', 'status' => 'read', 'timestamp' => (string) now()->timestamp]];

        $this->postManual($organization, $this->payload([$this->entry('waba-manual', [$change])]))->assertOk();

        $this->assertSame('read', $outbound->fresh()->status);
        $this->assertTrue(Chat::where('organization_id', $organization->id)->where('wam_id', 'wamid.IN1')->exists());
    }

    public function test_changes_for_another_phone_number_are_ignored_on_the_manual_route(): void
    {
        $organization = $this->manualOrganization();
        $payload = $this->payload([
            $this->entry('waba-manual', [
                $this->messageChange('phone-manual', [$this->textMessage('wamid.MINE', 'mine')]),
                $this->messageChange('phone-other', [$this->textMessage('wamid.NOTMINE', 'not mine')]),
            ]),
        ]);

        $this->postManual($organization, $payload)->assertOk();

        $this->assertTrue(Chat::where('wam_id', 'wamid.MINE')->exists());
        $this->assertFalse(Chat::where('wam_id', 'wamid.NOTMINE')->exists());
    }

    public function test_embedded_route_routes_each_change_to_its_own_organization(): void
    {
        Setting::updateOrCreate(['key' => 'whatsapp_client_secret'], ['value' => 'embedded-secret']);

        $first = $this->embeddedOrganization('waba-a', 'phone-a');
        $second = $this->embeddedOrganization('waba-b', 'phone-b');

        $payload = $this->payload([
            $this->entry('waba-a', [$this->messageChange('phone-a', [$this->textMessage('wamid.A1', 'to a')])]),
            $this->entry('waba-b', [$this->messageChange('phone-b', [$this->textMessage('wamid.B1', 'to b')])]),
        ]);

        $this->postSigned('/webhook/waba', $payload, 'embedded-secret')->assertOk();

        $this->assertSame(['wamid.A1'], Chat::where('organization_id', $first->id)->pluck('wam_id')->all());
        $this->assertSame(['wamid.B1'], Chat::where('organization_id', $second->id)->pluck('wam_id')->all());
    }

    private function manualOrganization(): Organization
    {
        [, $organization] = $this->createOwnerContext([
            'whatsapp' => [
                'is_embedded_signup' => 0,
                'waba_id' => 'waba-manual',
                'phone_number_id' => 'phone-manual',
                'app_secret_encrypted' => (new WhatsappTokenVault())->encryptAppSecret('manual-secret'),
            ],
        ], true);

        return $organization;
    }

    private function embeddedOrganization(string $wabaId, string $phoneNumberId): Organization
    {
        [, $organization] = $this->createOwnerContext([
            'whatsapp' => [
                'is_embedded_signup' => 1,
                'waba_id' => $wabaId,
                'phone_number_id' => $phoneNumberId,
            ],
        ], true);

        return $organization;
    }

    private function postManual(Organization $organization, string $payload): TestResponse
    {
        return $this->postSigned('/webhook/whatsapp/'.$organization->identifier, $payload, 'manual-secret');
    }

    private function postSigned(string $uri, string $payload, string $secret): TestResponse
    {
        return $this->call('POST', $uri, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $payload, $secret),
        ], $payload);
    }

    private function payload(array $entries): string
    {
        return json_encode(['object' => 'whatsapp_business_account', 'entry' => $entries], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function entry(string $wabaId, array $changes): array
    {
        return ['id' => $wabaId, 'changes' => $changes];
    }

    private function messageChange(string $phoneNumberId, array $messages): array
    {
        return [
            'field' => 'messages',
            'value' => [
                'messaging_product' => 'whatsapp',
                'metadata' => ['phone_number_id' => $phoneNumberId],
                'contacts' => [['wa_id' => '201001234567', 'profile' => ['name' => 'Customer']]],
                'messages' => $messages,
            ],
        ];
    }

    private function textMessage(string $wamId, string $body): array
    {
        return [
            'from' => '201001234567',
            'id' => $wamId,
            'timestamp' => (string) now()->timestamp,
            'type' => 'text',
            'text' => ['body' => $body],
        ];
    }
}
