<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminSettingsFeaturesSurfaceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_settings_index_redirects_to_general_tab(): void
    {
        $admin = $this->createAdminUser();

        $this->actingAs($admin, 'admin')
            ->get('/admin/settings')
            ->assertRedirect('/admin/settings/general');

        $this->actingAs($admin, 'admin')
            ->get('/admin/settings/features')
            ->assertRedirect('/admin/settings/features/embedded-signup');
    }

    public function test_feature_pages_render_inside_admin_settings(): void
    {
        $admin = $this->createAdminUser();

        $this->actingAs($admin, 'admin')
            ->get('/admin/settings/features/embedded-signup')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Setting/EmbeddedSignup')
                ->has('addon')
            );

        $this->actingAs($admin, 'admin')
            ->get('/admin/settings/features/ai-assistant')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Setting/Features/AiAssistant')
                ->has('addon')
            );

        $this->actingAs($admin, 'admin')
            ->get('/admin/settings/features/flow-builder')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Setting/Features/FlowBuilder')
                ->has('addon')
            );
    }

    public function test_feature_settings_routes_toggle_ai_assistant_and_flow_builder(): void
    {
        $admin = $this->createAdminUser();
        $aiAssistant = $this->createFeatureAddon('AI Assistant', 'ai');
        $flowBuilder = $this->createFeatureAddon('Flow builder', 'automation');

        $aiResponse = $this->actingAs($admin, 'admin')
            ->post('/admin/settings/features/ai-assistant', [
                'uuid' => $aiAssistant->uuid,
                'settings' => [
                    'ai_global_api_key' => '',
                    'ai_key_policy' => 'hybrid',
                    'ai_allow_org_override' => true,
                ],
                'is_active' => 1,
            ]);

        $aiResponse->assertRedirect();
        $aiResponse->assertSessionHas('status', fn ($status): bool => is_array($status)
            && ($status['type'] ?? null) === 'success'
            && ($status['message'] ?? null) === __('Feature updated successfully!')
        );

        $this->assertSame(1, (int) $aiAssistant->fresh()->is_active);
        $this->assertDatabaseHas('settings', [
            'key' => 'ai_key_policy',
            'value' => 'hybrid',
        ]);

        $flowResponse = $this->actingAs($admin, 'admin')
            ->post('/admin/settings/features/flow-builder', [
                'uuid' => $flowBuilder->uuid,
                'settings' => [],
                'is_active' => 1,
            ]);

        $flowResponse->assertRedirect();
        $flowResponse->assertSessionHas('status', fn ($status): bool => is_array($status)
            && ($status['type'] ?? null) === 'success'
            && ($status['message'] ?? null) === __('Feature updated successfully!')
        );

        $this->assertSame(1, (int) $flowBuilder->fresh()->is_active);
    }

    public function test_feature_settings_routes_reject_mismatched_or_retired_addons(): void
    {
        $admin = $this->createAdminUser();
        $aiAssistant = $this->createFeatureAddon('AI Assistant', 'ai');
        $flowBuilder = $this->createFeatureAddon('Flow builder', 'automation');
        $retiredWebhook = $this->createFeatureAddon('Webhooks', 'integration');

        $mismatchResponse = $this->actingAs($admin, 'admin')
            ->post('/admin/settings/features/flow-builder', [
                'uuid' => $aiAssistant->uuid,
                'settings' => [
                    'frontend_variant' => 'marketing_v2',
                    'ai_key_policy' => 'global_only',
                ],
                'is_active' => 1,
            ]);

        $mismatchResponse->assertRedirect();
        $mismatchResponse->assertSessionHas('status', fn ($status): bool => is_array($status)
            && ($status['type'] ?? null) === 'error'
            && ($status['message'] ?? null) === __('This feature is not available in this client version.')
        );

        $this->assertSame(0, (int) $aiAssistant->fresh()->is_active);
        $this->assertSame(0, (int) $flowBuilder->fresh()->is_active);
        $this->assertDatabaseMissing('settings', [
            'key' => 'frontend_variant',
            'value' => 'marketing_v2',
        ]);
        $this->assertDatabaseMissing('settings', [
            'key' => 'ai_key_policy',
            'value' => 'global_only',
        ]);

        $retiredResponse = $this->actingAs($admin, 'admin')
            ->post('/admin/settings/features/ai-assistant', [
                'uuid' => $retiredWebhook->uuid,
                'settings' => [],
                'is_active' => 1,
            ]);

        $retiredResponse->assertRedirect();
        $retiredResponse->assertSessionHas('status', fn ($status): bool => is_array($status)
            && ($status['type'] ?? null) === 'error'
        );

        $this->assertSame(0, (int) $retiredWebhook->fresh()->is_active);
    }

    public function test_feature_settings_routes_ignore_settings_outside_the_feature_contract(): void
    {
        $admin = $this->createAdminUser();
        $flowBuilder = $this->createFeatureAddon('Flow builder', 'automation');

        $response = $this->actingAs($admin, 'admin')
            ->post('/admin/settings/features/flow-builder', [
                'uuid' => $flowBuilder->uuid,
                'settings' => [
                    'frontend_variant' => 'marketing_v2',
                    'ai_key_policy' => 'global_only',
                ],
                'is_active' => 1,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', fn ($status): bool => is_array($status)
            && ($status['type'] ?? null) === 'success'
        );

        $this->assertSame(1, (int) $flowBuilder->fresh()->is_active);
        $this->assertDatabaseMissing('settings', [
            'key' => 'frontend_variant',
            'value' => 'marketing_v2',
        ]);
        $this->assertDatabaseMissing('settings', [
            'key' => 'ai_key_policy',
            'value' => 'global_only',
        ]);
    }

    private function createAdminUser(): User
    {
        return User::create([
            'first_name' => 'Admin',
            'last_name' => 'Features',
            'email' => 'admin+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'email_verified_at' => now(),
        ]);
    }

    private function createFeatureAddon(string $name, string $category): Addon
    {
        return Addon::create([
            'uuid' => (string) Str::uuid(),
            'category' => $category,
            'name' => $name,
            'logo' => 'feature.svg',
            'description' => $name,
            'metadata' => json_encode(['name' => Str::studly($name)]),
            'status' => 1,
            'is_active' => 0,
            'is_plan_restricted' => 1,
        ]);
    }
}
