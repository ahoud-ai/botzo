<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminEmbeddedSignupSettingsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_view_embedded_signup_settings_page(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/settings/features/embedded-signup');

        $response->assertStatus(200);
    }

    public function test_admin_can_update_embedded_signup_settings(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin, 'admin')
            ->put('/admin/settings?type=embedded-signup', [
                'whatsapp_client_id' => 'app-id-123',
                'whatsapp_client_secret' => 'app-secret-123',
                'whatsapp_config_id' => 'config-id-123',
                'whatsapp_access_token' => 'fallback-token-123',
                'is_embedded_signup_active' => true,
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status', function ($status): bool {
            return is_array($status)
                && ($status['type'] ?? null) === 'success';
        });

        $this->assertDatabaseHas('settings', [
            'key' => 'whatsapp_client_id',
            'value' => 'app-id-123',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'whatsapp_client_secret',
            'value' => 'app-secret-123',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'whatsapp_config_id',
            'value' => 'config-id-123',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'whatsapp_access_token',
            'value' => 'fallback-token-123',
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'is_embedded_signup_active',
            'value' => '1',
        ]);
    }

    private function createAdminUser(): User
    {
        return User::create([
            'first_name' => 'Admin',
            'last_name' => 'Tester',
            'email' => 'admin+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'email_verified_at' => now(),
        ]);
    }
}
