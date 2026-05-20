<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminFlowBuilderAddonSetupRouteTest extends TestCase
{
    use DatabaseTransactions;

    public function test_flow_builder_setup_route_can_enable_addon_even_when_runtime_flag_is_disabled(): void
    {
        config()->set('automation_flows.enabled', false);

        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Tester',
            'email' => 'admin+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'email_verified_at' => now(),
        ]);

        $addon = Addon::create([
            'uuid' => (string) Str::uuid(),
            'category' => 'automation',
            'name' => 'Flow builder',
            'logo' => 'flow-builder.svg',
            'description' => __('Flow Builder v2'),
            'metadata' => json_encode([
                'name' => 'FlowBuilder',
            ]),
            'status' => 1,
            'is_active' => 0,
            'is_plan_restricted' => 1,
        ]);

        $response = $this->withoutMiddleware()
            ->actingAs($admin, 'admin')
            ->post('/admin/settings/features/flow-builder', [
                'uuid' => $addon->uuid,
                'settings' => [],
                'is_active' => 1,
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status', function ($status): bool {
            return is_array($status)
                && ($status['type'] ?? null) === 'success'
                && ($status['message'] ?? null) === __('Feature updated successfully!');
        });

        $addon->refresh();
        $this->assertSame(1, (int) $addon->is_active);
    }

    public function test_flow_builder_setup_route_can_disable_addon_when_needed(): void
    {
        config()->set('automation_flows.enabled', true);

        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Tester',
            'email' => 'admin+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'email_verified_at' => now(),
        ]);

        $addon = Addon::create([
            'uuid' => (string) Str::uuid(),
            'category' => 'automation',
            'name' => 'Flow builder',
            'logo' => 'flow-builder.svg',
            'description' => __('Flow Builder v2'),
            'metadata' => json_encode([
                'name' => 'FlowBuilder',
            ]),
            'status' => 1,
            'is_active' => 1,
            'is_plan_restricted' => 1,
        ]);

        $response = $this->withoutMiddleware()
            ->actingAs($admin, 'admin')
            ->post('/admin/settings/features/flow-builder', [
                'uuid' => $addon->uuid,
                'settings' => [],
                'is_active' => 0,
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status', function ($status): bool {
            return is_array($status)
                && ($status['type'] ?? null) === 'success'
                && ($status['message'] ?? null) === __('Feature updated successfully!');
        });

        $addon->refresh();
        $this->assertSame(0, (int) $addon->is_active);
    }
}
