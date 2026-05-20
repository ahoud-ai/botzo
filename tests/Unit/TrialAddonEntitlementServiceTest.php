<?php

namespace Tests\Unit;

use App\Models\Addon;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\AddonStateService;
use App\Services\EmbeddedSignup\EmbeddedSignupGate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrialAddonEntitlementServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_trial_addon_falls_back_to_plan_entitlement_when_no_trial_override_exists(): void
    {
        $organization = $this->createOrganization();
        $this->createAddon('AI Assistant');
        $this->createSubscription($organization->id, 'AI Assistant', false);

        $this->assertFalse(
            app(AddonStateService::class)->isModuleEnabledForOrganization('AI Assistant', $organization->id)
        );
    }

    public function test_trial_addon_can_be_explicitly_enabled_via_trial_override_setting(): void
    {
        $organization = $this->createOrganization();
        $this->createAddon('AI Assistant');
        Setting::updateOrCreate(
            ['key' => 'trial_addons'],
            ['value' => json_encode(['AI Assistant' => true])]
        );
        $this->createSubscription($organization->id, 'AI Assistant', false);

        $this->assertTrue(
            app(AddonStateService::class)->isModuleEnabledForOrganization('AI Assistant', $organization->id)
        );
    }

    public function test_embedded_signup_trial_gate_uses_trial_override_instead_of_opening_everything(): void
    {
        $organization = $this->createOrganization();
        $this->createAddon('Embedded Signup');
        Setting::updateOrCreate(['key' => 'is_embedded_signup_active'], ['value' => '1']);
        $this->createSubscription($organization->id, 'Embedded Signup', false);

        $this->assertFalse(app(EmbeddedSignupGate::class)->isPlanEnabled($organization->id));

        Setting::updateOrCreate(
            ['key' => 'trial_addons'],
            ['value' => json_encode(['Embedded Signup' => true])]
        );

        $this->assertTrue(app(EmbeddedSignupGate::class)->isPlanEnabled($organization->id));
    }

    private function createOrganization(): Organization
    {
        return Organization::create([
            'name' => 'Trial Org '.Str::random(4),
            'identifier' => 'trial-'.Str::lower(Str::random(8)),
            'organization_type' => 'main',
            'metadata' => json_encode([
                'addons' => [
                    'embedded_signup_enabled' => true,
                ],
            ]),
            'created_by' => 1,
        ]);
    }

    private function createAddon(string $name): void
    {
        Addon::updateOrCreate(
            ['name' => $name],
            [
                'uuid' => Addon::query()->where('name', $name)->value('uuid') ?: (string) Str::uuid(),
                'category' => 'business',
                'logo' => Str::slug($name).'.svg',
                'description' => $name.' addon',
                'metadata' => json_encode(['name' => $name]),
                'status' => 1,
                'is_active' => 1,
                'is_plan_restricted' => 1,
            ]
        );
    }

    private function createSubscription(int $organizationId, string $addonName, bool $addonEnabled): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Trial Plan '.Str::random(4),
            'price' => 10,
            'period' => 'monthly',
            'metadata' => json_encode([
                'addons' => [
                    $addonName => $addonEnabled,
                ],
            ]),
            'status' => 'active',
        ]);

        Subscription::create([
            'organization_id' => $organizationId,
            'plan_id' => $plan->id,
            'status' => 'trial',
            'start_date' => now()->subDay(),
            'valid_until' => now()->addDays(14),
        ]);
    }
}
