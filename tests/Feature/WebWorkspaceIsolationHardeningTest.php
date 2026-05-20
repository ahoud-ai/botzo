<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Template;
use App\Jobs\SendCampaignMessageJob;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesOrganizationContext;
use Tests\TestCase;

class WebWorkspaceIsolationHardeningTest extends TestCase
{
    use CreatesOrganizationContext;
    use DatabaseTransactions;

    public function test_contact_detail_does_not_expose_contact_from_another_workspace(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);
        [, $foreignOrganization] = $this->createOwnerContext([], true);
        $foreignContact = $this->createContact($foreignOrganization->id, $user->id, [
            'first_name' => 'ForeignScopedContact',
        ]);

        $response = $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/contacts/'.$foreignContact->uuid);

        $response->assertOk();
        $response->assertDontSee('ForeignScopedContact');
    }

    public function test_contact_group_update_rejects_group_from_another_workspace(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);
        [, $foreignOrganization] = $this->createOwnerContext([], true);
        $foreignGroup = $this->createContactGroup($foreignOrganization->id, $user->id, 'Foreign Group');

        $response = $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/contact-groups/'.$foreignGroup->uuid, [
                'name' => 'Hijacked Group',
            ]);

        $response->assertNotFound();
        $this->assertSame('Foreign Group', $foreignGroup->fresh()->name);
    }

    public function test_template_detail_rejects_template_from_another_workspace(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);
        [, $foreignOrganization] = $this->createOwnerContext([], true);
        $foreignTemplate = $this->createTemplate($foreignOrganization->id, $user->id, 'foreign_template');

        $response = $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/templates/'.$foreignTemplate->uuid);

        $response->assertNotFound();
    }

    public function test_campaign_detail_and_delete_reject_campaign_from_another_workspace(): void
    {
        [$user, $organization] = $this->createOwnerContext([], true);
        [, $foreignOrganization] = $this->createOwnerContext([], true);
        $foreignTemplate = $this->createTemplate($foreignOrganization->id, $user->id, 'foreign_campaign_template');
        $foreignGroup = $this->createContactGroup($foreignOrganization->id, $user->id, 'Foreign Campaign Group');
        $foreignCampaign = $this->createCampaign($foreignOrganization->id, $foreignTemplate->id, $foreignGroup->id, $user->id);

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->get('/campaigns/'.$foreignCampaign->uuid)
            ->assertNotFound();

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->delete('/campaigns/'.$foreignCampaign->uuid)
            ->assertRedirect();

        $this->assertNull($foreignCampaign->fresh()->deleted_at);
    }

    public function test_campaign_creation_rejects_foreign_template_or_group(): void
    {
        Queue::fake([SendCampaignMessageJob::class]);

        [$user, $organization] = $this->createOwnerContext([], true);
        [, $foreignOrganization] = $this->createOwnerContext([], true);
        $foreignTemplate = $this->createTemplate($foreignOrganization->id, $user->id, 'foreign_create_template');
        $localTemplate = $this->createTemplate($organization->id, $user->id, 'local_create_template');
        $foreignGroup = $this->createContactGroup($foreignOrganization->id, $user->id, 'Foreign Create Group');
        $localGroup = $this->createContactGroup($organization->id, $user->id, 'Local Create Group');
        $this->createContact($organization->id, $user->id);

        $basePayload = [
            'name' => 'Scoped Campaign',
            'contacts' => $localGroup->uuid,
            'template' => $localTemplate->uuid,
            'skip_schedule' => true,
            'header' => ['format' => 'TEXT', 'parameters' => []],
            'body' => ['parameters' => []],
            'footer' => [],
            'buttons' => [],
        ];

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/campaigns', array_merge($basePayload, [
                'template' => $foreignTemplate->uuid,
            ]))
            ->assertSessionHasErrors('template');

        $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/campaigns', array_merge($basePayload, [
                'contacts' => $foreignGroup->uuid,
            ]))
            ->assertSessionHasErrors('contacts');

        $this->assertDatabaseMissing('campaigns', [
            'organization_id' => $organization->id,
            'name' => 'Scoped Campaign',
        ]);
    }

    public function test_campaign_creation_rejects_empty_recipient_scope(): void
    {
        Queue::fake([SendCampaignMessageJob::class]);

        [$user, $organization] = $this->createOwnerContext([], true);
        $localTemplate = $this->createTemplate($organization->id, $user->id, 'empty_scope_template');
        $emptyGroup = $this->createContactGroup($organization->id, $user->id, 'Empty Campaign Group');

        $response = $this->actingAs($user, 'user')
            ->withSession(['current_organization' => $organization->id])
            ->post('/campaigns', [
                'name' => 'Empty Scope Campaign',
                'contacts' => $emptyGroup->uuid,
                'template' => $localTemplate->uuid,
                'skip_schedule' => true,
                'header' => ['format' => 'TEXT', 'parameters' => []],
                'body' => ['parameters' => []],
                'footer' => [],
                'buttons' => [],
            ]);

        $response->assertSessionHasErrors('contacts');

        $this->assertDatabaseMissing('campaigns', [
            'organization_id' => $organization->id,
            'name' => 'Empty Scope Campaign',
        ]);
    }

    private function createContact(int $organizationId, int $userId, array $attributes = []): Contact
    {
        return Contact::create(array_merge([
            'organization_id' => $organizationId,
            'first_name' => 'Scoped',
            'last_name' => 'Contact',
            'phone' => '+1555'.random_int(1000000, 9999999),
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    private function createContactGroup(int $organizationId, int $userId, string $name): ContactGroup
    {
        return ContactGroup::create([
            'organization_id' => $organizationId,
            'name' => $name,
            'created_by' => $userId,
        ]);
    }

    private function createTemplate(int $organizationId, int $userId, string $name): Template
    {
        return Template::create([
            'organization_id' => $organizationId,
            'meta_id' => 'meta-'.Str::random(8),
            'name' => $name,
            'category' => 'MARKETING',
            'language' => 'en_US',
            'metadata' => json_encode(['body' => ['text' => 'Hello']]),
            'status' => 'APPROVED',
            'created_by' => $userId,
        ]);
    }

    private function createCampaign(int $organizationId, int $templateId, int $groupId, int $userId): Campaign
    {
        return Campaign::create([
            'organization_id' => $organizationId,
            'name' => 'Foreign Campaign',
            'template_id' => $templateId,
            'contact_group_id' => $groupId,
            'metadata' => json_encode([]),
            'status' => 'scheduled',
            'scheduled_at' => now()->addDay(),
            'created_by' => $userId,
            'created_at' => now(),
        ]);
    }
}
