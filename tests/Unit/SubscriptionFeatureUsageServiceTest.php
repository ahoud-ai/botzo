<?php

namespace Tests\Unit;

use App\Models\Chat;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\OrganizationEmployee;
use App\Models\OrganizationEmployeeAssignment;
use App\Models\OrganizationRole;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\TeamInvite;
use App\Models\User;
use App\Services\SubscriptionFeatureUsageService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionFeatureUsageServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_message_limit_counts_only_outbound_messages_within_the_current_subscription_cycle_and_family_scope(): void
    {
        $owner = $this->createUser();
        $parent = $this->createOrganization($owner);
        $branch = $this->createOrganization($owner, [
            'organization_type' => 'branch',
            'parent_organization_id' => $parent->id,
        ]);

        $plan = $this->createPlan([
            'message_limit' => 2,
            'contacts_limit' => 10,
        ]);

        $subscription = Subscription::create([
            'organization_id' => $parent->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDays(5),
            'valid_until' => now()->addDays(25),
        ]);

        $parentContact = $this->createContact($parent->id, $owner->id, '+14155550101');
        $branchContact = $this->createContact($branch->id, $owner->id, '+14155550102');

        $this->createChat($parent->id, $parentContact->id, 'outbound', now()->subDay());
        $this->createChat($parent->id, $parentContact->id, 'inbound', now()->subHours(12));
        $this->createChat($branch->id, $branchContact->id, 'outbound', now()->subHours(6));
        $this->createChat(
            $parent->id,
            $parentContact->id,
            'outbound',
            now()->subDays(10)->startOfHour()
        );

        $snapshot = app(SubscriptionFeatureUsageService::class)->snapshot($branch->id, 'message_limit');

        $this->assertSame($subscription->organization_id, $snapshot['billing_organization_id']);
        $this->assertSame(2, $snapshot['used']);
        $this->assertSame(2, $snapshot['limit']);
        $this->assertTrue($snapshot['reached']);
        $this->assertNotNull($snapshot['window_start']);
        $this->assertNotNull($snapshot['window_end']);
    }

    public function test_contacts_limit_uses_the_family_scope_for_branch_workspaces(): void
    {
        $owner = $this->createUser();
        $parent = $this->createOrganization($owner);
        $branch = $this->createOrganization($owner, [
            'organization_type' => 'branch',
            'parent_organization_id' => $parent->id,
        ]);

        $plan = $this->createPlan([
            'contacts_limit' => 1,
            'message_limit' => 10,
        ]);

        Subscription::create([
            'organization_id' => $parent->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $this->createContact($parent->id, $owner->id, '+14155550103');

        $snapshot = app(SubscriptionFeatureUsageService::class)->snapshot($branch->id, 'contacts_limit');

        $this->assertSame(1, $snapshot['used']);
        $this->assertSame(1, $snapshot['limit']);
        $this->assertTrue($snapshot['reached']);
    }

    public function test_campaign_limit_ignores_soft_deleted_campaigns(): void
    {
        $owner = $this->createUser();
        $organization = $this->createOrganization($owner);

        $plan = $this->createPlan([
            'campaign_limit' => 1,
            'message_limit' => 10,
        ]);

        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $this->createCampaign($organization->id, $owner->id, 'Deleted campaign', now());
        $this->createCampaign($organization->id, $owner->id, 'Active campaign');

        $snapshot = app(SubscriptionFeatureUsageService::class)->snapshot($organization->id, 'campaign_limit');

        $this->assertSame(1, $snapshot['used']);
        $this->assertSame(1, $snapshot['limit']);
        $this->assertTrue($snapshot['reached']);
    }

    public function test_team_limit_counts_unique_people_once_across_family_workspaces(): void
    {
        $owner = $this->createUser();
        $branchUser = $this->createUser();
        $parent = $this->createOrganization($owner);
        $branch = $this->createOrganization($owner, [
            'organization_type' => 'branch',
            'parent_organization_id' => $parent->id,
        ]);

        $ownerRole = $this->ownerRole();

        Team::create([
            'organization_id' => $parent->id,
            'user_id' => $owner->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $owner->id,
        ]);

        Team::create([
            'organization_id' => $branch->id,
            'user_id' => $owner->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $owner->id,
        ]);

        Team::create([
            'organization_id' => $branch->id,
            'user_id' => $branchUser->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $owner->id,
        ]);

        $plan = $this->createPlan([
            'team_limit' => 2,
            'message_limit' => 10,
        ]);

        Subscription::create([
            'organization_id' => $parent->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $snapshot = app(SubscriptionFeatureUsageService::class)->snapshot($branch->id, 'team_limit');

        $this->assertSame(2, $snapshot['used']);
        $this->assertSame(2, $snapshot['limit']);
        $this->assertTrue($snapshot['reached']);
    }

    public function test_team_limit_counts_pending_company_and_workspace_invites_once_per_person(): void
    {
        $owner = $this->createUser();
        $parent = $this->createOrganization($owner);
        $branch = $this->createOrganization($owner, [
            'organization_type' => 'branch',
            'parent_organization_id' => $parent->id,
        ]);

        $role = $this->localRole($parent);
        $ownerRole = $this->ownerRole();

        Team::create([
            'organization_id' => $parent->id,
            'user_id' => $owner->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
            'created_by' => $owner->id,
        ]);

        $pendingEmployeeEmail = 'pending-company+'.Str::lower(Str::random(8)).'@example.com';

        $employee = OrganizationEmployee::create([
            'main_organization_id' => $parent->id,
            'email' => $pendingEmployeeEmail,
            'first_name' => 'Pending',
            'last_name' => 'Employee',
            'status' => 'pending',
            'invited_by' => $owner->id,
            'invited_at' => now(),
            'invite_code' => Str::random(32),
            'invite_expires_at' => now()->addDay(),
        ]);

        OrganizationEmployeeAssignment::create([
            'organization_employee_id' => $employee->id,
            'organization_id' => $parent->id,
            'organization_role_id' => $role->id,
            'status' => 'pending',
            'assigned_by' => $owner->id,
            'assigned_at' => now(),
        ]);

        OrganizationEmployeeAssignment::create([
            'organization_employee_id' => $employee->id,
            'organization_id' => $branch->id,
            'organization_role_id' => $role->id,
            'status' => 'pending',
            'assigned_by' => $owner->id,
            'assigned_at' => now(),
        ]);

        TeamInvite::create([
            'organization_id' => $branch->id,
            'organization_role_id' => $ownerRole->id,
            'email' => $pendingEmployeeEmail,
            'code' => md5((string) Str::uuid()),
            'invited_by' => $owner->id,
            'expire_at' => now()->addDay(),
        ]);

        TeamInvite::create([
            'organization_id' => $parent->id,
            'organization_role_id' => $ownerRole->id,
            'email' => 'pending-workspace+'.Str::lower(Str::random(8)).'@example.com',
            'code' => md5((string) Str::uuid()),
            'invited_by' => $owner->id,
            'expire_at' => now()->addDay(),
        ]);

        $plan = $this->createPlan([
            'team_limit' => 3,
            'message_limit' => 10,
        ]);

        Subscription::create([
            'organization_id' => $parent->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        $snapshot = app(SubscriptionFeatureUsageService::class)->snapshot($parent->id, 'team_limit');

        $this->assertSame(3, $snapshot['used']);
        $this->assertSame(3, $snapshot['limit']);
        $this->assertTrue($snapshot['reached']);
    }

    private function createUser(): User
    {
        return User::create([
            'first_name' => 'Usage',
            'last_name' => 'Tester',
            'email' => 'usage+'.Str::random(8).'@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
            'email_verified_at' => now(),
        ]);
    }

    private function createOrganization(User $creator, array $attributes = []): Organization
    {
        return Organization::create(array_merge([
            'name' => 'Organization '.Str::random(4),
            'identifier' => now()->format('YmdHis').Str::random(6),
            'created_by' => $creator->id,
            'organization_type' => 'main',
            'parent_organization_id' => null,
            'metadata' => json_encode([]),
        ], $attributes));
    }

    private function createPlan(array $metadata): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Plan '.Str::random(4),
            'name_ar' => 'خطة '.Str::random(4),
            'name_en' => 'Plan '.Str::random(4),
            'price' => 99.00,
            'period' => 'monthly',
            'metadata' => json_encode($metadata),
            'status' => 'active',
        ]);
    }

    private function ownerRole(): OrganizationRole
    {
        return OrganizationRole::firstOrCreate(
            [
                'organization_id' => null,
                'name' => 'Owner',
            ],
            [
                'description' => 'Owner role for subscription usage tests',
                'permissions' => ['*'],
            ]
        );
    }

    private function localRole(Organization $organization): OrganizationRole
    {
        return OrganizationRole::create([
            'organization_id' => $organization->id,
            'name' => 'Member '.Str::random(6),
            'description' => 'Member role for subscription usage tests',
            'permissions' => ['contacts.view_all'],
        ]);
    }

    private function createContact(int $organizationId, int $createdBy, string $phone): Contact
    {
        return Contact::create([
            'organization_id' => $organizationId,
            'first_name' => 'Contact',
            'last_name' => 'Tester',
            'phone' => $phone,
            'email' => Str::random(8).'@example.com',
            'created_by' => $createdBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createChat(int $organizationId, int $contactId, string $type, $createdAt): Chat
    {
        return Chat::create([
            'organization_id' => $organizationId,
            'contact_id' => $contactId,
            'wam_id' => (string) Str::uuid(),
            'type' => $type,
            'metadata' => json_encode(['type' => $type]),
            'status' => 'sent',
            'created_at' => $createdAt,
        ]);
    }

    private function createCampaign(int $organizationId, int $createdBy, string $name, $deletedAt = null): Campaign
    {
        return Campaign::create([
            'organization_id' => $organizationId,
            'name' => $name,
            'template_id' => 0,
            'contact_group_id' => 0,
            'metadata' => json_encode([]),
            'status' => 'scheduled',
            'scheduled_at' => now()->addDay(),
            'created_by' => $createdBy,
            'created_at' => now(),
            'deleted_at' => $deletedAt,
        ]);
    }
}
