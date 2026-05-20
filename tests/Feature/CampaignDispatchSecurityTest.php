<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use App\Jobs\ProcessCampaignMessagesJob;
use Mockery;
use Tests\TestCase;

class CampaignDispatchSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_send_requires_dispatch_token_when_configured(): void
    {
        config(['app.campaign_dispatch_token' => 'dispatch-secret']);

        $response = $this->getJson('/campaign-send');

        $response->assertForbidden();
        $response->assertJson([
            'status' => 'forbidden',
        ]);
    }

    public function test_campaign_send_accepts_matching_dispatch_token(): void
    {
        config(['app.campaign_dispatch_token' => 'dispatch-secret']);

        $response = $this->getJson('/campaign-send?token=dispatch-secret');

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
        ]);
    }

    public function test_campaign_send_accepts_signed_requests_without_token(): void
    {
        config(['app.campaign_dispatch_token' => '']);

        $response = $this->getJson(URL::signedRoute('campaign.send'));

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
        ]);
    }

    public function test_campaign_send_returns_error_when_dispatch_processing_fails(): void
    {
        config(['app.campaign_dispatch_token' => '']);

        $mock = Mockery::mock(ProcessCampaignMessagesJob::class);
        $mock->shouldReceive('handle')
            ->once()
            ->andThrow(new \RuntimeException('dispatch pipeline failed'));
        $this->app->instance(ProcessCampaignMessagesJob::class, $mock);

        $response = $this->getJson(URL::signedRoute('campaign.send'));

        $response->assertStatus(500);
        $response->assertJson([
            'status' => 'error',
            'message' => __('Request unable to be processed'),
        ]);
    }
}
