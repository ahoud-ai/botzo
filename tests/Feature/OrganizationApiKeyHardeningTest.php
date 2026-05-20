<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckSubscriptionStatus;
use App\Models\OrganizationApiKey;
use App\Services\OrganizationApiTokenHasher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class OrganizationApiKeyHardeningTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_developer_token_creation_returns_one_time_plaintext_and_stores_hash(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/developer-tools/access-tokens');

        $response->assertRedirect();
        $response->assertSessionHas('generated_api_token');

        $plainToken = session('generated_api_token');
        $apiKey = OrganizationApiKey::query()->where('organization_id', $organization->id)->firstOrFail();

        $this->assertNotSame($plainToken, $apiKey->getRawOriginal('token'));
        $this->assertSame(
            app(OrganizationApiTokenHasher::class)->hashToken($plainToken),
            $apiKey->getRawOriginal('token')
        );
        $this->assertSame(substr($plainToken, -4), $apiKey->token_last_four);
    }

    public function test_developer_token_list_masks_tokens(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);
        $plainToken = 'plain-visible-token-1234567890';

        OrganizationApiKey::create([
            'organization_id' => $organization->id,
            'token' => app(OrganizationApiTokenHasher::class)->hashToken($plainToken),
            'token_last_four' => substr($plainToken, -4),
        ]);

        $response = $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/developer-tools/access-tokens');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('rows.data', 1)
            ->where('rows.data.0.token_last_four', substr($plainToken, -4))
            ->where('rows.data.0.masked_token', '************************************'.substr($plainToken, -4))
            ->where('rows.data.0.last_used_at', null)
            ->missing('rows.data.0.token')
        );
    }

    public function test_api_verify_accepts_hashed_stored_token(): void
    {
        [, $organization] = $this->createOwnerContext([], true);
        $plainToken = 'verify-token-1234567890';

        $apiKey = OrganizationApiKey::create([
            'organization_id' => $organization->id,
            'token' => app(OrganizationApiTokenHasher::class)->hashToken($plainToken),
            'token_last_four' => substr($plainToken, -4),
        ]);

        $this->assertNull($apiKey->last_used_at);

        $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->getJson('/api/verify');

        $response->assertOk();
        $response->assertJson([
            'statusCode' => 200,
        ]);
        $this->assertNotNull($apiKey->fresh()->last_used_at);
    }
}
