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

class AdminFrontendSeoSettingsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_view_frontend_seo_settings_page(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/settings/frontend/seo');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Setting/Frontend/Seo')
        );
    }

    public function test_admin_can_update_frontend_seo_settings_and_share_image(): void
    {
        Storage::fake(config('filesystems.default'));
        $admin = $this->createAdminUser();
        $robotsPath = public_path('robots.txt');
        $robotsBefore = file_exists($robotsPath) ? file_get_contents($robotsPath) : null;

        try {
            $response = $this->actingAs($admin, 'admin')
                ->put('/admin/settings?type=frontend-seo', [
                    'seo_home_title_ar' => 'عنوان رئيسي',
                    'seo_home_title_en' => 'Main SEO title',
                    'seo_default_description_en' => 'Default description for search engines.',
                    'seo_robots_index' => false,
                    'seo_robots_follow' => false,
                    'seo_canonical_base_url' => 'https://example.com',
                    'tracking_tiktok_pixel_id' => 'C0ABCDE1234567890',
                    'seo_share_image' => UploadedFile::fake()->image('share-image.png', 1200, 630),
                ]);

            $response->assertStatus(302);
            $response->assertSessionHas('status', function ($status): bool {
                return is_array($status)
                    && ($status['type'] ?? null) === 'success';
            });

            $this->assertDatabaseHas('settings', [
                'key' => 'seo_home_title_en',
                'value' => 'Main SEO title',
            ]);
            $this->assertDatabaseHas('settings', [
                'key' => 'tracking_tiktok_pixel_id',
                'value' => 'C0ABCDE1234567890',
            ]);

            $shareImagePath = Setting::where('key', 'seo_share_image')->value('value');
            $this->assertNotNull($shareImagePath);
            Storage::disk(config('filesystems.default'))->assertExists($shareImagePath);

            $this->assertFileExists($robotsPath);
            $robotsContent = file_get_contents($robotsPath);
            $this->assertStringContainsString('Disallow: /', (string) $robotsContent);
        } finally {
            if ($robotsBefore === null) {
                @unlink($robotsPath);
            } elseif ($robotsBefore !== false) {
                file_put_contents($robotsPath, $robotsBefore);
            }
        }
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
