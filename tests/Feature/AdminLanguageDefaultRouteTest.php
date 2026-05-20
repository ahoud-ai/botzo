<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminLanguageDefaultRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_language_change_rejects_get_requests(): void
    {
        $admin = $this->createAdmin();
        $this->seedLanguages();

        Setting::query()->updateOrCreate(
            ['key' => 'default_language'],
            ['value' => 'en']
        );

        $response = $this->actingAs($admin, 'admin')
            ->get('/admin/languages/ar/default');

        $response->assertStatus(405);
        $this->assertSame('en', Setting::query()->where('key', 'default_language')->value('value'));
    }

    public function test_admin_can_change_default_language_via_post_request(): void
    {
        $admin = $this->createAdmin();
        $this->seedLanguages();

        Setting::query()->updateOrCreate(
            ['key' => 'default_language'],
            ['value' => 'en']
        );

        $response = $this->actingAs($admin, 'admin')
            ->post('/admin/languages/ar/default');

        $response->assertRedirect('/admin/languages');
        $response->assertSessionHas('status.type', 'success');
        $this->assertSame('ar', Setting::query()->where('key', 'default_language')->value('value'));
    }

    private function createAdmin(): User
    {
        return User::query()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin-language+'.Str::random(8).'@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }

    private function seedLanguages(): void
    {
        Language::query()->create([
            'name' => 'English',
            'code' => 'en',
            'status' => 'active',
            'is_rtl' => 0,
        ]);

        Language::query()->create([
            'name' => 'Arabic',
            'code' => 'ar',
            'status' => 'active',
            'is_rtl' => 1,
        ]);
    }
}
