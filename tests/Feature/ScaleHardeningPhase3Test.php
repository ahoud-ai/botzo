<?php

namespace Tests\Feature;

use App\Jobs\ProcessCampaignMessagesJob;
use App\Jobs\ProcessWebhookJob;
use App\Jobs\SendCampaignMessageJob;
use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\Contact;
use App\Services\Whatsapp\WhatsappTokenVault;
use App\Services\WhatsappRateLimiter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use ReflectionMethod;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class ScaleHardeningPhase3Test extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_whatsapp_webhook_dispatches_processing_job_after_signature_validation(): void
    {
        Queue::fake();

        [, $organization] = $this->createOwnerContext([
            'whatsapp' => [
                'is_embedded_signup' => 0,
                'app_secret_encrypted' => (new WhatsappTokenVault())->encryptAppSecret('phase3-secret'),
                'waba_id' => 'phase3-waba',
                'phone_number_id' => 'phase3-phone',
            ],
        ]);

        $payload = json_encode([
            'entry' => [[
                'id' => 'phase3-waba',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => [
                            'phone_number_id' => 'phase3-phone',
                        ],
                        'statuses' => [[
                            'id' => 'wam-phase3',
                            'status' => 'read',
                        ]],
                    ],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);

        $signature = 'sha256=' . hash_hmac('sha256', $payload, 'phase3-secret');

        $response = $this->call(
            'POST',
            '/webhook/whatsapp/'.$organization->identifier,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
            ],
            $payload
        );

        $response->assertOk();
        $response->assertJson(['status' => 'success']);

        Queue::assertPushedOn('webhook-media', ProcessWebhookJob::class);
    }

    public function test_campaign_redispatch_skips_database_queue_probe_when_queue_is_redis(): void
    {
        Queue::fake();
        config(['queue.default' => 'redis']);

        [$user, $organization] = $this->createOwnerContext([], true);
        $campaign = Campaign::create([
            'organization_id' => $organization->id,
            'name' => 'Redis Queue Campaign',
            'template_id' => 0,
            'contact_group_id' => 0,
            'metadata' => json_encode([]),
            'status' => 'ongoing',
            'scheduled_at' => now()->subMinute(),
            'created_by' => $user->id,
            'created_at' => now(),
        ]);
        $contact = Contact::create([
            'organization_id' => $organization->id,
            'first_name' => 'Redis',
            'last_name' => 'Contact',
            'phone' => '+1555'.random_int(1000000, 9999999),
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $log = CampaignLog::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => 'pending',
            'scheduled_at' => now()->subMinute(),
            'retry_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $method = new ReflectionMethod(ProcessCampaignMessagesJob::class, 'redispatchStuckPendingLogs');
        $method->setAccessible(true);
        $method->invoke(new ProcessCampaignMessagesJob());

        Queue::assertNotPushed(SendCampaignMessageJob::class);
        $this->assertSame('pending', $log->fresh()->status);
    }

    public function test_whatsapp_rate_limiter_is_scoped_per_organization(): void
    {
        Cache::flush();

        for ($i = 0; $i < WhatsappRateLimiter::MAX_MESSAGES_PER_SECOND; $i++) {
            $this->assertTrue(WhatsappRateLimiter::recordSent(1001));
        }

        $this->assertFalse(WhatsappRateLimiter::recordSent(1001));
        $this->assertTrue(WhatsappRateLimiter::recordSent(2002));
    }
}
