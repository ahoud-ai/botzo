<?php

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Contact;
use App\Services\IntelliReply\AiConversationPauseService;
use App\Services\Whatsapp\WhatsappTokenVault;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

/**
 * A team member answering a website visitor from the inbox: the reply is stored for the widget,
 * never sent to WhatsApp, and automatic replies pause like after any human reply.
 */
class WebWidgetInboxTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*' => Http::response([], 500)]);
    }

    public function test_a_text_reply_to_a_visitor_is_stored_for_the_widget(): void
    {
        [$user, $organization, $visitor] = $this->visitorContext();

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson('/chats', ['uuid' => $visitor->uuid, 'message' => 'أهلاً، أنا من الفريق', 'type' => 'text'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $chat = Chat::where('contact_id', $visitor->id)->sole();
        $this->assertSame('outbound', $chat->type);
        $this->assertSame($user->id, (int) $chat->user_id);
        $this->assertSame('أهلاً، أنا من الفريق', json_decode($chat->metadata, true)['text']['body']);
        $this->assertTrue(app(AiConversationPauseService::class)->isPaused($visitor->fresh()));
        Http::assertNothingSent();
    }

    public function test_media_cannot_be_sent_to_a_visitor(): void
    {
        [$user, $organization, $visitor] = $this->visitorContext();

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->postJson('/chats', ['uuid' => $visitor->uuid, 'type' => 'image'])
            ->assertUnprocessable();

        $this->assertSame(0, Chat::where('contact_id', $visitor->id)->count());
    }

    /**
     * @return array{0:\App\Models\User,1:\App\Models\Organization,2:Contact}
     */
    private function visitorContext(): array
    {
        [$user, $organization] = $this->createOwnerContext(['whatsapp' => [
            'is_embedded_signup' => 0,
            'app_id' => 'app',
            'phone_number_id' => 'phone',
            'waba_id' => 'waba',
            'app_secret_encrypted' => (new WhatsappTokenVault())->encryptAppSecret('secret'),
        ]], true);

        $visitor = Contact::create([
            'first_name' => 'زائر الموقع 1234',
            'phone' => null,
            'organization_id' => $organization->id,
            'channel' => Contact::CHANNEL_WEB,
            'created_by' => 0,
        ]);

        return [$user, $organization, $visitor];
    }
}
