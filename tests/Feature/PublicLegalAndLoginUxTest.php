<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicLegalAndLoginUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_legal_routes_render_without_server_errors(): void
    {
        Page::query()->create([
            'name' => 'Privacy Policy',
            'name_ar' => 'سياسة الخصوصية',
            'name_en' => 'Privacy Policy',
            'content' => '<p>Privacy content</p>',
            'content_ar' => '<p>Privacy content</p>',
            'content_en' => '<p>Privacy content</p>',
        ]);

        Page::query()->create([
            'name' => 'Terms of Service',
            'name_ar' => 'شروط الاستخدام',
            'name_en' => 'Terms of Service',
            'content' => '<p>Terms content</p>',
            'content_ar' => '<p>Terms content</p>',
            'content_en' => '<p>Terms content</p>',
        ]);

        $this->get('/privacy')->assertOk();
        $this->get('/terms-of-service')->assertOk();
    }

    public function test_previous_tos_url_redirects_to_terms_of_service(): void
    {
        $this->get('/tos')->assertRedirect('/terms-of-service');
    }

    public function test_unknown_login_email_uses_one_generic_credentials_error(): void
    {
        $response = $this->postJson('/login', [
            'email' => 'unknown@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password'])
            ->assertJsonMissingValidationErrors(['email']);
    }
}
