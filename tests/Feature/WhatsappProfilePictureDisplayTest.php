<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckSubscriptionStatus;
use App\Services\Whatsapp\WhatsappTokenVault;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class WhatsappProfilePictureDisplayTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    private const PICTURE_URL = 'https://pps.whatsapp.net/v/current-picture.jpg';

    public function test_the_page_shows_the_picture_meta_has_without_saving_its_link(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response([
            'data' => [['profile_picture_url' => self::PICTURE_URL]],
        ])]);
        [$user, $organization] = $this->createOwnerContext($this->whatsappMetadata());

        $this->openSettings($user, $organization)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('metaProfilePictureUrl', self::PICTURE_URL));

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/phone-1/whatsapp_business_profile')
            && str_contains($request->url(), 'fields=profile_picture_url')
            && $request->hasHeader('Authorization', 'Bearer secret-test-token'));
        $this->assertStringNotContainsString(self::PICTURE_URL, (string) $organization->fresh()->metadata);
    }

    public function test_the_page_still_opens_when_meta_refuses(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'bad token', 'code' => 190]], 401)]);
        [$user, $organization] = $this->createOwnerContext($this->whatsappMetadata());

        $this->openSettings($user, $organization)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('metaProfilePictureUrl', null));
    }

    public function test_the_page_still_opens_when_meta_cannot_be_reached(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));
        [$user, $organization] = $this->createOwnerContext($this->whatsappMetadata());

        $this->openSettings($user, $organization)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('metaProfilePictureUrl', null));
    }

    public function test_a_workspace_without_whatsapp_does_not_call_meta(): void
    {
        Http::fake();
        [$user, $organization] = $this->createOwnerContext();

        $this->openSettings($user, $organization)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('metaProfilePictureUrl', null));

        Http::assertNothingSent();
    }

    private function openSettings($user, $organization)
    {
        return $this->withoutMiddleware(CheckSubscriptionStatus::class)
            ->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/settings/whatsapp');
    }

    private function whatsappMetadata(): array
    {
        return [
            'whatsapp' => [
                'access_token_encrypted' => (new WhatsappTokenVault())->encryptToken('secret-test-token'),
                'app_id' => 'app-1',
                'phone_number_id' => 'phone-1',
                'waba_id' => 'waba-1',
            ],
        ];
    }
}
