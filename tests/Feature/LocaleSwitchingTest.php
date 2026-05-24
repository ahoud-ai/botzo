<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_language_switch_persists_to_profile(): void
    {
        $user = User::create([
            'first_name' => 'Locale',
            'last_name' => 'User',
            'email' => 'locale-user@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user',
            'language' => 'en',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->from('/dashboard')
            ->get('/language/ar')
            ->assertRedirect('/dashboard')
            ->assertSessionHas('locale', 'ar');

        $this->assertSame('ar', $user->fresh()->language);
    }

    public function test_guest_language_switch_stays_in_session(): void
    {
        $this->from('/')
            ->get('/language/ar')
            ->assertRedirect('/')
            ->assertSessionHas('locale', 'ar');
    }
}
