<?php

namespace Tests\Feature;

use App\Events\NewChatEvent;
use App\Jobs\ResumeAutomationFlowRunJob;
use App\Models\AutomationFlowRun;
use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\Contact;
use App\Models\Template;
use App\Services\AutomationFlows\AutomationFlowRuntimeService;
use App\Services\CampaignService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

/**
 * Shared hosting runs without a queue worker (queue "sync"): campaigns and automation waits must
 * be carried by the scheduler instead of queued jobs.
 */
class WorkerlessSchedulingTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    /** @var array<int, array> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'sync']);
        Event::fake([NewChatEvent::class]);
        Http::fake();
    }

    public function test_a_scheduled_campaign_goes_out_once_its_time_comes(): void
    {
        [$owner, $organization] = $this->connectedOrganization();
        $this->fakeMeta(2);
        $campaign = $this->campaign($organization->id, $owner->id, 'scheduled', now('UTC')->subMinute());
        $logs = collect([$this->contact($organization->id, '+966501110001'), $this->contact($organization->id, '+966501110002')])
            ->map(fn (Contact $contact) => CampaignLog::create(['campaign_id' => $campaign->id, 'contact_id' => $contact->id, 'status' => 'pending', 'retry_count' => 0, 'scheduled_at' => now('UTC')->subMinute()]));

        $this->artisan('campaigns:dispatch')->assertSuccessful();

        $this->assertCount(2, $this->sent);
        $this->assertSame('welcome_offer', $this->sent[0]['template']['name']);
        $this->assertSame(['success', 'success'], $logs->map(fn ($log) => $log->fresh()->status)->all());
        $this->assertSame('completed', $campaign->fresh()->status);

        // Running again sends nothing twice.
        $this->artisan('campaigns:dispatch')->assertSuccessful();
        $this->assertCount(2, $this->sent);
    }

    public function test_a_campaign_scheduled_for_later_waits(): void
    {
        [$owner, $organization] = $this->connectedOrganization();
        $this->fakeMeta(0);
        $campaign = $this->campaign($organization->id, $owner->id, 'scheduled', now('UTC')->addHour());
        CampaignLog::create(['campaign_id' => $campaign->id, 'contact_id' => $this->contact($organization->id, '+966501110003')->id, 'status' => 'pending', 'retry_count' => 0, 'scheduled_at' => now('UTC')->addHour()]);

        $this->artisan('campaigns:dispatch')->assertSuccessful();

        $this->assertCount(0, $this->sent);
        $this->assertSame('scheduled', $campaign->fresh()->status);
    }

    public function test_creating_a_campaign_does_not_send_inside_the_request_without_a_worker(): void
    {
        Bus::fake();
        [$owner, $organization] = $this->connectedOrganization();
        $campaign = $this->campaign($organization->id, $owner->id, 'ongoing', null);
        $contact = $this->contact($organization->id, '+966501110004');

        $method = new \ReflectionMethod(CampaignService::class, 'createCampaignLogsImmediately');
        $created = $method->invoke(new CampaignService(), $campaign, $organization->id, true, null);

        $this->assertSame(1, $created);
        Bus::assertNothingDispatched();
        $this->assertSame('pending', CampaignLog::where('campaign_id', $campaign->id)->value('status'));
    }

    public function test_an_automation_wait_is_resumed_by_the_scheduler_not_skipped(): void
    {
        Bus::fake([ResumeAutomationFlowRunJob::class]);
        [, $organization] = $this->createOwnerContext([], true);
        [$flowId, $versionId] = $this->flow($organization->id);
        $due = $this->waitingRun($organization->id, $flowId, $versionId, $this->contact($organization->id, '+966501110005')->id, now()->subMinute());
        $later = $this->waitingRun($organization->id, $flowId, $versionId, $this->contact($organization->id, '+966501110006')->id, now()->addMinutes(10));

        $resumed = [];
        $runtime = \Mockery::mock(AutomationFlowRuntimeService::class);
        $runtime->shouldReceive('resumeDelayedRun')->andReturnUsing(function (AutomationFlowRun $run) use (&$resumed) {
            $resumed[] = $run->id;
        });
        $this->app->instance(AutomationFlowRuntimeService::class, $runtime);

        $this->artisan('automation:resume-delayed')->assertSuccessful();

        $this->assertSame([$due->id], $resumed);
        $this->assertNotContains($later->id, $resumed);
    }

    public function test_the_scheduler_runs_campaigns_only_without_a_worker_and_resumes_flows_always(): void
    {
        // The schedule is defined for the console; load it the way schedule:run does.
        \Illuminate\Support\Facades\Artisan::call('schedule:list');
        $commands = fn () => collect(app(Schedule::class)->events())
            ->filter(fn ($event) => $event->filtersPass(app()))
            ->map(fn ($event) => (string) $event->command)
            ->implode("\n");

        $this->assertStringContainsString('campaigns:dispatch', $commands());
        $this->assertStringContainsString('automation:resume-delayed', $commands());
        $this->assertStringContainsString('webhooks:retry', $commands());

        config(['queue.default' => 'database']);
        $this->assertStringNotContainsString('campaigns:dispatch', $commands());
    }

    private function connectedOrganization(): array
    {
        [$owner, $organization] = $this->createOwnerContext([
            'whatsapp' => ['app_id' => 'app-1', 'phone_number_id' => 'phone-1', 'waba_id' => 'waba-1', 'access_token' => 'meta-test-token'],
        ], false);
        $this->createActiveSubscription($organization->id, ['message_limit' => 1000, 'contacts_limit' => 1000, 'campaign_limit' => 10]);

        return [$owner, $organization];
    }

    private function campaign(int $organizationId, int $ownerId, string $status, $scheduledAt): Campaign
    {
        $template = Template::create([
            'organization_id' => $organizationId,
            'meta_id' => 'meta-'.Str::random(8),
            'name' => 'welcome_offer',
            'category' => 'MARKETING',
            'language' => 'ar',
            'metadata' => json_encode(['name' => 'welcome_offer', 'components' => [['type' => 'BODY', 'text' => 'Hello']]]),
            'status' => 'APPROVED',
            'created_by' => $ownerId,
        ]);

        return Campaign::create([
            'organization_id' => $organizationId,
            'name' => 'Campaign '.Str::random(5),
            'template_id' => $template->id,
            'contact_group_id' => 0,
            'metadata' => json_encode(['header' => ['format' => null, 'text' => null, 'parameters' => []], 'body' => ['text' => 'Hello'], 'footer' => ['text' => null], 'buttons' => [], 'media' => null]),
            'status' => $status,
            'scheduled_at' => $scheduledAt?->toDateTimeString(),
            'created_by' => $ownerId,
        ]);
    }

    private function contact(int $organizationId, string $phone): Contact
    {
        return Contact::create(['organization_id' => $organizationId, 'first_name' => 'Test', 'phone' => $phone, 'created_by' => 0]);
    }

    private function flow(int $organizationId): array
    {
        $flowId = \DB::table('automation_flows')->insertGetId(['uuid' => (string) Str::uuid(), 'organization_id' => $organizationId, 'name' => 'Follow up', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        $versionId = \DB::table('automation_flow_versions')->insertGetId(['uuid' => (string) Str::uuid(), 'automation_flow_id' => $flowId, 'organization_id' => $organizationId, 'version_number' => 1, 'graph_json' => '{}', 'compiled_json' => '{}', 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return [$flowId, $versionId];
    }

    private function waitingRun(int $organizationId, int $flowId, int $versionId, int $contactId, $resumeAt): AutomationFlowRun
    {
        return AutomationFlowRun::forceCreate([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'automation_flow_id' => $flowId,
            'automation_flow_version_id' => $versionId,
            'contact_id' => $contactId,
            'status' => 'waiting_delay',
            'waiting_for' => 'delay',
            'next_resume_at' => $resumeAt,
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);
    }

    private function fakeMeta(int $count): void
    {
        $responses = array_map(fn ($i) => new Response(200, [], json_encode(['messages' => [['id' => 'wamid.CAMP'.$i, 'message_status' => 'accepted']]])), range(1, max(1, $count)));
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::mapRequest(function ($request) {
            $this->sent[] = json_decode((string) $request->getBody(), true);

            return $request;
        }));
        $this->app->instance(Client::class, new Client(['handler' => $stack]));
    }
}
