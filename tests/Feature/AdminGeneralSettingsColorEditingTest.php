<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminGeneralSettingsColorEditingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_general_settings_interface_does_not_expose_color_editing(): void
    {
        $generalSettings = file_get_contents(resource_path('js/Pages/Admin/Setting/General.vue'));
        $frontendIndex = file_get_contents(resource_path('js/Pages/Admin/Setting/Frontend/Index.vue'));

        $this->assertIsString($generalSettings);
        $this->assertIsString($frontendIndex);

        foreach ([
            'Color Scheme',
            'Primary Color',
            'Secondary Color',
            'type="color"',
            'form.primary_color',
            'form.secondary_color',
        ] as $removedToken) {
            $this->assertStringNotContainsString($removedToken, $generalSettings);
        }

        $this->assertStringNotContainsString('colors, and active frontend variant', $frontendIndex);
    }

    public function test_general_settings_update_ignores_color_payloads(): void
    {
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'General',
            'email' => 'admin-general+'.Str::random(8).'@example.com',
            'password' => Hash::make('password123'),
            'role' => 'owner',
            'email_verified_at' => now(),
        ]);

        Setting::query()->updateOrCreate(['key' => 'primary_color'], ['value' => '#034737']);
        Setting::query()->updateOrCreate(['key' => 'secondary_color'], ['value' => '#008000']);

        $response = $this->actingAs($admin, 'admin')
            ->put('/admin/settings?type=general', [
                'company_name' => 'Botzo',
                'primary_color' => '#111111',
                'secondary_color' => '#222222',
                'frontend_variant' => 'premium',
            ]);

        $response->assertRedirect();

        $this->assertSame('#034737', Setting::query()->where('key', 'primary_color')->value('value'));
        $this->assertSame('#008000', Setting::query()->where('key', 'secondary_color')->value('value'));
    }
}
