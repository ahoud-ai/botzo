<?php

namespace Tests\Feature;

use App\Jobs\SendCampaignMessageJob;
use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class CampaignRetrySettingsTest extends TestCase
{
    use CreatesOrganizationContext;
    use RefreshDatabase;

    public function test_retry_intervals_are_scheduled_in_hours(): void
    {
        Bus::fake();
        Carbon::setTestNow(Carbon::create(2026, 4, 8, 12, 0, 0, 'UTC'));

        [$owner, $organization] = $this->createOwnerContext();
        $primaryGroup = $this->createContactGroup($organization, $owner, 'Primary Group');
        $contact = $this->createContact($organization, $owner);
        $campaign = $this->createCampaign($organization, $owner, $primaryGroup);
        $log = CampaignLog::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => 'failed',
            'retry_count' => 0,
        ]);

        $this->retryJobHarness()->exposeScheduleRetry($log, [2, 6, 12], 3);

        $freshLog = $log->fresh();
        $scheduledAt = Carbon::parse($freshLog->getRawOriginal('scheduled_at'), 'UTC');

        $this->assertSame('retrying', $freshLog->status);
        $this->assertSame('2026-04-08 14:00:00', $scheduledAt->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_final_failure_moves_contact_to_scoped_group_without_duplicates(): void
    {
        Bus::fake();

        [$owner, $organization] = $this->createOwnerContext();
        $primaryGroup = $this->createContactGroup($organization, $owner, 'Primary Group');
        $fallbackGroup = $this->createContactGroup($organization, $owner, 'Failed Group');
        $contact = $this->createContact($organization, $owner);
        $campaign = $this->createCampaign($organization, $owner, $primaryGroup);
        $log = CampaignLog::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => 'failed',
            'retry_count' => 3,
        ]);

        $organizationMetadata = [
            'campaigns' => [
                'enable_resend' => true,
                'move_failed_contacts_to_group' => true,
                'resend_intervals' => [1, 3, 6],
                'failed_campaign_group' => $fallbackGroup->uuid,
            ],
        ];

        $job = $this->retryJobHarness();
        $job->exposeHandleRetryOrFinalFailure($log, $organizationMetadata);
        $job->exposeHandleRetryOrFinalFailure($log->fresh(), $organizationMetadata);

        $this->assertDatabaseHas('contact_contact_group', [
            'contact_id' => $contact->id,
            'contact_group_id' => $fallbackGroup->id,
        ]);
        $this->assertSame(1, DB::table('contact_contact_group')
            ->where('contact_id', $contact->id)
            ->where('contact_group_id', $fallbackGroup->id)
            ->count());
    }

    public function test_final_failure_ignores_group_uuid_from_another_workspace(): void
    {
        Bus::fake();

        [$owner, $organization] = $this->createOwnerContext();
        [$foreignOwner, $foreignOrganization] = $this->createOwnerContext();
        $primaryGroup = $this->createContactGroup($organization, $owner, 'Primary Group');
        $foreignGroup = $this->createContactGroup($foreignOrganization, $foreignOwner, 'Foreign Failed Group');
        $contact = $this->createContact($organization, $owner);
        $campaign = $this->createCampaign($organization, $owner, $primaryGroup);
        $log = CampaignLog::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => 'failed',
            'retry_count' => 3,
        ]);

        $this->retryJobHarness()->exposeHandleRetryOrFinalFailure($log, [
            'campaigns' => [
                'enable_resend' => true,
                'move_failed_contacts_to_group' => true,
                'resend_intervals' => [1, 3, 6],
                'failed_campaign_group' => $foreignGroup->uuid,
            ],
        ]);

        $this->assertDatabaseMissing('contact_contact_group', [
            'contact_id' => $contact->id,
            'contact_group_id' => $foreignGroup->id,
        ]);
    }

    private function retryJobHarness(): SendCampaignMessageJob
    {
        return new class(0) extends SendCampaignMessageJob {
            public function exposeScheduleRetry(CampaignLog $log, array $retryIntervals, int $maxRetries): void
            {
                $this->scheduleRetry($log, $retryIntervals, $maxRetries);
            }

            public function exposeHandleRetryOrFinalFailure(CampaignLog $log, array $organizationMetadata): void
            {
                $this->handleRetryOrFinalFailure($log, $organizationMetadata);
            }
        };
    }

    private function createCampaign(Organization $organization, User $owner, ContactGroup $group): Campaign
    {
        return Campaign::create([
            'organization_id' => $organization->id,
            'name' => 'Campaign '.uniqid(),
            'template_id' => 1,
            'contact_group_id' => $group->id,
            'metadata' => json_encode([]),
            'status' => 'ongoing',
            'created_by' => $owner->id,
        ]);
    }

    private function createContact(Organization $organization, User $owner): Contact
    {
        return Contact::create([
            'organization_id' => $organization->id,
            'first_name' => 'Retry',
            'last_name' => 'Contact',
            'phone' => '+966501000000',
            'email' => 'retry+'.uniqid().'@example.com',
            'created_by' => $owner->id,
        ]);
    }

    private function createContactGroup(Organization $organization, User $owner, string $name): ContactGroup
    {
        return ContactGroup::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'created_by' => $owner->id,
        ]);
    }
}
