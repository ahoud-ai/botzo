<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckSubscriptionStatus;
use App\Models\Organization;
use App\Services\Whatsapp\WhatsappTokenVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class UserWhatsappRefreshRouteContractTest extends TestCase
{
    use CreatesOrganizationContext;
    use RefreshDatabase;

    public function test_refresh_route_rejects_get_requests(): void
    {
        $this->get('/settings/whatsapp/refresh')->assertStatus(405);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_authenticated_user_can_refresh_whatsapp_data_via_post_request(): void
    {
        [$user, $organization] = $this->createOwnerContext([
            'whatsapp' => [
                'app_id' => 'manual-app-id',
                'waba_id' => 'manual-waba-id',
                'phone_number_id' => 'manual-phone-id',
                'token_source' => 'manual',
                'is_embedded_signup' => 0,
                'access_token_encrypted' => (new WhatsappTokenVault())->encryptToken('EAAB-refresh-token'),
            ],
        ]);

        $this->mockWhatsappServiceForRefresh();

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/settings/whatsapp/refresh');

        $response->assertRedirect();
        $response->assertSessionHas('status', function ($status): bool {
            return is_array($status)
                && ($status['type'] ?? null) === 'success'
                && ($status['message'] ?? null) === __('Whatsapp settings updated successfully');
        });

        $organization->refresh();
        $metadata = json_decode((string) $organization->metadata, true);

        $this->assertIsArray($metadata);
        $this->assertSame('fresh-phone-id', data_get($metadata, 'whatsapp.phone_number_id'));
        $this->assertSame('+966501111111', data_get($metadata, 'whatsapp.display_phone_number'));
        $this->assertSame('APPROVED', data_get($metadata, 'whatsapp.account_review_status'));
    }

    private function mockWhatsappServiceForRefresh(): void
    {
        $mock = Mockery::mock('overload:App\Services\WhatsappService');

        $mock->shouldReceive('getPhoneNumberId')
            ->once()
            ->andReturn((object) [
                'success' => true,
                'data' => (object) [
                    'id' => 'fresh-phone-id',
                    'display_phone_number' => '+966501111111',
                    'verified_name' => 'Refreshed Biz',
                    'quality_rating' => 'GREEN',
                    'messaging_limit_tier' => 'TIER_1',
                ],
            ]);

        $mock->shouldReceive('getPhoneNumberStatus')
            ->once()
            ->andReturn((object) [
                'success' => true,
                'data' => (object) [
                    'status' => 'CONNECTED',
                    'code_verification_status' => 'VERIFIED',
                ],
            ]);

        $mock->shouldReceive('getAccountReviewStatus')
            ->once()
            ->andReturn((object) [
                'success' => true,
                'data' => (object) [
                    'account_review_status' => 'APPROVED',
                ],
            ]);

        $mock->shouldReceive('getBusinessProfile')
            ->once()
            ->andReturn((object) [
                'success' => true,
                'data' => (object) [
                    'about' => 'About',
                    'address' => 'Address',
                    'description' => 'Description',
                    'vertical' => 'OTHER',
                    'email' => 'biz@example.com',
                ],
            ]);

        $mock->shouldReceive('syncTemplates')
            ->once()
            ->andReturn((object) [
                'success' => true,
            ]);
    }
}
