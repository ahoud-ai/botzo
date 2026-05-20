<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminPremiumHomeMediaSettingsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_view_premium_home_media_settings_page(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/settings/frontend/premium-home');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Setting/Frontend/PremiumHome')
        );
    }

    public function test_admin_can_update_premium_home_media_images(): void
    {
        Storage::fake(config('filesystems.default'));
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin, 'admin')
            ->put('/admin/settings?type=premium-home-media', [
                'premium_home_hero_avatar_top_right' => UploadedFile::fake()->image('hero-top-right.png'),
                'premium_home_feature_dashboard' => UploadedFile::fake()->image('feature-dashboard.png', 1400, 900),
                'premium_home_cta_background' => UploadedFile::fake()->image('cta-background.png', 1920, 600),
                'premium_home_footer_payment_methods' => UploadedFile::fake()->image('payment-methods.png', 1800, 480),
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status', function ($status): bool {
            return is_array($status)
                && ($status['type'] ?? null) === 'success';
        });

        $heroImagePath = Setting::where('key', 'premium_home_hero_avatar_top_right')->value('value');
        $featureImagePath = Setting::where('key', 'premium_home_feature_dashboard')->value('value');
        $ctaBackgroundPath = Setting::where('key', 'premium_home_cta_background')->value('value');
        $paymentMethodsPath = Setting::where('key', 'premium_home_footer_payment_methods')->value('value');

        $this->assertNotNull($heroImagePath);
        $this->assertNotNull($featureImagePath);
        $this->assertNotNull($ctaBackgroundPath);
        $this->assertNotNull($paymentMethodsPath);
        Storage::disk(config('filesystems.default'))->assertExists($heroImagePath);
        Storage::disk(config('filesystems.default'))->assertExists($featureImagePath);
        Storage::disk(config('filesystems.default'))->assertExists($ctaBackgroundPath);
        Storage::disk(config('filesystems.default'))->assertExists($paymentMethodsPath);
    }

    public function test_admin_can_reset_premium_home_media_to_built_in_default(): void
    {
        Storage::fake(config('filesystems.default'));
        $admin = $this->createAdminUser();
        $storedPath = 'public/'.Str::random(40).'.png';

        Setting::updateOrCreate(
            ['key' => 'premium_home_hero_avatar_top_left'],
            ['value' => $storedPath]
        );

        $this->assertDatabaseHas('settings', [
            'key' => 'premium_home_hero_avatar_top_left',
            'value' => $storedPath,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->put('/admin/settings?type=premium-home-media', [
                'remove_premium_home_hero_avatar_top_left' => true,
            ]);

        $response->assertStatus(302);
        $this->assertDatabaseMissing('settings', [
            'key' => 'premium_home_hero_avatar_top_left',
        ]);
        Storage::disk(config('filesystems.default'))->assertMissing($storedPath);
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
